<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks the vault again after a configurable idle time: the earlier password confirmation is forgotten, so the
 * `password.confirm` middleware that follows asks for the password before anything is shown.
 */
class VaultIdleLock
{
    private const LAST_ACTIVITY = 'vault.last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $last = $session->get(self::LAST_ACTIVITY);

        if ($last !== null && time() - $last > config('vault.idle_lock_minutes') * 60) {
            $session->forget('auth.password_confirmed_at');
        }
        $session->put(self::LAST_ACTIVITY, time());

        return $next($request);
    }
}
