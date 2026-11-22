<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Models\MonitorDay;
use Illuminate\Console\Command;

class RollupMonitorDays extends Command
{
    protected $signature = 'monitors:rollup {--days=2 : How many recent days to recompute}';

    protected $description = 'Summarise each monitor\'s checks per day, so the uptime wall outlives the pruned checks';

    public function handle(): int
    {
        $days = MonitorDay::exists() ? max(1, (int) $this->option('days')) : Monitor::KEEP_HISTORY_DAYS + 1;
        $since = now()->subDays($days - 1)->startOfDay();

        foreach (Monitor::orderBy('id')->get() as $monitor) {
            $perDay = [];
            foreach ($monitor->checks()->where('checked_at', '>=', $since)->orderBy('checked_at')->cursor() as $check) {
                $day = $check->checked_at->toDateString();
                $perDay[$day] ??= ['checks' => 0, 'up_checks' => 0];
                $perDay[$day]['checks']++;
                $perDay[$day]['up_checks'] += $check->is_up ? 1 : 0;
            }
            foreach ($perDay as $day => $counts) {
                MonitorDay::updateOrCreate(['monitor_id' => $monitor->id, 'day' => $day], $counts);
            }
        }
        MonitorDay::where('day', '<', now()->subDays(370)->toDateString())->delete();

        $this->info('Monitor days updated.');

        return self::SUCCESS;
    }
}
