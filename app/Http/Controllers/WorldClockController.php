<?php

namespace App\Http\Controllers;

use App\Models\WorldClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorldClockController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $cities = WorldClock::cities();
        $data = $request->validate(['city' => ['required', 'string', 'in:'.implode(',', array_map(fn (string $label) => str_replace(',', '', $label), $cities))]], ['city.in' => 'Pick a city from the list.']);

        $timezone = array_search($data['city'], $cities, true);
        WorldClock::firstOrCreate(['timezone' => $timezone], ['city' => $data['city'], 'position' => (int) WorldClock::max('position') + 1]);

        return redirect()->route('dashboard');
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer', 'exists:world_clocks,id']]);

        foreach (array_values($data['order']) as $position => $id) {
            WorldClock::whereKey($id)->update(['position' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }

    public function toggleStyle(WorldClock $worldClock): RedirectResponse
    {
        $worldClock->update(['is_analog' => ! $worldClock->is_analog]);

        return redirect()->route('dashboard');
    }

    public function toggleHeader(WorldClock $worldClock): RedirectResponse
    {
        $show = ! $worldClock->is_header;
        WorldClock::where('is_header', true)->update(['is_header' => false]);
        $worldClock->update(['is_header' => $show]);

        return redirect()->route('dashboard');
    }

    public function destroy(WorldClock $worldClock): RedirectResponse
    {
        $worldClock->delete();

        return redirect()->route('dashboard');
    }
}
