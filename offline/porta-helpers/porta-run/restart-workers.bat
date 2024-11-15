@echo off

:: Start a new local environment/scope
setlocal

:: Get the directory of install.bat & pass it to the head.bat script
:: `%0` refers to the name of the currently executing batch script
:: %~dp0 expands to the drive and path of the currently executing batch script
call "..\lib\head.bat" "%~dp0"

echo.
REM echo "Restarting Porta queue workers..."
REM docker exec -it porta bash -c "supervisorctl restart porta-worker:*"
REM echo.
echo "Restarting Porta scheduled task workers..."
docker exec -it porta bash -c "supervisorctl restart porta-task-worker:*"
echo.
echo "Restarting Porta Horizon queue workers..."
docker exec -it porta bash -c "supervisorctl restart porta-horizon-worker:*"
echo.

call "..\lib\foot.bat"
