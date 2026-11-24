<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsSite;
use App\Models\Monitor;
use App\Services\DeployImpact;
use App\Services\GitHubDeployHook;
use App\Services\GitHubHeatmapSnippet;
use App\Services\GoogleAnalytics;
use App\Services\HeatmapOverview;
use App\Services\SearchConsole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class AnalyticsController extends Controller
{
    public function index(GoogleAnalytics $analytics, HeatmapOverview $heatmaps, GitHubHeatmapSnippet $snippet, SearchConsole $searchConsole, DeployImpact $impact): View
    {
        $monitors = Monitor::orderBy('name')->get();

        return view('analytics.index', [
            'monitors' => $monitors,
            'searchSites' => $monitors->where('show_in_search')->map(fn (Monitor $monitor) => ['monitor' => $monitor, 'report' => ($report = $searchConsole->report($monitor)) === null ? null : $report + ['impact' => $impact->forTraffic($monitor->name, $report['daily_impressions'])]])->when(! $searchConsole->isConfigured() || $searchConsole->properties() === null, fn ($rows) => collect()),
            'snippet' => $snippet,
            'clickMonitors' => $clickMonitors = $monitors->reject->isLore()->values(),
            ...$heatmaps->for($clickMonitors),
            'sites' => AnalyticsSite::orderBy('name')->get(),
            'analytics' => $analytics->summary(),
            'configured' => $analytics->isConfigured(),
            'hasCredentials' => $analytics->hasCredentials(),
        ]);
    }

    public function live(GoogleAnalytics $analytics): JsonResponse
    {
        $sites = $analytics->liveBySite();

        return response()->json([
            'total' => $sites === null ? null : array_sum($sites),
            'sites' => collect($sites ?? [])->filter()->map(fn (int $users, string $name) => ['name' => $name, 'users' => $users])->values(),
            'countries' => (object) ($analytics->liveByCountry() ?? []),
        ]);
    }

    public function store(Request $request, GoogleAnalytics $analytics, GitHubDeployHook $hook, GitHubHeatmapSnippet $heatmap): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:2000'],
            'deploy_path' => ['nullable', 'regex:#^/var/www/[A-Za-z0-9._-]+$#'],
            'property_id' => ['nullable', 'regex:/^\d{6,15}$/', 'unique:analytics_sites,property_id'],
        ], ['deploy_path.regex' => 'Use the site\'s folder on the server, like /var/www/my-site.', 'property_id.regex' => 'Use the numeric GA4 property ID (Admin → Property details), not the G-XXXX measurement ID.']);

        $monitor = Monitor::create(['name' => $data['name'], 'url' => $data['url'], 'deploy_path' => $data['deploy_path'] ?? null, 'show_in_search' => $request->boolean('show_in_search', true)]);
        $monitor->subscribers()->attach($request->user()->id);
        Monitor::check($monitor->newCollection([$monitor]));

        if (! empty($data['property_id'])) {
            AnalyticsSite::create(['name' => $data['name'], 'property_id' => $data['property_id']]);
            $analytics->forget();
        }

        $redirect = redirect()->back(fallback: route('analytics.index'));
        if ($monitor->deploy_path !== null && $hook->isConfigured()) {
            try {
                $redirect->with('hook', "Pushes to {$hook->install($monitor)} now deploy through Lore.");
            } catch (Throwable $e) {
                $redirect->with('hook_error', "Site added, but deploy-on-push was not set up: {$e->getMessage()}");
            }
        }

        if ($monitor->deploy_path !== null && $hook->isConfigured()) {
            try {
                $heatmap->install($monitor);
                $redirect->with('heatmap', "{$monitor->name} now sends its clicks to Heatmaps once the push is deployed.");
            } catch (Throwable $e) {
                $redirect->with('heatmap_error', "Site added, but the heatmap tracker was not added: {$e->getMessage()}");
            }
        }

        return $redirect;
    }

    public function destroy(AnalyticsSite $site, GoogleAnalytics $analytics): RedirectResponse
    {
        $site->delete();
        $analytics->forget();

        return redirect()->route('analytics.index');
    }
}
