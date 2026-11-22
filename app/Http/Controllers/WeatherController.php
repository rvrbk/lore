<?php

namespace App\Http\Controllers;

use App\Models\WorldClock;
use App\Services\Weather;
use Illuminate\Http\JsonResponse;

class WeatherController extends Controller
{
    /**
     * Weather per clock time zone, for the header clock and the world clocks to fill in after the page has loaded.
     */
    public function __invoke(Weather $weather): JsonResponse
    {
        return response()->json((object) WorldClock::all()->unique('timezone')->mapWithKeys(fn (WorldClock $clock) => [$clock->timezone => $weather->for($clock)])->filter()->all());
    }
}
