param(
    [string] $SourceRoot = "F:\OpenServer\home\posio.cabinet",
    [string] $PackageRoot = (Resolve-Path "$PSScriptRoot\..").Path,
    [string] $Manifest = "$PSScriptRoot\sync-manifest.json",
    [string] $StateFile = "$PSScriptRoot\upstream-sync-state.json",
    [string] $Report = "",
    [switch] $Apply,
    [switch] $OpenDiff,
    [switch] $IncludePackageOnly,
    # Записать текущий HEAD апстрима как новую точку переноса (после того, как
    # перенос действительно сделан). Прежняя точка уходит в history.
    [switch] $RecordBaseline,
    # Короткая справка о точке переноса без аудита файлов — для быстрого старта
    # следующей сессии переноса.
    [switch] $ShowBaseline
)

$ErrorActionPreference = "Stop"

# Точка, до которой апстрим уже перенесён: следующий проход смотрит только то,
# что появилось после неё, а не всю историю проекта.
function Get-SyncState {
    param([string] $Path)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        return $null
    }

    # Без явной кодировки Windows PowerShell читает UTF-8 без BOM как ANSI, и
    # кириллица заметок переписывается обратно уже испорченной.
    return Get-Content -LiteralPath $Path -Raw -Encoding UTF8 | ConvertFrom-Json
}

function Get-UpstreamLog {
    param(
        [string] $Root,
        [string] $FromCommit,
        [string[]] $GitArgs
    )

    $git = Get-Command git -ErrorAction SilentlyContinue
    if (-not $git) {
        return @("git is not available; cannot list upstream commits.")
    }

    if ([string]::IsNullOrWhiteSpace($FromCommit)) {
        return @("No baseline commit recorded; review the whole manifest instead.")
    }

    $out = & git -C $Root @GitArgs "$FromCommit..HEAD" 2>&1
    if ($LASTEXITCODE -ne 0) {
        return @("git failed with exit code $LASTEXITCODE (baseline commit unknown to the upstream repository?)", ($out | Out-String).TrimEnd())
    }

    # Пустой вывод означает «после точки переноса ничего нет» — это результат, а не
    # отсутствие результата, поэтому пустой массив здесь недопустим.
    $text = ($out | ForEach-Object { "$_" }) | Where-Object { $_ -ne $null }
    if (-not $text) {
        return @("(nothing new upstream)")
    }

    return [string[]] $text
}

function Resolve-EntryPath {
    param(
        [string] $Root,
        [string] $RelativePath
    )

    if ([string]::IsNullOrWhiteSpace($RelativePath)) {
        return $null
    }

    return [System.IO.Path]::GetFullPath((Join-Path $Root $RelativePath))
}

function Test-IsInsideRoot {
    param(
        [string] $Root,
        [string] $Path
    )

    $rootFull = [System.IO.Path]::GetFullPath($Root).TrimEnd('\', '/') + [System.IO.Path]::DirectorySeparatorChar
    $pathFull = [System.IO.Path]::GetFullPath($Path)

    return $pathFull.StartsWith($rootFull, [System.StringComparison]::OrdinalIgnoreCase)
}

function Get-ShortHash {
    param([string] $Path)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        return $null
    }

    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.Substring(0, 12).ToLowerInvariant()
}

function Get-GitDiff {
    param(
        [string] $Source,
        [string] $Target
    )

    $git = Get-Command git -ErrorAction SilentlyContinue
    if (-not $git) {
        return @("git is not available; install Git or compare files manually.")
    }

    $diff = & git diff --no-index -- $Source $Target 2>&1
    if ($LASTEXITCODE -gt 1) {
        return @("git diff failed with exit code $LASTEXITCODE", $diff)
    }

    return $diff
}

if (-not (Test-Path -LiteralPath $Manifest -PathType Leaf)) {
    throw "Manifest not found: $Manifest"
}

$manifestData = Get-Content -LiteralPath $Manifest -Raw -Encoding UTF8 | ConvertFrom-Json
$sourceRootFull = [System.IO.Path]::GetFullPath($SourceRoot)
$packageRootFull = [System.IO.Path]::GetFullPath($PackageRoot)

if (-not (Test-Path -LiteralPath $sourceRootFull -PathType Container)) {
    throw "Source root not found: $sourceRootFull"
}

if (-not (Test-Path -LiteralPath $packageRootFull -PathType Container)) {
    throw "Package root not found: $packageRootFull"
}

$syncState = Get-SyncState -Path $StateFile
$baseline = if ($syncState) { $syncState.synced_through } else { $null }
$baselineCommit = if ($baseline) { $baseline.commit } else { $null }

if ($baseline) {
    Write-Host "Ported through: $($baseline.version) ($($baseline.short_commit), $($baseline.commit_date)) -> package $($baseline.package_version)"
} else {
    Write-Host "No baseline recorded in $StateFile - the whole manifest needs review."
}

if ($RecordBaseline) {
    $git = Get-Command git -ErrorAction SilentlyContinue
    if (-not $git) {
        throw "git is required to record a baseline."
    }

    $head = (& git -C $sourceRootFull log -1 --format="%H|%h|%ad|%s" --date=short 2>&1)
    if ($LASTEXITCODE -ne 0) {
        throw "Cannot read upstream HEAD: $head"
    }

    $parts = ($head | Out-String).Trim() -split '\|', 4
    if (-not $syncState) {
        $syncState = [pscustomobject]@{
            schema  = "cabinet-kit/upstream-sync-state@1"
            history = @()
        }
    }

    # Версия апстрима — первое слово темы коммита: проект коммитит релизы как "2.5.38 ...".
    $version = ($parts[3] -split '\s+')[0]
    $previous = $baseline

    $syncState.synced_through = [pscustomobject]@{
        version         = $version
        commit          = $parts[0]
        short_commit    = $parts[1]
        commit_subject  = $parts[3]
        commit_date     = $parts[2]
        synced_at       = (Get-Date -Format 'yyyy-MM-dd')
        package_version = ((& git -C $packageRootFull describe --tags --abbrev=0 2>$null) | Out-String).Trim()
        notes           = ""
    }

    if ($previous) {
        $syncState.history = @($previous) + @($syncState.history | Where-Object { $_ })
    }

    # UTF-8 без BOM: с BOM файл не разбирают JSON-парсеры PHP и Node.
    $json = $syncState | ConvertTo-Json -Depth 6
    [System.IO.File]::WriteAllText($StateFile, $json, (New-Object System.Text.UTF8Encoding $false))
    Write-Host "Baseline recorded: $version ($($parts[1]))"
    return
}

if ($ShowBaseline) {
    Write-Host ""
    Write-Host "Upstream commits after the baseline:"
    Get-UpstreamLog -Root $sourceRootFull -FromCommit $baselineCommit -GitArgs @('log', '--oneline') | ForEach-Object { Write-Host "  $_" }
    return
}

if ([string]::IsNullOrWhiteSpace($Report)) {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $Report = Join-Path $packageRootFull "sync-audit-$stamp.md"
}

$reportFull = [System.IO.Path]::GetFullPath($Report)
if (-not (Test-IsInsideRoot -Root $packageRootFull -Path $reportFull)) {
    throw "Report path must stay inside package root: $reportFull"
}

$rows = New-Object System.Collections.Generic.List[object]
$diffSections = New-Object System.Collections.Generic.List[string]
$bt = [char]96

foreach ($entry in $manifestData.entries) {
    if ($entry.mode -eq "package_only" -and -not $IncludePackageOnly) {
        continue
    }

    $source = Resolve-EntryPath -Root $sourceRootFull -RelativePath $entry.source
    $target = Resolve-EntryPath -Root $packageRootFull -RelativePath $entry.target

    if ($source -and -not (Test-IsInsideRoot -Root $sourceRootFull -Path $source)) {
        throw "Source path escapes source root for entry '$($entry.id)': $source"
    }

    if ($target -and -not (Test-IsInsideRoot -Root $packageRootFull -Path $target)) {
        throw "Target path escapes package root for entry '$($entry.id)': $target"
    }

    $sourceExists = $source -and (Test-Path -LiteralPath $source -PathType Leaf)
    $targetExists = $target -and (Test-Path -LiteralPath $target -PathType Leaf)
    $sourceHash = if ($sourceExists) { Get-ShortHash -Path $source } else { $null }
    $targetHash = if ($targetExists) { Get-ShortHash -Path $target } else { $null }

    $status = "n/a"
    if ($entry.mode -eq "package_only") {
        $status = if ($targetExists) { "package-only present" } else { "package-only missing" }
    } elseif (-not $sourceExists) {
        $status = "source missing"
    } elseif (-not $targetExists) {
        $status = "target missing"
    } elseif ($sourceHash -eq $targetHash) {
        $status = "identical"
    } else {
        $status = "different"
    }

    if ($Apply -and $entry.mode -eq "copy") {
        if (-not $sourceExists) {
            throw "Cannot copy missing source for entry '$($entry.id)': $source"
        }

        $targetDir = Split-Path -Parent $target
        New-Item -ItemType Directory -Force -Path $targetDir | Out-Null
        Copy-Item -LiteralPath $source -Destination $target -Force
        $targetExists = $true
        $targetHash = Get-ShortHash -Path $target
        $status = if ($sourceHash -eq $targetHash) { "copied" } else { "copy failed" }
    }

    $rows.Add([pscustomobject]@{
        Id = $entry.id
        Mode = $entry.mode
        Status = $status
        Source = $entry.source
        Target = $entry.target
        SourceHash = $sourceHash
        TargetHash = $targetHash
        Notes = $entry.notes
    })

    if ($OpenDiff -and $entry.mode -ne "package_only" -and $sourceExists -and $targetExists -and $sourceHash -ne $targetHash) {
        $diff = Get-GitDiff -Source $source -Target $target
        $diffText = ($diff | Out-String).TrimEnd()
        $sectionLines = @(
            "## Diff: $($entry.id)",
            "",
            "Source: $bt$($entry.source)$bt",
            "Target: $bt$($entry.target)$bt",
            "",
            '```diff',
            $diffText,
            '```'
        )
        $section = $sectionLines -join [Environment]::NewLine
        $diffSections.Add($section)
    }
}

$differentCount = ($rows | Where-Object { $_.Status -eq "different" }).Count
$missingCount = ($rows | Where-Object { $_.Status -like "*missing" }).Count
$copiedCount = ($rows | Where-Object { $_.Status -eq "copied" }).Count

$lines = New-Object System.Collections.Generic.List[string]
$lines.Add("# CabinetKit sync audit")
$lines.Add("")
$lines.Add("- Date: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')")
$lines.Add("- Source root: $bt$sourceRootFull$bt")
$lines.Add("- Package root: $bt$packageRootFull$bt")
$lines.Add("- Apply mode: $bt$Apply$bt")
$lines.Add("- Summary: $differentCount different, $missingCount missing, $copiedCount copied")

if ($baseline) {
    $lines.Add("- Ported through: $bt$($baseline.version)$bt ($bt$($baseline.short_commit)$bt, $($baseline.commit_date)) -> package $bt$($baseline.package_version)$bt")
    $lines.Add("")
    $lines.Add("## Upstream changes after the baseline")
    $lines.Add("")
    $lines.Add("Only these commits need review; everything older is already in the package.")
    $lines.Add("")
    $lines.Add('```')
    $lines.AddRange([string[]]((Get-UpstreamLog -Root $sourceRootFull -FromCommit $baselineCommit -GitArgs @('log', '--oneline') | ForEach-Object { "$_" })))
    $lines.Add('```')
    $lines.Add("")
    $lines.Add('```')
    $lines.AddRange([string[]]((Get-UpstreamLog -Root $sourceRootFull -FromCommit $baselineCommit -GitArgs @('diff', '--stat') | ForEach-Object { "$_" })))
    $lines.Add('```')
} else {
    $lines.Add("- Ported through: not recorded (see $bt tools/upstream-sync-state.json $bt)")
}

$lines.Add("")
$lines.Add("| id | mode | status | source | target | hashes |")
$lines.Add("| --- | --- | --- | --- | --- | --- |")

foreach ($row in $rows) {
    $hashes = "$($row.SourceHash) / $($row.TargetHash)"
    $lines.Add("| $($row.Id) | $($row.Mode) | $($row.Status) | $bt$($row.Source)$bt | $bt$($row.Target)$bt | $bt$hashes$bt |")
}

$lines.Add("")
$lines.Add("## Review notes")
$lines.Add("")
foreach ($row in $rows) {
    if (-not [string]::IsNullOrWhiteSpace($row.Notes)) {
        $lines.Add("- " + $bt + $row.Id + $bt + ": " + $row.Notes)
    }
}

if ($diffSections.Count -gt 0) {
    $lines.Add("")
    $lines.AddRange($diffSections)
}

Set-Content -LiteralPath $reportFull -Value $lines -Encoding UTF8

Write-Host "Audit written to: $reportFull"
Write-Host "Different: $differentCount; missing: $missingCount; copied: $copiedCount"

if ($Apply) {
    Write-Host "Apply mode only copies manifest entries with mode=copy. Current manifest is conservative; manual entries are never overwritten."
}
