@echo off

:: Start a new local environment/scope
setlocal

:: Get the directory of install.bat & pass it to the head.bat script
:: `%0` refers to the name of the currently executing batch script
:: %~dp0 expands to the drive and path of the currently executing batch script
call "..\lib\head.bat" "%~dp0"

:: Run the BACKUP & TRANSFER script in WSL
set RUN_SCRIPT='%BACKUPS_DIR%/transfer-cold-db.sh'
wsl -u %WSL_USER% bash -c "%RUN_SCRIPT%"

REM create dump dir
REM wsl -u %WSL_USER% bash -c "mkdir -p %WSL_DEST%/manual_db_dumps"

REM Run the TRANSFER script in WSL
REM wsl -u %WSL_USER% bash -c "chmod ug+rwx %WSL_DEST%/database-transfer-tools/bin/db-tools.sh && %WSL_DEST%/database-transfer-tools/bin/db-tools.sh"

call "..\lib\foot.bat"
