<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitorController extends Controller
{
    public function index(): View
    {
        return view('monitors.index', ['monitors' => Monitor::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $monitor = Monitor::create($this->validated($request));
        Monitor::check($monitor->newCollection([$monitor]));

        return redirect()->route('monitors.index');
    }

    public function update(Request $request, Monitor $monitor): RedirectResponse
    {
        $monitor->update($this->validated($request));
        Monitor::check($monitor->newCollection([$monitor]));

        return redirect()->route('monitors.index');
    }

    public function destroy(Monitor $monitor): RedirectResponse
    {
        $monitor->delete();

        return redirect()->route('monitors.index');
    }

    /**
     * Re-check one app, or every app when none is given.
     */
    public function check(Request $request, ?Monitor $monitor = null): RedirectResponse
    {
        Monitor::check($monitor ? $monitor->newCollection([$monitor]) : Monitor::all());

        return redirect()->back();
    }

    /**
     * @return array{name: string, url: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:2000'],
        ]);
    }
}
