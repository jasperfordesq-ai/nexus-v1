<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — forwards all calls to \App\Core\Auth which
 * holds the real implementation.
 *
 * This class is kept for backward compatibility: legacy Nexus\ namespace
 * code references it. The public API is identical.
 *
 * @see \App\Core\Auth  The authoritative implementation.
 * @deprecated Use \App\Core\Auth instead.
 */
class Auth
{
    public static function user(): ?array
    {
        return \App\Core\Auth::user();
    }

    public static function check(): bool
    {
        return \App\Core\Auth::check();
    }

    public static function id(): ?int
    {
        return \App\Core\Auth::id();
    }

    public static function require(bool $jsonResponse = false): array
    {
        return \App\Core\Auth::require($jsonResponse);
    }

    public static function requireAdmin(bool $jsonResponse = false): array
    {
        return \App\Core\Auth::requireAdmin($jsonResponse);
    }

    public static function isAdmin(?array $user = null): bool
    {
        return \App\Core\Auth::isAdmin($user);
    }

    public static function validateCsrf(?string $token = null): bool
    {
        return \App\Core\Auth::validateCsrf($token);
    }

    public static function logout(): void
    {
        \App\Core\Auth::logout();
    }

    public static function role(): ?string
    {
        return \App\Core\Auth::role();
    }
}
