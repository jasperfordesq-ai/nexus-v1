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
 * Ensure the authenticated user has admin-level privileges.
 *
 * Checks is_admin, is_super_admin, is_tenant_super_admin, or is_god.
 * Returns 403 JSON response for non-admin users.
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'errors' => [
                    ['code' => 'auth_required', 'message' => 'Authentication required'],
                ],
                'success' => false,
            ], 401, [
                'API-Version' => '2.0',
            ]);
        }

        $isAdmin = $user->is_admin
            || $user->is_super_admin
            || $user->is_tenant_super_admin
            || $user->is_god
            || in_array($user->role ?? '', ['admin', 'tenant_admin', 'super_admin', 'broker']);

        if (!$isAdmin) {
            return response()->json([
                'errors' => [
                    ['code' => 'forbidden', 'message' => 'Admin access required'],
                ],
                'success' => false,
            ], 403, [
                'API-Version' => '2.0',
            ]);
        }

        return $next($request);
    }
}
