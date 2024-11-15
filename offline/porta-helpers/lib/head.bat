@echo off

:: locale safe local datetime in `Ymd_his` format
for /f %%x in ('wmic path win32_localtime get /format:list ^| findstr "="') do set %%x
set dateYmd=%Year%%Month%%Day%_%Hour%%Minute%%Second%

set ONPREM_DIRNAME=porta-onprem
set ONPREM_BUNDLE_DIRNAME=porta-onprem-bundle
set ONPREM_BATCH_DIRNAME=porta-helpers
set PORTA_ONPREM_APP_PORT=8080
set PORTA_ONPREM_API_PORT=8000

:: Parent folder for the installation machine info files
set INSTALL_CONF_DIR=conf
:: Define the path to the configuration files
set HOST_CONFIG_FILE=%INSTALL_CONF_DIR%\host_machine.conf
set BACKUP_CONFIG_FILE=%INSTALL_CONF_DIR%\backup_machine.conf

set WINDOWS_USER=%USERNAME%
:: Prompt the user for the Windows username
set /p WINDOWS_USER="Enter the Windows username (or hit enter for: %WINDOWS_USER%): "
echo Proceeding with Windows username: %WINDOWS_USER%

set WSL_USER=px
:: Capture the output of 'wsl whoami' command into a variable
for /f %%a in ('wsl whoami') do (
    set WSL_USER=%%a
)
:: Prompt the user for the Ubuntu username
set /p WSL_USER="Enter the Ubuntu username (or hit enter for: %WSL_USER%): "
echo Proceeding with Ubuntu username: %WSL_USER%

:: Set the current directory of the calling script
:: `%1` Accesses the arg passed to the script
:: `%~dp1` expands to the drive and path of the first arg
set "CURRDIR=%~dp1"

set ZIP_FILE=%ONPREM_DIRNAME%.zip
:: The path to the windows zipped archive in windows
set ZIP_WINDIR=%CURRDIR%..
set ZIP_WIN_FILEPATH=%ZIP_WINDIR%\%ONPREM_DIRNAME%.zip
set UNZIP_WIN_FILEPATH=%ZIP_WINDIR%\%ONPREM_DIRNAME%

:: WSL destination
set WSL_DEST=/home/%WSL_USER%/porta
set WSL_SCRIPTS_PATH=%WSL_DEST%/%ONPREM_DIRNAME%
set WSL_PORTA_LOGS_PATH=%WSL_DEST%/logs
set WSL_PORTA_INSTALLER_LOGS_PATH=%WSL_PORTA_LOGS_PATH%/installer

:: Set the path to the different WSL scripts parent dirs
set UTIL_SCRIPTS_DIR=%WSL_SCRIPTS_PATH%/utils
set HELPER_SCRIPTS_DIR=%UTIL_SCRIPTS_DIR%/helpers

set BACKUPS_DIR=%HELPER_SCRIPTS_DIR%/backups
set REPL_FIXES_DIR=%HELPER_SCRIPTS_DIR%/repl-actions
set VIEWERS_DIR=%HELPER_SCRIPTS_DIR%/viewers
set DIAG_DIR=%HELPER_SCRIPTS_DIR%/diag

:: Make sure installer logs folder exists
wsl -u %WSL_USER% bash -c "mkdir -p '%WSL_PORTA_LOGS_PATH%' && chown -R $USER '%WSL_PORTA_LOGS_PATH%' && chmod -R ug+wx '%WSL_PORTA_LOGS_PATH%' && mkdir -p '%WSL_PORTA_INSTALLER_LOGS_PATH%' &&  chown -R $USER '%WSL_PORTA_INSTALLER_LOGS_PATH%' && chmod -R ug+wx '%WSL_PORTA_INSTALLER_LOGS_PATH%' "

:: ################################################################################################
:: ## FUNCTIONS SECTION ##


REM DOESN'T WORK
REM :: EXAMPLE --> `call :convert_to_wsl_path "%WINDOWS_PATH%" WSL_PATH`
REM :: Define function to convert Windows path to WSL path
REM :: %~1 is the Windows path
REM :: %~2 is the variable to store the WSL path
REM :convert_to_wsl_path
REM for /f "usebackq tokens=*" %%i in (`wsl wslpath -u "%~1"`) do set "%2=%%i"
REM exit /b

:: ################################################################################################
