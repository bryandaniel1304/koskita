#!/bin/sh
set -e

# Platform hosting (Render/Railway/Fly) inject PORT secara dinamis; default 8080 untuk run lokal.
PORT="${PORT:-8080}"
sed "s/__PORT__/${PORT}/" /etc/nginx/http.d/default.conf > /tmp/default.conf
cp /tmp/default.conf /etc/nginx/http.d/default.conf

# APP_KEY HARUS di-set lewat environment variable platform hosting (bukan
# di-generate otomatis di sini), supaya nilainya tetap sama tiap kali
# container restart/redeploy -- kalau berubah-ubah, semua sesi & data
# terenkripsi dari deploy sebelumnya jadi tidak bisa dibaca lagi.
if [ -z "$APP_KEY" ]; then
    echo "FATAL: APP_KEY belum di-set. Generate lewat 'php artisan key:generate --show' lalu set sebagai environment variable APP_KEY di dashboard hosting." >&2
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan migrate --force

exec supervisord -c /etc/supervisord.conf
