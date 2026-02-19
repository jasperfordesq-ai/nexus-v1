<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Controllers;

use Nexus\Core\TenantContext;

class RobotsController
{
    /**
     * Generate dynamic robots.txt based on Tenant Context
     */
    public function index(): void
    {
        $baseUrl = TenantContext::getBasePath(); // e.g. "" or "/tenant-slug"
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        // Security: Sanitize HTTP_HOST to alphanumeric, dashes, dots, and colons (ports)
        // This mitigates Host Header Injection by stripping illegal chars
        $rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', $rawHost);

        // Ensure proper content type
        header("Content-Type: text/plain; charset=utf-8");

        // 1. User Agent Definition
        echo "User-agent: *\n";

        // 2. Architectural Restrictions (Global for all tenants)
        // Admin & System Areas
        echo "Disallow: {$baseUrl}/admin-legacy/\n";
        echo "Disallow: {$baseUrl}/super-admin/\n";
        echo "Disallow: {$baseUrl}/cron/\n";

        // API & Machine Interfaces
        echo "Disallow: {$baseUrl}/api/\n";

        // Auth & Identity Flows (No useful content for crawlers)
        echo "Disallow: {$baseUrl}/login\n";
        echo "Disallow: {$baseUrl}/register\n";
        echo "Disallow: {$baseUrl}/password/\n";
        echo "Disallow: {$baseUrl}/logout\n";

        // Search Results (Prevents Infinite Crawl Traps)
        echo "Disallow: {$baseUrl}/search\n";

        // Private/User Specific Areas
        echo "Disallow: {$baseUrl}/dashboard\n";
        echo "Disallow: {$baseUrl}/profile\n";
        echo "Disallow: {$baseUrl}/messages\n";
        echo "Disallow: {$baseUrl}/wallet\n";
        echo "Disallow: {$baseUrl}/notifications\n";

        // 3. Sitemap Declaration (Dynamic per Tenant)
        // Sitemaps are always at the root of the "site" definition
        echo "Sitemap: {$scheme}://{$host}{$baseUrl}/sitemap.xml\n";

        exit;
    }
}
