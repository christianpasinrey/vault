# Vault

A self-hosted, single-user password and key manager where **the server can never
decrypt what it stores**.

All cryptography happens in the browser. The master password derives a master
key; from it, HKDF produces an *auth hash* (the only thing that travels to the
server) and a *wrapping key* (which never leaves). The wrapping key wraps a
random Vault Key, and it is that Vault Key which encrypts every item with
AES-256-GCM. The server only ever sees opaque blobs.

> **The master password cannot be recovered.** There is no reset email and no
> security question: the server has nothing to decrypt anything with. Lose it
> and you lose the vault. Store it outside this system.

## What it does

- **Logins, API keys and secure notes**, each with the fields that type actually
  needs, in folders, with instant search and favourites.
- **Passkeys as a mandatory second factor**, on top of the master password.
- **A password generator** that draws from `crypto.getRandomValues` and rejects
  biased samples rather than taking a modulo.
- **Secrets stay sealed** until you ask for them, and anything copied is wiped
  from the clipboard 25 seconds later.
- **Automatic locking** after inactivity: the Vault Key is overwritten in memory
  and has to be derived again.
- **A trash** that empties itself after 30 days, and an export you can only
  import back into a vault holding the same Vault Key.
- **Master password rotation** that re-wraps the Vault Key instead of
  re-encrypting every item, so it is atomic and instant.

An `api_key` marked `prod` is flagged in red throughout the interface, and one
with an `expires_at` in the next 30 days says so before you rely on it.

## Security

- KDF: PBKDF2-SHA256, 600,000 iterations, 16-byte salt
- Encryption: AES-256-GCM, random 12-byte IV per operation, never reused
- The *auth hash* is re-hashed with Argon2id before being stored
- Mandatory second factor via WebAuthn (passkeys)
- The Vault Key lives in memory only, outside reactive state; never in
  `localStorage`, `sessionStorage` or IndexedDB
- A Content-Security-Policy with no inline scripts and no external origin at all;
  the application ships no remote fonts, scripts or styles
- Strict, encrypted, HTTP-only session cookies, and a refusal to boot with
  `APP_DEBUG` on in production

Nothing sensitive is committed: not the SQLite file, not `.env`, not any key.
The single account is created on the target machine, interactively.

## Getting started

```bash
composer install && npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
php artisan vault:init
```

`vault:init` prints a single-use link, valid for 15 minutes. Open it, choose your
master password and register two passkeys. Nothing else is set up by hand.

> Passkeys need a secure context. `http://localhost` counts as one; any other
> domain over plain HTTP does not.

For Docker (Laravel Sail) and server deployment: **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**.

Before you trust it with anything that matters, work through
[the checklist](docs/DEPLOYMENT.md#before-you-trust-it-with-your-passwords):
two passkeys, a restore you have actually tested, and a `.env` that returns 404.

## Tests

```bash
php artisan test        # backend
npm test                # cryptography, clipboard and generator
npm run typecheck
./vendor/bin/pint --test
```

## Documentation

- [Technical design](docs/specs/2026-07-27-vault-design.html)
- [Implementation plan](docs/plans/2026-07-27-vault.md)
- [Deployment](docs/DEPLOYMENT.md)

## License

MIT.
