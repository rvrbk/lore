<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HeatmapController extends Controller
{
    public const DAY_CHOICES = [7, 30, 90];

    public function show(Request $request, Monitor $monitor): View
    {
        $days = in_array((int) $request->query('days'), self::DAY_CHOICES, true) ? (int) $request->query('days') : 30;

        $since = now()->subDays($days);
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
            'days' => $days,
            'dayChoices' => self::DAY_CHOICES,
        ]);
    }
}
