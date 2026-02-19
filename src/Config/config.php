<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Nexus\Core\Env;

// ------------------------------------------------------------------
// ROBUST ENVIRONMENT LOADER
// ------------------------------------------------------------------
// Using nested dirname() for maximum PHP compatibility (Pre-7.0 safe)

$possiblePaths = [
    __DIR__ . '/../../.env',                          // Standard relative path
    dirname(dirname(__DIR__)) . '/.env',              // Nested dirname (Safe)
    $_SERVER['DOCUMENT_ROOT'] . '/.env',              // Httpdocs root
    dirname($_SERVER['DOCUMENT_ROOT']) . '/.env'      // Parent of httpdocs
];

$loaded = false;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        Env::load($path);
        $loaded = true;
        break;
    }
}

// ------------------------------------------------------------------
// CONFIGURATION ARRAY
// ------------------------------------------------------------------

return [
    'db' => [
        'type' => Env::get('DB_TYPE', 'mysql'),
        'host' => Env::get('DB_HOST', 'localhost'),
        'port' => Env::get('DB_PORT', '3306'),
        'name' => Env::get('DB_NAME', 'tenant_broken_'),
        'user' => Env::get('DB_USER', 'root'),
        'pass' => Env::get('DB_PASS', ''),
        'file' => Env::get('DB_FILE', __DIR__ . '/../../database.sqlite'),
    ],
    'app' => [
        'name' => Env::get('APP_NAME', 'Project NEXUS'),
        'url'  => Env::get('APP_URL', 'http://localhost:8000')
    ]
];
