<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Middleware;

use App\Middleware\FederationApiMiddleware as AppFederationApiMiddleware;

/**
 * Legacy delegate — real implementation is now in App\Middleware\FederationApiMiddleware.
 *
 * @deprecated Use App\Middleware\FederationApiMiddleware directly.
 */
class FederationApiMiddleware
{
    public static function authenticate(): bool
    {
        return AppFederationApiMiddleware::authenticate();
    }

    public static function getAuthMethod(): ?string
    {
        return AppFederationApiMiddleware::getAuthMethod();
    }

    public static function generateSigningSecret(): string
    {
        return AppFederationApiMiddleware::generateSigningSecret();
    }

    public static function generateSignature(string $secret, string $method, string $path, string $timestamp, string $body = ''): string
    {
        return AppFederationApiMiddleware::generateSignature($secret, $method, $path, $timestamp, $body);
    }

    public static function getPartner(): ?array
    {
        return AppFederationApiMiddleware::getPartner();
    }

    public static function getPartnerTenantId(): ?int
    {
        return AppFederationApiMiddleware::getPartnerTenantId();
    }

    public static function hasPermission(string $feature): bool
    {
        return AppFederationApiMiddleware::hasPermission($feature);
    }

    public static function requirePermission(string $feature): bool
    {
        return AppFederationApiMiddleware::requirePermission($feature);
    }

    public static function sendError(int $statusCode, string $message, string $code): void
    {
        AppFederationApiMiddleware::sendError($statusCode, $message, $code);
    }

    public static function sendSuccess(array $data, int $statusCode = 200): void
    {
        AppFederationApiMiddleware::sendSuccess($data, $statusCode);
    }

    public static function sendPaginated(array $items, int $total, int $page, int $perPage): void
    {
        AppFederationApiMiddleware::sendPaginated($items, $total, $page, $perPage);
    }
}
