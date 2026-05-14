<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\PrerenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AdminPrerenderController — admin endpoints for the prerender engine.
 *
 * Read endpoints accept any admin. Mutating endpoints (enqueue, purge,
 * cancel, invalidate, auto-recache, detect-drift, purge-unexpected) require
 * a PLATFORM super-admin — the engine operates cross-tenant (a job can
 * target any tenant slug), so a tenant-scoped super-admin must not be able
 * to enqueue work against other tenants' snapshots.
 */
class AdminPrerenderController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(private readonly PrerenderService $service) {}

    /** GET /api/v2/admin/prerender/summary */
    public function summary(): JsonResponse
    {
        $this->requireAdmin();
        return $this->respondWithData($this->service->summary());
    }

    /**
     * GET /api/v2/admin/prerender/inventory?tenant=slug
     *
     * Returns every rendered HTML file in the cache with staleness flags.
     * Optional ?tenant=slug filters to a single host.
     */
    public function inventory(Request $r): JsonResponse
    {
        $this->requireAdmin();
        $tenantSlug = $r->query('tenant');
        if (is_string($tenantSlug)) {
            $tenantSlug = trim($tenantSlug);
            if (!preg_match('/^[A-Za-z0-9_-]{0,64}$/', $tenantSlug)) {
                return $this->error('Invalid tenant slug', 400, 'VALIDATION_INVALID');
            }
        } else {
            $tenantSlug = null;
        }
        return $this->respondWithData([
            'cache_readable' => $this->service->cacheReadable(),
            'cache_path'     => $this->service->cachePath(),
            'items'          => $this->service->inventory($tenantSlug),
        ]);
    }

    /**
     * GET /api/v2/admin/prerender/inspect?path=host/route/index.html
     *
     * Deep-parse a single snapshot — used by the inventory drawer.
     */
    public function inspect(Request $r): JsonResponse
    {
        $this->requireAdmin();
        $path = (string) $r->query('path', '');
        if ($path === '') {
            return $this->error('Missing path', 400, 'VALIDATION_REQUIRED_FIELD');
        }
        $data = $this->service->inspect($path);
        if ($data === null) {
            return $this->error('Snapshot not found', 404, 'NOT_FOUND');
        }
        return $this->respondWithData($data);
    }

    /** GET /api/v2/admin/prerender/coverage */
    public function coverage(): JsonResponse
    {
        $this->requireAdmin();
        return $this->respondWithData([
            'expected_routes' => PrerenderService::EXPECTED_ROUTES,
            'rows'            => $this->service->coverage(),
        ]);
    }

    /** GET /api/v2/admin/prerender/events?limit=200 */
    public function events(Request $r): JsonResponse
    {
        $this->requireAdmin();
        $limit = (int) $r->query('limit', 200);
        return $this->respondWithData([
            'events' => $this->service->tailEvents($limit),
        ]);
    }

    /** GET /api/v2/admin/prerender/failures */
    public function failures(): JsonResponse
    {
        $this->requireAdmin();
        return $this->respondWithData([
            'items' => $this->service->readFailures(),
        ]);
    }

    /** GET /api/v2/admin/prerender/jobs?status=&limit= */
    public function jobs(Request $r): JsonResponse
    {
        $this->requireAdmin();
        $status = $r->query('status');
        $limit = (int) $r->query('limit', 50);
        return $this->respondWithData([
            'items' => $this->service->listJobs($limit, is_string($status) ? $status : null),
        ]);
    }

    /** GET /api/v2/admin/prerender/jobs/{id} */
    public function showJob(int $id): JsonResponse
    {
        $this->requireAdmin();
        $row = $this->service->getJob($id);
        if (!$row) return $this->error('Job not found', 404, 'NOT_FOUND');
        return $this->respondWithData($row);
    }

    /**
     * POST /api/v2/admin/prerender/jobs
     *
     * Body:
     *   tenant_slug?: string  — limit to a single tenant
     *   routes?:      string  — comma-separated, e.g. "/about,/blog"
     *   force?:       bool    — render even if up-to-date
     *   dry_run?:     bool    — plan only, no Playwright run
     *
     * Requires super_admin because force-refreshes affect every tenant's
     * snapshot and can take many minutes of host CPU.
     */
    public function enqueue(Request $r): JsonResponse
    {
        $userId = $this->requirePlatformSuperAdmin();
        if (!$this->checkActionRate($userId, 'enqueue', 30, 60)) {
            return $this->error('Too many enqueue requests — slow down', 429, 'RATE_LIMITED');
        }

        $payload = $r->json()->all();
        $tenantSlug = $payload['tenant_slug'] ?? null;
        $routes = $payload['routes'] ?? null;
        $force = (bool) ($payload['force'] ?? false);
        $dryRun = (bool) ($payload['dry_run'] ?? false);
        $priority = isset($payload['priority']) ? max(1, min(9, (int) $payload['priority'])) : PrerenderService::PRIORITY_NORMAL;

        $tenantId = null;
        if (is_string($tenantSlug) && $tenantSlug !== '') {
            if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $tenantSlug)) {
                return $this->error('Invalid tenant slug', 400, 'VALIDATION_INVALID');
            }
            $row = \DB::table('tenants')
                ->where('slug', $tenantSlug)
                ->where('is_active', 1)
                ->first();
            if (!$row) return $this->error('Tenant not found', 404, 'NOT_FOUND');
            $tenantId = (int) $row->id;
        }

        $routesValue = null;
        if (is_string($routes) && trim($routes) !== '') {
            $tokens = array_filter(array_map('trim', explode(',', $routes)));
            foreach ($tokens as $tok) {
                if (!preg_match('#^/[A-Za-z0-9._~/%:@!$()*+,;=\-]*$#', $tok)) {
                    return $this->error(
                        "Invalid route: $tok",
                        400,
                        'VALIDATION_INVALID'
                    );
                }
            }
            $routesValue = implode(',', $tokens);
        }

        $jobId = $this->service->enqueueJob(
            $tenantId,
            $routesValue,
            $force,
            $dryRun,
            $userId,
            $priority
        );

        $this->service->audit(
            'enqueue', $userId, $tenantId, $jobId, 'ok',
            ['routes' => $routesValue, 'force' => $force, 'dry_run' => $dryRun, 'priority' => $priority],
            $r->ip(), substr((string) $r->userAgent(), 0, 255)
        );

        return $this->respondWithData([
            'job_id' => $jobId,
            'job'    => $this->service->getJob($jobId),
        ]);
    }

    /**
     * POST /api/v2/admin/prerender/purge
     *
     * Body:
     *   pattern:      string  — required, glob like "/blog/*" or "/listings/**"
     *   tenant_slug?: string  — limit to a single tenant
     *   dry_run?:     bool    — return matches without deleting
     *   recache?:     bool    — also enqueue a low-priority recache job
     *
     * Requires super_admin: a poorly-chosen pattern can blow away the whole
     * cache, which is fine operationally (snapshots regenerate) but expensive.
     */
    public function purge(Request $r): JsonResponse
    {
        $userId = $this->requirePlatformSuperAdmin();
        if (!$this->checkActionRate($userId, 'purge', 10, 60)) {
            return $this->error('Too many purge requests', 429, 'RATE_LIMITED');
        }

        $payload = $r->json()->all();
        $pattern = trim((string) ($payload['pattern'] ?? ''));
        if ($pattern === '' || $pattern[0] !== '/') {
            return $this->error('Pattern must start with "/"', 400, 'VALIDATION_INVALID');
        }
        if (!preg_match('#^/[A-Za-z0-9._~/%:@!$()+,;=\-\*\?]*$#', $pattern)) {
            return $this->error('Invalid pattern characters', 400, 'VALIDATION_INVALID');
        }

        $hostFilter = null;
        $tenantSlug = $payload['tenant_slug'] ?? null;
        $tenantId = null;
        if (is_string($tenantSlug) && $tenantSlug !== '') {
            if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $tenantSlug)) {
                return $this->error('Invalid tenant slug', 400, 'VALIDATION_INVALID');
            }
            $row = \DB::table('tenants')->where('slug', $tenantSlug)->where('is_active', 1)->first();
            if (!$row) return $this->error('Tenant not found', 404, 'NOT_FOUND');
            $tenantId = (int) $row->id;
            // Resolve host so purgePattern can filter on it.
            $domain = trim((string) ($row->domain ?? ''));
            $appHost = parse_url((string) env('FRONTEND_URL', 'https://app.project-nexus.ie'), PHP_URL_HOST)
                       ?: 'app.project-nexus.ie';
            $hostFilter = $domain !== '' ? $domain : $appHost;
        }

        $dryRun = (bool) ($payload['dry_run'] ?? false);
        $recache = (bool) ($payload['recache'] ?? false);

        $result = $this->service->purgePattern($pattern, $hostFilter, $dryRun);

        $jobId = null;
        if ($recache && !$dryRun && !empty($result['deleted'])) {
            // Enqueue a low-priority recache. We can't pass a glob to
            // prerender-tenants.sh; the worker discovers missing snapshots
            // on the next pass, so a force-render of the same tenant scope
            // is the broadest sensible action.
            $jobId = $this->service->enqueueJob(
                $tenantId,
                null,
                false,
                false,
                $userId,
                PrerenderService::PRIORITY_LOW
            );
        }

        $this->service->audit(
            'purge', $userId, $tenantId, $jobId, 'ok',
            ['pattern' => $pattern, 'dry_run' => $dryRun, 'deleted_count' => count($result['deleted']), 'recache' => $recache],
            $r->ip(), substr((string) $r->userAgent(), 0, 255)
        );

        return $this->respondWithData([
            'pattern'      => $pattern,
            'tenant_slug'  => $tenantSlug,
            'dry_run'      => $dryRun,
            'deleted_count'=> count($result['deleted']),
            'deleted'      => array_slice($result['deleted'], 0, 500),
            'recache_job_id' => $jobId,
        ]);
    }

    /** POST /api/v2/admin/prerender/jobs/{id}/cancel */
    public function cancelJob(Request $r, int $id): JsonResponse
    {
        $userId = $this->requirePlatformSuperAdmin();
        $ok = $this->service->cancelJob($id);
        if (!$ok) {
            return $this->error('Job is not cancellable (already claimed or finished)', 409, 'CONFLICT');
        }
        $this->service->audit(
            'cancel', $userId, null, $id, 'ok',
            null, $r->ip(), substr((string) $r->userAgent(), 0, 255)
        );
        return $this->respondWithData(['cancelled' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/prerender/invalidate
     *
     * External-system invalidation hook. Lets a headless CMS / marketing
     * automation / ops tool flag specific routes as stale without going
     * through the model layer.
     *
     * Authentication options (in order of preference):
     *   1. Bearer token = config('prerender.webhook_token')   (preferred)
     *   2. X-Nexus-Signature: hex-HMAC-SHA256 of "<timestamp>.<raw body>" with that token,
     *      where the timestamp is the value of the X-Nexus-Timestamp header (Unix seconds).
     *      Requests with a timestamp older than ±5 minutes are rejected (replay protection).
     *   3. Admin session (platform super-admin)                 (fallback for ops UI)
     *
     * Body:
     *   tenant_id:    int       — required
     *   routes:       string[]  — required, tenant-local paths ("/blog/foo")
     *   recache:      bool      — also enqueue a NORMAL-priority recache job (default true)
     *
     * Returns: { invalidated: N, job_id: id|null }
     */
    public function invalidate(Request $r): JsonResponse
    {
        $token = (string) config('prerender.webhook_token', '');
        $authed = false;

        if ($token !== '') {
            $bearer = (string) $r->bearerToken();
            if ($bearer !== '' && hash_equals($token, $bearer)) {
                $authed = true;
            } else {
                $sig = (string) $r->header('X-Nexus-Signature', '');
                $tsHeader = (string) $r->header('X-Nexus-Timestamp', '');
                if ($sig !== '' && $tsHeader !== '' && ctype_digit($tsHeader)) {
                    $ts = (int) $tsHeader;
                    $skew = abs(time() - $ts);
                    if ($skew <= 300) {
                        // Signed payload is "<timestamp>.<raw body>" so a
                        // captured (timestamp, body, signature) tuple is only
                        // usable inside the 5-minute window.
                        $expected = hash_hmac('sha256', $ts . '.' . $r->getContent(), $token);
                        if (hash_equals($expected, $sig)) $authed = true;
                    }
                }
            }
        }

        if (!$authed) {
            // Fall back to admin-session auth so the admin UI can call this
            // directly without needing the shared secret.
            try {
                $this->requirePlatformSuperAdmin();
                $authed = true;
            } catch (\Throwable $e) {
                return $this->error('Unauthorized', 401, 'UNAUTHENTICATED');
            }
        }

        $payload = $r->json()->all();
        $tenantId = isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : 0;
        if ($tenantId <= 0) {
            return $this->error('tenant_id is required', 400, 'VALIDATION_REQUIRED_FIELD');
        }
        $routes = $payload['routes'] ?? [];
        if (!is_array($routes) || empty($routes)) {
            return $this->error('routes[] is required and must be non-empty', 400, 'VALIDATION_REQUIRED_FIELD');
        }
        // Same regex the rest of the engine uses.
        foreach ($routes as $r2) {
            if (!is_string($r2) || !preg_match('#^/[A-Za-z0-9._~/%:@!$()*+,;=\-]*$#', $r2)) {
                return $this->error("Invalid route: " . (is_string($r2) ? $r2 : '(non-string)'), 400, 'VALIDATION_INVALID');
            }
        }
        $recache = (bool) ($payload['recache'] ?? true);

        $count = $this->service->invalidateRoutes($tenantId, $routes, $recache);
        $jobId = null;
        if ($recache && $count > 0) {
            // invalidateRoutes already enqueues; surface the job id by reading
            // the most recent matching queued row.
            $row = \DB::table('prerender_jobs')
                ->where('tenant_id', $tenantId)
                ->where('status', 'queued')
                ->orderByDesc('id')
                ->first();
            $jobId = $row ? (int) $row->id : null;
        }

        $this->service->audit(
            'invalidate', null, $tenantId, $jobId, 'ok',
            ['routes' => $routes, 'invalidated' => $count, 'recache' => $recache],
            $r->ip(), substr((string) $r->userAgent(), 0, 255)
        );

        return $this->respondWithData([
            'invalidated' => $count,
            'tenant_id'   => $tenantId,
            'routes'      => $routes,
            'job_id'      => $jobId,
        ]);
    }

    /**
     * GET /api/v2/admin/prerender/analytics?since=ISO8601&limit=200
     *
     * Aggregates the bot-only JSONL access log nginx writes for every search
     * engine / social / AI crawler hit. Returns hits by status, crawler, host,
     * top URIs, and the most recent rows. Default window is 7 days.
     */
    public function analytics(Request $r): JsonResponse
    {
        $this->requireAdmin();
        $since = $r->query('since');
        $limit = (int) $r->query('limit', 200);
        $limit = max(10, min(1000, $limit));
        return $this->respondWithData(
            $this->service->crawlerAnalytics(is_string($since) ? $since : null, $limit)
        );
    }

    /**
     * POST /api/v2/admin/prerender/auto-recache
     *
     * Trigger one immediate auto-recache pass. Same logic as the cron, but
     * exposed for manual operator control. Always dry-runs unless `apply=1`.
     */
    public function autoRecache(Request $r): JsonResponse
    {
        $userId = $this->requirePlatformSuperAdmin();
        if (!$this->checkActionRate($userId, 'auto_recache', 5, 60)) {
            return $this->error('Too many requests', 429, 'RATE_LIMITED');
        }
        $apply = (bool) $r->json('apply', false);
        $exit = \Artisan::call('prerender:auto-recache', $apply ? [] : ['--dry-run' => true]);
        $this->service->audit('auto_recache', $userId, null, null, 'ok', ['applied' => $apply, 'exit_code' => $exit]);
        return $this->respondWithData([
            'exit_code' => $exit,
            'output'    => \Artisan::output(),
            'applied'   => $apply,
        ]);
    }

    /**
     * POST /api/v2/admin/prerender/detect-drift
     *
     * Trigger an immediate sitemap-drift detection pass. Dry-run by default;
     * pass `apply: true` to actually enqueue recache jobs. Same logic as the
     * 2-minute cron, exposed for operators investigating a stale-page report.
     */
    public function detectDrift(Request $r): JsonResponse
    {
        $userId = $this->requirePlatformSuperAdmin();
        if (!$this->checkActionRate($userId, 'detect_drift', 5, 60)) {
            return $this->error('Too many requests', 429, 'RATE_LIMITED');
        }
        $apply = (bool) $r->json('apply', false);
        $exit = \Artisan::call('prerender:detect-drift', $apply ? [] : ['--dry-run' => true]);
        $this->service->audit('detect_drift', $userId, null, null, 'ok', ['applied' => $apply, 'exit_code' => $exit]);
        return $this->respondWithData([
            'exit_code' => $exit,
            'output'    => \Artisan::output(),
            'applied'   => $apply,
        ]);
    }

    /**
     * POST /api/v2/admin/prerender/purge-unexpected
     *
     * Sweep the snapshot cache for routes that aren't in any tenant's current
     * expected set (e.g. /jobs snapshots for a tenant with job_vacancies off).
     * Common after toggling a feature off, or to clean up the inventory after
     * the engine went tenant-aware (this fix's first run).
     *
     * Body:
     *   apply: bool  — false = dry run (returns what would be deleted)
     *
     * Returns: { deleted_total, by_tenant: { slug: [routes] }, dry_run }
     */
    public function purgeUnexpected(Request $r): JsonResponse
    {
        $userId = $this->requirePlatformSuperAdmin();
        if (!$this->checkActionRate($userId, 'purge_unexpected', 5, 60)) {
            return $this->error('Too many requests', 429, 'RATE_LIMITED');
        }
        $apply = (bool) $r->json('apply', false);
        $result = $this->service->purgeUnexpectedSnapshots(!$apply);
        $this->service->audit(
            'purge_unexpected', $userId, null, null, 'ok',
            ['applied' => $apply, 'deleted_total' => $result['deleted_total'] ?? 0],
            $r->ip(), substr((string) $r->userAgent(), 0, 255)
        );
        return $this->respondWithData($result);
    }

    /**
     * GET /api/v2/admin/prerender/metrics
     *
     * Prometheus-format text. Add to your scrape config as:
     *   - job_name: nexus-prerender
     *     metrics_path: /api/v2/admin/prerender/metrics
     *     bearer_token: <admin token>
     */
    public function metrics(): \Symfony\Component\HttpFoundation\Response
    {
        $this->requireAdmin();
        $body = $this->service->prometheusMetrics();
        return response($body, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * GET /api/v2/admin/prerender/realtime-channel
     *
     * Returns the channel + event the UI should subscribe to for live job
     * updates. Decouples the React client from the channel name.
     */
    public function realtimeChannel(): JsonResponse
    {
        $this->requireAdmin();
        return $this->respondWithData([
            'channel' => \App\Services\PrerenderService::REALTIME_CHANNEL,
            'event'   => \App\Services\PrerenderService::REALTIME_EVENT,
        ]);
    }

    // -------------------------------------------------------------------------
    // Round 2 — health, audit, breaker, emergency reset
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/admin/prerender/health
     *
     * Traffic-light JSON: status (green|yellow|red) plus a list of per-check
     * details with actionable suggestions. Designed to be scraped by uptime
     * monitors or rendered in the admin UI banner.
     */
    public function health(): JsonResponse
    {
        $this->requireAdmin();
        $data = $this->service->health();
        // HTTP 200 even on red — uptime monitors decide what to alert on by
        // reading the body's status field. A 5xx would mask the engine being
        // up but the queue being jammed.
        return $this->respondWithData($data);
    }

    /**
     * GET /api/v2/admin/prerender/audit?action=&limit=
     *
     * Recent audit log entries. Read-only for any admin.
     */
    public function auditLog(Request $r): JsonResponse
    {
        $this->requireAdmin();
        $action = $r->query('action');
        $limit  = (int) $r->query('limit', 100);
        return $this->respondWithData([
            'items' => $this->service->recentAudit($limit, is_string($action) ? $action : null),
        ]);
    }

    /**
     * POST /api/v2/admin/prerender/reset-breaker
     *
     * Manually close the circuit breaker. The audit row records who did it
     * and when; the operator should have investigated the failure trigger
     * before resetting.
     */
    public function resetBreaker(Request $r): JsonResponse
    {
        $userId = $this->requirePlatformSuperAdmin();
        if (!$this->checkActionRate($userId, 'reset_breaker', 5, 60)) {
            return $this->error('Too many requests', 429, 'RATE_LIMITED');
        }
        $wasTripped = $this->service->breakerTrippedUntil();
        $this->service->resetBreaker();
        $this->service->audit(
            'reset_breaker', $userId, null, null, 'ok',
            ['was_tripped_until' => $wasTripped],
            $r->ip(), substr((string) $r->userAgent(), 0, 255)
        );
        return $this->respondWithData(['ok' => true, 'was_tripped_until' => $wasTripped]);
    }

    /**
     * POST /api/v2/admin/prerender/reset-queue
     *
     * Emergency: requeue every claimed/running row whose worker likely died,
     * AND clear the breaker. Equivalent to running reap-stale --requeue and
     * reset-breaker in one click. Rate-limited because it's destructive
     * if used while a healthy worker is actually working.
     */
    public function resetQueue(Request $r): JsonResponse
    {
        $userId = $this->requirePlatformSuperAdmin();
        if (!$this->checkActionRate($userId, 'reset_queue', 2, 300)) {
            return $this->error('Too many requests — wait a few minutes', 429, 'RATE_LIMITED');
        }

        $now = date('Y-m-d H:i:s');
        // Anything older than 30 min in claimed/running is fair game.
        $cutoff = date('Y-m-d H:i:s', time() - 1800);
        $reset = \DB::table('prerender_jobs')
            ->whereIn('status', ['claimed', 'running'])
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('claimed_at')->orWhere('claimed_at', '<', $cutoff);
            })
            ->update([
                'status'        => 'queued',
                'claimed_at'    => null,
                'claimed_by'    => null,
                'started_at'    => null,
                'error_message' => 'reset by admin via /reset-queue',
                'updated_at'    => $now,
            ]);

        $this->service->resetBreaker();

        $this->service->audit(
            'reset_queue', $userId, null, null, 'ok',
            ['rows_reset' => $reset],
            $r->ip(), substr((string) $r->userAgent(), 0, 255)
        );

        return $this->respondWithData([
            'rows_reset'      => $reset,
            'breaker_cleared' => true,
        ]);
    }

    /**
     * Per-user per-action rate limit using the cache. Returns true if the
     * action is allowed, false if the caller has exceeded $limit attempts
     * in $windowSeconds. Hard-coded keys; no need for the cluster-wide
     * RateLimiter facade abstraction.
     */
    private function checkActionRate(int $userId, string $action, int $limit, int $windowSeconds): bool
    {
        $key = "prerender:rate:{$action}:{$userId}";
        $count = (int) \Illuminate\Support\Facades\Cache::increment($key);
        if ($count === 1) {
            \Illuminate\Support\Facades\Cache::put($key, 1, $windowSeconds);
        }
        if ($count > $limit) {
            $this->service->audit(
                $action, $userId, null, null, 'denied',
                ['reason' => 'rate_limit_exceeded', 'count' => $count, 'limit' => $limit]
            );
            return false;
        }
        return true;
    }
}
