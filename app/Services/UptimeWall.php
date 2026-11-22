<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\MonitorDay;

/**
 * A year of days for one site, like a contribution graph: green for a clean day, amber and red for outages.
 */
class UptimeWall
{
    public const WEEKS = 52;

    /**
     * Weeks of seven days (Monday first), oldest first. A day without checks has a null percentage and a day in the future is null.
     *
     * @return array<int, array<int, array{day: string, percent: float|null}|null>>
     */
    public function for(Monitor $monitor): array
    {
        $start = now()->startOfWeek()->subWeeks(self::WEEKS - 1)->startOfDay();
        $byDay = MonitorDay::where('monitor_id', $monitor->id)->where('day', '>=', $start->toDateString())->get()
            ->mapWithKeys(fn (MonitorDay $day) => [$day->day->toDateString() => $day->checks > 0 ? round($day->up_checks / $day->checks * 100, 2) : null]);

        $weeks = [];
        for ($week = 0; $week < self::WEEKS; $week++) {
            $column = [];
            for ($weekday = 0; $weekday < 7; $weekday++) {
                $date = $start->copy()->addWeeks($week)->addDays($weekday);
                $column[] = $date->isFuture() ? null : ['day' => $date->toDateString(), 'percent' => $byDay->get($date->toDateString())];
            }
            $weeks[] = $column;
        }

        return $weeks;
    }

    /** Tailwind background class for a day's uptime. */
    public static function colour(?float $percent): string
    {
        return match (true) {
            $percent === null => 'bg-zinc-800',
            $percent >= 99.9 => 'bg-emerald-600',
            $percent >= 99 => 'bg-emerald-700',
            $percent >= 95 => 'bg-amber-500',
            default => 'bg-red-500',
        };
    }
}
