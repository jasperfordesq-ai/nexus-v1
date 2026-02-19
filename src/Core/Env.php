<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

class Env
{
    private static $path;

    public static function load($path)
    {
        if (!file_exists($path)) {
            return;
        }

        self::$path = $path;
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

    public static function get($key, $default = null)
    {
        // Check multiple sources in order of priority
        // 1. Check $_ENV (most reliable across platforms)
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        // 2. Check $_SERVER (often populated by web servers)
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        // 3. Check getenv() (system environment variables)
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }
}
