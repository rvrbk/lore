<?php

namespace App\Http\Controllers;

use App\Models\HeatClick;
use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HeatmapController extends Controller
{
    public const MAX_POINTS = 50000;

    public const DAY_CHOICES = [7, 30, 90];

    public function show(Request $request, Monitor $monitor): View
    {
        $days = in_array((int) $request->query('days'), self::DAY_CHOICES, true) ? (int) $request->query('days') : 30;
        $device = $request->query('device') === 'mobile' ? 'mobile' : 'desktop';

        $since = now()->subDays($days);
        $paths = $monitor->heatClicks()->where('created_at', '>=', $since)->where('device', $device)
            ->selectRaw('path, count(*) as clicks')->groupBy('path')->orderByDesc('clicks')->limit(50)->pluck('clicks', 'path');
        $path = $paths->has($request->query('path')) ? (string) $request->query('path') : (string) $paths->keys()->first();

        $points = collect();
        if ($path !== '') {
            $points = $monitor->heatClicks()->where('created_at', '>=', $since)->where('device', $device)->where('path', $path)
                ->latest('id')->limit(self::MAX_POINTS)->get(['x', 'y'])->map(fn (HeatClick $click) => [$click->x, $click->y]);
        }

        $perDay = $monitor->heatClicks()->where('created_at', '>=', $since->copy()->startOfDay())
            ->selectRaw('date(created_at) as day, count(*) as clicks')->groupBy('day')->pluck('clicks', 'day');
        $daily = collect(range($days - 1, 0))->map(fn (int $ago) => [
            'day' => now()->subDays($ago)->startOfDay(),
            'clicks' => (int) $perDay->get(now()->subDays($ago)->toDateString(), 0),
        ]);
        $topPages = $monitor->heatClicks()->where('created_at', '>=', $since)->selectRaw('path, count(*) as clicks')
            ->groupBy('path')->orderByDesc('clicks')->limit(8)->pluck('clicks', 'path');
        $devices = $monitor->heatClicks()->where('created_at', '>=', $since)->selectRaw('device, count(*) as clicks')->groupBy('device')->pluck('clicks', 'device');

        return view('heatmaps.show', [
            'daily' => $daily,
            'topPages' => $topPages,
            'devices' => $devices,
            'monitor' => $monitor,
            'paths' => $paths,
            'path' => $path,
            'device' => $device,
            'days' => $days,
            'dayChoices' => self::DAY_CHOICES,
            'points' => $points->values(),
            'width' => $device === 'mobile' ? 390 : 1280,
        ]);
    }
}
