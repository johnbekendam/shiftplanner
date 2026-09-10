# APPLICATION-SPECIFIC build steps for ShiftPlanner.
#
# This block is pasted into the marked APPLICATION-SPECIFIC section of
# /srv/apps/shiftplanner/update.sh on the VPS (generated once by the shared
# vps-setup provision-app.sh). The surrounding update.sh supplies $PHP_BIN
# and wraps these steps with git fetch/reset, maintenance mode, and the
# queue-worker stop/start. See doc/vps-deployment.md.

echo "==> Installing Composer dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Installing npm dependencies and building assets"
npm ci && npm run build

echo "==> Running database migrations"
"$PHP_BIN" artisan migrate --force

echo "==> Caching config"
"$PHP_BIN" artisan config:cache

echo "==> Caching routes"
"$PHP_BIN" artisan route:cache

echo "==> Caching views"
"$PHP_BIN" artisan view:cache
