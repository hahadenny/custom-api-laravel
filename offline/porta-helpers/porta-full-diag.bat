@echo off

:: Start a new local environment/scope
setlocal

:: Get the directory of install.bat & pass it to the head.bat script
:: `%0` refers to the name of the currently executing batch script
:: %~dp0 expands to the drive and path of the currently executing batch script
call "lib\head.bat" "%~dp0"

:: Store the output of `ipconfig` to a temporary file
:: contents will be read by the WSL script
echo "Running ipconfig..."
set TEMP_IPCONFIG_FILE=%TEMP%\ipconfig_output.txt
ipconfig > %TEMP_IPCONFIG_FILE%

echo.
echo "Converting ipconfig tmp path to WSL path..."
:: Convert Windows directory path to WSL equivalent
for /f "usebackq tokens=*" %%i in (`wsl wslpath -u "%TEMP_IPCONFIG_FILE%"`) do set "WSL_TEMP_IPCONFIG_FILE=%%i"

:: Store the output of `systeminfo` to a temporary file
:: contents will be read by the WSL script
set TEMP_SYSINFO_FILE=%TEMP%\sysinfo_output.txt
systeminfo > %TEMP_SYSINFO_FILE%

echo.
echo "Converting `systeminfo` tmp path to WSL path..."
:: Convert Windows directory path to WSL equivalent
for /f "usebackq tokens=*" %%i in (`wsl wslpath -u "%TEMP_SYSINFO_FILE%"`) do set "WSL_TEMP_SYSINFO_FILE=%%i"

REM :: Store the output of Docker diag check to a temporary file
REM :: contents will be read by the WSL script
REM set TEMP_DOCKER_CHECK_FILE=%TEMP%\docker_check_output.txt
REM & "C:\Program Files\Docker\Docker\resources\com.docker.diagnose.exe" check > %TEMP_DOCKER_CHECK_FILE%

REM echo.
REM echo "Converting Docker check tmp path to WSL path..."
REM :: Convert Windows directory path to WSL equivalent
REM for /f "usebackq tokens=*" %%i in (`wsl wslpath -u "%TEMP_DOCKER_CHECK_FILE%"`) do set "WSL_TEMP_DOCKER_CHECK_FILE=%%i"


:: Get hosts file path for WSL
echo.
echo "Converting hosts file path to WSL path..."
set hosts_file=%SystemRoot%\System32\drivers\etc\hosts
:: Convert Windows directory path to WSL equivalent
for /f "usebackq tokens=*" %%i in (`wsl wslpath -u "%hosts_file%"`) do set "WSL_HOSTS_FILE=%%i"

REM -- Not doing this yet since the file may not exist
REM :: Get .wslconfig file path for WSL
REM echo.
REM echo "Converting .wslconfig file path to WSL path..."
REM set wslconfig_file=%USERPROFILE%\.wslconfig
REM :: Convert Windows directory path to WSL equivalent
REM for /f "usebackq tokens=*" %%i in (`wsl wslpath -u "%wslconfig_file%"`) do set "WSL_CONFIG_FILE=%%i"

:: Get Bridge logs path for WSL
echo.
set "bridge_logs=%APPDATA%\Disguise\Porta Bridge\Logs"
echo "Converting Porta Bridge logs path '%bridge_logs%' to WSL path..."
:: Convert Windows directory path to WSL equivalent
for /f "usebackq tokens=*" %%i in (`wsl wslpath -u "%bridge_logs%"`) do set "WSL_BRIDGE_LOGS=%%i"
echo "Passing %WSL_BRIDGE_LOGS% to WSL script..."

:: Create diag log file before running the script
echo.
set "diagLog=%WSL_PORTA_LOGS_PATH%/diag_%dateYmd%.log"
echo "Creating diag log file %diagLog%..."
wsl -u %WSL_USER% bash -c "touch '%diagLog%'"

:: Run the script in WSL
echo.
echo "Running Porta diagnostic..."
wsl -u %WSL_USER% bash -c "%DIAG_DIR%/porta-full-diag.sh \"%WINDOWS_USER%\" \"%WSL_TEMP_IPCONFIG_FILE%\" \"%WSL_HOSTS_FILE%\" \"%WSL_BRIDGE_LOGS%\" \"%WSL_TEMP_SYSINFO_FILE%\" | tee -a \"%diagLog%\""

call "lib\foot.bat"
