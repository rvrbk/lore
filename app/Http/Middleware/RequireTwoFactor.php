<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps people out of the vault unless two-factor authentication is on and was completed in this session.
 *
 * Remember-me cookies sign a user in without asking for a code, so the session flag set by the challenge is what counts.
 */
class RequireTwoFactor
{
    /** Session key set once a one-time code (or recovery code) was accepted. */
    public const PASSED = 'two_factor.passed';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return $this->deny($request, redirect()->route('settings.two-factor')->with('status', 'Turn on two-factor authentication to use the vault.'));
        }

        if (! $request->session()->get(self::PASSED)) {
            $request->session()->put('url.intended', $request->isMethod('GET') ? $request->fullUrl() : route('vault.index'));

            return $this->deny($request, redirect()->route('two-factor.challenge'));
        }

        return $next($request);
    }

    private function deny(Request $request, Response $redirect): Response
    {
        return $request->expectsJson() ? response()->json(['message' => 'Two-factor authentication required.'], 423) : $redirect;
    }
}
