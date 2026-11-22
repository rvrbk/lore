<?php

namespace App\Services;

use App\Models\HeatClick;
use App\Models\Monitor;
use Illuminate\Support\Collection;

/**
 * Click totals and per-day series of the last 30 days for the heatmap section of Analytics.
 */
class HeatmapOverview
{
    public const DAYS = 30;

    /**
     * @param  Collection<int, Monitor>  $monitors
     * @return array{counts: Collection<int, int>, daily: Collection<int, list<int>>}
     */
    public function for(Collection $monitors): array
    {
        $since = now()->subDays(self::DAYS - 1)->startOfDay();
        $counts = HeatClick::where('created_at', '>=', $since)->selectRaw('monitor_id, count(*) as clicks')->groupBy('monitor_id')->pluck('clicks', 'monitor_id');

        $perDay = HeatClick::where('created_at', '>=', $since)
            ->selectRaw('monitor_id, date(created_at) as day, count(*) as clicks')->groupBy('monitor_id', 'day')->get()->groupBy('monitor_id');
        $days = collect(range(self::DAYS - 1, 0))->map(fn (int $ago) => now()->subDays($ago)->toDateString());
        $daily = $monitors->mapWithKeys(fn (Monitor $monitor) => [
            $monitor->id => $days->map(fn (string $day) => (int) ($perDay->get($monitor->id)?->firstWhere('day', $day)?->clicks ?? 0))->all(),
        ]);

        return ['counts' => $counts, 'daily' => $daily];
    }
}
