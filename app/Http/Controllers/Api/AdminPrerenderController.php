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
 * All routes require admin auth. Force-refresh endpoints additionally require
 * super-admin since they affect every tenant snapshot and trigger a
 * cross-tenant Playwright run on the host.
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
        $userId = $this->requireSuperAdmin();

        $payload = $r->json()->all();
        $tenantSlug = $payload['tenant_slug'] ?? null;
        $routes = $payload['routes'] ?? null;
        $force = (bool) ($payload['force'] ?? false);
        $dryRun = (bool) ($payload['dry_run'] ?? false);

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
            $userId
        );

        return $this->respondWithData([
            'job_id' => $jobId,
            'job'    => $this->service->getJob($jobId),
        ]);
    }

    /** POST /api/v2/admin/prerender/jobs/{id}/cancel */
    public function cancelJob(int $id): JsonResponse
    {
        $this->requireSuperAdmin();
        $ok = $this->service->cancelJob($id);
        if (!$ok) {
            return $this->error('Job is not cancellable (already claimed or finished)', 409, 'CONFLICT');
        }
        return $this->respondWithData(['cancelled' => true, 'id' => $id]);
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
}
