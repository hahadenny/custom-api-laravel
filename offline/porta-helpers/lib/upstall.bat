@echo off

:: %WSL_DEST% -- porta home dir, i.e., `/home/px/porta`
:: %ONPREM_DIRNAME% -- on prem dirname, i.e., `porta-onprem`
:: ...and others, defined in the calling script

:: make sure docker has log rotation enabled
:: requires admin privs
call "%~dp0\setup-docker-logrotate.bat"

:: Check if UNZIP_WIN_FILEPATH exists and delete it
if exist "%UNZIP_WIN_FILEPATH%" (
     echo Deleting existing directory '%UNZIP_WIN_FILEPATH%'...
     rd /s /q "%UNZIP_WIN_FILEPATH%"
)

:: Unzip the archive
echo.
echo Extracting the zip file '%ZIP_WIN_FILEPATH%' to '%UNZIP_WIN_FILEPATH%'...
powershell -Command "Expand-Archive -Path '%ZIP_WIN_FILEPATH%' -DestinationPath %UNZIP_WIN_FILEPATH%"

:: Convert Windows directory path to WSL equivalent
for /f "usebackq tokens=*" %%i in (`wsl wslpath -u "%UNZIP_WIN_FILEPATH%"`) do set "ZIP_WSLDIR=%%i"
:: The path to the windows unzipped archive in WSL
set ZIP_WSL_FILEPATH=%ZIP_WSLDIR%/%ONPREM_DIRNAME%

:: Prepare to copy to WSL
echo.
echo User %WSL_USER% is preparing to copy to WSL: make porta dir, rename old dir and remove existing files...
:: make ~/porta if not exists
wsl -u %WSL_USER% bash -c "mkdir -p '%WSL_DEST%'"

:: if ~/porta/porta-onprem exists, rename it to ~/porta/porta-onprem-old
:: remove ~/porta/porta-onprem-old if it exists
wsl -u %WSL_USER% bash -c "if [[ -e '%WSL_DEST%/%ONPREM_DIRNAME%-old' ]]; then rm -rf '%WSL_DEST%/%ONPREM_DIRNAME%-old'; fi"
wsl -u %WSL_USER% bash -c "if [[ -e '%WSL_DEST%/%ONPREM_DIRNAME%' ]]; then mv '%WSL_DEST%/%ONPREM_DIRNAME%' '%WSL_DEST%/%ONPREM_DIRNAME%-old'; fi"

:: find version number from the existing installation -- LOTS OF ERRORS HERE
:: if ~/porta/porta-onprem exists, rename it to ~/porta/porta-onprem-X.X.X+XXXX
REM wsl -u %WSL_USER% bash -c "\
REM if [[ -e '%WSL_DEST%/%ONPREM_DIRNAME%' ]]; then \
REM     VERSION=$(< '%WSL_DEST%/%ONPREM_DIRNAME%/version.txt'); \
REM     echo $VERSION; \
REM     mv '%WSL_DEST%/%ONPREM_DIRNAME%' '%WSL_DEST%/%ONPREM_DIRNAME%-$VERSION'; \
REM else \
REM     echo 'no dir!'; \
REM fi"


:: Copy the unzipped folder to WSL -- this takes care of the nested folder name porta-onprem/porta-onprem
echo.
echo User %WSL_USER% is copying '%ZIP_WSL_FILEPATH%' to WSL filesystem at '%WSL_DEST%'...
wsl -u %WSL_USER% bash -c "cp -ra '%ZIP_WSL_FILEPATH%' '%WSL_DEST%'"

:: Run the chmod and install script in WSL
echo.
echo Running WSL scripts...
wsl -u %WSL_USER% bash -c "cd '%WSL_DEST%' && sudo chown -R %WSL_USER%:%WSL_USER% . && chmod -R ug+x '%WSL_SCRIPTS_PATH%' && chmod -R 775 '%WSL_SCRIPTS_PATH%/install/config'"

REM keep the zipped porta-onprem in case of re-installs for debugging
REM :: Remove zip file now that we already unzipped and copied it to WSL
REM if exist "%ZIP_WIN_FILEPATH%" (
REM      echo Removing '%ZIP_WIN_FILEPATH%'...
REM      del "%ZIP_WIN_FILEPATH%"
REM )

:: Remove now that we already copied it to WSL
if exist "%UNZIP_WIN_FILEPATH%" (
     echo Removing directory '%UNZIP_WIN_FILEPATH%'...
     rd /s /q "%UNZIP_WIN_FILEPATH%"
)
