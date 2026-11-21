<?php

namespace App\Providers;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Page;
use App\Models\Reminder;
use App\Models\Space;
use App\Models\WorldClock;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Runs the scheduler (reminder notifications) alongside `composer dev` / `php artisan dev`.
        DevCommands::artisan('schedule:work', 'scheduler');

        View::composer('components.layouts.app', fn ($view) => $view->with([
            'navSpaces' => Space::orderBy('name')->get(),
            'headerClock' => WorldClock::where('is_header', true)->first(),
            'dueCount' => Reminder::due()->count(),
            'openIncidents' => Incident::open()->orderBy('started_at')->get(),
            'downCount' => Monitor::where('is_up', false)->count(),
            'pinnedPages' => Page::whereNotNull('pinned_at')->orderBy('pinned_at')->limit(10)->get(),
            'recentPages' => Page::whereNotNull('viewed_at')->orderByDesc('viewed_at')->limit(5)->get(),
        ]));
    }
}
