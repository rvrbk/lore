<?php

namespace App\Http\Middleware;

use App\Models\NavVisit;
use App\Support\NavMenu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackNavVisit
{
    /**
     * Count page views of menu items so the most used ones can be listed first.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $item = $request->isMethod('GET') && ! $request->expectsJson() && ! $request->ajax() && $response->isSuccessful()
            ? NavMenu::itemFor($request->route()?->getName())
            : null;

        if ($item !== null) {
            NavVisit::firstOrCreate(['item' => $item])->increment('visits');
        }

        return $response;
    }
}
