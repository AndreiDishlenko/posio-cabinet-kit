@echo off
setlocal EnableExtensions EnableDelayedExpansion
rem ---------------------------------------------------------------------------
rem  release.bat - one-step patch release for any git repository.
rem
rem  Reads the current release tag reachable from HEAD, increments its third
rem  number (patch), commits the whole working tree with the bare version as the
rem  commit message, tags that commit and pushes branch + tag in one atomic push.
rem
rem  Self-contained: no PowerShell script, no project-specific paths. Copy this
rem  file into the root of any repository and it works there.
rem
rem  Usage:
rem    release.bat            0.3.33 -> 0.3.34, commit, tag, push
rem    release.bat -DryRun    print the plan, change nothing
rem    release.bat -NoPush    commit and tag locally, push nothing
rem    release.bat -h         show this help
rem
rem  Tag format follows the current tag: 0.3.33 -> 0.3.34, v0.3.33 -> v0.3.34.
rem  The commit message is always the bare version, without the "v".
rem  Minor and major bumps stay manual: git tag X.Y.0 && git push origin X.Y.0
rem ---------------------------------------------------------------------------

rem Captured before any shift: shift moves %0 too, and %~dp0 would then resolve
rem against the caller's directory instead of this file's repository.
set "SELFDIR=%~dp0"

set "DRYRUN="
set "NOPUSH="

:parse
if "%~1"=="" goto :parsed
if /i "%~1"=="-DryRun" (set "DRYRUN=1" & shift /1 & goto :parse)
if /i "%~1"=="-NoPush" (set "NOPUSH=1" & shift /1 & goto :parse)
if /i "%~1"=="-h" goto :help
if /i "%~1"=="--help" goto :help
if /i "%~1"=="/?" goto :help
echo ERROR: unknown argument "%~1"
goto :help
:parsed

rem Work in the repository the batch file itself lives in, not the caller's cwd.
cd /d "!SELFDIR!"

git rev-parse --is-inside-work-tree >nul 2>&1
if errorlevel 1 (
    echo ERROR: "!SELFDIR!" is not a git repository.
    exit /b 1
)

for /f "delims=" %%b in ('git rev-parse --abbrev-ref HEAD') do set "BRANCH=%%b"
if "!BRANCH!"=="HEAD" (
    echo ERROR: detached HEAD - check out a branch first.
    exit /b 1
)

rem --- current release --------------------------------------------------------

rem The tag reachable from HEAD, not the highest-sorted one: version sort puts
rem a legacy "v0.3.3" ahead of "0.3.33" and would walk the numbering backwards.
set "CURRENT="
for /f "delims=" %%t in ('git describe --tags --abbrev^=0 --match "[0-9]*.[0-9]*.[0-9]*" --match "v[0-9]*.[0-9]*.[0-9]*" 2^>nul') do set "CURRENT=%%t"

if not defined CURRENT (
    for /f "delims=" %%t in ('git tag --list --sort^=-v:refname "[0-9]*.[0-9]*.[0-9]*" 2^>nul') do (
        if not defined CURRENT set "CURRENT=%%t"
    )
)
if not defined CURRENT (
    for /f "delims=" %%t in ('git tag --list --sort^=-v:refname "v[0-9]*.[0-9]*.[0-9]*" 2^>nul') do (
        if not defined CURRENT set "CURRENT=%%t"
    )
)
if not defined CURRENT (
    echo ERROR: no X.Y.Z tag found - create the first one by hand, e.g. git tag 0.1.0
    exit /b 1
)

rem --- next version -----------------------------------------------------------

set "PREFIX="
set "NUMBER=!CURRENT!"
if /i "!CURRENT:~0,1!"=="v" (
    set "PREFIX=v"
    set "NUMBER=!CURRENT:~1!"
)

for /f "tokens=1,2,3 delims=." %%a in ("!NUMBER!") do (
    set "MAJOR=%%a"
    set "MINOR=%%b"
    set "PATCH=%%c"
)

echo !MAJOR!.!MINOR!.!PATCH!| findstr /r /c:"^[0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*$" >nul
if errorlevel 1 (
    echo ERROR: cannot parse "!CURRENT!" as X.Y.Z
    exit /b 1
)

rem Leading zeros would make arithmetic read the patch as an octal literal.
for /f "tokens=* delims=0" %%n in ("!PATCH!") do set "PATCHNUM=%%n"
if not defined PATCHNUM set "PATCHNUM=0"
set /a "NEXT=PATCHNUM+1"

set "VERSION=!MAJOR!.!MINOR!.!NEXT!"
set "TAG=!PREFIX!!VERSION!"

git rev-parse -q --verify "refs/tags/!TAG!" >nul 2>&1
if not errorlevel 1 (
    echo ERROR: tag !TAG! already exists.
    exit /b 1
)

echo Branch:          !BRANCH!
echo Current release: !CURRENT!
echo New release:     !TAG!

rem --- remote -----------------------------------------------------------------

set "REMOTE="
for /f "delims=" %%r in ('git remote') do (
    if not defined REMOTE set "REMOTE=%%r"
    if /i "%%r"=="origin" set "REMOTE=origin"
)

rem --- commit -----------------------------------------------------------------

set "DIRTY="
for /f "delims=" %%s in ('git status --porcelain') do set "DIRTY=1"

if defined DIRTY (
    echo Committing working tree as !VERSION! ...
    if defined DRYRUN (
        echo DRY-RUN: git add -A
        echo DRY-RUN: git commit -m "!VERSION!"
    ) else (
        git add -A || goto :fail
        git commit -m "!VERSION!" || goto :fail
    )
) else (
    echo No changes - tagging the current HEAD.
)

rem --- tag and push -----------------------------------------------------------

if defined DRYRUN (
    echo DRY-RUN: git tag -a "!TAG!" -m "!VERSION!"
) else (
    git tag -a "!TAG!" -m "!VERSION!" || goto :fail
)

if defined NOPUSH (
    if defined REMOTE (
        echo Done locally. Push with: git push --atomic !REMOTE! !BRANCH! !TAG!
    ) else (
        echo Done locally. No remote configured.
    )
    exit /b 0
)

if not defined REMOTE (
    echo No remote configured - commit and tag stay local.
    exit /b 0
)

if defined DRYRUN (
    echo DRY-RUN: git push --atomic !REMOTE! !BRANCH! !TAG!
) else (
    git push --atomic !REMOTE! !BRANCH! !TAG! || goto :fail
)

echo Released !TAG!
exit /b 0

:fail
echo.
echo RELEASE FAILED - fix the error above. Nothing was pushed.
exit /b 1

:help
echo.
echo  release.bat - one-step patch release for any git repository.
echo  Reads the current release tag, increments its third number, commits the
echo  working tree with the bare version as the message, tags it and pushes.
echo.
echo    release.bat            0.3.33 -^> 0.3.34, commit, tag, push
echo    release.bat -DryRun    print the plan, change nothing
echo    release.bat -NoPush    commit and tag locally, push nothing
echo    release.bat -h         show this help
echo.
echo  Tag format follows the current tag ^(0.3.33 or v0.3.33^); the commit
echo  message is always the bare version. Minor/major bumps stay manual.
echo.
exit /b 0
