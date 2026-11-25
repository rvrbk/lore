# Vault: decisions

Chosen by the owner and not changed: server-side encryption with Laravel's `encrypted` cast (APP_KEY). Anyone with both the
database and APP_KEY can read the vault. Everything below tries to make that combination hard to get.

## What Lore looked like (step 0)

Laravel 13 / PHP 8.5, Blade + Tailwind 4 + plain JS modules through Vite (no Livewire, Inertia or Vue). Username + password login, no 2FA.
Sessions, cache and queue use the `database` driver, logs go to `storage/logs/laravel.log`, production is PostgreSQL on a Hetzner box
deployed with `git pull` (a push to `main` triggers the deploy workflow). A daily `lore:backup` writes plain `pg_dump` files next to the
`.env` that holds APP_KEY. Debugbar and Telescope are not installed.

## Decisions

**2FA (TOTP) was missing, so it was added first.** It is optional for the rest of Lore and required for the vault.
- No new Composer package (the project rule says dependencies need approval): `App\Services\Totp` implements RFC 6238 (checked against
  the RFC test vectors). Consequence: no QR code. Setup shows the key and an `otpauth://` link to type or open on the phone. A QR library
  (for example `bacon/bacon-qr-code`) can be added if you want one.
- The secret and the recovery codes are encrypted with APP_KEY. Recovery codes are stored as SHA-256 hashes, shown once (in the response of
  the request that makes them, never in a flash message, because flashes are stored in the session table).
- A used code cannot be reused (`two_factor_last_step`).
- Login with 2FA no longer signs in until the code is accepted. The vault checks a session flag (`two_factor.passed`), not just "logged in",
  because a remember-me cookie signs in without the challenge. Without the flag the vault sends you to `/two-factor-challenge`.

**Locking.** Every vault route (the list too, since names and URLs are information) needs: auth, 2FA, `password.confirm`, and a sliding idle
lock (`VAULT_IDLE_LOCK_MINUTES`, default 10) that forgets the password confirmation after that much inactivity. Laravel's own
`password_timeout` alone is absolute, not idle, so `VaultIdleLock` sits in front of it. Ajax calls get HTTP 423 and the page reloads into the
unlock screen.

**Reveal is a POST that returns JSON**, so a password is never in a URL, a page, a history entry or a GET cache. It updates
`last_revealed_at` (without touching `updated_at`) and writes `user_id`, `item_id` and `ip` to a separate `vault` log channel
(`storage/logs/vault-*.log`, 365 days). The value is never logged. The browser hides a revealed password after 15 seconds and clears the clipboard after 30.

**Form field names.** The username and notes inputs are `vault_username` / `vault_notes`. Laravel flashes failed form input into the
session (a database table) except for a global list of names; `password` is on it already, `username` could not be added because the login form
needs its old value. A test proves nothing secret ends up in the session. (`$dontFlash` on a FormRequest does not exist in Laravel 13; I found out
because the test failed.)

**Throttle keys.** Stacked `throttle:` middleware share one counter per user unless they get a name. Without names, normal vault use ate the
export limit. The vault uses `throttle:60,1,vault`, reveal `throttle:30,1,vault-reveal`, export `throttle:3,60,vault-export`, and the
confirm and challenge routes have their own names too. A test covers it.

**Nothing secret in logs.** `zend.exception_ignore_args` is switched on at boot, so a stack trace never prints call arguments (PHP would print
the first 15 characters of a password passed to any function in the trace). `Totp` and the sealer additionally use `#[\SensitiveParameter]`.
The test throws an exception during a save and checks the log file and the error page. `APP_DEBUG` must be false in production: the debug page prints request data.

**Debugbar/Telescope.** Not installed. `VaultNoRecording` disables them for vault routes if somebody adds them later, and sets
`Cache-Control: no-store, no-cache, must-revalidate` on every vault response.

**CSV export is plain text on purpose** (Bitwarden format, unencrypted download). Values are not altered to defuse spreadsheet formulas, because that would
corrupt passwords that start with `=`, `+`, `-` or `@`. Import it into a password manager, do not open it in Excel, and delete it afterwards.

**One keypair for exports and backups** (`VAULT_PUBLIC_KEY`, libsodium sealed boxes). The private key never touches the server.
Sealed files start with `LVT1` so the scripts can recognise them. The encrypted export seals the whole CSV in memory, which is fine for a personal vault.

**Backups contain decrypted items, sealed to your public key,** not the raw ciphertext. So they do not depend on this install's APP_KEY (a
lost or rotated APP_KEY does not make them useless), and a restore works on a fresh install. Restore needs the private key, so it runs wherever the
key is (your laptop against a restored database, or temporarily on the server). Items are matched to users by id plus username, then by username alone; otherwise they are skipped and counted.

**Retention:** the newest backup of each of the last 7 days, plus the newest of each of the last 4 weeks; everything else goes. Only
files named `vault-YYYYMMDD-HHMMSS.sealed` are touched. The pull script never deletes locally, so your laptop keeps more history than the server.

**The existing `lore:backup` no longer includes `vault_items`.** The plain SQL dumps sit on the server next to `.env`, which would be exactly the
database + APP_KEY combination. The vault has its own sealed backup instead. (Two-factor secrets are still in the `users` dump, encrypted with APP_KEY.)

**`vault:reencrypt`** was added so key rotation can actually finish (see the README): rows are rewritten with the new key, so `APP_PREVIOUS_KEYS` can be dropped.

## Not done / limits

- No QR code (see above). No sharing between users, no folders, no TOTP storage for the items themselves, no browser extension.
- Nothing was pushed or deployed; pushing to `main` deploys to production.
- `resources/js/vault.js` (reveal, copy, generator) was built with Vite but not driven in a real browser in this session.
- This machine's PHP has no sodium extension. Tests that need it skip here and were run in the local `php:8.5-cli` Docker image with no network
  (`docker run --rm --network none ... php vendor/bin/phpunit`). On Arch: `sudo pacman -S php-sodium`. The production server has sodium.
