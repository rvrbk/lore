<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The signed-in user's own settings: the email for password resets and their Job Scout account.
 */
class SettingsController extends Controller
{
    public function show(Request $request)
    {
        return view('settings.index', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'job_scout_email' => ['nullable', 'email', 'max:255'],
        ]);

        $user->forceFill(['email' => $data['email'] ?? null, 'job_scout_email' => $data['job_scout_email'] ?? null])->save();

        return redirect()->route('settings.index')->with('status', 'Settings saved.');
    }
}
