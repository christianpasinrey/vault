#!/bin/sh
# Production container startup.
#
# Everything generated here lives on the volume, not in the image: rebuilding
# the image can neither destroy the vault nor invalidate open sessions.
set -eu

DATA_DIR="${VAULT_DATA_DIR:-/vault-data}"
mkdir -p "$DATA_DIR"

# --- APP_KEY -----------------------------------------------------------------
# Encrypts the session cookies. If it changed, every open session would be
# invalidated, so it is generated once and kept. Set APP_KEY from outside if you
# would rather manage it yourself.
if [ -z "${APP_KEY:-}" ]; then
    if [ ! -f "$DATA_DIR/app_key" ]; then
        php artisan key:generate --show > "$DATA_DIR/app_key"
        chmod 600 "$DATA_DIR/app_key"
        echo "vault: APP_KEY generated at $DATA_DIR/app_key"
    fi
    APP_KEY="$(cat "$DATA_DIR/app_key")"
    export APP_KEY
fi

# --- Database ----------------------------------------------------------------
DB_DATABASE="${DB_DATABASE:-$DATA_DIR/database.sqlite}"
export DB_DATABASE DB_CONNECTION="${DB_CONNECTION:-sqlite}"

if [ ! -f "$DB_DATABASE" ]; then
    install -m 600 /dev/null "$DB_DATABASE"
    echo "vault: vault created at $DB_DATABASE"
fi

# The scheduler container shares this volume and this entrypoint, but must not
# migrate or rebuild caches: the web container owns that, and two processes
# doing it at once on the same SQLite file is asking for trouble.
if [ -n "${VAULT_SIDECAR:-}" ]; then
    exec "$@"
fi

php artisan migrate --force

# --- Boot cache --------------------------------------------------------------
# Rebuilt on every start because it depends on environment variables that may
# change between deployments (APP_URL, SERVER_NAME).
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
