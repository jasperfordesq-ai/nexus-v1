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
            // Tenant custom domain — that tenant's sitemap, this domain's URLs
            $xml = $service->generateForTenant((int) $tenant->id, $baseUrl);
            return $this->xmlResponse($xml);
        }

        // 2. Main app domain — all tenants without custom domains
        if ($host === $frontendHost) {
            $xml = $service->generateForAppDomain();
            return $this->xmlResponse($xml);
        }

        // 3. Any other domain — master tenant's content, this domain's URLs
        $masterTenant = DB::selectOne(
            "SELECT id FROM tenants WHERE is_active = 1 ORDER BY id LIMIT 1"
        );
        if ($masterTenant) {
            $xml = $service->generateForTenant((int) $masterTenant->id, $baseUrl);
        } else {
            $xml = $this->emptyUrlset();
        }

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
        $xml = $service->generateForTenant($tenantId, 'https://' . $host);

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
