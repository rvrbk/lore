<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequireTwoFactor;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The second step of signing in, and the way back in when a remember-me cookie skipped it.
 */
class TwoFactorChallengeController extends Controller
{
    public const PENDING_USER = 'two_factor.login_id';

    public const PENDING_REMEMBER = 'two_factor.remember';

    public function show(Request $request): View|RedirectResponse
    {
        return $this->subject($request) === null ? redirect()->route('login') : view('auth.two-factor-challenge');
    }

    public function verify(Request $request, Totp $totp): RedirectResponse
    {
        $user = $this->subject($request);
        if ($user === null) {
            return redirect()->route('login');
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        if (! $this->accepted($user, $data['code'], $totp)) {
            throw ValidationException::withMessages(['code' => 'That code is not right.']);
        }

        if (Auth::id() !== $user->id) {
            Auth::login($user, (bool) $request->session()->pull(self::PENDING_REMEMBER, false));
        }

        $request->session()->forget(self::PENDING_USER);
        $request->session()->regenerate();
        $request->session()->put(RequireTwoFactor::PASSED, true);

        return redirect()->intended(route('dashboard'));
    }

    /** Someone half way through signing in, or someone already signed in who has to prove the second factor again. */
    private function subject(Request $request): ?User
    {
        $id = $request->session()->get(self::PENDING_USER) ?? Auth::id();
        $user = $id === null ? null : User::find($id);

        return $user?->hasTwoFactorEnabled() ? $user : null;
    }

    private function accepted(User $user, string $code, Totp $totp): bool
    {
        $step = $totp->matchingStep($user->two_factor_secret, $code);
        if ($step !== null && $step > (int) $user->two_factor_last_step) {
            $user->forceFill(['two_factor_last_step' => $step])->save();

            return true;
        }

        return $user->useRecoveryCode($code);
    }
}
