@echo off

:: Start a new local environment/scope
setlocal

:: Get the directory of install.bat & pass it to the head.bat script
:: `%0` refers to the name of the currently executing batch script
:: %~dp0 expands to the drive and path of the currently executing batch script
call "..\lib\head.bat" "%~dp0"

echo.
echo "Forcing rotation of Porta's php, laravel, horizon, task logs..."
:: Needs root perms to rotate logs
docker exec -it -u 0 porta bash -c "logrotate -fv /etc/logrotate.conf"
echo.

call "..\lib\foot.bat"
