<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederationJwtService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationJwtService::generateToken().
     */
    public function generateToken(string $platformId, string $userId, int $tenantId, array $scopes = [], int $lifetime = self::DEFAULT_TOKEN_LIFETIME): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy FederationJwtService::validateToken().
     */
    public function validateToken(string $token): ?array
    {
        return static::validateTokenStatic($token);
    }

    /**
     * Static proxy for validateToken — used by middleware that cannot inject an instance.
     */
    public static function validateTokenStatic(string $token): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy FederationJwtService::handleTokenRequest().
     */
    public function handleTokenRequest(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
