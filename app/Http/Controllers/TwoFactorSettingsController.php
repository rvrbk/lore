<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequireTwoFactor;
use App\Services\Totp;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorSettingsController extends Controller
{
    public function show(Request $request, Totp $totp): View
    {
        $user = $request->user();
        $pending = ! $user->hasTwoFactorEnabled() && $user->two_factor_secret !== null;

        return view('settings.two-factor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'secret' => $pending ? $user->two_factor_secret : null,
            'uri' => $pending ? $totp->uri($user->two_factor_secret, $user->username, config('app.name')) : null,
            'qr' => $pending ? $this->qrSvg($totp->uri($user->two_factor_secret, $user->username, config('app.name'))) : null,
            'recoveryCodes' => null,
            'recoveryLeft' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    /** Starts setup: a secret is made, but nothing is enforced until a code from the app proves it works. */
    public function start(Request $request, Totp $totp): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasTwoFactorEnabled()) {
            $user->forceFill(['two_factor_secret' => $totp->generateSecret(), 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        }

        return redirect()->route('settings.two-factor');
    }

    /** Switches it on and shows the recovery codes once, in this response rather than a flash that would be stored in the session. */
    public function confirm(Request $request, Totp $totp): View
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);

        $step = $user->two_factor_secret === null || $user->hasTwoFactorEnabled() ? null : $totp->matchingStep($user->two_factor_secret, $data['code']);
        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'That code is not right. Check the clock on your phone and try again.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->save();
        $request->session()->put(RequireTwoFactor::PASSED, true);

        return view('settings.two-factor', ['enabled' => true, 'secret' => null, 'uri' => null, 'qr' => null, 'recoveryCodes' => $user->resetRecoveryCodes(), 'recoveryLeft' => 10]);
    }

    public function regenerateRecoveryCodes(Request $request): View
    {
        abort_unless($request->user()->hasTwoFactorEnabled(), 404);

        return view('settings.two-factor', ['enabled' => true, 'secret' => null, 'uri' => null, 'qr' => null, 'recoveryCodes' => $request->user()->resetRecoveryCodes(), 'recoveryLeft' => 10]);
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        $request->session()->forget(RequireTwoFactor::PASSED);

        return redirect()->route('settings.two-factor')->with('status', 'Two-factor authentication is off. The vault is locked until you turn it on again.');
    }

    private function qrSvg(string $uri): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($uri);
    }
}
