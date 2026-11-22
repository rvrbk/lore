<?php

use App\Models\HeatClick;
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
Schedule::command('monitors:check-expiry')->hourly()->withoutOverlapping();
Schedule::command('server:measure')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('lore:digest')->dailyAt('07:30');
Schedule::command('sites:count-users')->everyFifteenMinutes()->withoutOverlapping();
Schedule::call(fn () => HeatClick::where('created_at', '<', now()->subDays(HeatClick::KEEP_DAYS))->delete())->name('heat:prune')->daily();
