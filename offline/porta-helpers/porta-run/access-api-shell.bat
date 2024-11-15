@echo off

:: Start a new local environment/scope
setlocal

:: Get the directory of install.bat & pass it to the head.bat script
:: `%0` refers to the name of the currently executing batch script
:: %~dp0 expands to the drive and path of the currently executing batch script
call "..\lib\head.bat" "%~dp0"

echo.
echo "Accessing Porta API shell..."
docker exec -it porta bash

REM call "..\lib\foot.bat"