@echo off

:: %WSL_DEST% -- porta home dir, i.e., `/home/px/porta`
:: %ONPREM_DIRNAME% -- on prem dirname, i.e., `porta-onprem`

set CONFIG_FILE=%~1
REM Assumes this value was NOT sent with a trailing `\`
set SHORTCUT_PATH=%~2
set SHORTCUT_NAME=%~3
REM Assumes this value was NOT sent with a leading `/`
REM Defaults to blank if not sent
REM the `~` in `%~4` removes any wrapped quotes
set URL_PATH=%~4
:: Set default value for optional argument
if "%5"=="" (
    set PORT=%PORTA_ONPREM_APP_PORT%
) else (
    set PORT=%~5
)

:: Create Shortcuts
echo.
echo Creating shortcut "%SHORTCUT_NAME%" to Porta for %CONFIG_FILE%...

:: Check if the configuration file exists
if not exist "%CONFIG_FILE%" (
    echo Configuration file not found at "%CONFIG_FILE%".
    pause
    exit /b
)

:: Read the contents of the configuration file to get the IP address
<"%CONFIG_FILE%" set /p MACHINE_IP=

REM echo Read IP: %MACHINE_IP%

:: Check if the IP is empty
if "%MACHINE_IP%"=="" (
    echo IP not found in the configuration file.
    exit /b
)

set WEBPAGE_URL=http://%MACHINE_IP%:%PORT%/%URL_PATH%
REM echo Webpage URL: %WEBPAGE_URL%
REM echo.

REM echo %USERPROFILE%\Desktop
REM REM two levels up would be the desktop if porta-onprem-bundle was moved to the desktop
REM echo ORRRRR %CURRDIR%..\..

:: Define the path for the shortcut file
set FULL_SHORTCUT_PATH=%SHORTCUT_PATH%\%SHORTCUT_NAME%.url

:: Delete the shortcut file if it already exists
if exist "%FULL_SHORTCUT_PATH%" del "%FULL_SHORTCUT_PATH%"

:: Create the shortcut file
echo [InternetShortcut] > "%FULL_SHORTCUT_PATH%"
echo URL=%WEBPAGE_URL% >> "%FULL_SHORTCUT_PATH%"
echo IconIndex=0 >> "%FULL_SHORTCUT_PATH%"
echo IconFile="%CHROME_PATH%\chrome.exe" >> "%FULL_SHORTCUT_PATH%"

echo.
echo Shortcut to "%WEBPAGE_URL%" created on the desktop.
