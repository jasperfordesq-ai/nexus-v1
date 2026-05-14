<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PrerenderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Continuous freshness loop. Walks the deep inventory, identifies snapshots
 * that are either content-stale (DB row newer than the snapshot) or TTL-stale
 * (older than config/prerender.php allows for their route), and enqueues
 * low-priority recache jobs grouped by tenant.
 *
 * Designed to run from cron every 15–30 minutes. The priority lane added in
 * Phase 1 keeps these jobs from starving urgent user-initiated runs.
 */
class PrerenderAutoRecache extends Command
{
    protected $signature = 'prerender:auto-recache '
        . '{--max-tenants= : Max tenants to enqueue this run} '
        . '{--max-routes= : Max routes per tenant per run} '
        . '{--min-stale-seconds= : Minimum staleness to consider} '
        . '{--include-ttl=1 : Also recache TTL-expired snapshots} '
        . '{--include-content=1 : Also recache content-stale snapshots} '
        . '{--dry-run : Print plan without enqueueing}';

    protected $description = 'Auto-enqueue prerender recache jobs for stale snapshots.';

    public function __construct(private readonly PrerenderService $service) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cfg = config('prerender.auto_recache', []);
        $maxTenants = (int) ($this->option('max-tenants') ?? $cfg['max_tenants_per_run'] ?? 10);
        $maxRoutes  = (int) ($this->option('max-routes')  ?? $cfg['max_routes_per_tenant'] ?? 50);
        $minStale   = (int) ($this->option('min-stale-seconds') ?? $cfg['min_stale_seconds'] ?? 300);
        $includeTtl     = (int) $this->option('include-ttl') === 1;
        $includeContent = (int) $this->option('include-content') === 1;
        $dryRun = (bool) $this->option('dry-run');

        // Walk the deep inventory (asset issues + content drift checks).
        $inventory = $this->service->inventory(null, true);
        if (empty($inventory)) {
            $this->info('Inventory empty; nothing to recache.');
            return self::SUCCESS;
        }

        // Resolve tenant slugs from hosts. We need the slug to pass --tenant
        // to prerender-tenants.sh.
        $tenants = $this->service->loadTenantTargets();
        $hostToSlug = [];
        foreach ($tenants as $t) $hostToSlug[$t['host']] = $t['slug'];

        // Group stale routes by tenant slug.
        $byTenant = [];
        $reasons = [];
        foreach ($inventory as $row) {
            if ($row['age_s'] < $minStale) continue;
            $stale = false;
            $why = null;
            if ($includeContent && !empty($row['content_stale'])) {
                $stale = true;
                $why = 'content';
            }
            if (!$stale && $includeTtl) {
                $ttl = $this->service->ttlForRoute($row['route']);
                if ($row['age_s'] >= $ttl) {
                    $stale = true;
                    $why = 'ttl';
                }
            }
            if (!$stale) continue;

            $slug = $hostToSlug[$row['host']] ?? null;
            if ($slug === null) continue; // Snapshot for a host we don't recognise.
            $byTenant[$slug][] = $row['route'];
            $reasons[$slug . ':' . $row['route']] = $why;
        }

        if (empty($byTenant)) {
            $this->info('No stale snapshots above min-stale-seconds threshold.');
            return self::SUCCESS;
        }

        // Tenants with active jobs (queued/running) are skipped if configured.
        $skipActive = (bool) (config('prerender.auto_recache.skip_if_tenant_has_active_job', true));
        $activeTenants = [];
        if ($skipActive) {
            $activeTenants = DB::table('prerender_jobs as j')
                ->join('tenants as t', 't.id', '=', 'j.tenant_id')
                ->whereIn('j.status', ['queued', 'claimed', 'running'])
                ->pluck('t.slug')
                ->toArray();
        }
        $activeSet = array_flip($activeTenants);

        // Sort tenants by largest stale-route count first so the biggest
        // freshness wins land first when capped.
        uasort($byTenant, fn($a, $b) => count($b) <=> count($a));

        $enqueued = [];
        $skipped = [];
        $tenantCount = 0;
        foreach ($byTenant as $slug => $routes) {
            if ($tenantCount >= $maxTenants) {
                $skipped[$slug] = 'tenant_cap';
                continue;
            }
            if (isset($activeSet[$slug])) {
                $skipped[$slug] = 'active_job_exists';
                continue;
            }
            // Dedup + cap routes per tenant.
            $routes = array_values(array_unique($routes));
            if (count($routes) > $maxRoutes) $routes = array_slice($routes, 0, $maxRoutes);

            $tenantId = (int) DB::table('tenants')
                ->where('slug', $slug)->where('is_active', 1)->value('id');
            if ($tenantId === 0) {
                $skipped[$slug] = 'no_tenant_row';
                continue;
            }

            $routesCsv = implode(',', $routes);
            $jobId = null;
            if (!$dryRun) {
                $jobId = $this->service->enqueueJob(
                    $tenantId,
                    $routesCsv,
                    false,   // force (no — only stale routes get recached)
                    false,   // dry_run on the job itself
                    null,    // requested_by (system)
                    PrerenderService::PRIORITY_LOW
                );
            }
            $enqueued[$slug] = [
                'job_id' => $jobId,
                'route_count' => count($routes),
                'sample_routes' => array_slice($routes, 0, 5),
            ];
            $tenantCount++;
        }

        $this->line(json_encode([
            'dry_run'  => $dryRun,
            'enqueued' => $enqueued,
            'skipped'  => $skipped,
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}
