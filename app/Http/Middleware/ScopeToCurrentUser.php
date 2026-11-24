<?php

namespace App\Http\Middleware;

use App\Models\Scopes\OwnedByUserScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes everything the signed-in user owns the only thing their request can see or change.
 */
class ScopeToCurrentUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            app()->instance(OwnedByUserScope::KEY, $request->user()->getAuthIdentifier());
        }

        return $next($request);
    }
}
