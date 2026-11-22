<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * How many days in a row there has been a commit in Lore or any deployed site.
 */
class CommitStreak
{
    /** From this hour on, a streak without a commit today is "at risk". */
    public const WARN_HOUR = 21;

    public function __construct(private Deployer $deployer) {}

    /**
     * @return array{days: int, committed_today: bool, at_risk: bool, best: int}
     */
    public function current(): array
    {
        $dates = Cache::remember('commit-streak.v1', now()->addMinutes(10), fn () => $this->commitDates());
        $set = array_flip($dates);

        $today = today();
        $committedToday = isset($set[$today->toDateString()]);
        $cursor = $committedToday ? $today->copy() : $today->copy()->subDay();
        $days = 0;
        while (isset($set[$cursor->toDateString()])) {
            $days++;
            $cursor->subDay();
        }

        $best = 0;
        $run = 0;
        for ($ago = 89; $ago >= 0; $ago--) {
            $run = isset($set[$today->copy()->subDays($ago)->toDateString()]) ? $run + 1 : 0;
            $best = max($best, $run);
        }

        return ['days' => $days, 'committed_today' => $committedToday, 'at_risk' => $days > 0 && ! $committedToday && now()->hour >= self::WARN_HOUR, 'best' => $best];
    }

    /**
     * @return array<int, string> Distinct dates (Y-m-d) with a commit in the last 90 days.
     */
    private function commitDates(): array
    {
        $dates = [];
        foreach ($this->deployer->targets() as $target) {
            try {
                $result = Process::path($target['path'])->timeout(10)->run(['git', '-c', 'safe.directory='.$target['path'], 'log', '--since=90.days', '--format=%ct']);
            } catch (Throwable) {
                continue;
            }
            if ($result->failed()) {
                continue;
            }
            foreach (array_filter(explode("\n", trim($result->output()))) as $timestamp) {
                $dates[Carbon::createFromTimestamp((int) $timestamp, config('app.timezone'))->toDateString()] = true;
            }
        }

        return array_keys($dates);
    }
}
