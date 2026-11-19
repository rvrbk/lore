<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (! User::exists()) {
            return redirect()->route('register');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['username' => 'Wrong username or password.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Registration only exists to create the first account; after that it is closed.
     */
    public function showRegister(): View|RedirectResponse
    {
        if (User::exists()) {
            return redirect()->route('login');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        abort_if(User::exists(), 403, 'Registration is closed.');

        $data = $request->validate([
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:40', 'unique:users,username'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        Auth::login(User::create($data));
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
