@echo off

setlocal enabledelayedexpansion


:: Get the directory of install.bat & pass it to the head.bat script
:: `%0` refers to the name of the currently executing batch script
:: %~dp0 expands to the drive and path of the currently executing batch script
call "..\lib\head.bat" "%~dp0"

rem Define your fixed list of strings
set "string_list=porta-db porta-db-2 porta-db-3"

rem Get a list of running Docker containers
for /f "tokens=*" %%a in ('docker ps --format "{{.Names}}"') do (
    set "container_name=%%a"
    rem Check if the container name matches any of the strings in the list
    for %%b in (%string_list%) do (
        if "!container_name!"=="%%b" (
            echo.
            echo Accessing database !container_name! container shell...
            echo.
            goto :end
        )
    )
)

:: will skip this if a matching container is found
echo No matching container found.

:: matching container was found
:end

docker exec -it !container_name! mysql -uporta -pporta -Dporta

REM call "..\lib\foot.bat"
