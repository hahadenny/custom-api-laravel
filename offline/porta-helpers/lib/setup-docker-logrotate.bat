@echo off

:: make sure docker has log rotation enabled

set "JsonFilePath=%USERPROFILE%\.docker\daemon.json"

echo.
echo Ensuring Docker's '%JsonFilePath%' is setup to auto-rotate logs...

:: use PowerShell to read and update JSON
:: set json config for logrotate and exposing prometheus metrics

REM PowerShell -Command " $jsonPath = '%JsonFilePath%'; `
REM     $dockerDaemonJson = Get-Content -Path $jsonPath -Raw | ConvertFrom-Json; `
REM     if (-not $dockerDaemonJson.'log-driver') { `
REM         `$dockerDaemonJson | Add-Member -NotePropertyName 'log-driver' -NotePropertyValue 'json-file' -Force; `
REM     } `
REM     if (-not $dockerDaemonJson.'log-opts') { `
REM         $logOpts = @{ `
REM             'max-size' = '10m'; `
REM             'max-file' = '3'; `
REM         }; `
REM         $dockerDaemonJson | Add-Member -NotePropertyName 'log-opts' -NotePropertyValue $logOpts -Force; `
REM     } `
REM     if (-not $dockerDaemonJson.'metrics-addr') { `
REM         $dockerDaemonJson | Add-Member -NotePropertyName 'metrics-addr' -NotePropertyValue '127.0.0.1:9323' -Force; `
REM     } `
REM     $dockerDaemonJson | ConvertTo-Json | Set-Content -Path $jsonPath; `
REM "

:: newlines is a nightmare in powershell, so we use a single line command
PowerShell -Command " $jsonPath = '%JsonFilePath%';    $dockerDaemonJson = Get-Content -Path $jsonPath -Raw | ConvertFrom-Json;    if (-not $dockerDaemonJson.'log-driver') {        `$dockerDaemonJson | Add-Member -NotePropertyName 'log-driver' -NotePropertyValue 'json-file' -Force;    }    if (-not $dockerDaemonJson.'log-opts') {        $logOpts = @{            'max-size' = '10m';            'max-file' = '3';        };        $dockerDaemonJson | Add-Member -NotePropertyName 'log-opts' -NotePropertyValue $logOpts -Force;    }    if (-not $dockerDaemonJson.'metrics-addr') {        $dockerDaemonJson | Add-Member -NotePropertyName 'metrics-addr' -NotePropertyValue '127.0.0.1:9323' -Force;    }    $dockerDaemonJson | ConvertTo-Json | Set-Content -Path $jsonPath;"

:: requires admin privs
echo Restarting Docker Desktop...
PowerShell -Command "Restart-Service -Name 'com.docker.service' -Force"
