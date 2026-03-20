<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — forwards all calls to \App\Core\Env which
 * holds the real implementation.
 *
 * This class is kept for backward compatibility: legacy Nexus\ namespace
 * code references it. The public API is identical.
 *
 * @see \App\Core\Env  The authoritative implementation.
 * @deprecated Use \App\Core\Env instead.
 */
class Env
{
    public static function load($path): void
    {
        \App\Core\Env::load($path);
    }

    public static function get($key, $default = null)
    {
        return \App\Core\Env::get($key, $default);
    }
}
