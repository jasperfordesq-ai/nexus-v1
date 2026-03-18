<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Middleware;

use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hybrid authentication middleware — supports both Sanctum and legacy JWT tokens.
 *
 * During the Laravel migration, clients may send either:
 *   1. A Sanctum token (new login sessions)
 *   2. A legacy JWT token (existing sessions from before migration)
 *
 * This middleware tries Sanctum first, then falls back to legacy JWT validation.
 */
class Authenticate
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        // 1. Try Sanctum guard first (preferred)
        $guards = empty($guards) ? ['sanctum'] : $guards;

        foreach ($guards as $guard) {
            if (auth()->guard($guard)->check()) {
                auth()->shouldUse($guard);
                return $next($request);
            }
        }

        // 2. Fall back to legacy JWT token validation
        $token = $this->extractBearerToken($request);
        if ($token) {
            $user = $this->validateLegacyToken($token);
            if ($user) {
                auth()->shouldUse('sanctum');
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

    /**
     * Extract Bearer token from Authorization header or cookie.
     */
    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        // Also check cookie-based token (used by React frontend)
        return $request->cookie('auth_token');
    }

    /**
     * Validate a legacy JWT token and set up the legacy auth context.
     *
     * The legacy system uses $_SESSION['user_id'] for auth checks,
     * and delegation controllers call legacy code that reads from session.
     */
    private function validateLegacyToken(string $token): bool
    {
        try {
            $tokenService = app(TokenService::class);
            $payload = $tokenService->validateToken($token);

            if (!$payload) {
                \Illuminate\Support\Facades\Log::debug('[Auth] Legacy token validation returned null');
                return false;
            }

            // Token payload uses 'user_id' (not 'sub')
            $userId = (int) ($payload['user_id'] ?? $payload['sub'] ?? 0);
            if (!$userId) {
                \Illuminate\Support\Facades\Log::debug('[Auth] No user_id/sub in payload', ['keys' => array_keys($payload)]);
                return false;
            }

            // Tenant is already resolved by ResolveTenant middleware (runs before this)

            // Also set Laravel's auth user for any code using auth()
            $eloquentUser = \App\Models\User::withoutGlobalScopes()->find($userId);
            if (!$eloquentUser) {
                \Illuminate\Support\Facades\Log::debug('[Auth] User not found in DB', ['user_id' => $userId]);
                return false;
            }

            auth()->guard('sanctum')->setUser($eloquentUser);

            // Set legacy session so delegation controllers' Auth::check() works
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['user_id'] = $userId;
            $_SESSION['tenant_id'] = $eloquentUser->tenant_id;

            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[Auth] Legacy token exception: ' . $e->getMessage());
            return false;
        }
    }
}
