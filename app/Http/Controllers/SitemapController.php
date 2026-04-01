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
 * SitemapController — Serves dynamically generated XML sitemaps.
 *
 * Smart domain detection: if the request comes from a tenant's custom
 * domain (e.g., hour-timebank.ie), /sitemap.xml returns that tenant's
 * sitemap directly. On the main app domain, it returns the full index.
 *
 * This means every domain just submits /sitemap.xml to Google Search
 * Console — simple, no special paths needed.
 */
class SitemapController
{
    /**
     * GET /sitemap.xml
     *
     * If the request comes from a tenant's custom domain, return that
     * tenant's sitemap. Otherwise return the sitemap index.
     */
    public function index(SitemapService $service, Request $request): Response
    {
        // Check if the request host matches a tenant's custom domain.
        // X-Sitemap-Host is set by the frontend nginx proxy to preserve
        // the original domain (e.g., hour-timebank.ie) before proxying
        // to the API backend.
        $host = $request->header('X-Sitemap-Host', $request->getHost());
        $tenant = DB::selectOne(
            "SELECT id, slug FROM tenants WHERE domain = ? AND is_active = 1",
            [$host]
        );

        // Only known domains get a sitemap. Unknown domains get empty XML
        // to prevent cross-domain contamination in Google Search Console.
        $frontendHost = parse_url(env('FRONTEND_URL', 'https://app.project-nexus.ie'), PHP_URL_HOST);

        if ($tenant) {
            // Known tenant custom domain — return that tenant's sitemap
            $xml = $service->generateForTenant((int) $tenant->id);
        } elseif ($host === $frontendHost || $host === 'api.project-nexus.ie') {
            // Main app domain — return the full sitemap index
            $xml = $service->generateIndex();
        } else {
            // Unknown domain — return empty sitemap (don't leak cross-domain URLs)
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                 . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>' . "\n";
        }

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-Robots-Tag', 'noindex');
    }

    /**
     * GET /sitemap-{slug}.xml
     *
     * Returns the sitemap for a specific tenant identified by slug.
     */
    public function tenant(SitemapService $service, string $slug): Response
    {
        $tenantId = $service->resolveTenantBySlug($slug);

        if ($tenantId === null) {
            return response(
                '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>' . "\n",
                404
            )
                ->header('Content-Type', 'application/xml; charset=UTF-8');
        }

        $xml = $service->generateForTenant($tenantId);

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-Robots-Tag', 'noindex');
    }
}
