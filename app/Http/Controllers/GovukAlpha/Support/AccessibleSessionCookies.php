<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Support;

use Illuminate\Contracts\Cookie\QueueingFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Creates and queues the accessible frontend's rotating-session cookies.
 */
final class AccessibleSessionCookies
{
    public const ACCESS_COOKIE = 'auth_token';
    public const REFRESH_COOKIE = 'accessible_refresh_token';
    public const ACCESS_MINUTES = 15;

    public function __construct(
        private readonly QueueingFactory $cookies,
    ) {}

    public function attachTo(
        RedirectResponse $response,
        Request $request,
        string $accessToken,
        string $refreshToken,
        int $refreshExpiresIn,
    ): RedirectResponse {
        foreach ($this->tokenPair($request, $accessToken, $refreshToken, $refreshExpiresIn) as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    public function queueTokenPair(
        Request $request,
        string $accessToken,
        string $refreshToken,
        int $refreshExpiresIn,
    ): void {
        foreach ($this->tokenPair($request, $accessToken, $refreshToken, $refreshExpiresIn) as $cookie) {
            $this->cookies->queue($cookie);
        }
    }

    public function expireOn(RedirectResponse $response): RedirectResponse
    {
        foreach ($this->expiredPair() as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    public function queueExpiredPair(): void
    {
        foreach ($this->expiredPair() as $cookie) {
            $this->cookies->queue($cookie);
        }
    }

    /**
     * @return array{Cookie, Cookie}
     */
    private function tokenPair(
        Request $request,
        string $accessToken,
        string $refreshToken,
        int $refreshExpiresIn,
    ): array {
        if ($accessToken === '' || $refreshToken === '' || $refreshExpiresIn <= 0) {
            throw new \InvalidArgumentException('A complete accessible session token pair is required.');
        }

        $secure = app()->environment('production') || $request->isSecure();
        $refreshMinutes = max(1, (int) ceil($refreshExpiresIn / 60));

        return [
            $this->cookies->make(
                self::ACCESS_COOKIE,
                $accessToken,
                self::ACCESS_MINUTES,
                '/',
                null,
                $secure,
                true,
                false,
                'Lax',
            ),
            $this->cookies->make(
                self::REFRESH_COOKIE,
                $refreshToken,
                $refreshMinutes,
                '/',
                null,
                $secure,
                true,
                false,
                'Lax',
            ),
        ];
    }

    /**
     * @return array{Cookie, Cookie}
     */
    private function expiredPair(): array
    {
        return [
            $this->cookies->forget(self::ACCESS_COOKIE, '/'),
            $this->cookies->forget(self::REFRESH_COOKIE, '/'),
        ];
    }
}
