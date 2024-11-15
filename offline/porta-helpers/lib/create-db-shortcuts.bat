@echo off

:: %WSL_DEST% -- porta home dir, i.e., `/home/px/porta`
:: %ONPREM_DIRNAME% -- on prem dirname, i.e., `porta-onprem`

:: Create Shortcuts
echo.
echo Creating shortcuts to Porta's database tools...

:: Set the path to the folder in the same directory as the batch script
set "DB_FOLDER_PATH=%CURRDIR%\database"

:: Find chrome path
call "%~dp0find-chrome.bat"

:: Make the shortcuts
REM echo Host config: %HOST_CONFIG_FILE%
call "%~dp0create-web-shortcut.bat" "%CURRDIR%%HOST_CONFIG_FILE%" "%DB_FOLDER_PATH%" "Porta On Prem -- DB Dashboard" "health" 8000
REM echo Backup config: %BACKUP_CONFIG_FILE%
call "%~dp0create-web-shortcut.bat" "%CURRDIR%%BACKUP_CONFIG_FILE%" "%DB_FOLDER_PATH%" "Porta On Prem Backup -- DB Dashboard" "health" 8000

:: echo.
:: set "TARGET_FOLDER=%DB_FOLDER_PATH%"
:: set "SHORTCUT_NAME=Porta On Prem Database Actions"
:: :: Define the path for the shortcut file on the desktop
:: set "SHORTCUT_PATH=%USERPROFILE%\Desktop\%SHORTCUT_NAME%"
:: echo Creating shortcut to folder "%DB_FOLDER_PATH%" at "%SHORTCUT_PATH%"...

:: :: Delete the shortcut file if it already exists
:: if exist "%SHORTCUT_PATH%" del "%SHORTCUT_PATH%"

REM echo Creating symbolic link...
REM :: Create the symbolic link to the folder -- NEEDS ADMIN PRIVILEGES
REM mklink /D "%SHORTCUT_PATH%" "%DB_FOLDER_PATH%"

REM REM NOTE -- NEEDS 3rd PARTY TOOL
REM echo Creating shortcut link...
REM nircmd.exe shortcut "%DB_FOLDER_PATH%" "%SHORTCUT_PATH%"

REM REM Create the VBScript file -- creates correctly but does not work
REM (
REM     echo Set objWS = WScript.CreateObject^("WScript.Shell"^)
REM     echo strDesktop = objWS.SpecialFolders^("Desktop"^)
REM     echo Set objSC = objWS.CreateShortcut^(strDesktop ^& "\\" ^& "%SHORTCUT_NAME%.lnk"^)
REM     echo objSC.TargetPath = "%TARGET_FOLDER%"
REM     echo objSC.Save
REM ) > "%TEMP%\CreateShortcut.vbs"

REM REM Run the VBScript file to create the shortcut
REM cscript //NoLogo "%TEMP%\CreateShortcut.vbs"

REM REM Clean up the temporary VBScript file
REM del "%TEMP%\CreateShortcut.vbs" /f /q

REM -- works for exe but not folder
REM SETLOCAL ENABLEDELAYEDEXPANSION
REM SET LinkName=Hello
REM SET Esc_LinkDest=%%HOMEDRIVE%%%%HOMEPATH%%\Desktop\!LinkName!.lnk
REM SET Esc_LinkTarget=%%SYSTEMROOT%%\notepad.exe
REM SET cSctVBS=CreateShortcut.vbs
REM SET LOG=".\%~N0_runtime.log"
REM ((
REM   echo Set oWS = WScript.CreateObject^("WScript.Shell"^)
REM   echo sLinkFile = oWS.ExpandEnvironmentStrings^("!Esc_LinkDest!"^)
REM   echo Set oLink = oWS.CreateShortcut^(sLinkFile^)
REM   echo oLink.TargetPath = oWS.ExpandEnvironmentStrings^("!Esc_LinkTarget!"^)
REM   echo oLink.Save
REM )1>!cSctVBS!
REM cscript //nologo .\!cSctVBS!
REM DEL !cSctVBS! /f /q
REM )1>>!LOG! 2>>&1


REM REM doesnt work at all -- syntax error
REM set TARGET="%USERPROFILE%\Desktop"
REM set SHORTCUT="%SHORTCUT_PATH%.lnk"
REM set PWS=powershell.exe -ExecutionPolicy Bypass -NoLogo -NonInteractive -NoProfile
REM %PWS% -Command "$ws = New-Object -ComObject WScript.Shell; $s = $ws.CreateShortcut(%SHORTCUT%); $S.TargetPath = %TARGET%; $S.Save()"


:: echo.
:: echo Shortcut to folder "%DB_FOLDER_PATH%" created on the desktop.

