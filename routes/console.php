<?php

use App\Models\HeatClick;
use App\Services\CalendarAgenda;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:notify')->everyMinute();
Schedule::command('monitors:check')->everyMinute()->withoutOverlapping();
Schedule::command('lore:deploy')->everyMinute()->withoutOverlapping(60);
Schedule::command('lore:backup')->dailyAt('03:15');
Schedule::command('vault:backup')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('monitors:check-expiry')->hourly()->withoutOverlapping();
Schedule::command('server:measure')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('lore:digest')->dailyAt('07:30');
Schedule::command('sites:count-users')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('search-console:refresh')->hourly()->withoutOverlapping();
Schedule::command('monitors:rollup')->hourly()->withoutOverlapping();
Schedule::command('lore:streak-nudge')->dailyAt('21:00');
Schedule::command('jobscout:snapshot')->hourly()->withoutOverlapping();
Schedule::call(fn () => app(CalendarAgenda::class)->refresh())->name('calendar:refresh')->everyFifteenMinutes()->withoutOverlapping();
Schedule::call(fn () => HeatClick::where('created_at', '<', now()->subDays(HeatClick::KEEP_DAYS))->delete())->name('heat:prune')->daily();
