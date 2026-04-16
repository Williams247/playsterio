#!/bin/sh
set -e
cd /var/www/html

# Baked .env in the image has empty MONGODB_URI. Laravel loads the file first; on some hosts
# that masks Render's real env vars. Remove the file so only process environment applies.
rm -f .env

# Fail fast with a clear Render log line if secrets are missing
if [ -z "$APP_KEY" ]; then
  echo "audiodec: ERROR — set APP_KEY in Render Environment (run: php artisan key:generate --show locally)" >&2
  exit 1
fi
if [ -z "$MONGODB_URI" ]; then
  echo "audiodec: ERROR — set MONGODB_URI in Render Environment (Atlas connection string)" >&2
  exit 1
fi

export DB_CONNECTION="${DB_CONNECTION:-mongodb}"
export MONGODB_DATABASE="${MONGODB_DATABASE:-audiodec}"

export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

php artisan optimize:clear 2>/dev/null || true

PORT="${PORT:-10000}"
exec php artisan serve --host=0.0.0.0 --port="$PORT"
