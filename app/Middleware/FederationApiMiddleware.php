<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Middleware;

use Nexus\Middleware\FederationApiMiddleware as LegacyFederationApiMiddleware;

/**
 * App-namespace wrapper for Nexus\Middleware\FederationApiMiddleware.
 *
 * Delegates to the legacy implementation. Note: App\Core\FederationApiMiddleware
 * also exists as a direct Laravel implementation — this wrapper is for
 * code that specifically references the Middleware namespace.
 */
class FederationApiMiddleware
{
    public static function authenticate(): bool
    {
        if (!class_exists(LegacyFederationApiMiddleware::class)) { return false; }
        return LegacyFederationApiMiddleware::authenticate();
    }

    public static function getAuthMethod(): ?string
    {
        if (!class_exists(LegacyFederationApiMiddleware::class)) { return null; }
        return LegacyFederationApiMiddleware::getAuthMethod();
    }

    public static function generateSigningSecret(): string
    {
        if (!class_exists(LegacyFederationApiMiddleware::class)) { return bin2hex(random_bytes(32)); }
        return LegacyFederationApiMiddleware::generateSigningSecret();
    }

    public static function generateSignature(string $secret, string $method, string $path, string $timestamp, string $body = ''): string
    {
        if (!class_exists(LegacyFederationApiMiddleware::class)) {
            return hash_hmac('sha256', implode("\n", [strtoupper($method), $path, $timestamp, $body]), $secret);
        }
        return LegacyFederationApiMiddleware::generateSignature($secret, $method, $path, $timestamp, $body);
    }

    public static function getPartner(): ?array
    {
        if (!class_exists(LegacyFederationApiMiddleware::class)) { return null; }
        return LegacyFederationApiMiddleware::getPartner();
    }

    public static function getPartnerTenantId(): ?int
    {
        if (!class_exists(LegacyFederationApiMiddleware::class)) { return null; }
        return LegacyFederationApiMiddleware::getPartnerTenantId();
    }

    public static function hasPermission(string $feature): bool
    {
        if (!class_exists(LegacyFederationApiMiddleware::class)) { return false; }
        return LegacyFederationApiMiddleware::hasPermission($feature);
    }

    public static function requirePermission(string $feature): bool
    {
        if (!class_exists(LegacyFederationApiMiddleware::class)) { return false; }
        return LegacyFederationApiMiddleware::requirePermission($feature);
    }

    public static function sendError(int $statusCode, string $message, string $code): void
    {
        if (!class_exists(LegacyFederationApiMiddleware::class)) { return; }
        LegacyFederationApiMiddleware::sendError($statusCode, $message, $code);
    }

    public static function sendSuccess(array $data, int $statusCode = 200): void
    {
        if (!class_exists(LegacyFederationApiMiddleware::class)) { return; }
        LegacyFederationApiMiddleware::sendSuccess($data, $statusCode);
    }

    public static function sendPaginated(array $items, int $total, int $page, int $perPage): void
    {
        if (!class_exists(LegacyFederationApiMiddleware::class)) { return; }
        LegacyFederationApiMiddleware::sendPaginated($items, $total, $page, $perPage);
    }
}
