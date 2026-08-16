#!/bin/sh
#
# Everything that has to happen against a *running* database, rather than at
# build time when there isn't one.
set -e

# Railway hands the port in at runtime; FrankenPHP is told to listen on it
# rather than on a number baked into the image.
: "${PORT:=8080}"

# A missing key is a 500 on every page with nothing in the log to explain it,
# so say so plainly instead.
if [ -z "${APP_KEY}" ]; then
    echo "APP_KEY is not set. Generate one with 'php artisan key:generate --show' and set it in the service variables." >&2
    exit 1
fi

php artisan migrate --force --no-interaction

# Cached at boot rather than in the image: the config cache bakes in
# environment variables, and those are only known once the service starts.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize

# The public disk holds invoice PDFs, driver licences and vehicle photographs.
php artisan storage:link || true

exec frankenphp php-server --root /app/public --listen ":${PORT}"
