<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitors:check')]
#[Description('Request every monitored app URL and store whether it is up')]
class CheckMonitors extends Command
{
    public function handle(): int
    {
        $monitors = Monitor::orderBy('id')->get();
        Monitor::check($monitors);
        MonitorCheck::where('checked_at', '<', now()->subDays(Monitor::KEEP_HISTORY_DAYS))->delete();

        $down = $monitors->fresh()->where('is_up', false)->count();
        $this->info("Checked {$monitors->count()} app(s), {$down} down.");

        return self::SUCCESS;
    }
}
