<?php

namespace App\Http\Controllers;

use App\Models\CalendarSource;
use App\Services\CalendarAgenda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CalendarSourceController extends Controller
{
    public function store(Request $request, CalendarAgenda $agenda): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'url' => ['required', 'string', 'max:2000', 'regex:#^(https?|webcal)://#i'],
            'color' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
        ], ['url.regex' => 'Paste the calendar\'s ICS address (it starts with https:// or webcal://).']);

        CalendarSource::create($data);
        $agenda->refresh();

        return redirect()->route('dashboard');
    }

    public function destroy(CalendarSource $calendarSource): RedirectResponse
    {
        Cache::forget($calendarSource->cacheKey());
        $calendarSource->delete();

        return redirect()->route('dashboard');
    }
}
