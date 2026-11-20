<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Models\Page;
use App\Models\Reminder;
use App\Services\GoogleAnalytics;
use App\Services\JobScout;
use App\Services\SiteUsers;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('dashboard', [
            'jobScout' => app(JobScout::class)->newMatches(),
            'analytics' => app(GoogleAnalytics::class)->summary(),
            'siteUsers' => app(SiteUsers::class)->counts(),
            'monitors' => $monitors = Monitor::orderBy('name')->get(),
            'sparks' => Monitor::sparklines($monitors),
            'due' => Reminder::due()->with('page')->orderBy('due_at')->get(),
            'upcoming' => Reminder::open()->where('due_at', '>', now())->where('due_at', '<=', now()->addDays(14))->with('page')->orderBy('due_at')->get(),
            'recent' => Page::with('space')->latest('updated_at')->limit(8)->get(),
        ]);
    }
}
