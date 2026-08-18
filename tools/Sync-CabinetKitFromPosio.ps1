param(
    [string] $SourceRoot = "F:\OpenServer\home\posio.cabinet",
    [string] $PackageRoot = (Resolve-Path "$PSScriptRoot\..").Path,
    [string] $Manifest = "$PSScriptRoot\sync-manifest.json",
    [string] $Report = "",
    [switch] $Apply,
    [switch] $OpenDiff,
    [switch] $IncludePackageOnly
)

$ErrorActionPreference = "Stop"

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

$manifestData = Get-Content -LiteralPath $Manifest -Raw | ConvertFrom-Json
$sourceRootFull = [System.IO.Path]::GetFullPath($SourceRoot)
$packageRootFull = [System.IO.Path]::GetFullPath($PackageRoot)

if (-not (Test-Path -LiteralPath $sourceRootFull -PathType Container)) {
    throw "Source root not found: $sourceRootFull"
}

if (-not (Test-Path -LiteralPath $packageRootFull -PathType Container)) {
    throw "Package root not found: $packageRootFull"
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
