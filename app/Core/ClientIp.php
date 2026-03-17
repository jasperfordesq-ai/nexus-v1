<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Nexus\Core\ClientIp as LegacyClientIp;

/**
 * App-namespace wrapper for Nexus\Core\ClientIp.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Laravel's Request::ip() / TrustedProxy middleware.
 */
class ClientIp
{
    /**
     * Get the real client IP address.
     * Safe to call from anywhere -- result is cached per-request.
     */
    public static function get(): string
    {
        return LegacyClientIp::get();
    }

    /**
     * Clear the cached IP (useful for testing).
     */
    public static function clearCache(): void
    {
        LegacyClientIp::clearCache();
    }

    /**
     * Get all IP-related debug information for the current request.
     * Only call this from admin/debug endpoints -- never expose to public.
     */
    public static function debug(): array
    {
        return LegacyClientIp::debug();
    }
}
