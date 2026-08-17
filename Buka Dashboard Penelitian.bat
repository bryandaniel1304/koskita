@echo off
title KosKita - Dashboard Penelitian
call "%~dp0_start-servers.bat"
echo [KosKita] Membuka Dashboard Penelitian di browser...
start "" "http://127.0.0.1:8001/"
timeout /t 2 /nobreak >nul
exit
