#!/bin/bash
# Build ulang aplikasi Flutter versi web untuk uji coba responden
# (coba.koskita). Jalankan di komputer pengembang -- server cPanel tidak
# punya Flutter -- lalu commit folder deploy/coba/app dan "Deploy HEAD
# Commit" di cPanel seperti biasa; deploy.sh yang menyalinnya ke subdomain.
#
#   bash deploy/coba/build.sh
set -euo pipefail

API_BASE_URL="${API_BASE_URL:-https://koskita.inovasidigital-si.id/api}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="$ROOT/deploy/coba/app"

cd "$ROOT/frontend"
flutter build web --release --base-href /app/ --dart-define=API_BASE_URL="$API_BASE_URL"

rm -rf "$OUT"
mkdir -p "$OUT"
cp -R build/web/. "$OUT/"
# CanvasKit dimuat dari CDN Google (gstatic) secara bawaan, jadi salinan
# lokal 36 MB ini tidak pernah dipakai -- tidak perlu ikut ke repo/server.
rm -rf "$OUT/canvaskit"

echo "Selesai: $OUT ($(du -sh "$OUT" | cut -f1))"
