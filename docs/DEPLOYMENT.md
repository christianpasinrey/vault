# Deployment

Two separate environments that should not be mixed up:

| | Purpose | File |
|---|---|---|
| **Laravel Sail** | Local development | `compose.yaml` (root) |
| **FrankenPHP** | Production on a server | `deploy/compose.yaml` |

Sail mounts the source as a volume and runs with `APP_DEBUG`. **Do not deploy it
to a server.**

---

## Local development

Requires Docker. From the repository root:

```bash
cp .env.example .env
composer install
npm install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev
```

Then create the account:

```bash
./vendor/bin/sail artisan vault:init
```

The command asks for an email address and prints a single-use link. The master
password is chosen in the browser; the server never sees it.

> Passkeys work on `http://localhost` because browsers treat it as a secure
> context. On any other domain without HTTPS, they do not.

If you would rather skip Docker locally (Laragon, Herd, `php artisan serve`),
the project works just the same: Sail is a convenience, not a requirement.

---

## Production on a server

### Before you start

1. A domain pointing at the server's IP. **It has to resolve already**, or
   Let's Encrypt cannot issue the certificate.
2. Ports 80 and 443 open.
3. Docker and the Compose plugin installed.

### Bring it up

```bash
git clone <your-repo> vault && cd vault/deploy
cp .env.example .env
$EDITOR .env          # set your VAULT_DOMAIN
docker compose up -d --build
```

Startup generates the `APP_KEY`, creates the SQLite file and runs the
migrations. All of that lives on the `vault-data` volume, not in the image:
rebuilding destroys nothing.

### Create the account

```bash
docker compose exec vault php artisan vault:init
```

Open the link it prints within the next 15 minutes.

### Update

```bash
git pull && docker compose up -d --build
```

---

## Scheduled work

`docker compose up` starts a second container running `schedule:work`. It has
one job: `vault:purge`, which destroys for good the items that have been in the
trash for more than 30 days. Without it the trash never empties and becomes a
second copy of everything you deleted.

To run it by hand:

```bash
docker compose exec vault php artisan vault:purge
```

---

## Backups

The only thing to back up is the `vault-data` volume. Its contents are already
encrypted: whoever gets hold of the file does not have the master password and
cannot read a thing.

```bash
deploy/backup.sh
```

The script snapshots the database with sqlite3's `.backup` (never `cp`, which
can capture a half-written page), copies it out along with the `app_key`, and
deletes copies older than 30 days. From cron:

```cron
0 4 * * * /var/www/vault/deploy/backup.sh >> /var/log/vault-backup.log 2>&1
```

Keep `/vault-data/app_key` too. Without it sessions are invalidated — no data is
lost, but everyone has to sign in again.

---

## Before you trust it with your passwords

Three checks, in this order:

1. **Register two passkeys**, on different devices. With only one, losing it
   locks you out of the account forever.
2. **Restore a backup** into a separate container and confirm you can sign in.
   A backup that has never been restored is not a backup.
3. **Confirm nothing leaks**: `curl -I https://your-domain/.env` and
   `curl -I https://your-domain/database/database.sqlite` must both return 404.

The master password cannot be recovered. There is no reset email and no security
question, because the server has nothing to decrypt anything with.
