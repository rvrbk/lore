<?php

namespace App\Http\Controllers;

use App\Models\BookingType;
use App\Models\Incident;
use App\Models\JobScoutSnapshot;
use App\Models\Monitor;
use App\Models\Mood;
use App\Models\Page;
use App\Models\Reminder;
use App\Models\WorldClock;
use App\Services\CommitLog;
use App\Services\CommitStreak;
use App\Services\Fortune;
use App\Services\GoogleAnalytics;
use App\Services\JobScout;
use App\Services\MoodInsights;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('dashboard', [
            'clocks' => WorldClock::orderBy('position')->orderBy('id')->get(),
            'cities' => WorldClock::cities(),
            'commits' => app(CommitLog::class)->latest(),
            'jobScout' => app(JobScout::class)->newMatches(),
            'streak' => app(CommitStreak::class)->current(),
            'fortune' => app(Fortune::class)->today(),
            'moodDays' => $moodDays = app(MoodInsights::class)->days(),
            'moodInsight' => app(MoodInsights::class)->insight($moodDays),
            'todayMood' => Mood::where('day', today()->toDateString())->value('level'),
            'openMonitorIds' => Incident::open()->whereNotNull('monitor_id')->pluck('monitor_id')->all(),
            'jobScoutTrend' => JobScoutSnapshot::trend(30),
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
