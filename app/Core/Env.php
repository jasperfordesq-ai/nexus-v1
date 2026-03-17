<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

/**
 * App-namespace wrapper for Nexus\Core\Env.
 *
 * Uses Laravel's env() helper when available, falling back to the legacy
 * Nexus\Core\Env implementation for backwards compatibility.
 */
class Env
{
    /**
     * Load environment variables from a .env file.
     *
     * In a full Laravel context this is handled by Dotenv automatically.
     * This method delegates to the legacy loader for compatibility.
     */
    public static function load(string $path): void
    {
        \Nexus\Core\Env::load($path);
    }

    /**
     * Get an environment variable value.
     *
     * Prefers Laravel's env() helper when available, then falls back to
     * the legacy Nexus\Core\Env::get() implementation.
     *
     * @param string $key     Environment variable name
     * @param mixed  $default Default value if not found
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        // Use Laravel's env() helper if available (it handles caching, type casting, etc.)
        if (function_exists('env')) {
            $value = env($key);
            if ($value !== null) {
                return $value;
            }
        }

        return \Nexus\Core\Env::get($key, $default);
    }
}
