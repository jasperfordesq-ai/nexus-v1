<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \App\Providers\AppServiceProvider::class,
        \App\Providers\RouteServiceProvider::class,
        \App\Providers\EventServiceProvider::class,
        \App\Providers\BroadcastServiceProvider::class,
    ])
    ->withCommands([
        __DIR__ . '/../app/Console/Commands',
    ])
    ->withSchedule(function (Schedule $schedule) {
        // CronJobRunner::runAll() has internal time-checking logic that determines
        // which tasks to execute based on current minute/hour/day-of-week.
        // We schedule it every minute and let its internal scheduling handle the rest.
        $schedule->call(function () {
            $runner = app(\App\Services\CronJobRunner::class);
            $runner->runAll();
        })
            ->everyMinute()
            ->name('nexus:run-all')
            ->withoutOverlapping(10);
    })
    ->withRouting(
        // Routes loaded by RouteServiceProvider (no /api prefix).
        // Only register the health-check here to avoid double-loading.
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // EnsureCorsHeaders runs as the outermost middleware to guarantee
        // CORS headers on ALL responses, including 401/403 from auth middleware.
        $middleware->prepend(\App\Http\Middleware\EnsureCorsHeaders::class);

        $middleware->api(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\ResolveTenant::class,
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'admin' => \App\Http\Middleware\EnsureIsAdmin::class,
            'super-admin' => \App\Http\Middleware\EnsureIsSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // JSON error responses for API — see App\Exceptions\Handler
        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => [
                    ['code' => 'validation_failed', 'message' => __('api.validation_failed'), 'details' => $e->errors()],
                ],
                'success' => false,
            ], 422, ['API-Version' => '2.0']);
        });

        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json([
                'errors' => [
                    ['code' => 'auth_required', 'message' => __('api.auth_required_detail')],
                ],
                'success' => false,
            ], 401, ['API-Version' => '2.0']);
        });

        $exceptions->renderable(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $model = class_basename($e->getModel());
            return response()->json([
                'errors' => [
                    ['code' => 'not_found', 'message' => __('api.not_found', ['model' => $model])],
                ],
                'success' => false,
            ], 404, ['API-Version' => '2.0']);
        });

        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e) {
            return response()->json([
                'errors' => [
                    ['code' => 'rate_limited', 'message' => __('api.rate_limit_exceeded')],
                ],
                'success' => false,
                'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
            ], 429, ['API-Version' => '2.0']);
        });

        // Sentry integration — report to Sentry in production
        $exceptions->reportable(function (\Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    })
    ->create();

// NOTE: App\Core\Database now delegates directly to DB::connection()->getPdo()
// so no explicit bridge setup is needed.

return $app;
