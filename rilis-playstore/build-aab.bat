@echo off
REM Build file .aab untuk Google Play Console (versi Windows).
REM Pemakaian (di Command Prompt, dari folder rilis-playstore):
REM   build-aab.bat https://domain-produksi/api
REM Syarat: Android Studio/SDK terpasang dan app\android\key.properties sudah dibuat.
setlocal
set API_BASE_URL=%~1
if "%API_BASE_URL%"=="" (
  echo Pemakaian: build-aab.bat https://domain-produksi/api
  exit /b 1
)
if /I not "%API_BASE_URL:~0,8%"=="https://" (
  echo API_BASE_URL harus diawali https://
  exit /b 1
)
cd /d "%~dp0app"
if not exist android\key.properties (
  echo android\key.properties belum ada -- lihat PANDUAN-PLAYSTORE.md langkah 3.
  exit /b 1
)
call flutter pub get || exit /b 1
call flutter build appbundle --release --dart-define=API_BASE_URL=%API_BASE_URL% || exit /b 1
if not exist "%~dp0hasil-build" mkdir "%~dp0hasil-build"
copy /Y build\app\outputs\bundle\release\app-release.aab "%~dp0hasil-build\koskita-release.aab"
echo Selesai: %~dp0hasil-build\koskita-release.aab
endlocal
