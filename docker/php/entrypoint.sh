#!/bin/sh
set -e

# Config/route caches are built here (at container start) rather than baked
# into the image at build time, so the same image is portable across
# environments that provide different env vars.
if [ "$APP_ENV" != "local" ]; then
  php artisan config:cache
  php artisan route:cache
fi

exec "$@"
