<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsSite;
use App\Models\Monitor;
use App\Services\GoogleAnalytics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(GoogleAnalytics $analytics): View
    {
        return view('analytics.index', [
            'sites' => AnalyticsSite::orderBy('name')->get(),
            'analytics' => $analytics->summary(),
            'configured' => $analytics->isConfigured(),
            'hasCredentials' => $analytics->hasCredentials(),
        ]);
    }

    public function store(Request $request, GoogleAnalytics $analytics): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:2000'],
            'property_id' => ['nullable', 'regex:/^\d{6,15}$/', 'unique:analytics_sites,property_id'],
        ], ['property_id.regex' => 'Use the numeric GA4 property ID (Admin → Property details), not the G-XXXX measurement ID.']);

        $monitor = Monitor::create(['name' => $data['name'], 'url' => $data['url']]);
        Monitor::check($monitor->newCollection([$monitor]));

        if (! empty($data['property_id'])) {
            AnalyticsSite::create(['name' => $data['name'], 'property_id' => $data['property_id']]);
            $analytics->forget();
        }

        return redirect()->back(fallback: route('analytics.index'));
    }

    public function destroy(AnalyticsSite $site, GoogleAnalytics $analytics): RedirectResponse
    {
        $site->delete();
        $analytics->forget();

        return redirect()->route('analytics.index');
    }
}
