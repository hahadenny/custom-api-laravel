@echo off

:: Query the Windows Registry to get the Chrome installation path
for /f "tokens=2*" %%a in ('reg query "HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\chrome.exe" /v "Path" 2^>nul ^| find "REG_SZ"') do (
    set CHROME_PATH=%%b
)

:: Check if Chrome path is found
:: echo Chrome executable path: %CHROME_PATH%\chrome.exe
if not defined CHROME_PATH (
    echo Chrome is not installed or the path couldn't be found.
    pause
    exit /b
)
