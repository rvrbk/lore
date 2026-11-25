<?php

namespace App\Http\Middleware;

use Barryvdh\Debugbar\Facades\Debugbar;
use Closure;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps vault requests out of debugging tools and out of every cache, in the browser and on the way there.
 */
class VaultNoRecording
{
    public function handle(Request $request, Closure $next): Response
    {
        if (class_exists(Debugbar::class)) {
            Debugbar::disable();
        }
        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
