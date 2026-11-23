<?php

namespace App\Services;

use App\Models\HeatClick;
use App\Models\Monitor;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Click totals, per-day series and the most clicked pages and elements of the last 30 days for the clicks section of Analytics.
 */
class HeatmapOverview
{
    public const DAYS = 30;

    public const TOP_PAGES = 3;

    public const TOP_TARGETS = 3;

    /**
     * @param  Collection<int, Monitor>  $monitors
     * @return array{counts: Collection<int, int>, daily: Collection<int, list<int>>, tops: Collection<int, list<array{path: string, clicks: int, targets: list<array{element: string, label: string|null, href: string|null, clicks: int}>}>>}
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

        return ['counts' => $counts, 'daily' => $daily, 'tops' => $this->tops($monitors, $since)];
    }

    /**
     * Per site, its most clicked pages and, under each, the elements clicked most there.
     *
     * @param  Collection<int, Monitor>  $monitors
     * @return Collection<int, list<array{path: string, clicks: int, targets: list<array{element: string, label: string|null, href: string|null, clicks: int}>}>>
     */
    private function tops(Collection $monitors, CarbonInterface $since): Collection
    {
        $pages = HeatClick::where('created_at', '>=', $since)->selectRaw('monitor_id, path, count(*) as clicks')->groupBy('monitor_id', 'path')->get()->groupBy('monitor_id');
        $targets = HeatClick::where('created_at', '>=', $since)->whereNotNull('element')
            ->selectRaw('monitor_id, path, element, label, href, count(*) as clicks')->groupBy('monitor_id', 'path', 'element', 'label', 'href')->get()->groupBy('monitor_id');

        return $monitors->mapWithKeys(fn (Monitor $monitor) => [
            $monitor->id => ($pages->get($monitor->id) ?? collect())->sortByDesc('clicks')->take(self::TOP_PAGES)->map(fn (HeatClick $page) => [
                'path' => $page->path,
                'clicks' => (int) $page->clicks,
                'targets' => ($targets->get($monitor->id) ?? collect())->where('path', $page->path)->sortByDesc('clicks')->take(self::TOP_TARGETS)
                    ->map(fn (HeatClick $target) => ['element' => $target->element, 'label' => $target->label, 'href' => $target->href, 'clicks' => (int) $target->clicks])->values()->all(),
            ])->values()->all(),
        ]);
    }
}
