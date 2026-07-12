<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Providers;

use App\Core\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

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
        View::addNamespace('accessible-frontend', base_path('accessible-frontend/views'));

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

        // Bulk data export / import — 1 per minute keyed by authenticated user
        // (falls back to IP for unauthenticated, but all current callers are
        // behind auth:sanctum). This replaces the per-IP `throttle:1,1` that
        // allowed multiple admins behind a shared NAT to starve each other.
        RateLimiter::for('bulk-export', function (Request $request) {
            return Limit::perMinute(1)->by(
                $request->user()?->id ? 'user:' . $request->user()->id : 'ip:' . $request->ip()
            );
        });

        RateLimiter::for('groups-join', static fn (Request $request): array => [
            Limit::perMinute(30)->by(self::groupsRateKey($request, 'join')),
            Limit::perMinute(120)->by(self::groupsActorRateKey($request, 'join')),
        ]);
        RateLimiter::for('groups-invite-read', static fn (Request $request): array => [
            Limit::perMinute(60)->by(self::groupsRateKey($request, 'invite-read')),
            Limit::perMinute(180)->by(self::groupsActorRateKey($request, 'invite-read')),
        ]);
        RateLimiter::for('groups-invite-write', static fn (Request $request): array => [
            Limit::perHour(10)->by(self::groupsRateKey($request, 'invite-write')),
            Limit::perHour(30)->by(self::groupsActorRateKey($request, 'invite-write')),
        ]);
        RateLimiter::for('groups-vote', static fn (Request $request): array => [
            Limit::perMinute(60)->by(self::groupsRateKey($request, 'vote')),
            Limit::perMinute(180)->by(self::groupsActorRateKey($request, 'vote')),
        ]);
        RateLimiter::for('groups-chat-write', static fn (Request $request): array => [
            Limit::perMinute(30)->by(self::groupsRateKey($request, 'chat-write')),
            Limit::perMinute(90)->by(self::groupsActorRateKey($request, 'chat-write')),
        ]);
        RateLimiter::for('groups-upload', static fn (Request $request): array => [
            Limit::perMinute(10)->by(self::groupsRateKey($request, 'upload')),
            Limit::perMinute(30)->by(self::groupsActorRateKey($request, 'upload')),
        ]);
        RateLimiter::for('groups-analytics-read', static fn (Request $request): array => [
            Limit::perMinute(60)->by(self::groupsRateKey($request, 'analytics-read')),
            Limit::perMinute(180)->by(self::groupsActorRateKey($request, 'analytics-read')),
        ]);
        RateLimiter::for('groups-analytics-export', static fn (Request $request): array => [
            Limit::perMinute(5)->by(self::groupsRateKey($request, 'analytics-export')),
            Limit::perMinute(15)->by(self::groupsActorRateKey($request, 'analytics-export')),
        ]);
        RateLimiter::for('groups-export-write', static fn (Request $request): array => [
            Limit::perMinute(5)->by(self::groupsRateKey($request, 'export-write')),
            Limit::perMinute(15)->by(self::groupsActorRateKey($request, 'export-write')),
        ]);
        RateLimiter::for('groups-export-read', static fn (Request $request): array => [
            Limit::perMinute(60)->by(self::groupsRateKey($request, 'export-read')),
            Limit::perMinute(180)->by(self::groupsActorRateKey($request, 'export-read')),
        ]);

        // Podcast audio streaming — generous because seeking fires bursts of
        // HTTP Range requests (each is a fresh request), but bounded so a
        // single client cannot use the media proxy for bandwidth DoS.
        RateLimiter::for('podcast-media', function (Request $request) {
            return Limit::perMinute(180)->by(
                $request->user()?->id ? 'user:' . $request->user()->id : 'ip:' . $request->ip()
            );
        });

        $this->routes(function () {
            // API routes — prefixed with /api (Apache rewrites /api/* → index.php,
            // so REQUEST_URI keeps the /api prefix that Laravel must match)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware([
                // Outermost: post-processes the final response to strip the
                // /{slug}/alpha prefix on custom accessible domains (no-op elsewhere).
                \App\Http\Middleware\StripTenantSlugOnAccessibleDomain::class,
                \App\Http\Middleware\SecurityHeaders::class,
                \App\Http\Middleware\ResolveTenant::class,
                \App\Http\Middleware\CheckMaintenanceMode::class,
                \App\Http\Middleware\SetLocale::class,
                'web',
                // Runs AFTER StartSession so it can read/write the session and the
                // alpha cookie-token member; SetLocale (above) runs too early for both.
                \App\Http\Middleware\AlphaSetLocale::class,
            ])->group(base_path('routes/govuk-alpha.php'));

            // HTTP cron endpoint REMOVED (2026-04-02) — email bombing root cause.
            // The /cron/run-all route allowed a second execution path (curl-based cron)
            // that bypassed withoutOverlapping() and caused duplicate newsletter sends.
            // The ONLY cron trigger is now: docker exec nexus-php-app artisan schedule:run
            // (root crontab on the host, every minute).

            // Sitemap endpoints — no /api prefix (crawlers access these directly)
            // Compatibility for newsletter links generated without the /api
            // prefix. Already-sent emails must keep resolving.
            Route::middleware('api')->group(function () {
                Route::get('/v2/newsletter/unsubscribe', [\App\Http\Controllers\Api\NewsletterController::class, 'unsubscribe'])
                    ->middleware('throttle:30,1');
                Route::post('/v2/newsletter/unsubscribe', [\App\Http\Controllers\Api\NewsletterController::class, 'unsubscribe'])
                    ->middleware('throttle:30,1');
                Route::get('/v2/newsletter/pixel/{token}', [\App\Http\Controllers\Api\NewsletterController::class, 'trackOpen']);
                Route::get('/v2/newsletter/click/{token}', [\App\Http\Controllers\Api\NewsletterController::class, 'trackClick'])
                    ->middleware('throttle:120,1');
            });

            Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index']);
            Route::get('/sitemap-{slug}.xml', [\App\Http\Controllers\SitemapController::class, 'tenant'])
                ->where('slug', '[a-zA-Z0-9_-]+');

            // AI-readable site summaries (https://llmstxt.org/).
            // Each tenant domain gets its own llms.txt and llms-full.txt.
            Route::get('/llms.txt', [\App\Http\Controllers\LlmsController::class, 'index']);
            Route::get('/llms-full.txt', [\App\Http\Controllers\LlmsController::class, 'full']);

            // Channel authorization routes for broadcasting
            if (file_exists(base_path('routes/channels.php'))) {
                require base_path('routes/channels.php');
            }
        });
    }

    private static function groupsRateKey(Request $request, string $family): string
    {
        $groupId = $request->route('id') ?? $request->route('groupId');
        $token = $request->route('token');
        $scope = is_scalar($groupId) && (string) $groupId !== ''
            ? 'group:' . (string) $groupId
            : (is_scalar($token) && (string) $token !== ''
                ? 'token:' . hash('sha256', (string) $token)
                : 'route:' . hash('sha256', $request->path()));

        return self::groupsActorRateKey($request, $family) . ":{$scope}";
    }

    private static function groupsActorRateKey(Request $request, string $family): string
    {
        $tenantId = (int) TenantContext::getId();
        $userId = $request->user()?->getAuthIdentifier();
        $actor = $userId !== null ? 'user:' . $userId : 'ip:' . $request->ip();

        return "groups:{$family}:tenant:{$tenantId}:{$actor}:all";
    }
}
