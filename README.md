# Lore

A local, single-user knowledge base (spaces → nested Markdown pages, revision history, search) with reminders that fire desktop notifications.

## Run

```sh
composer dev        # server (http://localhost:8000), queue, vite, and the scheduler
```

Reminder notifications need the scheduler running (`php artisan schedule:work`, included in `composer dev`).
It calls `notify-send` once a reminder is due; due reminders also show as a badge in the sidebar and on the home page.
To get notifications without a terminal open, add a cron entry:

```
* * * * * cd /home/rvrbk/Work/lore && php artisan schedule:run >> /dev/null 2>&1
```

## Setup from scratch

```sh
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
```

SQLite database lives at `database/database.sqlite`; back that file up. Timezone is `APP_TIMEZONE` (Europe/Amsterdam).
Sign-in is username + password (no email). The first visit to `/login` redirects to `/register` to create the one account; registration closes after that. Do not expose this to a network without HTTPS.
