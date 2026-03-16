<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationJwtService — Laravel DI wrapper for legacy \Nexus\Services\FederationJwtService.
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
        return \Nexus\Services\FederationJwtService::generateToken($platformId, $userId, $tenantId, $scopes, $lifetime);
    }

    /**
     * Delegates to legacy FederationJwtService::validateToken().
     */
    public function validateToken(string $token): ?array
    {
        return \Nexus\Services\FederationJwtService::validateToken($token);
    }

    /**
     * Delegates to legacy FederationJwtService::handleTokenRequest().
     */
    public function handleTokenRequest(): array
    {
        return \Nexus\Services\FederationJwtService::handleTokenRequest();
    }
}
