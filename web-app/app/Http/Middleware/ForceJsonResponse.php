<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API clients always get JSON: middleware such as `verified` or `auth` answer with 403/401 JSON instead of redirects,
 * even when the mobile app forgets the Accept header.
 */
class ForceJsonResponse
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
