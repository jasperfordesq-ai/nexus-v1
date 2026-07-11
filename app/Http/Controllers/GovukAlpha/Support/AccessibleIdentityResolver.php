<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Support;

use App\Core\TenantContext;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Resolves the accessible frontend's established member identity sources and
 * validates that the identity is an active, approved member of this tenant.
 *
 * The accessible frontend supports the Laravel guard, the legacy native PHP
 * session, its auth_token cookie/JWT, and Sanctum personal-access tokens. Keep
 * those sources centralized so route middleware and controllers cannot drift.
 */
final class AccessibleIdentityResolver
{
    public function userId(Request $request): ?int
    {
        $resolved = $request->attributes->get('accessible_user_id');
        if (is_int($resolved) && $resolved > 0) {
            return $resolved;
        }

        $user = Auth::user();
        if ($user !== null) {
            return $this->validatedTenantUserId((int) $user->id);
        }

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
            return $this->validatedTenantUserId((int) $_SESSION['user_id']);
        }

        $token = $request->bearerToken() ?: $request->cookie('auth_token');
        if (!is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = app(TokenService::class)->validateToken($token);
            $userId = (int) ($payload['user_id'] ?? $payload['sub'] ?? 0);
            if ($userId > 0) {
                return $this->validatedTenantUserId($userId);
            }
        } catch (\Throwable) {
            // The token may be a Sanctum personal-access token instead.
        }

        try {
            $accessToken = PersonalAccessToken::findToken($token);
            $tokenable = $accessToken?->tokenable;
            if ($tokenable !== null && isset($tokenable->id)) {
                return $this->validatedTenantUserId((int) $tokenable->id);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function validatedTenantUserId(int $userId): ?int
    {
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('is_approved', 1)
                    ->orWhereIn('role', ['admin', 'tenant_admin', 'super_admin', 'god'])
                    ->orWhere('is_tenant_super_admin', 1)
                    ->orWhere('is_super_admin', 1)
                    ->orWhere('is_god', 1);
            })
            ->first(['id']);

        return $user !== null ? (int) $user->id : null;
    }
}
