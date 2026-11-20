<?php

namespace App\Http\Controllers;

use App\Models\WorldClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorldClockController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $cities = WorldClock::cities();
        $data = $request->validate(['city' => ['required', 'string', 'in:'.implode(',', array_map(fn (string $label) => str_replace(',', '', $label), $cities))]], ['city.in' => 'Pick a city from the list.']);

        $timezone = array_search($data['city'], $cities, true);
        WorldClock::firstOrCreate(['timezone' => $timezone], ['city' => $data['city']]);

        return redirect()->route('dashboard');
    }

    public function destroy(WorldClock $worldClock): RedirectResponse
    {
        $worldClock->delete();

        return redirect()->route('dashboard');
    }
}
