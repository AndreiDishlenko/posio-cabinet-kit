@echo off
setlocal EnableExtensions EnableDelayedExpansion
rem ---------------------------------------------------------------------------
rem  managed-by: posio/cabinet-kit
rem
rem  ВНИМАНИЕ: файл принадлежит пакету posio/cabinet-kit и перезаписывается при
rem  каждом обновлении кабинета (updcab.bat -^> php artisan cabinet-kit:sync-config).
rem  Правка здесь пропадёт на ближайшем обновлении. Любые изменения вносятся только
rem  в пакет - stubs\release.bat.stub - и оттуда централизованно доходят до всех
rem  проектов. Проверки перед релизом - scripts\pre-push-checks.sh - принадлежат проекту.
rem
rem  release.bat - one-step patch release for any git repository.
rem
rem  Берёт текущую версию релиза, увеличивает третье число (patch), коммитит всё
rem  рабочее дерево с версией в сообщении, ставит тег и отправляет ветку и тег
rem  одним атомарным push.
rem
rem    release.bat                    0.3.33 -^> 0.3.34, коммит, тег, push
rem    release.bat fixed the header   то же с описанием: "0.3.34 fixed the header"
rem    release.bat -m "fixed header"  то же описание, заданное явно
rem    release.bat -same              повторный коммит с текущей версией, без нового тега
rem    release.bat -version X.Y.Z     ровно эта версия вместо автоувеличения; становится
rem                                   новой точкой отсчёта для следующих запусков
rem    release.bat -dryrun            показать план, ничего не менять
rem    release.bat -nopush            коммит и тег локально, без отправки
rem    release.bat -notests           без проверок перед релизом
rem    release.bat -h                 справка
rem    set NO_COLOR=1                 вывод без цвета
rem
rem  Ключи нечувствительны к регистру, но пишутся строчными. Всё, что не ключ, -
rem  свободный текст описания: его можно писать сразу после команды без кавычек.
rem
rem  Если в репозитории есть scripts\pre-push-checks.sh, он запускается через Git Bash
rem  до коммита; провал останавливает релиз, ничего не тронув.
rem
rem  Формат тега повторяет текущий: 0.3.33 -^> 0.3.34, v0.3.33 -^> v0.3.34. Сообщение
rem  коммита всегда начинается с голой версии, без "v". Минорные и мажорные версии -
rem  вручную: -version X.Y.0.
rem ---------------------------------------------------------------------------

rem Captured before any shift: shift moves %0 too, and %~dp0 would then resolve
rem against the caller's directory instead of this file's repository.
set "SELFDIR=%~dp0"

call :ui_init "38;5;208"
rem Кириллица - только после переключения консоли на UTF-8: строка, прочитанная
rem раньше, разобралась бы в прежней кодовой странице.
set "UI_NAME=Релиз"
call :main %*
set "RC=!errorlevel!"
call :ui_done
exit /b %RC%

:main
set "DRYRUN="
set "NOPUSH="
set "NOTESTS="
set "SAME="
set "NEWVERSION="
set "DESC="

:parse
if "%~1"=="" goto :parsed
if /i "%~1"=="-dryrun" (set "DRYRUN=1" & shift /1 & goto :parse)
if /i "%~1"=="-nopush" (set "NOPUSH=1" & shift /1 & goto :parse)
if /i "%~1"=="-notests" (set "NOTESTS=1" & shift /1 & goto :parse)
if /i "%~1"=="-same"   (set "SAME=1"   & shift /1 & goto :parse)
if /i "%~1"=="-version" (
    if "%~2"=="" (
        echo !C_FAIL!FAIL!C_RESET! -version требует значение, например -version 2.6.0
        exit /b 1
    )
    set "NEWVERSION=%~2"
    shift /1
    shift /1
    goto :parse
)
if /i "%~1"=="-m" (
    if "%~2"=="" (
        echo !C_FAIL!FAIL!C_RESET! -m требует значение, например -m "fixed the header"
        exit /b 1
    )
    call :adddesc "%~2"
    shift /1
    shift /1
    goto :parse
)
if /i "%~1"=="-h" goto :help
if /i "%~1"=="--help" goto :help
if /i "%~1"=="/?" goto :help
rem Free text: everything that does not look like a switch describes the release,
rem so a message can be typed right after the command without quoting it.
set "ARG=%~1"
if "!ARG:~0,1!"=="-" (
    echo !C_FAIL!FAIL!C_RESET! Неизвестный параметр: %~1
    goto :help
)
if "!ARG:~0,1!"=="/" (
    echo !C_FAIL!FAIL!C_RESET! Неизвестный параметр: %~1
    goto :help
)
call :adddesc "%~1"
shift /1
goto :parse
:parsed

if defined SAME if defined NEWVERSION (
    echo !C_FAIL!FAIL!C_RESET! -same и -version вместе не используются.
    exit /b 1
)

rem Work in the repository the batch file itself lives in, not the caller's cwd.
cd /d "!SELFDIR!"

call :stage "Версия"

git rev-parse --is-inside-work-tree >nul 2>&1
if errorlevel 1 (
    echo !C_FAIL!FAIL!C_RESET! "!SELFDIR!" - не git-репозиторий.
    exit /b 1
)

for /f "delims=" %%b in ('git rev-parse --abbrev-ref HEAD') do set "BRANCH=%%b"
if "!BRANCH!"=="HEAD" (
    echo !C_FAIL!FAIL!C_RESET! Отсоединённый HEAD - сначала переключитесь на ветку.
    exit /b 1
)

rem --- current release --------------------------------------------------------

rem The baseline is the highest version reachable from HEAD, never the nearest
rem tag: the nearest one walks the numbering backwards whenever a newer release
rem sits on a side path. Commit subjects are weighed in as well, so a version
rem that was committed but never tagged still moves the baseline forward instead
rem of being silently released a second time under an older number.
set "CURRENT="
set "BESTKEY="

set "TOPTAG="
for /f "delims=" %%t in ('git tag -l "[0-9]*.[0-9]*.[0-9]*" --merged HEAD --sort^=-v:refname 2^>nul') do (
    if not defined TOPTAG set "TOPTAG=%%t"
)
if defined TOPTAG call :consider "!TOPTAG!"

rem The two tag formats are ranked separately: one version sort over both puts a
rem legacy "v0.3.3" ahead of "0.3.33".
set "TOPVTAG="
for /f "delims=" %%t in ('git tag -l "v[0-9]*.[0-9]*.[0-9]*" --merged HEAD --sort^=-v:refname 2^>nul') do (
    if not defined TOPVTAG set "TOPVTAG=%%t"
)
if defined TOPVTAG call :consider "!TOPVTAG!"

rem Only subjects newer than the best tag can add anything - older history is
rem already covered by that tag.
if defined CURRENT (
    set "LOGSPEC=!CURRENT!..HEAD"
) else (
    set "LOGSPEC=-n 200 HEAD"
)

for /f "usebackq tokens=1 delims= " %%m in (`git log --format^=%%s !LOGSPEC! 2^>nul ^| findstr /r /c:"^[0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*$" /c:"^[0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]* " /c:"^v[0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*$" /c:"^v[0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]* "`) do (
    call :consider "%%m"
)

if not defined CURRENT if not defined NEWVERSION (
    echo !C_FAIL!FAIL!C_RESET! Версия X.Y.Z не найдена - первый релиз задайте явно: release.bat -version 0.1.0
    exit /b 1
)

rem --- next version -----------------------------------------------------------

rem Explicit version: skip auto-increment entirely, this becomes the new
rem baseline that later plain "release.bat" runs bump the patch number from.
if defined NEWVERSION (
    set "PREFIX="
    set "NUMBER=!NEWVERSION!"
    if /i "!NEWVERSION:~0,1!"=="v" (
        set "PREFIX=v"
        set "NUMBER=!NEWVERSION:~1!"
    )

    echo !NUMBER!| findstr /r /c:"^[0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*$" >nul
    if errorlevel 1 (
        echo !C_FAIL!FAIL!C_RESET! "-version !NEWVERSION!" не читается как X.Y.Z
        exit /b 1
    )

    set "VERSION=!NUMBER!"
    set "TAG=!NEWVERSION!"

    git rev-parse -q --verify "refs/tags/!TAG!" >nul 2>&1
    if not errorlevel 1 (
        echo !C_FAIL!FAIL!C_RESET! Тег !TAG! уже существует.
        exit /b 1
    )

    echo !C_OK!OK  !C_RESET! Ветка:          !BRANCH!
    if defined CURRENT (
        echo !C_OK!OK  !C_RESET! Текущий релиз:  !CURRENT!
    ) else (
        echo !C_OK!OK  !C_RESET! Текущий релиз:  нет
    )
    echo !C_OK!OK  !C_RESET! Новый релиз:    !TAG! !C_DIM!^(явный -version, новая точка отсчёта^)!C_RESET!
    goto :message
)

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
    echo !C_FAIL!FAIL!C_RESET! "!CURRENT!" не читается как X.Y.Z
    exit /b 1
)

rem Repeat release: keep the current version, no bump and no new tag.
if defined SAME (
    set "VERSION=!NUMBER!"
    set "TAG=!CURRENT!"
    echo !C_OK!OK  !C_RESET! Ветка:          !BRANCH!
    echo !C_OK!OK  !C_RESET! Текущий релиз:  !CURRENT! !C_DIM!^(повторный коммит, без нового тега^)!C_RESET!
    goto :message
)

rem Leading zeros would make arithmetic read the patch as an octal literal.
for /f "tokens=* delims=0" %%n in ("!PATCH!") do set "PATCHNUM=%%n"
if not defined PATCHNUM set "PATCHNUM=0"
set /a "NEXT=PATCHNUM+1"

set "VERSION=!MAJOR!.!MINOR!.!NEXT!"
set "TAG=!PREFIX!!VERSION!"

git rev-parse -q --verify "refs/tags/!TAG!" >nul 2>&1
if not errorlevel 1 (
    echo !C_FAIL!FAIL!C_RESET! Тег !TAG! уже существует.
    exit /b 1
)

echo !C_OK!OK  !C_RESET! Ветка:          !BRANCH!
echo !C_OK!OK  !C_RESET! Текущий релиз:  !CURRENT!
echo !C_OK!OK  !C_RESET! Новый релиз:    !TAG!

:message

rem --- message ----------------------------------------------------------------

rem The version stays the first word of the subject: that is what the baseline
rem scan above reads back on the next run.
set "MSG=!VERSION!"
if defined DESC set "MSG=!VERSION! !DESC!"
echo !C_OK!OK  !C_RESET! Сообщение:      !MSG!

rem --- remote -----------------------------------------------------------------

set "REMOTE="
for /f "delims=" %%r in ('git remote') do (
    if not defined REMOTE set "REMOTE=%%r"
    if /i "%%r"=="origin" set "REMOTE=origin"
)

rem --- checks -----------------------------------------------------------------

rem Checks run before the commit: a failure after tagging would leave a local
rem release behind, and the next run would bump the version once more. The push
rem then skips the pre-push hook so the same set does not run twice.
set "NOVERIFY="
if exist "scripts\pre-push-checks.sh" (
    set "NOVERIFY=--no-verify"
    call :stage "Проверки"
    if defined NOTESTS (
        echo !C_WARN!WARN!C_RESET! Проверки пропущены ^(-notests^)
    ) else if defined DRYRUN (
        echo       !C_DIM!DRY-RUN: bash scripts/pre-push-checks.sh!C_RESET!
    ) else (
        call :runchecks
        if errorlevel 1 goto :checksfail
        echo !C_OK!OK  !C_RESET! Проверки пройдены
    )
)

rem --- commit -----------------------------------------------------------------

call :stage "Коммит"

set "DIRTY="
for /f "delims=" %%s in ('git status --porcelain') do set "DIRTY=1"

if defined DIRTY (
    if defined DRYRUN (
        echo       !C_DIM!DRY-RUN: git add -A!C_RESET!
        echo       !C_DIM!DRY-RUN: git commit -m "!MSG!"!C_RESET!
    ) else (
        git add -A || goto :fail
        git commit -q -m "!MSG!" || goto :fail
        echo !C_OK!OK  !C_RESET! Рабочее дерево закоммичено как !VERSION!
    )
) else (
    if defined SAME (
        echo !C_FAIL!FAIL!C_RESET! Нечего коммитить - повторному релизу нужны изменения в рабочем дереве.
        exit /b 1
    )
    echo !C_OK!OK  !C_RESET! Изменений нет - тег ставится на текущий HEAD
)

rem --- tag and push -----------------------------------------------------------

call :stage "Тег и отправка"

rem A repeat release reuses the existing tag: only the branch moves forward.
if defined SAME goto :push

if defined DRYRUN (
    echo       !C_DIM!DRY-RUN: git tag -a "!TAG!" -m "!MSG!"!C_RESET!
) else (
    git tag -a "!TAG!" -m "!MSG!" || goto :fail
    echo !C_OK!OK  !C_RESET! Тег !TAG!
)

:push
set "PUSHARGS=!BRANCH! !TAG!"
if defined SAME set "PUSHARGS=!BRANCH!"

if defined NOPUSH (
    if defined REMOTE (
        echo !C_WARN!WARN!C_RESET! Отправка пропущена ^(-nopush^). Отправить: git push --atomic !NOVERIFY! !REMOTE! !PUSHARGS!
    ) else (
        echo !C_WARN!WARN!C_RESET! Удалённый репозиторий не настроен.
    )
    call :success "Релиз !TAG! готов локально"
    exit /b 0
)

if not defined REMOTE (
    echo !C_WARN!WARN!C_RESET! Удалённый репозиторий не настроен - коммит остаётся локальным.
    call :success "Релиз !TAG! готов локально"
    exit /b 0
)

if defined DRYRUN (
    echo       !C_DIM!DRY-RUN: git push --atomic !NOVERIFY! !REMOTE! !PUSHARGS!!C_RESET!
    call :success "План релиза !TAG! показан, ничего не изменено"
    exit /b 0
)

git push -q --atomic !NOVERIFY! !REMOTE! !PUSHARGS! || goto :fail
echo !C_OK!OK  !C_RESET! Отправлено в !REMOTE!: !PUSHARGS!

if defined SAME (
    call :success "Выпущен !TAG! (повторный коммит)"
) else (
    call :success "Выпущен !TAG!"
)
exit /b 0

:fail
call :failure "РЕЛИЗ НЕ УДАЛСЯ - исправьте ошибку выше. Ничего не отправлено."
exit /b 1

:checksfail
echo !C_FAIL!FAIL!C_RESET! Проверки не пройдены. Пропустить их намеренно: release.bat -notests
call :failure "РЕЛИЗ ОСТАНОВЛЕН - ничего не закоммичено, не помечено тегом и не отправлено."
exit /b 1

rem --- subroutines ------------------------------------------------------------

:runchecks
rem Git Bash is taken from the git installation itself: a plain "bash" on the
rem Windows PATH may be missing or resolve to WSL.
set "GITBASH="
for /f "delims=" %%g in ('where git 2^>nul') do (
    if not defined GITBASH set "GITBASH=%%~dpg..\bin\bash.exe"
)
if not exist "!GITBASH!" (
    echo !C_FAIL!FAIL!C_RESET! Git Bash не найден рядом с git - установите Git for Windows или запустите с -notests.
    exit /b 1
)
"!GITBASH!" scripts/pre-push-checks.sh
exit /b !errorlevel!

:adddesc
if defined DESC (
    set "DESC=!DESC! %~1"
) else (
    set "DESC=%~1"
)
goto :eof

:consider
rem Keeps the largest version seen so far. Ranking runs on a zero-padded key
rem because a plain string compare reads 1.0.10 as older than 1.0.9, and set /a
rem cannot be used on a whole X.Y.Z at once.
set "_V=%~1"
set "_N=!_V!"
if /i "!_N:~0,1!"=="v" set "_N=!_N:~1!"
echo !_N!|findstr /r /c:"^[0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*$" >nul
if errorlevel 1 goto :eof
for /f "tokens=1,2,3 delims=." %%a in ("!_N!") do (
    set "_A=00000%%a"
    set "_B=00000%%b"
    set "_C=00000%%c"
)
set "_KEY=!_A:~-5!.!_B:~-5!.!_C:~-5!"
if defined BESTKEY if not "!_KEY!" gtr "!BESTKEY!" goto :eof
set "BESTKEY=!_KEY!"
set "CURRENT=!_V!"
goto :eof

:help
echo.
echo  release.bat - релиз patch-версии одной командой для любого git-репозитория.
echo  Берёт текущую версию, увеличивает третье число, коммитит рабочее дерево
echo  с версией в сообщении, ставит тег и отправляет.
echo.
echo    release.bat                    0.3.33 -^> 0.3.34, коммит, тег, push
echo    release.bat fixed the header   то же с описанием: "0.3.34 fixed the header"
echo    release.bat -m "fixed header"  то же описание, заданное явно
echo    release.bat -same              повторный коммит с текущей версией, без нового тега
echo    release.bat -version X.Y.Z     ровно эта версия; новая точка отсчёта
echo    release.bat -dryrun            показать план, ничего не менять
echo    release.bat -nopush            коммит и тег локально, без отправки
echo    release.bat -notests           без проверок перед релизом
echo    release.bat -h                 эта справка
echo.
echo  Ключи нечувствительны к регистру, но пишутся строчными. Всё, что не ключ, -
echo  описание релиза: его можно писать сразу после команды без кавычек.
echo.
echo  Следующая версия считается от самой старшей, достижимой из HEAD, - по тегам
echo  и по темам коммитов, так что закоммиченная без тега версия не выпустится
echo  второй раз под старым номером.
echo.
echo  Формат тега повторяет текущий ^(0.3.33 или v0.3.33^); сообщение коммита
echo  всегда начинается с голой версии. Минорные и мажорные версии - через -version X.Y.0.
echo.
exit /b 0

rem --- Оформление -----------------------------------------------------------
rem  Одно на все скрипты пакета (deploy, build, cc, release.bat): этап - «■ Скрипт · этап»
rem  своим цветом у каждого скрипта, строки результата - OK / WARN / FAIL, итог - ✓ / ✗.

:ui_init
set "C_STAGE=" & set "C_OK=" & set "C_WARN=" & set "C_FAIL=" & set "C_DIM=" & set "C_RESET="
rem Значки и кириллица этапов - в UTF-8; прежняя кодовая страница консоли
rem возвращается на выходе, чтобы не сломать вывод вызвавшему окну.
set "UI_OLDCP="
for /f "tokens=2 delims=:" %%c in ('chcp') do set "UI_OLDCP=%%c"
if defined UI_OLDCP set "UI_OLDCP=!UI_OLDCP: =!"
if defined UI_OLDCP set "UI_OLDCP=!UI_OLDCP:.=!"
chcp 65001 >nul
if defined NO_COLOR exit /b 0
for /f %%e in ('echo prompt $E^| cmd') do set "ESC=%%e"
set "C_STAGE=!ESC![%~1m"
set "C_OK=!ESC![1;32m"
set "C_WARN=!ESC![1;33m"
set "C_FAIL=!ESC![1;31m"
set "C_DIM=!ESC![90m"
set "C_RESET=!ESC![0m"
exit /b 0

:ui_done
if defined UI_OLDCP chcp !UI_OLDCP! >nul
exit /b 0

:stage
echo.
echo !C_STAGE!■ !UI_NAME! · %~1!C_RESET!
exit /b 0

:success
echo.
echo !C_OK!✓ %~1!C_RESET!
exit /b 0

:failure
echo.
echo !C_FAIL!✗ %~1!C_RESET!
exit /b 0
