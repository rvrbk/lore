<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsSite;
use App\Models\Deployment;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Page;
use App\Models\Reminder;
use App\Models\Task;
use App\Services\DeployImpact;
use App\Services\ErrorLog;
use App\Services\GoogleAnalytics;
use App\Services\ServerHealth;
use App\Services\Sql;
use App\Services\UptimeWall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitorController extends Controller
{
    public function index(Request $request, ServerHealth $health, ErrorLog $errors): View
    {
        $monitors = Monitor::orderBy('name')->get();

        return view('monitors.index', [
            'monitors' => $monitors,
            'subscribedIds' => $request->user()->monitorSubscriptions()->pluck('monitors.id'),
            'sparks' => Monitor::sparklines($monitors),
            'metrics' => $health->recent(),
            'diskDaysLeft' => $health->daysUntilDiskFull(),
            'errorCounts' => $monitors->mapWithKeys(fn (Monitor $monitor) => [$monitor->id => $errors->forMonitor($monitor)['last_day'] ?? null]),
        ]);
    }

    public function show(Monitor $monitor, GoogleAnalytics $analytics, DeployImpact $impact, ErrorLog $errors): View
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

        $tag = $monitor->tagName();
        $tasks = $tag === '' ? collect() : Task::withTag($tag)->where('status', '!=', 'done')->orderBy('position')->get();
        $pages = $tag === '' ? collect() : Page::withTag($tag)->with('space')->orderBy('title')->get();
        $reminders = Reminder::open()->orderBy('due_at')
            ->where(fn ($query) => $query->whereIn('task_id', $tasks->modelKeys())->orWhereIn('page_id', $pages->modelKeys())->orWhere('title', Sql::like(), $monitor->name.': %'))
            ->limit(10)->get();

        $deploys = $monitor->deploy_path ? Deployment::where('path', $monitor->deploy_path)->where('status', Deployment::SUCCEEDED)->where('finished_at', '>=', now()->subDay())->orderBy('finished_at')->get() : collect();

        return view('monitors.show', [
            'monitor' => $monitor,
            'responseImpact' => $impact->forResponse($recent, $deploys),
            'wall' => app(UptimeWall::class)->for($monitor),
            'errorLog' => $errors->forMonitor($monitor),
            'tag' => $tag,
            'tasks' => $tasks,
            'pages' => $pages,
            'reminders' => $reminders,
            'siteIncidents' => $monitor->incidents()->latest('started_at')->limit(10)->get(),
            'deployments' => $monitor->deploy_path ? Deployment::where('path', $monitor->deploy_path)->latest('id')->limit(8)->get() : collect(),
            'traffic' => collect($analytics->summary() ?? [])->first(fn (array $site) => AnalyticsSite::normalize($site['name']) === AnalyticsSite::normalize($monitor->name)),
            'checks' => $recent,
            'hours' => $hours,
            'incidents' => array_reverse($incidents),
            'uptime' => ['24 hours' => $monitor->uptimePercent(1), '7 days' => $monitor->uptimePercent(7), '30 days' => $monitor->uptimePercent(30)],
            'averageMs' => (int) round($recent->where('is_up', true)->avg('response_ms')),
        ]);
    }

    public function update(Request $request, Monitor $monitor): RedirectResponse
    {
        $monitor->update($this->validated($request) + ['show_in_search' => $request->boolean('show_in_search')]);
        $monitor->setPublic($request->boolean('is_public'));
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
     * @return array{name: string, url: string, deploy_path: string|null}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:2000'],
            'deploy_path' => ['nullable', 'regex:#^/var/www/[A-Za-z0-9._-]+$#'],
        ]);
    }
}
