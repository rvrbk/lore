<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * The public, login-free status page of a site whose owner switched it on. It only reveals whether the site is
 * up, its daily uptime and past outages, never URLs of internal checks, error text or notes.
 */
class StatusPageController extends Controller
{
    public const DAYS = 30;

    public function __invoke(string $token): View
    {
        $monitor = Monitor::where('public_token', $token)->firstOrFail();

        $days = Cache::remember("status-page.{$monitor->id}", now()->addMinute(), function () use ($monitor): array {
            $counts = $monitor->checks()
                ->where('checked_at', '>=', now()->subDays(self::DAYS - 1)->startOfDay())
                ->selectRaw('date(checked_at) as day, count(*) as total, sum(case when is_up then 1 else 0 end) as up')
                ->groupBy('day')->toBase()->get()->keyBy('day');

            return collect(range(self::DAYS - 1, 0))->map(function (int $ago) use ($counts): array {
                $day = now()->subDays($ago)->startOfDay();
                $row = $counts->get($day->format('Y-m-d'));

                return ['date' => $day, 'percent' => $row && $row->total > 0 ? round($row->up / $row->total * 100, 1) : null];
            })->all();
        });

        return view('status.show', [
            'monitor' => $monitor,
            'days' => $days,
            'uptime' => $monitor->uptimePercent(self::DAYS),
            'outages' => $monitor->incidents()->whereIn('kind', [Incident::DOWN, Incident::SMOKE_FAILED])
                ->where('started_at', '>=', now()->subDays(self::DAYS))->latest('started_at')->get(),
        ]);
    }
}
