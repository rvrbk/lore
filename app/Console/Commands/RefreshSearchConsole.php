<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Services\SearchConsole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshSearchConsole extends Command
{
    protected $signature = 'search-console:refresh';

    protected $description = 'Refresh the cached Search Console numbers of every monitored site, so pages open quickly';

    public function handle(SearchConsole $searchConsole): int
    {
        if (! $searchConsole->isConfigured()) {
            $this->warn('No Google service account key configured.');

            return self::SUCCESS;
        }

        Cache::forget('search-console.v1.properties');
        foreach (Monitor::where('show_in_search', true)->orderBy('name')->get() as $monitor) {
            Cache::forget('search-console.v1.report.'.$monitor->id);
            $this->line($monitor->name.': '.($searchConsole->report($monitor) ? 'ok' : 'not in Search Console or no answer'));
        }

        return self::SUCCESS;
    }
}
