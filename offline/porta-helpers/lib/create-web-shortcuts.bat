@echo off

:: %WSL_DEST% -- porta home dir, i.e., `/home/px/porta`
:: %ONPREM_DIRNAME% -- on prem dirname, i.e., `porta-onprem`

:: Create Shortcuts
echo.
echo Creating shortcuts to Porta...

:: find chrome path
call "%~dp0find-chrome.bat"

:: Make the shortcuts
REM echo Host config: %HOST_CONFIG_FILE%
call "%~dp0create-web-shortcut.bat" "%CURRDIR%%HOST_CONFIG_FILE%" "%USERPROFILE%\Desktop" "Porta On Prem"
REM echo Backup config: %BACKUP_CONFIG_FILE%
call "%~dp0create-web-shortcut.bat" "%CURRDIR%%BACKUP_CONFIG_FILE%" "%USERPROFILE%\Desktop" "Porta On Prem Backup"


