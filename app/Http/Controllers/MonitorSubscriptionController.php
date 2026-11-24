<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Users choose which monitors' domain expiry reminders they receive.
 */
class MonitorSubscriptionController extends Controller
{
    public function store(Request $request, Monitor $monitor): RedirectResponse
    {
        $monitor->subscribers()->syncWithoutDetaching([$request->user()->id]);

        return back();
    }

    public function destroy(Request $request, Monitor $monitor): RedirectResponse
    {
        abort_if($request->user()->isAdmin(), 403, 'Admins are subscribed to every monitor.');

        $monitor->unsubscribe($request->user());

        return back();
    }
}
