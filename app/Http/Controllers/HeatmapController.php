<?php

namespace App\Http\Controllers;

use App\Models\HeatClick;
use App\Models\Monitor;
use App\Services\GitHubHeatmapSnippet;
use App\Services\PageScreenshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HeatmapController extends Controller
{
    public const MAX_POINTS = 50000;

    public const DAY_CHOICES = [7, 30, 90];

    public function index(GitHubHeatmapSnippet $snippet, PageScreenshot $screenshots): View
    {
        $monitors = Monitor::orderBy('name')->get();
        $since = now()->subDays(30);
        $counts = HeatClick::where('created_at', '>=', $since)->selectRaw('monitor_id, count(*) as clicks')->groupBy('monitor_id')->pluck('clicks', 'monitor_id');

        $perDay = HeatClick::where('created_at', '>=', $since->copy()->startOfDay())
            ->selectRaw('monitor_id, date(created_at) as day, count(*) as clicks')->groupBy('monitor_id', 'day')->get()->groupBy('monitor_id');
        $days = collect(range(29, 0))->map(fn (int $ago) => now()->subDays($ago)->toDateString());
        $daily = $monitors->mapWithKeys(fn (Monitor $monitor) => [
            $monitor->id => $days->map(fn (string $day) => (int) ($perDay->get($monitor->id)?->firstWhere('day', $day)?->clicks ?? 0))->all(),
        ]);

        $thumbnails = [];
        foreach ($monitors as $monitor) {
            $top = HeatClick::where('monitor_id', $monitor->id)->where('created_at', '>=', $since)
                ->selectRaw('path, device, count(*) as clicks')->groupBy('path', 'device')->orderByDesc('clicks')->limit(5)->get()
                ->first(fn (HeatClick $click) => $screenshots->exists($monitor, $click->path, $click->device));
            if ($top !== null) {
                $thumbnails[$monitor->id] = route('heatmaps.screenshot', ['monitor' => $monitor, 'path' => $top->path, 'device' => $top->device]);
            }
        }

        return view('heatmaps.index', ['monitors' => $monitors, 'counts' => $counts, 'daily' => $daily, 'thumbnails' => $thumbnails, 'snippet' => $snippet]);
    }

    public function show(Request $request, Monitor $monitor, PageScreenshot $screenshots): View
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

        $screenshotHeight = $path === '' ? null : $screenshots->height($monitor, $path, $device);

        return view('heatmaps.show', [
            'daily' => $daily,
            'topPages' => $topPages,
            'devices' => $devices,
            'screenshotHeight' => $screenshotHeight,
            'canCapture' => $screenshots->binary() !== null,
            'monitor' => $monitor,
            'paths' => $paths,
            'path' => $path,
            'device' => $device,
            'days' => $days,
            'dayChoices' => self::DAY_CHOICES,
            'points' => $points->values(),
            'pageUrl' => rtrim($monitor->url, '/').$path,
            'width' => $device === 'mobile' ? 390 : 1280,
        ]);
    }

    public function screenshot(Request $request, Monitor $monitor, PageScreenshot $screenshots): BinaryFileResponse
    {
        $file = $screenshots->relativePath($monitor, (string) $request->query('path'), $request->query('device') === 'mobile' ? 'mobile' : 'desktop');
        abort_unless(Storage::disk('local')->exists($file), 404);

        return response()->file(Storage::disk('local')->path($file), ['Cache-Control' => 'private, max-age=300']);
    }

    public function refresh(Request $request, Monitor $monitor, PageScreenshot $screenshots): RedirectResponse
    {
        $device = $request->input('device') === 'mobile' ? 'mobile' : 'desktop';
        $path = (string) $request->input('path');
        $deepest = (int) $monitor->heatClicks()->where('device', $device)->where('path', $path)->max('y');

        $taken = $deepest > 0 && $screenshots->capture($monitor, $path, $device, $screenshots->heightFor($deepest));

        return redirect()->route('heatmaps.show', ['monitor' => $monitor, 'path' => $path, 'device' => $device, 'days' => $request->input('days')])
            ->with($taken ? 'status' : 'error', $taken ? 'Screenshot updated.' : 'Could not take a screenshot (is Chromium installed?).');
    }
}
