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
        // SINGLE source of truth for scheduling — do NOT also define in Kernel.php.
        // CronJobRunner::runAll() has internal time-checking that determines which
        // tasks to execute. We schedule it every minute and let it handle the rest.
        $schedule->call(function () {
            $runner = app(\App\Services\CronJobRunner::class);
            $runner->runAll();
        })
            ->everyMinute()
            ->name('nexus:run-all')
            ->withoutOverlapping(10);

        $schedule->call(function () {
            \App\Services\JobExpiryNotificationService::notifyExpiringSoon();
        })
            ->dailyAt('08:00')
            ->name('job-expiry-notifications')
            ->withoutOverlapping();

        $schedule->command('safeguarding:clear-expired-monitoring')
            ->daily()
            ->withoutOverlapping()
            ->name('safeguarding-clear-expired-monitoring');

        $schedule->command('safeguarding:purge-message-copies')
            ->weekly()
            ->withoutOverlapping()
            ->name('safeguarding-purge-message-copies');

        $schedule->command('federation:purge-external-logs')
            ->daily()
            ->withoutOverlapping()
            ->name('federation-purge-external-logs');

        $schedule->command('sitemap:generate')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->name('sitemap-generate');

        $schedule->call(function () {
            app(\App\Services\FeedService::class)->publishScheduledPosts();
        })
            ->everyMinute()
            ->name('feed:publish-scheduled-posts')
            ->withoutOverlapping(5);
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
            \App\Http\Middleware\SeoRedirectMiddleware::class,
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
