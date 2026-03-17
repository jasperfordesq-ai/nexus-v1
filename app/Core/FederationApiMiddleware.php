<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Nexus\Middleware\FederationApiMiddleware as LegacyFederationApiMiddleware;

/**
 * Thin wrapper delegating all public static calls to \Nexus\Middleware\FederationApiMiddleware.
 */
class FederationApiMiddleware
{
    public static function authenticate(): bool
    {
        return LegacyFederationApiMiddleware::authenticate();
    }

    public static function getAuthMethod(): ?string
    {
        return LegacyFederationApiMiddleware::getAuthMethod();
    }

    public static function generateSigningSecret(): string
    {
        return LegacyFederationApiMiddleware::generateSigningSecret();
    }

    public static function generateSignature(string $secret, string $method, string $path, string $timestamp, string $body = ''): string
    {
        return LegacyFederationApiMiddleware::generateSignature($secret, $method, $path, $timestamp, $body);
    }

    public static function getPartner(): ?array
    {
        return LegacyFederationApiMiddleware::getPartner();
    }

    public static function getPartnerTenantId(): ?int
    {
        return LegacyFederationApiMiddleware::getPartnerTenantId();
    }

    public static function hasPermission(string $feature): bool
    {
        return LegacyFederationApiMiddleware::hasPermission($feature);
    }

    public static function requirePermission(string $feature): bool
    {
        return LegacyFederationApiMiddleware::requirePermission($feature);
    }

    public static function sendError(int $statusCode, string $message, string $code): void
    {
        LegacyFederationApiMiddleware::sendError($statusCode, $message, $code);
    }

    public static function sendSuccess(array $data, int $statusCode = 200): void
    {
        LegacyFederationApiMiddleware::sendSuccess($data, $statusCode);
    }

    public static function sendPaginated(array $items, int $total, int $page, int $perPage): void
    {
        LegacyFederationApiMiddleware::sendPaginated($items, $total, $page, $perPage);
    }
}
