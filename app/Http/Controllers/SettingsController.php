<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings.index', ['timeFormat' => Setting::timeFormat()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['time_format' => ['required', 'in:12,24']]);
        Setting::put(Setting::TIME_FORMAT, $data['time_format']);

        return redirect()->route('settings.index')->with('status', 'Settings saved.');
    }
}
