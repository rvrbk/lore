<?php

namespace Database\Seeders;

use App\Models\Reminder;
use App\Models\Space;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Starter content: the PHP 8.6 investigation of the Hetzner server. Only runs on an empty database. */
    public function run(): void
    {
        if (Space::exists()) {
            return;
        }

        $space = Space::create(['name' => 'Infrastructure', 'description' => 'Servers, hosting and upgrades.']);
        $hetzner = $space->pages()->create(['title' => 'Hetzner server', 'body' => <<<'MD'
Production host for the Laravel apps (Ubuntu 24.04 LTS, PHP from the Sury PPA).

- Apps: agriflow, canvas, fuganda, job-scout, prompt-a-tool, verbeek-ug (all Laravel 13.x, `php: ^8.3`)
- crowded-airport is Next.js (no PHP)
- One shared `php8.5-fpm.sock` for all nginx vhosts; cron and queue worker use `/usr/bin/php`
MD]);
        $hetzner->revisions()->create(['title' => $hetzner->title, 'body' => $hetzner->body]);

        $php = $space->pages()->create(['parent_id' => $hetzner->id, 'title' => 'PHP 8.6 upgrade', 'body' => <<<'MD'
## Status (2026-11-19)

PHP 8.6 releases today. The Sury PPA only has `8.6.0~rc3`; server runs PHP 8.5.12. No app pins an old PHP, so nothing blocks the upgrade. Wait for the stable package and a point release or two.

## What may affect the apps

- **Sessions:** `use_strict_mode` and `cookie_httponly` on by default, `cookie_samesite=Lax`. Check cross-site POST flows (OAuth, payments).
- **Deprecations:** `spl_object_hash()`, `is_long()`/`is_integer()`/`doubleval()`, `return` in `finally`, objects passed to `http_build_query()`/`array_walk()`, `mb_ereg*`/`mb_split`.
- **`trim()`** now strips `\f`.
- **Stricter `ValueError`s** instead of silent truncation. Most likely to expose hidden bugs.

## Plan

1. Wait for stable `php8.6-*` packages; confirm Laravel 13 and key packages support 8.6.
2. Grep `app/ routes/ config/` and `vendor/` for deprecated calls.
3. Install `php8.6-fpm` + extensions beside 8.5 (8.5 stays as rollback).
4. Test on a separate pool with `error_reporting=E_ALL`.
5. Move apps one at a time (nginx `fastcgi_pass`, cron and queue-worker `php`), job-scout last.
MD]);
        $php->revisions()->create(['title' => $php->title, 'body' => $php->body]);

        Reminder::create(['page_id' => $php->id, 'title' => 'Check Sury PPA for stable php8.6', 'due_at' => '2026-11-26 09:00']);
        Reminder::create(['page_id' => $php->id, 'title' => 'Reconsider production move to PHP 8.6 (8.6.2?)', 'due_at' => '2027-01-14 09:00']);
    }
}
