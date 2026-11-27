#!/bin/bash
# Perbarui server KosKita rilis ke commit terbaru branch rilis-playstore.
#   sudo bash /var/www/koskita/rilis-playstore/server/update.sh
# Juga dipakai sesudah mengubah .env (mis. mengisi SMTP).
set -euo pipefail
BASE=/var/www/koskita
APP=$BASE/rilis-playstore/backend
[ "$(id -u)" -eq 0 ] || { echo "Jalankan dengan sudo."; exit 1; }
as_web() { sudo -u www-data -H env COMPOSER_HOME=/var/www/.composer-koskita "$@"; }
artisan() { (cd "$APP" && as_web php artisan "$@"); }

echo "== $(date '+%F %T') update KosKita"
artisan down --retry=30 || true
trap 'artisan up || true' EXIT
as_web git -C "$BASE" pull --ff-only
(cd "$APP" && as_web composer install --no-dev --optimize-autoloader --no-interaction --no-progress)
artisan migrate --force
artisan db:seed --class=ProduksiSeeder --force
artisan koskita:siapkan-master-admin
artisan optimize:clear
artisan optimize
supervisorctl restart koskita:
systemctl reload php8.4-fpm
echo "== selesai: $(as_web git -C "$BASE" log --oneline -1)"
