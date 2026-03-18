<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate middleware for Sanctum-protected API routes.
 *
 * Returns a 401 JSON response for unauthenticated requests.
 * This is an API-only application — no redirect to login page.
 */
class Authenticate
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        // Default to 'sanctum' guard for API requests
        $guards = empty($guards) ? ['sanctum'] : $guards;

        foreach ($guards as $guard) {
            if (auth()->guard($guard)->check()) {
                auth()->shouldUse($guard);
                return $next($request);
            }
        }

        return response()->json([
            'errors' => [
                ['code' => 'auth_required', 'message' => 'Authentication required'],
            ],
            'success' => false,
        ], 401, [
            'API-Version' => '2.0',
        ]);
    }
}
