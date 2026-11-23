#!/bin/sh
# Boot script: prepares env, database and caches, then hands over to supervisord.
set -e
cd /var/www/html

# --- Environment -----------------------------------------------------------
# No APP_KEY given (fresh clone / demo deploy) → make one for this boot.
if [ -z "${APP_KEY:-}" ]; then
    export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
fi

# The Reverb key travels in the WebSocket URL (wss://host/app/<key>): keep it URL-safe.
# (Render's generated values are base64 and may contain "/", "+" or "=".)
if [ -n "${REVERB_APP_KEY:-}" ]; then
    export REVERB_APP_KEY="$(printf '%s' "$REVERB_APP_KEY" | tr -dc 'A-Za-z0-9')"
fi

# On Render the public URL is only known at runtime.
if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="${APP_URL:-$RENDER_EXTERNAL_URL}"
    export REVERB_CLIENT_HOST="${REVERB_CLIENT_HOST:-$RENDER_EXTERNAL_HOSTNAME}"
fi

# Laravel reads its config from a .env file + real env vars; an empty .env keeps it quiet.
[ -f .env ] || touch .env

# --- Database --------------------------------------------------------------
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    export DB_DATABASE="$DB_FILE"
    mkdir -p "$(dirname "$DB_FILE")"

    # DEMO_RESET=true: every boot starts from clean demo data (used for the public demo).
    if [ "${DEMO_RESET:-false}" = "true" ] || [ ! -s "$DB_FILE" ]; then
        rm -f "$DB_FILE"
        touch "$DB_FILE"
        php artisan migrate:fresh --seed --force
    else
        php artisan migrate --force
    fi
    chown -R www-data:www-data "$(dirname "$DB_FILE")"
else
    php artisan migrate --force
fi

# --- Storage & caches --------------------------------------------------------
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
php artisan storage:link --force >/dev/null 2>&1 || true
php artisan config:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache

# --- Nginx listens on the port the platform gives us ---------------------------
mkdir -p /run/nginx
sed "s/__PORT__/${PORT:-8000}/" /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

exec "$@"
