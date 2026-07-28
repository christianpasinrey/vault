#!/usr/bin/env bash
# Copy the encrypted vault off the server.
#
# The contents are already opaque: whoever gets hold of this file does not have
# the master password and cannot read a single item. That is what makes it safe
# to keep these copies somewhere less guarded than the server itself.
#
# Run it from the host, e.g. daily from cron:
#   0 4 * * * /var/www/vault/deploy/backup.sh >> /var/log/vault-backup.log 2>&1
set -euo pipefail

COMPOSE_DIR="${COMPOSE_DIR:-$(cd "$(dirname "$0")" && pwd)}"
DESTINATION="${VAULT_BACKUP_DIR:-/var/backups/vault}"
KEEP_DAYS="${VAULT_BACKUP_KEEP_DAYS:-30}"
STAMP="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$DESTINATION"

cd "$COMPOSE_DIR"

# sqlite3's .backup is safe while the application is running; `cp` is not — it
# can capture a half-written page and produce a file that will not open.
docker compose exec -T vault sh -c \
    "sqlite3 /vault-data/database.sqlite \".backup '/vault-data/backup-$STAMP.sqlite'\""

docker compose cp "vault:/vault-data/backup-$STAMP.sqlite" "$DESTINATION/vault-$STAMP.sqlite"
docker compose exec -T vault rm -f "/vault-data/backup-$STAMP.sqlite"

# The APP_KEY is not secret material for the vault, but without it every open
# session is invalidated and everyone has to sign in again.
if [ ! -f "$DESTINATION/app_key" ]; then
    docker compose cp vault:/vault-data/app_key "$DESTINATION/app_key"
    chmod 600 "$DESTINATION/app_key"
fi

find "$DESTINATION" -name 'vault-*.sqlite' -mtime "+$KEEP_DAYS" -delete

echo "Backup written: $DESTINATION/vault-$STAMP.sqlite"
