<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PrerenderService;
use App\Services\SitemapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sitemap drift detector — the "the big names do this" layer.
 *
 * Walks every tenant's sitemap, compares each URL's <lastmod> against the
 * corresponding snapshot's mtime, and enqueues a HIGH-priority recache for
 * any URL whose source content has been updated more recently than its
 * snapshot was rendered.
 *
 * Why this exists alongside Eloquent observers:
 *   - Observers fire on Eloquent save/delete events. Raw DB inserts, queue
 *     jobs that use the query builder, migrations, and admin tools that
 *     bypass the model layer don't trigger them.
 *   - Without a drift detector, those code paths leave snapshots stale until
 *     the slow TTL sweep catches them — sometimes hours later. The drift
 *     detector runs every 2 minutes and closes that window.
 *
 * Designed to be cheap enough to run frequently:
 *   - One sitemap fetch per tenant (cached for an hour by SitemapService).
 *   - Walks the snapshot inventory once.
 *   - O(urls + snapshots) per pass, no DB writes for fresh routes.
 *
 * Cron entry (every 2 minutes):
 *   * /2 * * * *  cd /var/www/html && php artisan prerender:detect-drift
 */
class PrerenderDetectDrift extends Command
{
    protected $signature = 'prerender:detect-drift '
        . '{--max-tenants= : Cap tenants enqueued per pass} '
        . '{--max-routes= : Cap routes per tenant per pass} '
        . '{--min-drift-seconds=60 : Sitemap lastmod must lead snapshot mtime by at least this} '
        . '{--include-missing=1 : Also enqueue URLs in the sitemap with no snapshot at all} '
        . '{--priority=3 : Job priority (3=high, 5=normal, 7=low)} '
        . '{--dry-run : Print plan without enqueueing}';

    protected $description = 'Walk sitemaps, find snapshots stale vs their source content, enqueue HIGH-priority recache.';

    public function __construct(
        private readonly SitemapService $sitemap,
        private readonly PrerenderService $prerender,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cfg = config('prerender.auto_recache', []);
        $maxTenants = (int) ($this->option('max-tenants') ?? 20);
        $maxRoutes  = (int) ($this->option('max-routes')  ?? 100);
        $minDrift   = max(0, (int) ($this->option('min-drift-seconds') ?? 60));
        $includeMissing = (int) $this->option('include-missing') === 1;
        $priority   = max(1, min(9, (int) ($this->option('priority') ?? PrerenderService::PRIORITY_HIGH)));
        $dryRun     = (bool) $this->option('dry-run');

        // Build a host → tenant lookup and a (host,route) → snapshot mtime map.
        $tenants = $this->prerender->loadTenantTargets();
        if (empty($tenants)) {
            $this->info('No active tenants.');
            return self::SUCCESS;
        }

        // SHALLOW inventory — we only need mtimes, not asset/content drift flags.
        $inventory = $this->prerender->inventory(null, false);
        $snapMtime = [];
        foreach ($inventory as $row) {
            $snapMtime[$row['host']][$row['route']] = (int) $row['mtime'];
        }

        // Active-job skip list to avoid pile-up.
        $activeTenants = DB::table('prerender_jobs as j')
            ->join('tenants as t', 't.id', '=', 'j.tenant_id')
            ->whereIn('j.status', ['queued', 'claimed', 'running'])
            ->pluck('t.slug')
            ->toArray();
        $activeSet = array_flip($activeTenants);

        $now = time();
        $enqueued = [];
        $skipped = [];
        $tenantCount = 0;

        foreach ($tenants as $t) {
            if ($tenantCount >= $maxTenants) {
                $skipped[$t['slug']] = 'tenant_cap';
                continue;
            }
            if (isset($activeSet[$t['slug']])) {
                $skipped[$t['slug']] = 'active_job_exists';
                continue;
            }

            $tenantId = $t['tenant_id'];
            $host = $t['host'];
            $prefix = $t['prefix'];

            // Fetch the tenant's sitemap. SitemapService caches the result for
            // an hour so repeated runs are cheap.
            try {
                $baseUrl = 'https://' . $host . $prefix;
                $xml = $this->sitemap->generateForTenant($tenantId, $baseUrl);
            } catch (\Throwable $e) {
                $skipped[$t['slug']] = 'sitemap_error: ' . substr($e->getMessage(), 0, 80);
                continue;
            }

            $entries = $this->parseSitemap($xml, 'https://' . $host, $prefix);
            if (empty($entries)) {
                continue;
            }

            $staleRoutes = [];
            $missingRoutes = [];
            foreach ($entries as $route => $lastmodTs) {
                $snap = $snapMtime[$host][$route] ?? null;
                if ($snap === null) {
                    if ($includeMissing) $missingRoutes[] = $route;
                    continue;
                }
                if ($lastmodTs > 0 && $lastmodTs > $snap + $minDrift) {
                    $staleRoutes[] = $route;
                }
                if (count($staleRoutes) + count($missingRoutes) >= $maxRoutes) break;
            }

            $allRoutes = array_values(array_unique(array_merge($staleRoutes, $missingRoutes)));
            if (empty($allRoutes)) continue;

            // Cap routes per tenant so we don't generate a giant single job.
            if (count($allRoutes) > $maxRoutes) {
                $allRoutes = array_slice($allRoutes, 0, $maxRoutes);
            }

            $jobId = null;
            if (!$dryRun) {
                $jobId = $this->prerender->enqueueJob(
                    $tenantId,
                    implode(',', $allRoutes),
                    false,
                    false,
                    null,
                    $priority
                );
            }
            $enqueued[$t['slug']] = [
                'job_id'        => $jobId,
                'stale_routes'  => count($staleRoutes),
                'missing_routes'=> count($missingRoutes),
                'sample'        => array_slice($allRoutes, 0, 5),
            ];
            $tenantCount++;
        }

        $this->line(json_encode([
            'dry_run'   => $dryRun,
            'priority'  => $priority,
            'enqueued'  => $enqueued,
            'skipped'   => $skipped,
            'snapshot_count' => count($inventory),
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }

    /**
     * Parse a sitemap into [route => lastmodTs]. Routes are tenant-local
     * (stripped of host + tenant prefix) to align with the snapshot inventory.
     *
     * @return array<string,int>
     */
    private function parseSitemap(string $xml, string $base, string $prefix): array
    {
        $out = [];
        if (!preg_match_all('#<url>\s*(.*?)\s*</url>#is', $xml, $blocks)) return $out;
        foreach ($blocks[1] as $block) {
            if (!preg_match('#<loc>([^<]+)</loc>#i', $block, $lm)) continue;
            $loc = trim(html_entity_decode($lm[1], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
            if (!str_starts_with($loc, $base)) continue;
            $path = substr($loc, strlen($base));
            if ($prefix !== '' && str_starts_with($path, $prefix . '/')) {
                $path = substr($path, strlen($prefix));
            } elseif ($prefix !== '' && $path === $prefix) {
                $path = '/';
            }
            if ($path === '') $path = '/';
            if ($path[0] !== '/') continue;
            if (!preg_match('#^/[A-Za-z0-9._~/%:@!$()*+,;=\-]*$#', $path)) continue;

            $lastmod = 0;
            if (preg_match('#<lastmod>([^<]+)</lastmod>#i', $block, $lmm)) {
                $lastmod = (int) strtotime(trim($lmm[1]));
            }
            // Multiple URL entries may collide on the same route after prefix
            // stripping (shouldn't, but defensive). Keep the newest lastmod.
            $out[$path] = max($out[$path] ?? 0, $lastmod);
        }
        return $out;
    }
}
