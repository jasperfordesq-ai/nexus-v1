<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * SitemapController — Every domain gets /sitemap.xml with its own URLs.
 *
 * Domain detection:
 *   1. Tenant custom domain (hour-timebank.ie) → that tenant's content
 *   2. Main app domain (app.project-nexus.ie) → all tenants without custom domains
 *   3. Any other domain serving the React app → master tenant's content
 *
 * URLs in the sitemap always use the requesting domain — no cross-domain leaks.
 */
class SitemapController
{
    /**
     * GET /sitemap.xml
     *
     * Every domain gets a proper sitemap with its own URLs.
     */
    public function index(SitemapService $service, Request $request): Response
    {
        $host = $request->header('X-Sitemap-Host', $request->getHost());
        $baseUrl = 'https://' . $host;
        $frontendHost = parse_url(env('FRONTEND_URL', 'https://app.project-nexus.ie'), PHP_URL_HOST);

        // 1. Check if this domain belongs to a specific tenant
        $tenant = DB::selectOne(
            "SELECT id, slug FROM tenants WHERE domain = ? AND is_active = 1",
            [$host]
        );

        if ($tenant) {
            // Check if this tenant has direct sub-tenants that inherit its domain
            // (sub-tenants with no own domain). If so, return a sitemap index so
            // Google discovers both the parent's and sub-tenants' content.
            $subTenants = DB::select(
                "SELECT id, slug, updated_at FROM tenants
                  WHERE parent_id = ? AND is_active = 1
                    AND (domain IS NULL OR domain = '')
                  ORDER BY slug",
                [(int) $tenant->id]
            );

            if (!empty($subTenants)) {
                $sitemaps = [
                    ['loc' => "{$baseUrl}/sitemap-{$tenant->slug}.xml", 'lastmod' => null],
                ];
                foreach ($subTenants as $sub) {
                    $lastmod = null;
                    if (!empty($sub->updated_at)) {
                        try {
                            $lastmod = substr((string) $sub->updated_at, 0, 10);
                        } catch (\Throwable) {}
                    }
                    $sitemaps[] = [
                        'loc'     => "{$baseUrl}/sitemap-{$sub->slug}.xml",
                        'lastmod' => $lastmod,
                    ];
                }
                $xml = $service->buildSitemapIndexPublic($sitemaps);
                return $this->xmlResponse($xml);
            }

            // No sub-tenants — single-tenant domain, serve flat sitemap
            $xml = $service->generateForTenant((int) $tenant->id, $baseUrl);
            return $this->xmlResponse($xml);
        }

        // 2. Main app domain — all tenants without custom domains
        if ($host === $frontendHost) {
            $xml = $service->generateForAppDomain();
            return $this->xmlResponse($xml);
        }

        // 3. Any other domain (e.g., api.project-nexus.ie accessed directly)
        //    Reuse the app domain sitemap — it already uses FRONTEND_URL for
        //    base URLs. Never generate content URLs pointing to the API domain.
        $xml = $service->generateForAppDomain();

        return $this->xmlResponse($xml);
    }

    /**
     * GET /sitemap-{slug}.xml
     */
    public function tenant(SitemapService $service, Request $request, string $slug): Response
    {
        $tenantId = $service->resolveTenantBySlug($slug);

        if ($tenantId === null) {
            return $this->xmlResponse($this->emptyUrlset(), 404);
        }

        $host = $request->header('X-Sitemap-Host', $request->getHost());

        // Determine the correct base URL. If this tenant has no own domain but
        // the request host is its parent's domain, use host/slug as the base URL
        // so sitemap entries are timebanking.uk/cardiff/... not timebanking.uk/...
        $tenantRow = DB::selectOne(
            "SELECT domain, parent_id, slug FROM tenants WHERE id = ? AND is_active = 1",
            [$tenantId]
        );
        $baseUrl = 'https://' . $host;
        if ($tenantRow && empty($tenantRow->domain) && !empty($tenantRow->parent_id)) {
            $parentRow = DB::selectOne(
                "SELECT domain FROM tenants WHERE id = ? AND is_active = 1",
                [(int) $tenantRow->parent_id]
            );
            if ($parentRow && !empty($parentRow->domain)
                && rtrim((string) $parentRow->domain, '/') === $host) {
                $baseUrl = 'https://' . $host . '/' . $tenantRow->slug;
            }
        }

        $xml = $service->generateForTenant($tenantId, $baseUrl);
        return $this->xmlResponse($xml);
    }

    private function xmlResponse(string $xml, int $status = 200): Response
    {
        return response($xml, $status)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-Robots-Tag', 'noindex');
    }

    private function emptyUrlset(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>' . "\n";
    }
}
