#!/bin/bash
# Deploy backend PRODUKSI (aplikasi Play Store) ke cPanel -- instance TERPISAH
# dari server skripsi: folder repo, subdomain, dan DATABASE sendiri.
#
# Dijalankan oleh .cpanel.yml di branch "rilis-playstore" (isinya disalin dari
# rilis-playstore/server/cpanel.yml) saat "Deploy HEAD Commit" diklik pada
# repo ~/koskita-playstore di cPanel Git Version Control.
# Log: ~/koskita-playstore-deploy.log
#
# Instance skripsi (~/koskita, deploy/cpanel/deploy.sh) TIDAK disentuh sama
# sekali oleh script ini.
#
# Tata letak di server:
#   ~/koskita-playstore                 repo lengkap (di luar public_html)
#   ~/public_html/koskita-app           document root subdomain produksi
set -euo pipefail

PHP="${PHP:-/usr/local/bin/php}"
REPO="${REPO:-$HOME/koskita-playstore}"
APP="$REPO/backend"
DOCROOT="${DOCROOT:-$HOME/public_html/koskita-app}"
COMPOSER="$HOME/composer.phar"

echo "== $(date '+%Y-%m-%d %H:%M:%S') deploy PRODUKSI commit $(git -C "$REPO" rev-parse --short HEAD)"

# Pengaman: jangan sampai script ini berjalan terhadap repo/folder skripsi.
if [ "$REPO" = "$HOME/koskita" ] || [ "$DOCROOT" = "$HOME/public_html/koskita/backend/public" ]; then
    echo "!! REPO/DOCROOT menunjuk ke instance skripsi -- dibatalkan."
    exit 1
fi

# 1. Dependensi PHP (composer.phar dipakai bersama dengan instance skripsi).
if [ ! -f "$COMPOSER" ]; then
    echo "-- mengunduh composer.phar"
    "$PHP" -r "copy('https://getcomposer.org/download/latest-stable/composer.phar', '$COMPOSER');"
fi
echo "-- composer install"
cd "$APP"
COMPOSER_HOME="$HOME/.composer" "$PHP" -d memory_limit=-1 "$COMPOSER" install \
    --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts
"$PHP" artisan package:discover --no-ansi

# 2. Document root: file publik Laravel + index.php & .user.ini versi cPanel.
echo "-- menyalin file publik ke $DOCROOT"
mkdir -p "$DOCROOT"
cp -R "$APP/public/." "$DOCROOT/"
sed "s#__APP_PATH__#$APP#" "$REPO/deploy/cpanel/index.php" > "$DOCROOT/index.php"
cp "$REPO/deploy/cpanel/user.ini" "$DOCROOT/.user.ini"
if [ -L "$DOCROOT/storage" ]; then
    :
elif [ -e "$DOCROOT/storage" ]; then
    echo "!! $DOCROOT/storage sudah ada tapi bukan symlink -- dibiarkan; foto unggahan mungkin tidak tampil"
else
    ln -s "$APP/storage/app/public" "$DOCROOT/storage"
fi
chmod 711 "$REPO"

# 3. Laravel -- hanya setelah .env dibuat (lihat PANDUAN-PLAYSTORE.md langkah 2).
if [ ! -f "$APP/.env" ]; then
    echo "!! $APP/.env belum ada: migrate & cache dilewati. Buat .env lalu deploy ulang."
    exit 0
fi
chmod 600 "$APP/.env"
if ! grep -q '^KOSKITA_HANYA_KOS_TERVERIFIKASI=true' "$APP/.env"; then
    echo "!! .env produksi belum berisi KOSKITA_HANYA_KOS_TERVERIFIKASI=true -- dibatalkan"
    echo "   supaya kos yang belum diverifikasi tidak pernah tampil di aplikasi Play Store."
    exit 1
fi
if ! grep -q '^APP_KEY=base64:' "$APP/.env"; then
    echo "-- membuat APP_KEY"
    "$PHP" artisan key:generate --force
fi
echo "-- migrate"
"$PHP" artisan migrate --force
# Hanya master fasilitas & aturan -- TIDAK ada kos/pengguna contoh.
echo "-- seed master data produksi"
"$PHP" artisan db:seed --class=ProduksiSeeder --force
echo "-- cache konfigurasi, rute, view"
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

echo "== selesai"
