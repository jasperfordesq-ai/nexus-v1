<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'name' => env('APP_NAME', 'Project NEXUS'),
    'version' => env('APP_VERSION', '1.5.4'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'frontend_url' => env('FRONTEND_URL', env('APP_FRONTEND_URL', env('APP_URL', 'http://localhost'))),
    // Deploy-injected build commit (set by bluegreen-deploy.sh). Read via
    // config('app.build_commit') so it survives config:cache — env() returns
    // null once the config cache is built.
    'build_commit' => env('BUILD_COMMIT'),
    // Optional override directory for backup:verify. Read via
    // config('app.backup_verify_dir') for the same config-cache safety.
    'backup_verify_dir' => env('BACKUP_VERIFY_DIR'),
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'key' => env('APP_KEY'),
    'jwt_secret' => env('JWT_SECRET'),
    'cipher' => 'AES-256-CBC',
    'maintenance' => [
        'driver' => 'file',
    ],
];
