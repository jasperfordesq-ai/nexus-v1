<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

/**
 * AuthController — Authentication: login, logout, token refresh.
 */
class AuthController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * POST /api/v2/auth/login
     *
     * Authenticate a user with email and password.
     * Body: email (required), password (required), remember (optional bool).
     */
    public function login(): JsonResponse
    {
        $this->rateLimit('auth_login', 5, 60);

        $email = $this->requireInput('email');
        $password = $this->requireInput('password');
        $remember = $this->inputBool('remember', false);

        $result = $this->authService->attemptLogin($email, $password, $remember);

        if ($result === null) {
            return $this->respondWithError('AUTH_FAILED', 'Invalid email or password', null, 401);
        }

        return $this->respondWithData($result);
    }

    /**
     * POST /api/v2/auth/logout
     *
     * Revoke the current session/token.
     */
    public function logout(): JsonResponse
    {
        $userId = $this->requireAuth();

        $this->authService->logout($userId);

        return $this->respondWithData(['message' => 'Logged out successfully']);
    }

    /**
     * POST /api/v2/auth/refresh
     *
     * Refresh the current authentication token.
     */
    public function refreshToken(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('auth_refresh', 10, 60);

        $token = $this->authService->refreshToken($userId);

        if ($token === null) {
            return $this->respondWithError('TOKEN_REFRESH_FAILED', 'Unable to refresh token', null, 401);
        }

        return $this->respondWithData(['token' => $token]);
    }
}
