#!/bin/bash
# Deploy KosKita ke hosting cPanel tanpa akses SSH.
#
# Dijalankan oleh .cpanel.yml saat "Deploy HEAD Commit" diklik di cPanel
# Git Version Control (log: ~/koskita-deploy.log). Aman dijalankan berulang:
# setiap langkah memeriksa keadaan dulu, jadi deploy pertama maupun update
# berikutnya memakai script yang sama.
#
# Tata letak di server:
#   ~/koskita                                   repo lengkap (di luar public_html)
#   ~/public_html/koskita/backend/public        document root subdomain -- hanya
#                                               file publik + index.php yang memuat
#                                               aplikasi dari ~/koskita/backend
set -euo pipefail

PHP="${PHP:-/usr/local/bin/php}"   # PHP 8.4 CLI di server ini
REPO="$HOME/koskita"
APP="$REPO/backend"
DOCROOT="$HOME/public_html/koskita/backend/public"
COMPOSER="$HOME/composer.phar"

echo "== $(date '+%Y-%m-%d %H:%M:%S') deploy commit $(git -C "$REPO" rev-parse --short HEAD)"

# 1. Dependensi PHP. Server tidak menyediakan composer, jadi unduh composer.phar sekali.
if [ ! -f "$COMPOSER" ]; then
    echo "-- mengunduh composer.phar"
    "$PHP" -r "copy('https://getcomposer.org/download/latest-stable/composer.phar', '$COMPOSER');"
fi
echo "-- composer install"
cd "$APP"
# --no-scripts: server ini menonaktifkan proc_open, sedangkan script
# post-autoload-dump composer menjalankan `artisan package:discover` sebagai
# proses terpisah (gagal: "The Process class relies on proc_open"). Perintah
# yang sama dijalankan langsung di bawah, tanpa proses terpisah.
COMPOSER_HOME="$HOME/.composer" "$PHP" -d memory_limit=-1 "$COMPOSER" install \
    --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts
"$PHP" artisan package:discover --no-ansi

# 2. Document root: file publik Laravel, lalu index.php & .user.ini versi cPanel.
echo "-- menyalin file publik ke $DOCROOT"
mkdir -p "$DOCROOT"
cp -R "$APP/public/." "$DOCROOT/"
sed "s#__APP_PATH__#$APP#" "$REPO/deploy/cpanel/index.php" > "$DOCROOT/index.php"
cp "$REPO/deploy/cpanel/user.ini" "$DOCROOT/.user.ini"
if [ -L "$DOCROOT/storage" ]; then
    :   # link sudah ada dari deploy sebelumnya
elif [ -e "$DOCROOT/storage" ]; then
    echo "!! $DOCROOT/storage sudah ada tapi bukan symlink -- dibiarkan; foto unggahan mungkin tidak tampil"
else
    ln -s "$APP/storage/app/public" "$DOCROOT/storage"
fi
# Foto (storage) disajikan Apache langsung lewat link di atas, dan Apache
# berjalan sebagai user lain -- cPanel membuat folder repo dengan izin 0700
# sehingga Apache tidak bisa menembusnya (foto 404). 711 = boleh dilewati,
# tetapi isinya tetap tidak bisa didaftar.
chmod 711 "$REPO"

# 2b. Halaman uji coba responden (coba.koskita) -- statis, hasil
# deploy/coba/build.sh yang sudah di-commit. Dilewati kalau document root
# subdomainnya belum dibuat di cPanel > Domains.
COBA_DOCROOT="${COBA_DOCROOT:-$HOME/public_html/koskita/coba}"
if [ -d "$COBA_DOCROOT" ]; then
    echo "-- menyalin halaman uji coba ke $COBA_DOCROOT"
    rm -rf "$COBA_DOCROOT/app"
    cp -R "$REPO/deploy/coba/." "$COBA_DOCROOT/"
    rm -f "$COBA_DOCROOT/build.sh"
else
    echo "-- $COBA_DOCROOT belum ada: halaman uji coba dilewati"
fi

# 3. Laravel -- hanya setelah .env dibuat (lewat File Manager, dari .env.example).
if [ ! -f "$APP/.env" ]; then
    echo "!! $APP/.env belum ada: migrate & cache dilewati. Buat .env lalu deploy ulang."
    exit 0
fi
chmod 600 "$APP/.env"   # PHP berjalan sebagai pemilik akun; user lain tidak perlu membacanya
if ! grep -q '^APP_KEY=base64:' "$APP/.env"; then
    echo "-- membuat APP_KEY"
    "$PHP" artisan key:generate --force
fi
echo "-- migrate"
"$PHP" artisan migrate --force
echo "-- cache konfigurasi, rute, view"
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

echo "== selesai"
