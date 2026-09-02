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
#   4. Run database migrations against the PRODUCTION database (PostgreSQL).
#      This is required because the schema (sessions/cache/jobs/... tables)
#      must exist before the app starts serving requests. Laravel's `migrate`
#      is idempotent (it tracks applied migrations in the `migrations` table)
#      and safe to re-run on every deploy -- it only applies pending migrations
#      and is NOT `migrate:fresh`. Render Free has no Shell and no pre-deploy
#      hook, so migrations run here at startup. A migration failure aborts
#      startup (set -e) so the app never serves traffic without its schema.
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

# 4. Run pending migrations against the PRODUCTION database ----------------
# Idempotent: only applies new migrations; safe to repeat on every deploy.
# --force bypasses the production confirmation prompt (pairing the otherwise
# interactive prompt with --no-interaction keeps startup clean).
# Failure propagates (set -e) so a broken/missing schema aborts startup rather
# than serving a half-configured app.
su -s /bin/sh www-data -c "cd $APP_DIR && php artisan migrate --force --no-interaction"

# 5. Start supervisord (php-fpm + nginx) ------------------------------------
exec "$@"
