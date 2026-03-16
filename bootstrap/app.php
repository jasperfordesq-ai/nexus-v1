<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Sentry integration will be handled here
    })
    ->create();

// DB Bridge: Share Laravel's PDO connection with the legacy Database class.
// This ensures both frameworks use the same connection pool and transaction state.
$app->booted(function () use ($app) {
    try {
        $pdo = $app->make('db')->connection()->getPdo();
        \Nexus\Core\Database::setLaravelConnection($pdo);
    } catch (\Throwable $e) {
        // If Laravel DB isn't configured yet, legacy Database creates its own connection
        error_log('[Laravel Bridge] DB bridge skipped: ' . $e->getMessage());
    }
});

return $app;
