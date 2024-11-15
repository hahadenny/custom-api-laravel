@echo off

:: Start a new local environment/scope
setlocal

:: Get the directory of install.bat & pass it to the head.bat script
:: `%0` refers to the name of the currently executing batch script
:: %~dp0 expands to the drive and path of the currently executing batch script
call "%~dp0\lib\head.bat" "%~dp0"

:: Unzip, copy, and prepare for install/update in WSL
call "%~dp0\lib\upstall.bat"

:: Run the install/update script in WSL
wsl -u %WSL_USER% bash -c "'%WSL_SCRIPTS_PATH%/install/install.sh' | tee -a '%WSL_PORTA_INSTALLER_LOGS_PATH%/installer_%dateYmd%.log'"

:: Copy (not move) the conf files to WSL for database bash scripts to use
:: Make ~/porta/conf if not exists
wsl -u %WSL_USER% bash -c "mkdir -p '%WSL_DEST%/conf'"

:: Copy the conf files to WSL
:: Convert Windows directory path to WSL equivalent
for /f "usebackq tokens=*" %%i in (`wsl wslpath -u "%~dp0\conf"`) do set "WSLDIR=%%i"
wsl -u %WSL_USER% bash -c "cp -ra '%WSLDIR%'/* '%WSL_DEST%/conf'"

:: create shortcuts to porta on prem
call "%~dp0\lib\create-web-shortcuts.bat"

:: create shortcuts to porta on prem DB tools
call "%~dp0\lib\create-db-shortcuts.bat"

call "%~dp0\lib\foot.bat"
