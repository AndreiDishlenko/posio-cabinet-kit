@echo off
rem Release a new package version: release.bat [-Minor|-Major|-Version 0.4.0|-StampChangelog|-DryRun|-NoPush]
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\Release-CabinetKit.ps1" %*
