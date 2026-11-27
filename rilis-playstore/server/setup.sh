#!/bin/bash
# ======================================================================
# KosKita versi RILIS -- pasang seluruh server sekali jalan di VPS
# Ubuntu 24.04 (atau 22.04) yang masih baru/kosong.
#
# Yang dipasang & diatur otomatis:
#   - Nginx + PHP 8.4-FPM + MySQL 8 + Composer
#   - Kode HANYA folder rilis-playstore/ dari GitHub (branch rilis-playstore),
#     file skripsi tidak ikut diunduh
#   - Database & user MySQL baru (kata sandi acak), file .env produksi
#   - Migrasi, master data fasilitas/aturan, akun master admin pertama
#   - HTTPS gratis Let's Encrypt (perpanjang otomatis)
#   - Queue worker (email/notifikasi) & Reverb (chat realtime) via Supervisor
#   - Cron scheduler (backup database harian), firewall UFW, swap 2 GB
#
# Cara pakai (login SSH sebagai root ke VPS):
#   curl -fsSL https://raw.githubusercontent.com/bryandaniel1304/koskita/rilis-playstore/rilis-playstore/server/setup.sh -o setup.sh
#   DOMAIN=koskitaapp.com ADMIN_EMAIL=admin@contoh.com bash setup.sh
#
# Opsional (bisa juga diisi belakangan di .env lalu jalankan update.sh):
#   KONTAK_EMAIL=...           email kontak publik (default = ADMIN_EMAIL)
#   SMTP_HOST= SMTP_PORT= SMTP_USER= SMTP_PASS= MAIL_FROM=
#                              server email untuk verifikasi akun (WAJIB
#                              sebelum rilis -- tanpa ini email tidak terkirim)
#   REPO_URL= BRANCH=          default: repo GitHub KosKita, branch rilis-playstore
#
# Aman dijalankan ulang: .env, database, dan admin yang sudah ada tidak ditimpa.
# ======================================================================
set -euo pipefail

: "${DOMAIN:?Isi DOMAIN, mis. DOMAIN=koskitaapp.com}"
: "${ADMIN_EMAIL:?Isi ADMIN_EMAIL (email master admin & notifikasi SSL)}"
KONTAK_EMAIL="${KONTAK_EMAIL:-$ADMIN_EMAIL}"
REPO_URL="${REPO_URL:-https://github.com/bryandaniel1304/koskita.git}"
BRANCH="${BRANCH:-rilis-playstore}"
DOMAIN="${DOMAIN#www.}"

BASE=/var/www/koskita
APP=$BASE/rilis-playstore/backend
PHPV=8.4
COMPOSER_HOME_DIR=/var/www/.composer-koskita
CRED_FILE=/root/koskita-kredensial.txt

[ "$(id -u)" -eq 0 ] || { echo "Jalankan sebagai root (sudo -i)."; exit 1; }
. /etc/os-release
[ "$ID" = "ubuntu" ] || { echo "Skrip ini untuk Ubuntu 22.04/24.04."; exit 1; }

log() { echo -e "\n\033[1;34m== $*\033[0m"; }
as_web() { sudo -u www-data -H env COMPOSER_HOME="$COMPOSER_HOME_DIR" "$@"; }
artisan() { (cd "$APP" && as_web php artisan "$@"); }

# Tulis/ganti satu baris KEY=VALUE di .env (nilai selalu dikutip aman).
set_env() {
    python3 - "$APP/.env" "$1" "$2" <<'PY'
import sys, re
path, key, val = sys.argv[1:4]
val = val.replace('\\', '\\\\').replace('"', '\\"')
line = f'{key}="{val}"'
lines = open(path).read().splitlines()
for i, l in enumerate(lines):
    if re.match(rf'^#?\s*{re.escape(key)}=', l):
        lines[i] = line
        break
else:
    lines.append(line)
open(path, 'w').write('\n'.join(lines) + '\n')
PY
}
get_env() { grep -E "^$1=" "$APP/.env" | head -1 | cut -d= -f2- | tr -d '"'; }

# ---------------------------------------------------------------- paket
log "Memasang paket sistem (Nginx, PHP $PHPV, MySQL, Supervisor, Certbot)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y software-properties-common ca-certificates curl unzip git python3 \
    nginx mysql-server supervisor certbot python3-certbot-nginx ufw cron unattended-upgrades
# Ubuntu 24.04 bawaannya PHP 8.3; dependensi KosKita butuh 8.4 -> PPA ondrej/php.
if ! apt-cache show "php$PHPV-fpm" >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
fi
apt-get install -y "php$PHPV-fpm" "php$PHPV-cli" "php$PHPV-mysql" "php$PHPV-mbstring" \
    "php$PHPV-xml" "php$PHPV-curl" "php$PHPV-zip" "php$PHPV-gd" "php$PHPV-bcmath" "php$PHPV-intl"

cat > "/etc/php/$PHPV/fpm/conf.d/99-koskita.ini" <<EOF
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 256M
max_execution_time = 60
expose_php = Off
EOF

if ! command -v composer >/dev/null; then
    log "Memasang Composer"
    EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    ACTUAL="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [ "$EXPECTED" = "$ACTUAL" ] || { echo "Checksum installer Composer tidak cocok -- dibatalkan."; exit 1; }
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm -f /tmp/composer-setup.php
fi
mkdir -p "$COMPOSER_HOME_DIR" && chown www-data:www-data "$COMPOSER_HOME_DIR"

# Swap 2 GB -- VPS 1-2 GB RAM sering kehabisan memori saat composer install.
if ! swapon --show | grep -q .; then
    log "Membuat swap 2 GB"
    fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# ---------------------------------------------------------------- kode
log "Mengambil kode (hanya folder rilis-playstore, branch $BRANCH)"
if [ ! -d "$BASE/.git" ]; then
    mkdir -p "$BASE" && chown www-data:www-data "$BASE"
    as_web git clone --filter=blob:none --no-checkout --branch "$BRANCH" "$REPO_URL" "$BASE"
    # Mode non-cone: HANYA dua folder ini yang diunduh, file lain di repo
    # (termasuk seluruh file skripsi) tidak ikut ke server.
    as_web git -C "$BASE" sparse-checkout set --no-cone '/rilis-playstore/backend/' '/rilis-playstore/server/'
    as_web git -C "$BASE" checkout "$BRANCH"
else
    as_web git -C "$BASE" pull --ff-only
fi

log "composer install"
(cd "$APP" && as_web composer install --no-dev --optimize-autoloader --no-interaction --no-progress)

# ---------------------------------------------------------------- database & .env
if [ ! -f "$APP/.env" ]; then
    log "Membuat database MySQL & file .env produksi"
    DB_PASS="$(openssl rand -hex 20)"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS koskita CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'koskita'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER 'koskita'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON koskita.* TO 'koskita'@'localhost';
FLUSH PRIVILEGES;
SQL
    as_web cp "$APP/.env.example" "$APP/.env"
    chmod 640 "$APP/.env"

    set_env APP_NAME "KosKita"
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "https://$DOMAIN"
    set_env LOG_LEVEL warning
    set_env DB_CONNECTION mysql
    set_env DB_HOST 127.0.0.1
    set_env DB_PORT 3306
    set_env DB_DATABASE koskita
    set_env DB_USERNAME koskita
    set_env DB_PASSWORD "$DB_PASS"
    set_env SESSION_DRIVER database
    set_env SESSION_SECURE_COOKIE true
    set_env QUEUE_CONNECTION database
    set_env CACHE_STORE database
    set_env BROADCAST_CONNECTION reverb
    set_env REVERB_APP_ID "$(shuf -i 100000-999999 -n 1)"
    set_env REVERB_APP_KEY "$(openssl rand -hex 16)"
    set_env REVERB_APP_SECRET "$(openssl rand -hex 24)"
    set_env REVERB_HOST "$DOMAIN"
    set_env REVERB_PORT 443
    set_env REVERB_SCHEME https
    set_env REVERB_SERVER_HOST 127.0.0.1
    set_env REVERB_SERVER_PORT 8080
    set_env KOSKITA_EMAIL_KONTAK "$KONTAK_EMAIL"
    set_env KOSKITA_MASTER_ADMIN_EMAIL "$ADMIN_EMAIL"
    set_env KOSKITA_MASTER_ADMIN_NAMA "Master Admin"
    set_env MAIL_FROM_NAME "KosKita"
fi

if [ -n "${SMTP_HOST:-}" ]; then
    log "Mengatur SMTP email"
    set_env MAIL_MAILER smtp
    set_env MAIL_HOST "$SMTP_HOST"
    set_env MAIL_PORT "${SMTP_PORT:-587}"
    set_env MAIL_USERNAME "${SMTP_USER:-}"
    set_env MAIL_PASSWORD "${SMTP_PASS:-}"
    set_env MAIL_SCHEME "$([ "${SMTP_PORT:-587}" = 465 ] && echo smtps || echo smtp)"
    set_env MAIL_FROM_ADDRESS "${MAIL_FROM:-${SMTP_USER:-$KONTAK_EMAIL}}"
fi
chown www-data:www-data "$APP/.env"

# ---------------------------------------------------------------- Laravel
log "Menyiapkan aplikasi Laravel"
[ -n "$(get_env APP_KEY)" ] || artisan key:generate --force
artisan migrate --force
artisan db:seed --class=ProduksiSeeder --force
ADMIN_OUT="$(artisan koskita:siapkan-master-admin 2>&1 || true)"
echo "$ADMIN_OUT"
if echo "$ADMIN_OUT" | grep -q "Password digenerate"; then
    {
        echo "KosKita -- dibuat $(date '+%Y-%m-%d %H:%M')"
        echo "Panel admin : https://$DOMAIN/login"
        echo "$ADMIN_OUT"
    } > "$CRED_FILE"
    chmod 600 "$CRED_FILE"
fi
[ -L "$APP/public/storage" ] || artisan storage:link
chmod -R ug+rwX "$APP/storage" "$APP/bootstrap/cache"
artisan optimize

# ---------------------------------------------------------------- Nginx & HTTPS
log "Mengatur Nginx untuk $DOMAIN"
sed -e "s#__DOMAIN__#$DOMAIN#g" -e "s#__ROOT__#$APP#g" \
    "$BASE/rilis-playstore/server/nginx-koskita.conf" > /etc/nginx/sites-available/koskita
ln -sf /etc/nginx/sites-available/koskita /etc/nginx/sites-enabled/koskita
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
systemctl restart "php$PHPV-fpm"

log "Firewall (SSH, HTTP, HTTPS)"
ufw allow OpenSSH >/dev/null
ufw allow 'Nginx Full' >/dev/null
ufw --force enable >/dev/null

SERVER_IP="$(curl -fsS4 https://api.ipify.org || true)"
DOMAINS=(-d "$DOMAIN")
if [ -n "$SERVER_IP" ] && [ "$(getent ahostsv4 "www.$DOMAIN" | awk '{print $1; exit}')" = "$SERVER_IP" ]; then
    DOMAINS+=(-d "www.$DOMAIN")
fi
SSL_OK=no
if [ -n "$SERVER_IP" ] && [ "$(getent ahostsv4 "$DOMAIN" | awk '{print $1; exit}')" = "$SERVER_IP" ]; then
    log "Memasang sertifikat HTTPS Let's Encrypt"
    if certbot --nginx "${DOMAINS[@]}" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect --keep-until-expiring; then
        SSL_OK=yes
    fi
else
    echo "!! DNS $DOMAIN belum mengarah ke IP server ini ($SERVER_IP) -- HTTPS dilewati."
    echo "   Setelah DNS aktif, jalankan ulang skrip ini (aman diulang)."
fi

# ---------------------------------------------------------------- proses latar
log "Queue worker, Reverb, dan cron"
cat > /etc/supervisor/conf.d/koskita.conf <<EOF
[program:koskita-queue]
command=/usr/bin/php $APP/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopwaitsecs=60
stdout_logfile=/var/log/koskita-queue.log
redirect_stderr=true

[program:koskita-reverb]
command=/usr/bin/php $APP/artisan reverb:start --host=127.0.0.1 --port=8080
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/log/koskita-reverb.log
redirect_stderr=true

[group:koskita]
programs=koskita-queue,koskita-reverb
EOF
supervisorctl reread >/dev/null
supervisorctl update >/dev/null
supervisorctl restart koskita: >/dev/null || true

cat > /etc/cron.d/koskita <<EOF
# Laravel scheduler (backup database harian 02:00, disimpan 7 hari di storage/app/backups)
* * * * * www-data cd $APP && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
EOF
chmod 644 /etc/cron.d/koskita

# ---------------------------------------------------------------- ringkasan
log "SELESAI"
if [ "$SSL_OK" = yes ]; then echo "Website      : https://$DOMAIN"; else echo "Website      : http://$DOMAIN"; fi
[ "$SSL_OK" = yes ] || echo "               (HTTPS belum aktif -- lihat pesan DNS di atas)"
echo "Panel admin  : https://$DOMAIN/login  ($ADMIN_EMAIL)"
[ -f "$CRED_FILE" ] && echo "Kata sandi admin awal tersimpan di $CRED_FILE (catat lalu ganti setelah login)."
echo "API aplikasi : https://$DOMAIN/api   <- dipakai saat build .aab"
if [ "$(get_env MAIL_MAILER)" != smtp ]; then
    echo
    echo "!! EMAIL BELUM AKTIF: isi MAIL_* di $APP/.env (SMTP) lalu jalankan"
    echo "   bash $BASE/rilis-playstore/server/update.sh"
    echo "   Tanpa email, pengguna tidak bisa verifikasi akun (booking & chat terkunci)."
fi
