<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional authentication middleware.
 *
 * Attempts to authenticate the user via Sanctum if a Bearer token is present,
 * but does NOT return 401 if authentication fails. This allows public routes
 * to optionally use authenticated user context when available.
 */
class OptionalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only attempt authentication if a Bearer token is present
        if ($request->bearerToken()) {
            Auth::shouldUse('sanctum');
        }

        return $next($request);
    }
}
