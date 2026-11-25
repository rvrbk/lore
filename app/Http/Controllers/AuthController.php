<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Telegram;
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

        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if ($user === null || ! $provider->validateCredentials($user, $credentials)) {
            throw ValidationException::withMessages(['username' => 'Wrong username or password.']);
        }

        if (! $user->isApproved()) {
            throw ValidationException::withMessages(['username' => 'Your account is waiting for approval.']);
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put([
                TwoFactorChallengeController::PENDING_USER => $user->id,
                TwoFactorChallengeController::PENDING_REMEMBER => $request->boolean('remember'),
            ]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request, Telegram $telegram): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:40', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $isFirstAccount = ! User::exists();
        $user = User::create($data);

        if ($isFirstAccount) {
            $user->approve();
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('dashboard');
        }

        $telegram->sendToAdmins('🆕 <b>New Lore account</b> waiting for approval: '.e($user->username).' ('.e($user->email).")\n".route('admin.users.index'));

        return redirect()->route('login')->with('status', 'Thanks! Your account is created. You can log in once an admin has approved it.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
