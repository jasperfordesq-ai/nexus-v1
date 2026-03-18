<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

/**
 * Environment variable access using Laravel's env() helper.
 * Replaces legacy Nexus\Core\Env with direct implementation.
 */
class Env
{
    /**
     * Load environment variables from a .env file.
     *
     * In a full Laravel context this is handled by Dotenv automatically.
     * This method provides backwards compatibility for legacy code paths.
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Remove quotes if present
            if (strlen($value) > 1 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
                $value = substr($value, 1, -1);
            }

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    /**
     * Get an environment variable value.
     *
     * Uses Laravel's env() helper when available, then falls back to
     * checking $_ENV, $_SERVER, and getenv().
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

        // Check $_ENV (most reliable across platforms)
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        // Check $_SERVER (often populated by web servers)
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        // Check getenv() (system environment variables)
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }
}
