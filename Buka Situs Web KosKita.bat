@echo off
title KosKita - Situs Web
call "%~dp0_start-servers.bat"
echo [KosKita] Membuka Situs Web KosKita di browser...
start "" "http://127.0.0.1:8000/"
timeout /t 2 /nobreak >nul
exit
