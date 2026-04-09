<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The React frontend runs on:
    |   - Production: https://app.project-nexus.ie
    |   - Development: http://localhost:5173
    |
    | The API serves from:
    |   - Production: https://api.project-nexus.ie
    |   - Development: http://localhost:8090
    |
    | ALLOWED_ORIGINS env var is a comma-separated list of additional origins.
    |
    */

    'paths' => ['v2/*', 'api/*', 'sanctum/csrf-cookie', 'broadcasting/auth', 'up'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        // Static production origins (always allowed regardless of env)
        [
            'https://project-nexus.ie',
            'https://www.project-nexus.ie',
            'https://app.project-nexus.ie',
            'https://api.project-nexus.ie',
            'https://hour-timebank.ie',
            'https://www.hour-timebank.ie',
            'https://nexuscivic.ie',
            'https://www.nexuscivic.ie',
            'https://timebank.global',
            'https://www.timebank.global',
            'http://localhost:5173',
            'http://localhost:8090',
            'http://127.0.0.1:5173',
        ],
        // Additional origins from environment (additive)
        array_map('trim', array_filter(
            explode(',', env('CORS_ALLOWED_ORIGINS', env('ALLOWED_ORIGINS', '')))
        ))
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'Authorization', 'X-Requested-With', 'X-XSRF-TOKEN', 'X-Socket-Id', 'X-Timezone', 'X-Locale', 'Cache-Control', 'Pragma'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
