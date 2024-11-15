@echo off

:: Start a new local environment/scope
setlocal

:: Get the directory of install.bat & pass it to the head.bat script
:: `%0` refers to the name of the currently executing batch script
:: %~dp0 expands to the drive and path of the currently executing batch script
call "..\lib\head.bat" "%~dp0"

echo.
echo "Clearing Porta API cache..."
docker exec -it porta bash -c "php artisan cache:clear"
echo.

call "..\lib\foot.bat"
