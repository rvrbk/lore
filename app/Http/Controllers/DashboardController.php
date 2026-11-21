<?php

namespace App\Http\Controllers;

use App\Models\BookingType;
use App\Models\Monitor;
use App\Models\Page;
use App\Models\Reminder;
use App\Models\WorldClock;
use App\Services\CommitLog;
use App\Services\GoogleAnalytics;
use App\Services\JobScout;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('dashboard', [
            'clocks' => WorldClock::orderBy('position')->orderBy('id')->get(),
            'cities' => WorldClock::cities(),
            'commits' => app(CommitLog::class)->latest(),
            'jobScout' => app(JobScout::class)->newMatches(),
            'analytics' => app(GoogleAnalytics::class)->summary(),
            'monitors' => $monitors = Monitor::orderBy('name')->get(),
            'sparks' => Monitor::sparklines($monitors),
            'due' => Reminder::due()->with('page')->orderBy('due_at')->get(),
            'upcoming' => Reminder::open()->where('due_at', '>', now())->where('due_at', '<=', now()->addDays(14))->with('page')->orderBy('due_at')->get(),
            'bookingTypes' => BookingType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'recent' => Page::with('space')->latest('updated_at')->limit(8)->get(),
        ]);
    }
}
