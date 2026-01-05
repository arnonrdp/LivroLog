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
            // Set the default guard to Sanctum and attempt authentication
            Auth::shouldUse('sanctum');

            // Explicitly attempt to authenticate the user via Sanctum guard
            // This populates $request->user() if the token is valid
            try {
                Auth::guard('sanctum')->user();
            } catch (\Exception $e) {
                // Silently ignore authentication failures for optional auth
            }
        }

        return $next($request);
    }
}
