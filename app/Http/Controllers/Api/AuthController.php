<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AuthController — Authentication: login, logout, token refresh, session management.
 *
 * KEPT AS DELEGATION: The legacy AuthController uses TokenService, TotpService,
 * TwoFactorChallengeManager, RateLimiter (DB-based), RateLimitService (Redis),
 * session management (session_start, session_regenerate_id, $_SESSION),
 * and complex auth flows (2FA, login gates, super admin cross-tenant login).
 * These are deeply coupled to the legacy auth infrastructure and must remain
 * as delegation until the full Laravel auth migration is complete.
 */
class AuthController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    public function login(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AuthController::class, 'login');
    }

    public function logout(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AuthController::class, 'logout');
    }

    public function refreshToken(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AuthController::class, 'refreshToken');
    }

    public function heartbeat(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AuthController::class, 'heartbeat');
    }

    public function checkSession(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AuthController::class, 'checkSession');
    }

    public function refreshSession(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AuthController::class, 'refreshSession');
    }

    public function restoreSession(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AuthController::class, 'restoreSession');
    }

    public function validateToken(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AuthController::class, 'validateToken');
    }

    public function revokeToken(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AuthController::class, 'revokeToken');
    }

    public function revokeAllTokens(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AuthController::class, 'revokeAllTokens');
    }

    public function adminSession(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AuthController::class, 'adminSession');
    }

    public function getCsrfToken(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AuthController::class, 'getCsrfToken');
    }
}
