<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Nexus\Core\Csrf as LegacyCsrf;

/**
 * App-namespace wrapper for Nexus\Core\Csrf.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Laravel's built-in CSRF middleware.
 */
class Csrf
{
    /**
     * Generate a CSRF token (or return existing one for this session).
     */
    public static function generate(): string
    {
        return LegacyCsrf::generate();
    }

    /**
     * Alias for generate().
     */
    public static function token(): string
    {
        return LegacyCsrf::token();
    }

    /**
     * Verify the CSRF token from the request against the session.
     *
     * @param string|null $token Token to verify (auto-detected from POST/header/JSON if null)
     */
    public static function verify($token = null): bool
    {
        return LegacyCsrf::verify($token);
    }

    /**
     * Verify the token or stop execution with a 403 error.
     * Skips CSRF verification for Bearer token authenticated requests.
     */
    public static function verifyOrDie(): void
    {
        LegacyCsrf::verifyOrDie();
    }

    /**
     * Verify the token or return JSON error for API endpoints.
     * Skips CSRF verification for Bearer token authenticated requests.
     *
     * @return bool True if valid
     */
    public static function verifyOrDieJson(): bool
    {
        return LegacyCsrf::verifyOrDieJson();
    }

    /**
     * Output a hidden CSRF input field.
     */
    public static function input(): string
    {
        return LegacyCsrf::input();
    }

    /**
     * Alias for input().
     */
    public static function field(): string
    {
        return LegacyCsrf::field();
    }
}
