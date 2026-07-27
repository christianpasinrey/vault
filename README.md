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

## Status

Work in progress. What works today:

- [x] Client-side cryptography: PBKDF2 + HKDF, AES-256-GCM, Vault Key wrapping
- [x] Data model and opaque items
- [x] API: initial setup, login, WebAuthn, item CRUD with trash and export
- [x] Docker deployment
- [ ] **Web interface** — does not exist yet

Without the interface this is not usable: the API is complete and tested, but
the link `vault:init` issues points at a screen that has not been built yet.

## Security

- KDF: PBKDF2-SHA256, 600,000 iterations, 16-byte salt
- Encryption: AES-256-GCM, random 12-byte IV per operation, never reused
- The *auth hash* is re-hashed with Argon2id before being stored
- Mandatory second factor via WebAuthn (passkeys)
- The Vault Key lives in memory only, outside reactive state; never in
  `localStorage`, `sessionStorage` or IndexedDB

Nothing sensitive is committed: not the SQLite file, not `.env`, not any key.
The single account is created on the target machine, interactively.

## Getting started

```bash
composer install && npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan vault:init
```

For Docker (Laravel Sail) and server deployment: **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**.

## Tests

```bash
php artisan test        # backend
npm test                # client-side cryptography
npm run typecheck
```

## Documentation

- [Technical design](docs/specs/2026-07-27-vault-design.html)
- [Implementation plan](docs/plans/2026-07-27-vault.md)
- [Deployment](docs/DEPLOYMENT.md)

## License

MIT.
