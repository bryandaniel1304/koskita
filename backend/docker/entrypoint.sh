#!/bin/sh
set -e

# Platform hosting (Render/Railway/Fly) inject PORT secara dinamis; default 8080 untuk run lokal.
PORT="${PORT:-8080}"
sed "s/__PORT__/${PORT}/" /etc/nginx/http.d/default.conf > /tmp/default.conf
cp /tmp/default.conf /etc/nginx/http.d/default.conf

# Generate APP_KEY otomatis kalau belum di-set (mis. deploy pertama kali).
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

php artisan config:cache
php artisan route:cache
php artisan migrate --force

exec supervisord -c /etc/supervisord.conf
