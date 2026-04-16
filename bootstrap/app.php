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

        $schedule->command('federation:sync-partners')
            ->hourly()
            ->withoutOverlapping()
            ->name('federation-sync-partners');

        $schedule->command('federation:purge-external-logs')
            ->daily()
            ->withoutOverlapping()
            ->name('federation-purge-external-logs');

        $schedule->command('federation:expire-cc-validations')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->name('federation-expire-cc-validations');

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

        $schedule->command('listings:process-search-alerts')
            ->hourly()
            ->withoutOverlapping()
            ->name('listings-process-search-alerts');

        // Listings: auto-unfeature listings whose featured_until has passed
        $schedule->call(function () {
            app(\App\Services\ListingFeaturedService::class)->processExpiredFeatured();
        })
            ->hourly()
            ->name('listings:process-expired-featured')
            ->withoutOverlapping(5);

        // Marketplace: expire stale offers (pending/countered past their expires_at)
        $schedule->call(function () {
            \App\Services\MarketplaceOfferService::expireStaleOffers();
        })
            ->hourly()
            ->name('marketplace:expire-stale-offers')
            ->withoutOverlapping(5);

        // Jobs: send interview reminders (24h and 1h before)
        $schedule->call(function () {
            \App\Services\JobInterviewService::sendReminders();
        })
            ->everyFifteenMinutes()
            ->name('jobs:interview-reminders')
            ->withoutOverlapping(10);

        // Marketplace: deactivate expired promotions
        $schedule->call(function () {
            \App\Services\MarketplacePromotionService::deactivateExpired();
        })
            ->hourly()
            ->name('marketplace:deactivate-expired-promotions')
            ->withoutOverlapping(5);

        // Marketplace: auto-release escrow holds past their release_after date
        $schedule->call(function () {
            \App\Services\MarketplaceEscrowService::processAutoReleases();
        })
            ->hourly()
            ->name('marketplace:process-escrow-releases')
            ->withoutOverlapping(5);

        // Marketplace: auto-acknowledge DSA reports older than 24h (MKT6)
        $schedule->call(function () {
            \App\Services\MarketplaceReportService::processUnacknowledged();
        })
            ->hourly()
            ->name('marketplace:process-unacknowledged-reports')
            ->withoutOverlapping(5);

        // Identity: fallback-poll stuck Stripe Identity sessions.
        // Stripe Identity webhooks are unreliable — users who leave the
        // verification page before webhook delivery get stuck in pending
        // forever. This hourly poll catches them.
        $schedule->command('nexus:identity:poll-stuck')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground()
            ->name('identity-poll-stuck');

        // Subscriptions: send 7-day renewal reminder to tenant admins
        $schedule->call(function () {
            \App\Services\StripeSubscriptionService::sendRenewalReminders();
        })
            ->dailyAt('09:00')
            ->name('subscriptions:renewal-reminders')
            ->withoutOverlapping(5);

        // Subscriptions: send trial-ending reminders (7-day and 1-day before expiry)
        $schedule->call(function () {
            \App\Services\StripeSubscriptionService::sendTrialEndingReminders();
        })
            ->dailyAt('10:00')
            ->name('subscriptions:trial-ending-reminders')
            ->withoutOverlapping(5);

        // H6: Prune unbounded logging tables (cron_logs 90d, error_404_log 30d,
        // activity_log 180d, api_logs 30d, federation_api_logs 30d) daily at 03:00.
        $schedule->command('nexus:prune-logs')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->name('nexus-prune-logs');

        // Onboarding nurture sequence — Day 2, Day 5, Day 7 emails to new users
        $schedule->call(function () {
            \App\Services\OnboardingNurtureService::sendDueNurtureEmails();
        })
            ->dailyAt('08:00')
            ->name('onboarding:nurture-sequence')
            ->withoutOverlapping(30);
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
            'federation.api' => \App\Http\Middleware\FederationApiAuth::class,
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
