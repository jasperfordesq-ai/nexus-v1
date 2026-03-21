<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

/**
 * Environment variable access — thin wrapper around Laravel's env() helper.
 *
 * Kept for backward compatibility with legacy code that calls Env::get().
 * New code should use Laravel's env() or config() directly.
 */
class Env
{
    /**
     * Load environment variables from a .env file.
     *
     * In Laravel, Dotenv handles this automatically during bootstrap.
     * This method is a no-op when Laravel is booted; it only does manual
     * loading as a fallback for standalone scripts.
     */
    public static function load(string $path): void
    {
        // If Laravel is booted, Dotenv already loaded .env — nothing to do.
        if (function_exists('app') && app()->bound('config')) {
            return;
        }

        // Fallback for standalone scripts outside Laravel
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
     * Delegates to Laravel's env() helper which handles caching and
     * type casting. Falls back to raw lookups only if Laravel is not booted.
     *
     * @param string $key     Environment variable name
     * @param mixed  $default Default value if not found
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        // Laravel's env() is the canonical source
        if (function_exists('env')) {
            return env($key, $default);
        }

        // Fallback chain for non-Laravel context (standalone scripts)
        return $_ENV[$key] ?? $_SERVER[$key] ?? (($v = getenv($key)) !== false ? $v : $default);
    }
}
