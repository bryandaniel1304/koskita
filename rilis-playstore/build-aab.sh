#!/bin/bash
# Build file .aab (Android App Bundle) untuk diunggah ke Google Play Console.
#
#   API_BASE_URL=https://domain-produksi/api bash rilis-playstore/build-aab.sh
#
# Syarat: Android SDK terpasang (Android Studio) dan
# rilis-playstore/app/android/key.properties sudah dibuat (PANDUAN langkah 3).
# Naikkan "version:" di app/pubspec.yaml (angka setelah +) SETIAP kali
# mengunggah build baru -- Play Console menolak versionCode yang sama.
set -euo pipefail

: "${API_BASE_URL:?Isi dulu: API_BASE_URL=https://domain-produksi/api bash build-aab.sh}"
case "$API_BASE_URL" in
    https://*) ;;
    *) echo "API_BASE_URL harus https:// (aplikasi rilis menolak HTTP biasa)"; exit 1 ;;
esac

HERE="$(cd "$(dirname "$0")" && pwd)"
cd "$HERE/app"

if [ ! -f android/key.properties ]; then
    echo "android/key.properties belum ada -- lihat PANDUAN-PLAYSTORE.md langkah 3."
    exit 1
fi

flutter pub get
flutter build appbundle --release --dart-define=API_BASE_URL="$API_BASE_URL"

VERSION="$(grep '^version:' pubspec.yaml | awk '{print $2}')"
mkdir -p "$HERE/hasil-build"
OUT="$HERE/hasil-build/koskita-$VERSION.aab"
cp build/app/outputs/bundle/release/app-release.aab "$OUT"
echo "Selesai: $OUT"
