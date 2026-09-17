#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY must be set before starting the production container." >&2
    exit 1
fi

php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache

exec "$@"
