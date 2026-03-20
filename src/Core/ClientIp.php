<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — forwards all calls to \App\Core\ClientIp which
 * holds the real implementation.
 *
 * This class is kept for backward compatibility: legacy Nexus\ namespace
 * code references it. The public API is identical.
 *
 * @see \App\Core\ClientIp  The authoritative implementation.
 * @deprecated Use \App\Core\ClientIp instead.
 */
class ClientIp
{
    public static function get(): string
    {
        return \App\Core\ClientIp::get();
    }

    public static function clearCache(): void
    {
        \App\Core\ClientIp::clearCache();
    }

    public static function debug(): array
    {
        return \App\Core\ClientIp::debug();
    }
}
