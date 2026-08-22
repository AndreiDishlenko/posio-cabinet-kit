<#
.SYNOPSIS
    Выпуск новой версии пакета: коммит, semver-тег, пуш ветки и тега.

.DESCRIPTION
    Берёт последний semver-тег репозитория, инкрементит его (по умолчанию
    patch), коммитит текущие изменения, ставит тег и пушит всё одним
    атомарным push. Префикс тега ("v" или пустой) наследуется от последнего
    тега, чтобы схема нумерации не менялась от релиза к релизу.

.PARAMETER Minor
    Инкрементить minor вместо patch (0.3.32 -> 0.4.0).

.PARAMETER Major
    Инкрементить major (0.3.32 -> 1.0.0).

.PARAMETER Version
    Явная версия вместо инкремента, например 0.4.0.

.PARAMETER Message
    Описание для аннотации тега. Сообщение коммита им не подменяется — оно всегда
    равно номеру версии без префикса "v", как в истории репозитория.

.PARAMETER StampChangelog
    Заменить заголовки "## Unreleased — ..." в docs/CHANGELOG.md на "## <version> — ...",
    чтобы секции релиза не накапливались безымянными.

.PARAMETER NoPush
    Сделать коммит и тег локально, ничего не пушить.

.PARAMETER DryRun
    Только показать, что было бы сделано.

.EXAMPLE
    .\tools\Release-CabinetKit.ps1
    .\tools\Release-CabinetKit.ps1 -Minor -StampChangelog
    .\tools\Release-CabinetKit.ps1 -Version 1.0.0 -Message "breaking: config keys renamed"
#>
[CmdletBinding()]
param(
    [switch] $Minor,
    [switch] $Major,
    [string] $Version,
    [string] $Message,
    [switch] $StampChangelog,
    [switch] $NoPush,
    [switch] $DryRun
)

$ErrorActionPreference = 'Stop'

$repo = Split-Path -Parent $PSScriptRoot
Set-Location $repo

function Invoke-Git {
    param([string[]] $Arguments, [switch] $AllowFailure)

    if ($DryRun) {
        Write-Host "DRY-RUN: git $($Arguments -join ' ')" -ForegroundColor DarkGray
        return ''
    }

    $output = & git @Arguments
    if ($LASTEXITCODE -ne 0 -and -not $AllowFailure) {
        throw "git $($Arguments -join ' ') завершился с кодом $LASTEXITCODE"
    }
    return $output
}

# --- ветка -------------------------------------------------------------------

$branch = (& git rev-parse --abbrev-ref HEAD).Trim()
if ($branch -ne 'main') {
    throw "Релиз выпускается только из main, сейчас ветка '$branch'."
}

# --- последний semver-тег ----------------------------------------------------

$tags = & git tag --list | Where-Object { $_ -match '^v?\d+\.\d+\.\d+$' }
if (-not $tags) {
    throw 'В репозитории нет ни одного semver-тега — первую версию проставить вручную.'
}

$latest = $tags |
    Sort-Object -Property @{ Expression = { [version] ($_ -replace '^v', '') } } |
    Select-Object -Last 1

$prefix = ''
if ($latest -match '^v') { $prefix = 'v' }

$current = [version] ($latest -replace '^v', '')

# --- новая версия ------------------------------------------------------------

if ($Version) {
    if ($Version -notmatch '^\d+\.\d+\.\d+$') {
        throw "Версия '$Version' не в формате X.Y.Z."
    }
    $next = [version] $Version
} elseif ($Major) {
    $next = [version] "$($current.Major + 1).0.0"
} elseif ($Minor) {
    $next = [version] "$($current.Major).$($current.Minor + 1).0"
} else {
    $next = [version] "$($current.Major).$($current.Minor).$($current.Build + 1)"
}

if ($next -le $current -and -not $Version) {
    throw "Новая версия $next не больше текущей $current."
}

$tag = "$prefix$next"

if ($tags -contains $tag) {
    throw "Тег $tag уже существует."
}

Write-Host "Текущая версия: $latest" -ForegroundColor Cyan
Write-Host "Новая версия:   $tag" -ForegroundColor Green

# Сообщение релизного коммита — голый номер версии, без префикса тега.
$commitMessage = "$next"

$tagMessage = $commitMessage
if ($Message) { $tagMessage = $Message }

# --- CHANGELOG ---------------------------------------------------------------

if ($StampChangelog) {
    $changelog = Join-Path $repo 'docs/CHANGELOG.md'
    $body = Get-Content $changelog -Raw
    $stamped = $body -replace '(?m)^## Unreleased(?= )', "## $next"

    if ($stamped -eq $body) {
        Write-Host 'CHANGELOG: секций Unreleased нет, пропускаю.' -ForegroundColor DarkGray
    } elseif ($DryRun) {
        Write-Host "DRY-RUN: CHANGELOG — Unreleased -> $next" -ForegroundColor DarkGray
    } else {
        Set-Content -Path $changelog -Value $stamped -Encoding utf8 -NoNewline
        Write-Host "CHANGELOG: Unreleased -> $next" -ForegroundColor Cyan
    }
}

# --- коммит ------------------------------------------------------------------

$dirty = & git status --porcelain
if ($dirty) {
    Write-Host 'Коммичу изменения:' -ForegroundColor Cyan
    $dirty | ForEach-Object { Write-Host "  $_" }
    Invoke-Git @('add', '-A') | Out-Null
    Invoke-Git @('commit', '-m', $commitMessage) | Out-Null
} else {
    Write-Host 'Изменений нет — тег ставится на текущий HEAD.' -ForegroundColor DarkGray
}

# --- тег и пуш ---------------------------------------------------------------

Invoke-Git @('tag', '-a', $tag, '-m', $tagMessage) | Out-Null

if ($NoPush) {
    Write-Host "Готово локально. Пуш: git push --atomic origin main $tag" -ForegroundColor Yellow
    return
}

Invoke-Git @('push', '--atomic', 'origin', 'main', $tag) | Out-Null

Write-Host "Опубликовано: $tag" -ForegroundColor Green
Write-Host 'В проекте-потребителе: composer update posio/cabinet-kit' -ForegroundColor DarkGray
