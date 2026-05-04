<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'name' => env('APP_NAME', 'Project NEXUS'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'frontend_url' => env('FRONTEND_URL', env('APP_FRONTEND_URL', env('APP_URL', 'http://localhost'))),
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
