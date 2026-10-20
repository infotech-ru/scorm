@echo off
setlocal

set "MAIN_APP_HOST=app.local"
set "MAIN_APP_PORT=8090"
set "SCORM_PLAYER_HOST=scorm.local"
set "SCORM_PLAYER_PORT=8091"
set "SCORM_CONTENT_HOST=scorm.local"
set "SCORM_CONTENT_PORT=8092"

if exist "%~dp0.env" (
    for /f "usebackq eol=# tokens=1,* delims==" %%A in ("%~dp0.env") do set "%%A=%%B"
)

set "MAIN_APP_URL=http://%MAIN_APP_HOST%:%MAIN_APP_PORT%"
set "SCORM_PLAYER_URL=http://%SCORM_PLAYER_HOST%:%SCORM_PLAYER_PORT%"
set "SCORM_CONTENT_URL=http://%SCORM_CONTENT_HOST%:%SCORM_CONTENT_PORT%"

where php >nul 2>nul || (echo php not found in PATH & exit /b 1)

start "SCORM main-app" cmd /k "cd /d %~dp0main-app\web && php -S %MAIN_APP_HOST%:%MAIN_APP_PORT% router.php"
start "SCORM scorm-player" cmd /k "cd /d %~dp0scorm-player\web && php -S %SCORM_PLAYER_HOST%:%SCORM_PLAYER_PORT% router.php"
start "SCORM scorm-content" cmd /k "cd /d %~dp0scorm-player\content && php -S %SCORM_CONTENT_HOST%:%SCORM_CONTENT_PORT% router.php"

echo Main app:     %MAIN_APP_URL%
echo SCORM player: %SCORM_PLAYER_URL%
echo Content:      %SCORM_CONTENT_URL%
echo Stop with stop-servers.bat
