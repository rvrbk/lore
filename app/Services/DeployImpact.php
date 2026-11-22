<?php

namespace App\Services;

use App\Models\Deployment;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Lines deploys up against what happened around them: daily visitors from Google Analytics and the response time of the uptime checks.
 */
class DeployImpact
{
    /**
     * Successful deploys of the site in the last N days, oldest first.
     *
     * @return Collection<int, Deployment>
     */
    public function deploysFor(string $siteName, int $days = 7): Collection
    {
        $path = Monitor::where('name', $siteName)->value('deploy_path');
        if ($path === null) {
            return collect();
        }

        return Deployment::where('path', $path)->where('status', Deployment::SUCCEEDED)
            ->where('finished_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->orderBy('finished_at')->get();
    }

    /**
     * The days (0 = oldest day shown, last = today) a deploy went out on, and how the average daily visitors changed across the latest deploy.
     *
     * @param  array<int, int>  $daily  Visitors per day, oldest first, ending today.
     * @return array{markers: array<int, int>, change: array{deployed_at: Carbon, before: float, after: float, percent: int}|null}
     */
    public function forTraffic(string $siteName, array $daily): array
    {
        $deploys = $this->deploysFor($siteName, count($daily));
        $last = count($daily) - 1;
        $markers = $deploys->map(fn (Deployment $deploy) => $last - (int) $deploy->finished_at->copy()->startOfDay()->diffInDays(now()->startOfDay()))
            ->filter(fn (int $index) => $index >= 0 && $index <= $last)->unique()->values()->all();

        $latest = $deploys->last();
        $change = null;
        if ($latest !== null) {
            $index = $last - (int) $latest->finished_at->copy()->startOfDay()->diffInDays(now()->startOfDay());
            $before = array_slice($daily, 0, max(0, $index));
            $after = array_slice($daily, $index + 1);
            if ($before !== [] && $after !== [] && array_sum($before) > 0) {
                $beforeAverage = array_sum($before) / count($before);
                $afterAverage = array_sum($after) / count($after);
                $change = [
                    'deployed_at' => $latest->finished_at,
                    'before' => round($beforeAverage, 1),
                    'after' => round($afterAverage, 1),
                    'percent' => (int) round(($afterAverage - $beforeAverage) / $beforeAverage * 100),
                ];
            }
        }

        return ['markers' => $markers, 'change' => $change];
    }

    /**
     * Where each deploy falls among the checks (the index of the first check after it), and the average response time before and after the latest one.
     *
     * @param  Collection<int, MonitorCheck>  $checks  Oldest first.
     * @param  Collection<int, Deployment>  $deploys
     * @return array{markers: array<int, int>, change: array{before: int, after: int, percent: int}|null}
     */
    public function forResponse(Collection $checks, Collection $deploys): array
    {
        $checks = $checks->values();
        $markers = [];
        foreach ($deploys as $deploy) {
            $index = $checks->search(fn ($check) => $check->checked_at >= $deploy->finished_at);
            if ($index !== false && $index > 0) {
                $markers[] = $index;
            }
        }

        $change = null;
        $latest = $deploys->last();
        if ($latest !== null) {
            $up = $checks->where('is_up', true);
            $before = $up->filter(fn ($check) => $check->checked_at < $latest->finished_at)->avg('response_ms');
            $after = $up->filter(fn ($check) => $check->checked_at >= $latest->finished_at)->avg('response_ms');
            if ($before && $after) {
                $change = ['before' => (int) round($before), 'after' => (int) round($after), 'percent' => (int) round(($after - $before) / $before * 100)];
            }
        }

        return ['markers' => $markers, 'change' => $change];
    }
}
