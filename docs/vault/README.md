# Password vault

Saved logins (name, URL, username, password, notes) inside Lore, at `/vault`. Passwords are **encrypted on the server** with Laravel's `encrypted`
cast and APP_KEY, never hashed, so they can be shown and copied. See [decisions.md](decisions.md) for why things are the way they are.

> Trade-off, chosen on purpose: whoever has both the database **and** APP_KEY can read every password. Protect those two things separately.
> The vault itself never writes the secrets anywhere else: not logs, cache, queue, session, or plain database backups.

## How it works

- Table `vault_items`: `name` and `url` are plain (searchable), `username`, `password` and `notes` are encrypted `text` columns. Items belong to one user, enforced by the
  ownership scope every Lore model has plus `VaultItemPolicy`. Admins get no extra access.
- The list never contains passwords. **Reveal** and **Copy password** fetch one password with a POST, which logs `user`, `item id` and `ip` to `storage/logs/vault-*.log`
  and sets `last_revealed_at`.
- Access needs: signed in, **2FA on and passed in this session**, a recent password confirmation, and not idle longer than `VAULT_IDLE_LOCK_MINUTES` (default 10). All routes are CSRF protected and rate limited.
- Turn on 2FA at **Settings → Two-factor** (scan the QR code with an authenticator app, or type the key). Keep the ten recovery codes outside this server.
- The password generator runs in the browser with `crypto.getRandomValues` (length and character sets).

## APP_KEY

- The key lives in the server's `/var/www/lore/.env`. **Keep an offline copy** somewhere that is not the server (password manager, printed, encrypted USB). If it is lost, the encrypted columns cannot be read, ever. (Sealed vault backups do not depend on it.)
- Never commit it. `.env` is git-ignored; keep it that way.
- **Never run `php artisan key:generate` on this install**, and never run `composer setup` there (it does): a new key makes every existing vault item and 2FA secret unreadable.
- Rotating the key:
  1. Make a fresh sealed backup (`php artisan vault:backup`).
  2. Put the current key in `APP_PREVIOUS_KEYS` and the new one in `APP_KEY`, e.g. `APP_PREVIOUS_KEYS="base64:oldkey..."`. Generate the new one with `php artisan key:generate --show`. Reload config (`php artisan config:cache`).
  3. Run `php artisan vault:reencrypt` to rewrite every item (and 2FA secrets) with the new key.
  4. Check the vault opens, then remove `APP_PREVIOUS_KEYS`. Store the new key offline.
- Never run with `APP_DEBUG=true` in production.

## Keypair for exports and backups

On **your own computer** (needs the PHP sodium extension; Arch: `sudo pacman -S php-sodium`):

```bash
php scripts/vault-keygen.php            # writes ~/.lore-vault.key (mode 0600), prints VAULT_PUBLIC_KEY=...
```

Back up `~/.lore-vault.key` somewhere safe and offline (without it the backups cannot be opened) and put the printed `VAULT_PUBLIC_KEY=...` line in the server's `.env`
(then `php artisan config:cache`). The private key never goes on the server.

## Export

On the Vault page: **Export CSV** (`GET /vault/export`) or **Export encrypted** (`?format=encrypted`). Both need a recent password confirmation and are limited to 3 per hour.
Every export is logged (user, ip, time, format), never the content.

- CSV: Bitwarden's import format (`folder,favorite,type,name,notes,fields,reprompt,login_uri,login_username,login_password,login_totp`). **It is not encrypted**: delete it after importing.
- Encrypted: sealed to your public key. Open it on your computer:

```bash
php scripts/vault-decrypt.php lore-vault-2026-11-25.csv.sealed > vault.csv   # uses ~/.lore-vault.key, or pass a key file as the 2nd argument
```

## Backups

`vault:backup` runs daily at 03:30 in the Laravel scheduler (already running every minute through cron on the server). It writes
`storage/app/vault-backups/vault-YYYYMMDD-HHMMSS.sealed` (mode 0600), sealed to `VAULT_PUBLIC_KEY`, so the server can write them but not read them. `.env` and APP_KEY are never in them.
Retention: newest backup of each of the last 7 days and each of the last 4 weeks. If `VAULT_PUBLIC_KEY` or sodium is missing the command fails and says so.

The plain `lore:backup` database dumps deliberately skip the `vault_items` rows.

**Pull them to your computer** (they are encrypted, so rsync over SSH is safe):

```bash
scripts/pull-vault-backups.sh        # → ~/lore-vault-backups, never deletes local copies
```

Schedule it with cron (`30 4 * * * /path/to/lore/scripts/pull-vault-backups.sh`), launchd or a systemd timer: examples are in the header of the script.
SSH defaults to `deployer@5.78.112.252`; override with `LORE_SSH`, `LORE_PATH`, `LORE_DEST`.

**Restore** (needs your private key, so do it where the key is; the target needs the same users):

```bash
php artisan vault:restore vault-20261125-033000.sealed --key-file=~/.lore-vault.key --dry-run   # count only
php artisan vault:restore vault-20261125-033000.sealed --key-file=~/.lore-vault.key
```

Items are put back by id (existing ones are overwritten, nothing is deleted) and encrypted again with the current APP_KEY. To read a backup without restoring, use `scripts/vault-decrypt.php` (prints JSON).

## Putting it on the server (checklist)

1. Generate the keypair (above); add `VAULT_PUBLIC_KEY=` and optionally `VAULT_IDLE_LOCK_MINUTES=` to the server `.env`.
2. Deploy as usual (`git pull`, `composer install --no-dev`, `migrate --force`, `config:cache`). Assets: rsync `public/build/`.
3. Sign in, turn on 2FA, then use the vault. Run `php artisan vault:backup` once and pull it with the script to prove the loop works; run `vault:restore --dry-run` locally.
4. Keep offline: APP_KEY, the private key, the 2FA recovery codes.

## Tests

`php artisan test --filter=Vault` (plus `TwoFactorTest`, `TotpTest`, `BackupTest`). The sealed-box tests skip when the PHP sodium extension is missing.
