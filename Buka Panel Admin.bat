@echo off
title KosKita - Panel Admin
call "%~dp0_start-servers.bat"
echo [KosKita] Membuka Panel Admin di browser...
start "" "http://127.0.0.1:8000/login"
timeout /t 2 /nobreak >nul
exit
