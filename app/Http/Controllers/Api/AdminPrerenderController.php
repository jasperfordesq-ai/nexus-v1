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
                        if (hash_equals($expected, $sig)) {
                            // One-time-use nonce: even within the 5-minute
                            // window, the same signature can't be replayed.
                            // Key TTL = 2× max skew so an attacker can't
                            // simply wait for it to expire.
                            $nonceKey = 'prerender:webhook:nonce:' . hash('sha256', $ts . ':' . $sig);
                            $fresh = \Illuminate\Support\Facades\Cache::add($nonceKey, 1, 600);
                            if ($fresh) {
                                $authed = true;
                            } else {
                                // Replay detected. Don't tell the caller why
                                // — just bounce — but audit it so we have a
                                // forensic record.
                                $this->service->audit(
                                    'invalidate', null, null, null, 'denied',
                                    ['reason' => 'webhook_replay', 'ts' => $ts],
                                    $r->ip(), substr((string) $r->userAgent(), 0, 255)
                                );
                            }
                        }
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
     * GET /api/v2/admin/prerender/export/{kind}.csv
     *
     * Streamed CSV export. `kind` ∈ { audit, inventory, jobs }. No new logic —
     * just calls the read methods and emits CSV. Big-result-set safe because
     * we cap each kind at 5,000 rows; if you need more, use the JSON API
     * with paging.
     */
    public function exportCsv(Request $r, string $kind): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->requireAdmin();
        $kind = strtolower($kind);
        if (!in_array($kind, ['audit', 'inventory', 'jobs'], true)) {
            abort(404, 'Unknown export kind');
        }

        $filename = sprintf('prerender-%s-%s.csv', $kind, date('Ymd-His'));
        $headers = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ];

        return response()->stream(function () use ($kind, $r) {
            $out = fopen('php://output', 'w');

            if ($kind === 'audit') {
                fputcsv($out, ['id', 'created_at', 'action', 'outcome', 'actor_email', 'tenant_slug', 'job_id', 'ip', 'details']);
                foreach ($this->service->recentAudit(5000, $r->query('action')) as $row) {
                    fputcsv($out, [
                        $row['id'] ?? '',
                        $row['created_at'] ?? '',
                        $row['action'] ?? '',
                        $row['outcome'] ?? '',
                        $row['actor_email'] ?? '',
                        $row['tenant_slug'] ?? '',
                        $row['job_id'] ?? '',
                        $row['ip'] ?? '',
                        is_array($row['details'] ?? null) ? json_encode($row['details']) : '',
                    ]);
                }
            } elseif ($kind === 'inventory') {
                fputcsv($out, ['host', 'route', 'cache_path', 'size_bytes', 'mtime', 'age_s', 'staleness', 'http_status', 'content_stale', 'asset_issues']);
                $items = $this->service->inventory($r->query('tenant'));
                foreach (array_slice($items, 0, 5000) as $row) {
                    if (!empty($row['__truncated'])) continue;
                    fputcsv($out, [
                        $row['host'] ?? '',
                        $row['route'] ?? '',
                        $row['cache_path'] ?? '',
                        $row['size_bytes'] ?? '',
                        $row['mtime'] ?? '',
                        $row['age_s'] ?? '',
                        $row['staleness'] ?? '',
                        $row['http_status'] ?? '',
                        ($row['content_stale'] ?? false) ? '1' : '0',
                        is_array($row['asset_issues'] ?? null) ? implode('|', $row['asset_issues']) : '',
                    ]);
                }
            } else { // jobs
                fputcsv($out, ['id', 'status', 'priority', 'tenant_slug', 'routes', 'force', 'dry_run', 'queued_at', 'started_at', 'finished_at', 'duration_s', 'exit_code', 'rendered_count', 'planned_count', 'requested_by']);
                $rows = $this->service->listJobs(5000, $r->query('status'));
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row['id'] ?? '',
                        $row['status'] ?? '',
                        $row['priority'] ?? '',
                        $row['tenant_slug'] ?? '',
                        $row['routes'] ?? '',
                        ($row['force'] ?? false) ? '1' : '0',
                        ($row['dry_run'] ?? false) ? '1' : '0',
                        $row['queued_at'] ?? '',
                        $row['started_at'] ?? '',
                        $row['finished_at'] ?? '',
                        $row['duration_s'] ?? '',
                        $row['exit_code'] ?? '',
                        $row['rendered_count'] ?? '',
                        $row['planned_count'] ?? '',
                        is_array($row['requested_by'] ?? null) ? ($row['requested_by']['email'] ?? '') : '',
                    ]);
                }
            }

            fclose($out);
        }, 200, $headers);
    }

    /**
     * GET /api/v2/admin/prerender/ttl-inspector?route=/blog/foo
     *
     * Returns which config/prerender.php pattern matches the given route and
     * what TTL it gets. Lets operators see the freshness policy at a glance
     * without grepping config.
     */
    /**
     * POST /api/v2/admin/prerender/jobs/{id}/retry
     *
     * "Try again" for a finished job: clone its parameters into a new queued
     * row. The original is left alone (history preserved). The new row gets
     * a fresh audit trail.
     */
    public function retryJob(Request $r, int $id): JsonResponse
    {
        $userId = $this->requirePlatformSuperAdmin();
        if (!$this->checkActionRate($userId, 'retry', 30, 60)) {
            return $this->error('Too many retries', 429, 'RATE_LIMITED');
        }

        $original = $this->service->getJob($id);
        if (!$original) return $this->error('Job not found', 404, 'NOT_FOUND');
        if (in_array($original['status'] ?? '', ['queued', 'claimed', 'running'], true)) {
            return $this->error('Job is still in flight — cancel it before retrying', 409, 'CONFLICT');
        }

        $newId = $this->service->enqueueJob(
            $original['tenant_id'] ?? null,
            $original['routes'] ?? null,
            (bool) ($original['force'] ?? false),
            (bool) ($original['dry_run'] ?? false),
            $userId,
            \App\Services\PrerenderService::PRIORITY_NORMAL
        );

        $this->service->audit(
            'retry', $userId, $original['tenant_id'] ?? null, $newId, 'ok',
            ['retried_from_job_id' => $id, 'original_status' => $original['status'] ?? null],
            $r->ip(), substr((string) $r->userAgent(), 0, 255)
        );

        return $this->respondWithData([
            'job_id'             => $newId,
            'retried_from_job_id'=> $id,
            'job'                => $this->service->getJob($newId),
        ]);
    }

    /**
     * GET /api/v2/admin/prerender/sitemap-explorer?tenant=slug
     *
     * Returns the full list of routes the engine expects to render for a
     * tenant — static floor (feature/module gated) plus dynamic routes from
     * SitemapService. Lets operators answer "what does the engine think this
     * tenant has?" without grepping logs or running the artisan command.
     */
    public function sitemapExplorer(Request $r): JsonResponse
    {
        $this->requireAdmin();
        $slug = (string) $r->query('tenant', '');
        if ($slug === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $slug)) {
            return $this->error('Valid tenant slug required', 400, 'VALIDATION_INVALID');
        }
        $tenant = \DB::table('tenants')->where('slug', $slug)->where('is_active', 1)->first();
        if (!$tenant) return $this->error('Tenant not found', 404, 'NOT_FOUND');

        $staticRoutes  = $this->service->routesForTenant((int) $tenant->id);
        $dynamicRoutes = [];
        try {
            // Plan-routes artisan output: one route per line. Limit to 1000
            // entries to keep the JSON response sane.
            \Artisan::call('prerender:plan-routes', ['--tenant' => $slug]);
            $out = trim(\Artisan::output());
            $dynamicRoutes = array_values(array_filter(
                array_map('trim', explode("\n", $out)),
                fn($r) => $r !== '' && $r[0] === '/'
            ));
            $dynamicRoutes = array_slice($dynamicRoutes, 0, 1000);
        } catch (\Throwable $e) {
            // Fallback: just return the static floor.
        }

        return $this->respondWithData([
            'tenant_slug'    => $slug,
            'tenant_id'      => (int) $tenant->id,
            'static_routes'  => $staticRoutes,
            'dynamic_routes' => $dynamicRoutes,
            'total_count'    => count($staticRoutes) + count($dynamicRoutes),
        ]);
    }

    public function ttlInspector(Request $r): JsonResponse
    {
        $this->requireAdmin();
        $route = (string) $r->query('route', '');
        if ($route === '' || $route[0] !== '/') {
            return $this->error('route must start with "/"', 400, 'VALIDATION_INVALID');
        }
        if (!preg_match(\App\Services\PrerenderService::ROUTE_REGEX, $route)) {
            return $this->error('Invalid route', 400, 'VALIDATION_INVALID');
        }
        return $this->respondWithData($this->service->describeTtlForRoute($route));
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
