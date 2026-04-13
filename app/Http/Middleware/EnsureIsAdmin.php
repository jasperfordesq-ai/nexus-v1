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

        // Admin precedence (evaluated in order):
        //   1. EXPLICIT REJECT: role === 'broker' → never admin. Brokers are
        //      community moderators with their own scoped routes. Even if some
        //      legacy flag is set on their row, we deny admin here to avoid
        //      privilege-escalation via flag drift.
        //   2. Boolean flags (backward compat): is_admin, is_super_admin,
        //      is_tenant_super_admin, is_god. Any one grants admin.
        //   3. Role string: 'admin', 'tenant_admin', 'super_admin'. Any one
        //      grants admin.
        $role = (string) ($user->role ?? '');

        if ($role === 'broker') {
            return response()->json([
                'errors' => [
                    ['code' => 'forbidden', 'message' => 'Admin access required'],
                ],
                'success' => false,
            ], 403, [
                'API-Version' => '2.0',
            ]);
        }

        $hasAdminFlag = $user->is_admin
            || $user->is_super_admin
            || $user->is_tenant_super_admin
            || $user->is_god;

        $hasAdminRole = in_array($role, ['admin', 'tenant_admin', 'super_admin'], true);

        $isAdmin = $hasAdminFlag || $hasAdminRole;

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
