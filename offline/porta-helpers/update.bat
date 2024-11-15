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
wsl -u %WSL_USER% bash -c "mkdir -p '%WSL_PORTA_INSTALLER_LOGS_PATH%'"
wsl -u %WSL_USER% bash -c "rm -f '%WSL_PORTA_INSTALLER_LOGS_PATH%/installer_%dateYmd%.log'"
wsl -u %WSL_USER% bash -c "touch '%WSL_PORTA_INSTALLER_LOGS_PATH%/installer_%dateYmd%.log'"
wsl -u %WSL_USER% bash -c "'%WSL_SCRIPTS_PATH%/install/update.sh' | tee -a '%WSL_PORTA_INSTALLER_LOGS_PATH%/installer_%dateYmd%.log'"

call "%~dp0\lib\foot.bat"
