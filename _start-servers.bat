@echo off
REM Skrip bantuan -- dipanggil otomatis oleh shortcut "Buka ...bat" lain di
REM folder ini. Menyalakan server backend & dashboard penelitian KALAU
REM belum jalan (aman diklik berkali-kali, tidak akan nyalain dobel).
REM Pakai "start ... /D <folder>" (bukan "cd && ...") supaya tidak rawan
REM salah parsing gara-gara tanda && di dalam start/cmd bersarang.
setlocal

echo [KosKita] Mengecek server backend (port 8000)...
netstat -ano | findstr ":8000 " | findstr "LISTENING" >nul 2>&1
if errorlevel 1 (
    echo [KosKita] Server backend belum jalan/salah konfigurasi, menyalakan...
    start "KosKita Backend" /min /D "D:\KosKita\backend" php artisan serve --host=0.0.0.0 --port=8000
    timeout /t 3 /nobreak >nul
) else (
    echo [KosKita] Server backend sudah jalan.
)

echo [KosKita] Mengecek server dashboard penelitian (port 8001)...
netstat -ano | findstr ":8001 " | findstr "LISTENING" >nul 2>&1
if errorlevel 1 (
    echo [KosKita] Server dashboard penelitian belum jalan, menyalakan...
    start "KosKita Research Dashboard" /min /D "D:\KosKita\research-dashboard" php artisan serve --host=127.0.0.1 --port=8001
    timeout /t 3 /nobreak >nul
) else (
    echo [KosKita] Server dashboard penelitian sudah jalan.
)

endlocal
