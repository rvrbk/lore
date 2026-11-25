<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The page the `password.confirm` middleware sends people to before sensitive actions.
 */
class ConfirmPasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Auth::guard('web')->validate(['username' => $request->user()->username, 'password' => $request->input('password')])) {
            throw ValidationException::withMessages(['password' => 'That is not your password.']);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('vault.index'));
    }
}
