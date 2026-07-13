<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Support;

use App\Core\ApiErrorCodes;
use App\Core\TenantContext;
use App\Http\Controllers\Api\AuthController;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the accessible frontend's established member identity sources and
 * validates that the identity is an active, approved member of this tenant.
 *
 * The accessible frontend supports the Laravel guard, the legacy native PHP
 * session and its auth_token cookie/JWT. User-login Sanctum personal-access
 * tokens are deliberately not accepted because they bypass rotating sessions.
 * Keep these sources centralized so route middleware and controllers cannot
 * drift.
 */
final class AccessibleIdentityResolver
{
    private const ROTATION_ATTEMPTED_ATTRIBUTE = 'accessible_refresh_rotation_attempted';
    private const ACCESS_IDENTITY_REJECTED_ATTRIBUTE = 'accessible_access_identity_rejected';

    public function __construct(
        private readonly TokenService $tokens,
        private readonly AccessibleSessionCookies $cookies,
    ) {}

    public function userId(Request $request): ?int
    {
        $resolved = $request->attributes->get('accessible_user_id');
        if (is_int($resolved) && $resolved > 0) {
            return $resolved;
        }

        $bearerToken = $request->bearerToken();
        if (is_string($bearerToken) && $bearerToken !== '') {
            return $this->resolveAccessToken($request, $bearerToken, false);
        }

        $accessToken = $request->cookie(AccessibleSessionCookies::ACCESS_COOKIE);
        if (!is_string($accessToken) || $accessToken === '') {
            $user = Auth::user();
            if ($user !== null) {
                return $this->remember($request, $this->validatedTenantUserId((int) $user->id));
            }

            $legacySessionUserId = $this->boundedLegacySessionUserId();
            if ($legacySessionUserId !== null) {
                $validatedLegacyUserId = $this->validatedTenantUserId($legacySessionUserId);
                if ($validatedLegacyUserId !== null) {
                    return $this->remember($request, $validatedLegacyUserId);
                }

                $this->clearLegacySessionAuthentication();
            }

            $refreshToken = $request->cookie(AccessibleSessionCookies::REFRESH_COOKIE);
            return is_string($refreshToken) && $refreshToken !== ''
                ? $this->rotateCookieSession($request)
                : null;
        }

        $resolvedFromAccess = $this->resolveAccessToken($request, $accessToken, true);
        if ($resolvedFromAccess !== null) {
            return $resolvedFromAccess;
        }
        if ($request->attributes->get(self::ACCESS_IDENTITY_REJECTED_ATTRIBUTE) === true) {
            return null;
        }

        return $this->rotateCookieSession($request);
    }

    private function resolveAccessToken(
        Request $request,
        string $token,
        bool $clearInvalidTenant,
        bool $remember = true,
    ): ?int {
        try {
            $payload = $this->tokens->validateToken($token);
            if (!is_array($payload)) {
                return null;
            }

            $userId = (int) ($payload['user_id'] ?? $payload['sub'] ?? 0);
            if ($userId > 0) {
                $validatedUserId = $this->validatedTenantUserId($userId);
                if ($validatedUserId !== null) {
                    return $remember
                        ? $this->remember($request, $validatedUserId)
                        : $validatedUserId;
                }
            }

            $request->attributes->set(self::ACCESS_IDENTITY_REJECTED_ATTRIBUTE, true);
            if ($clearInvalidTenant) {
                $this->cookies->queueExpiredPair();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Rotate the accessible cookie session once, using the same controller path
     * as the public refresh endpoint. This preserves the controller's user lock,
     * policy re-checks, replay detection, and atomic refresh-family rotation.
     */
    private function rotateCookieSession(Request $request): ?int
    {
        if ($request->attributes->get(self::ROTATION_ATTEMPTED_ATTRIBUTE) === true) {
            return null;
        }
        $request->attributes->set(self::ROTATION_ATTEMPTED_ATTRIBUTE, true);

        $refreshToken = $request->cookie(AccessibleSessionCookies::REFRESH_COOKIE);
        if (!is_string($refreshToken) || $refreshToken === '') {
            $this->cookies->queueExpiredPair();
            return null;
        }

        try {
            $response = $this->refreshThroughAuthController($request, $refreshToken);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }

        $payload = $response->getData(true);
        $status = $response->getStatusCode();
        if ($status === Response::HTTP_REQUEST_TIMEOUT
            || $status === Response::HTTP_TOO_MANY_REQUESTS
            || $status >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            // Fail closed for this request without destroying a potentially
            // valid refresh credential during a transient failure window.
            return null;
        }

        if (
            $status === Response::HTTP_CONFLICT
            && ($payload['errors'][0]['code'] ?? null) === ApiErrorCodes::AUTH_REFRESH_SUPERSEDED
        ) {
            // The winning request owns the replacement pair. Preserve these
            // cookies so its response can install that pair after this loser.
            return null;
        }

        if ($status >= Response::HTTP_BAD_REQUEST) {
            $this->cookies->queueExpiredPair();
            return null;
        }

        if (($payload['success'] ?? false) !== true) {
            return null;
        }

        $accessToken = (string) ($payload['access_token'] ?? '');
        $rotatedRefreshToken = (string) ($payload['refresh_token'] ?? '');
        $refreshExpiresIn = (int) ($payload['refresh_expires_in'] ?? 0);
        if ($accessToken === '' || $rotatedRefreshToken === '' || $refreshExpiresIn <= 0) {
            // A malformed success response is an internal contract failure,
            // not authoritative evidence that the presented credential is bad.
            return null;
        }

        $userId = $this->resolveAccessToken($request, $accessToken, true, false);
        if ($userId === null) {
            $this->cookies->queueExpiredPair();
            return null;
        }

        try {
            $this->cookies->queueTokenPair(
                $request,
                $accessToken,
                $rotatedRefreshToken,
                $refreshExpiresIn,
            );
        } catch (\Throwable $e) {
            report($e);
            $this->cookies->queueExpiredPair();
            return null;
        }

        return $this->remember($request, $userId);
    }

    private function refreshThroughAuthController(Request $request, string $refreshToken): \Illuminate\Http\JsonResponse
    {
        $container = app();
        $originalRequest = $container->make('request');
        $refreshRequest = $request->duplicate();
        $refreshRequest->setMethod('POST');
        $refreshRequest->request->replace(['refresh_token' => $refreshToken]);
        $refreshRequest->headers->remove('Authorization');
        $refreshRequest->headers->remove('Content-Type');

        $container->instance('request', $refreshRequest);
        try {
            return $container->make(AuthController::class)->refreshToken();
        } finally {
            $container->instance('request', $originalRequest);
        }
    }

    private function remember(Request $request, ?int $userId): ?int
    {
        if ($userId !== null) {
            $request->attributes->set('accessible_user_id', $userId);
        }

        return $userId;
    }

    /**
     * Accept the raw PHP compatibility session only while the access-token
     * window that created it remains valid and tenant-bound.
     */
    private function boundedLegacySessionUserId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user_id'])) {
            return null;
        }

        $userId = (int) $_SESSION['user_id'];
        $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        $issuedAt = (int) ($_SESSION['_api_access_issued_at'] ?? 0);
        $expiresAt = (int) ($_SESSION['_api_access_expires_at'] ?? 0);
        $valid = (int) ($_SESSION['_api_session_bridge_version'] ?? 0) === 1
            && $userId === (int) ($_SESSION['_api_access_user_id'] ?? 0)
            && $tenantId === (int) ($_SESSION['_api_access_tenant_id'] ?? 0)
            && $tenantId === TenantContext::getId()
            && $this->tokens->validateApiSessionWindow(
                $userId,
                $tenantId,
                $issuedAt,
                $expiresAt,
            );

        if (!$valid) {
            $this->clearLegacySessionAuthentication();
            return null;
        }

        return $userId;
    }

    /** Remove authentication state while preserving unrelated locale/layout data. */
    private function clearLegacySessionAuthentication(): void
    {
        foreach ([
            'user_id',
            'user_name',
            'user_email',
            'user_role',
            'role',
            'is_super_admin',
            'is_tenant_super_admin',
            'is_god',
            'tenant_id',
            'user_avatar',
            'is_admin',
            'is_logged_in',
            '_api_session_bridge_version',
            '_api_access_user_id',
            '_api_access_tenant_id',
            '_api_access_issued_at',
            '_api_access_expires_at',
        ] as $key) {
            unset($_SESSION[$key]);
        }
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
