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
 * Plan per-tenant route lists for prerender-tenants.sh.
 *
 * Combines the hardcoded EXPECTED_ROUTES (the static-page floor: /, /about,
 * /privacy, etc.) with the dynamic URLs SitemapService publishes (blog posts,
 * listings, events, jobs, KB articles, ...). Outputs JSON so the bash
 * orchestrator can read every (tenant, route) pair without re-implementing
 * the sitemap traversal in shell.
 *
 * The bash script's hardcoded PUBLIC_ROUTES remains as a fallback if this
 * command is unavailable or errors out — we never want a stale build to lose
 * the ability to prerender the static floor.
 */
class PrerenderPlanRoutes extends Command
{
    /** Hard cap to keep one tenant's plan from blowing up the run. */
    private const MAX_ROUTES_PER_TENANT = 5000;

    protected $signature = 'prerender:plan-routes '
        . '{--tenant= : Limit to a single tenant slug} '
        . '{--limit=' . self::MAX_ROUTES_PER_TENANT . ' : Max routes per tenant} '
        . '{--include-static=1 : Include the static-page floor} '
        . '{--include-sitemap=1 : Include sitemap-derived URLs}';

    protected $description = 'Emit JSON of (tenant, routes) pairs for the prerender orchestrator.';

    public function __construct(private readonly SitemapService $sitemap) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantFilter = (string) ($this->option('tenant') ?? '');
        if ($tenantFilter !== '' && !preg_match('/^[A-Za-z0-9_-]+$/', $tenantFilter)) {
            $this->error('Invalid tenant slug.');
            return self::INVALID;
        }
        $limit = max(1, min(self::MAX_ROUTES_PER_TENANT, (int) $this->option('limit')));
        $includeStatic  = (int) $this->option('include-static') === 1;
        $includeSitemap = (int) $this->option('include-sitemap') === 1;

        $appHost = parse_url((string) env('FRONTEND_URL', 'https://app.project-nexus.ie'), PHP_URL_HOST)
                   ?: 'app.project-nexus.ie';

        $query = DB::table('tenants')
            ->where('is_active', 1)
            ->where('id', '<>', 1)
            ->orderBy('id');
        if ($tenantFilter !== '') $query->where('slug', $tenantFilter);

        // Pull features + configuration so the resolver can gate routes
        // per-tenant without a second query loop.
        $prerender = app(PrerenderService::class);

        $tenants = [];
        foreach ($query->get(['id', 'slug', 'domain', 'features', 'configuration']) as $t) {
            $domain = trim((string) ($t->domain ?? ''));
            $host = $domain !== '' ? $domain : $appHost;
            $prefix = $domain !== '' ? '' : '/' . $t->slug;

            $routes = [];
            if ($includeStatic) {
                // Tenant-aware static floor — only routes whose feature/module
                // gate is on for this tenant. No more rendering /jobs for a
                // tenant with job_vacancies disabled.
                foreach ($prerender->routesForTenant($t) as $r) {
                    $routes[$r] = true;
                }
            }
            if ($includeSitemap) {
                foreach ($this->routesFromSitemap((int) $t->id, $host, $prefix) as $r) {
                    if (!isset($routes[$r])) $routes[$r] = true;
                    if (count($routes) >= $limit) break;
                }
            }

            $tenants[] = [
                'tenant_id' => (int) $t->id,
                'slug'      => (string) $t->slug,
                'host'      => $host,
                'prefix'    => $prefix,
                'routes'    => array_keys($routes),
            ];
        }

        $this->line(json_encode(
            ['tenants' => $tenants],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
        return self::SUCCESS;
    }

    /**
     * Pull URLs from SitemapService and reduce them to tenant-local routes.
     * Strips the host and tenant prefix so prerender-tenants.sh can rebuild
     * full URLs the same way it does for the static floor.
     *
     * @return list<string>
     */
    private function routesFromSitemap(int $tenantId, string $host, string $prefix): array
    {
        try {
            // Build the sitemap as it would be served from the tenant's own
            // base URL. This is the same data Google would crawl.
            $baseUrl = 'https://' . $host . $prefix;
            $xml = $this->sitemap->generateForTenant($tenantId, $baseUrl);
        } catch (\Throwable $e) {
            // Sitemap generation is best-effort here. Fall back to static-only.
            return [];
        }

        $routes = [];
        $base = 'https://' . $host;
        // Quick parse — Sitemaps are simple <loc> elements; XPath would also work.
        if (!preg_match_all('#<loc>([^<]+)</loc>#i', $xml, $m)) return [];
        foreach ($m[1] as $loc) {
            $loc = trim(html_entity_decode($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
            if (!str_starts_with($loc, $base)) continue;
            $path = substr($loc, strlen($base));
            // Strip tenant prefix; routes in the orchestrator are tenant-local
            // (the prefix gets re-applied during URL assembly).
            if ($prefix !== '' && str_starts_with($path, $prefix . '/')) {
                $path = substr($path, strlen($prefix));
            } elseif ($prefix !== '' && $path === $prefix) {
                $path = '/';
            }
            if ($path === '') $path = '/';
            if ($path[0] !== '/') continue;
            // Reject anything that has unsafe characters — the bash side
            // re-validates with the same regex.
            if (!preg_match('#^/[A-Za-z0-9._~/%:@!$()*+,;=\-]*$#', $path)) continue;
            $routes[] = $path;
        }
        return array_values(array_unique($routes));
    }
}
