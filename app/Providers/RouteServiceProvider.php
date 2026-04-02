<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * RouteServiceProvider
 *
 * Project NEXUS API routes live at the root (e.g. /v2/...) — there is NO
 * /api prefix.  The legacy PHP router handles anything that Laravel doesn't
 * match, so we register routes without a global prefix.
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically used by authentication, but NEXUS is API-only.
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->ip()
            );
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(30)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        $this->routes(function () {
            // API routes — prefixed with /api (Apache rewrites /api/* → index.php,
            // so REQUEST_URI keeps the /api prefix that Laravel must match)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // HTTP cron endpoint REMOVED (2026-04-02) — email bombing root cause.
            // The /cron/run-all route allowed a second execution path (curl-based cron)
            // that bypassed withoutOverlapping() and caused duplicate newsletter sends.
            // The ONLY cron trigger is now: docker exec nexus-php-app artisan schedule:run
            // (root crontab on the host, every minute).

            // Sitemap endpoints — no /api prefix (crawlers access these directly)
            Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index']);
            Route::get('/sitemap-{slug}.xml', [\App\Http\Controllers\SitemapController::class, 'tenant'])
                ->where('slug', '[a-zA-Z0-9_-]+');

            // Channel authorization routes for broadcasting
            if (file_exists(base_path('routes/channels.php'))) {
                require base_path('routes/channels.php');
            }
        });
    }
}
