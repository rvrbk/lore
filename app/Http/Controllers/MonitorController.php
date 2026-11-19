<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitorController extends Controller
{
    public function index(): View
    {
        $monitors = Monitor::orderBy('name')->get();

        return view('monitors.index', ['monitors' => $monitors, 'sparks' => Monitor::sparklines($monitors)]);
    }

    public function show(Monitor $monitor): View
    {
        $recent = $monitor->checks()->where('checked_at', '>=', now()->subDay())->orderBy('checked_at')->get();

        $hours = $recent->groupBy(fn (MonitorCheck $c) => $c->checked_at->format('Y-m-d H'))->map(fn ($group) => [
            'at' => $group->first()->checked_at->copy()->startOfHour(),
            'up' => round($group->where('is_up', true)->count() / $group->count() * 100),
        ])->values();

        $transitions = $monitor->checks()->where('checked_at', '>=', now()->subDays(7))->orderBy('checked_at')->get(['is_up', 'checked_at']);
        $incidents = [];
        $downSince = null;
        foreach ($transitions as $check) {
            if (! $check->is_up && $downSince === null) {
                $downSince = $check->checked_at;
            } elseif ($check->is_up && $downSince !== null) {
                $incidents[] = ['from' => $downSince, 'to' => $check->checked_at];
                $downSince = null;
            }
        }
        if ($downSince !== null) {
            $incidents[] = ['from' => $downSince, 'to' => null];
        }

        return view('monitors.show', [
            'monitor' => $monitor,
            'checks' => $recent,
            'hours' => $hours,
            'incidents' => array_reverse($incidents),
            'uptime' => ['24 hours' => $monitor->uptimePercent(1), '7 days' => $monitor->uptimePercent(7), '30 days' => $monitor->uptimePercent(30)],
            'averageMs' => (int) round($recent->where('is_up', true)->avg('response_ms')),
        ]);
    }

    public function update(Request $request, Monitor $monitor): RedirectResponse
    {
        $monitor->update($this->validated($request));
        Monitor::check($monitor->newCollection([$monitor]));

        return redirect()->route('monitors.index');
    }

    public function destroy(Monitor $monitor): RedirectResponse
    {
        $monitor->delete();

        return redirect()->route('monitors.index');
    }

    /**
     * Re-check one app, or every app when none is given.
     */
    public function check(Request $request, ?Monitor $monitor = null): RedirectResponse
    {
        Monitor::check($monitor ? $monitor->newCollection([$monitor]) : Monitor::all());

        return redirect()->back();
    }

    /**
     * @return array{name: string, url: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:2000'],
        ]);
    }
}
