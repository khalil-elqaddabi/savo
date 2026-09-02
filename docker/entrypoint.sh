#!/bin/sh
set -e

# ---------------------------------------------------------------------------
# Render/Laravel container startup script.
#
# Responsibilities:
#   1. Fix storage + bootstrap/cache permissions for www-data.
#   2. Render the nginx server block with the Render-provided $PORT
#      (falls back to 8080 only if PORT is unset; never hardcoded in prod).
#   3. Run Laravel's production optimization commands *at boot* using the
#      environment variables injected by Render. This is intentionally NOT
#      done during the docker build: at build time there is no env present,
#      so caching there would bake placeholder/MySQL values into the image.
#      The boot-time cache therefore reflects the real PostgreSQL config and
#      never hardcodes local MySQL credentials or local dev settings.
#   4. Migrations are deliberately NOT run here (see report on Render steps).
#   5. Launch nginx + php-fpm under supervisord.
# ---------------------------------------------------------------------------

APP_DIR=/var/www/html

# 1. Permissions ------------------------------------------------------------
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R ug+rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# 2. Render PORT into the nginx server block -------------------------------
PORT="${PORT:-8080}"
export PORT
envsubst '${PORT}' < /etc/nginx/conf.d/default.conf > /etc/nginx/conf.d/default.conf.tmp
cat /etc/nginx/conf.d/default.conf.tmp > /etc/nginx/conf.d/default.conf
rm -f /etc/nginx/conf.d/default.conf.tmp

# 3. Laravel boot-time optimizations (as www-data) -------------------------
su -s /bin/sh www-data -c "cd $APP_DIR && php artisan package:discover && php artisan config:cache && php artisan route:cache && php artisan view:cache"

# 4. Start supervisord (php-fpm + nginx) ------------------------------------
exec "$@"
