<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;

/**
 * SitemapController — Serves dynamically generated XML sitemaps.
 *
 * These are web routes (not API), returning application/xml responses.
 * Crawlers access these directly without authentication.
 *
 * Routes:
 *   GET /sitemap.xml         → Sitemap index (all active tenants)
 *   GET /sitemap-{slug}.xml  → Per-tenant sitemap
 */
class SitemapController
{
    /**
     * GET /sitemap.xml
     *
     * Returns a sitemap index listing per-tenant sitemaps.
     */
    public function index(SitemapService $service): Response
    {
        $xml = $service->generateIndex();

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
