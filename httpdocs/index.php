<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Project NEXUS — Laravel Entry Point
 *
 * All API traffic is handled by Laravel (routes/api.php).
 * Security headers, CORS, and tenant resolution are Laravel middleware.
 * The legacy PHP framework has been fully migrated.
 */

// ─── Pre-framework maintenance mode (fast, no autoloader needed) ───
if (file_exists(__DIR__ . '/../.maintenance')) {
    $allowedIPs = ['127.0.0.1', '::1'];
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowedIPs)) {
        http_response_code(503);
        header('Retry-After: 300');
        if (file_exists(__DIR__ . '/maintenance.html')) {
            include __DIR__ . '/maintenance.html';
        }
        exit;
    }
}

// ─── Boot Laravel ───
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
