@echo off
taskkill /FI "WindowTitle eq SCORM main-app*" /T /F >nul 2>nul
taskkill /FI "WindowTitle eq SCORM scorm-player*" /T /F >nul 2>nul
taskkill /FI "WindowTitle eq SCORM scorm-content*" /T /F >nul 2>nul
echo Stopped.
