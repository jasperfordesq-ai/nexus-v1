<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Artisan command to generate/warm sitemap caches.
 *
 * Usage:
 *   php artisan sitemap:generate              # All active tenants
 *   php artisan sitemap:generate --tenant=2   # Specific tenant
 *   php artisan sitemap:generate --stats      # Show URL counts only
 *   php artisan sitemap:generate --clear      # Clear cache without regenerating
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate
                            {--tenant= : Generate for a specific tenant ID only}
                            {--stats : Show URL statistics without generating}
                            {--clear : Clear cached sitemaps without regenerating}';

    protected $description = 'Generate XML sitemaps for all active tenants (warms the cache)';

    public function handle(SitemapService $service): int
    {
        if ($this->option('clear')) {
            return $this->handleClear($service);
        }

        if ($this->option('stats')) {
            return $this->handleStats($service);
        }

        return $this->handleGenerate($service);
    }

    private function handleGenerate(SitemapService $service): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId !== null) {
            return $this->generateForTenant($service, (int) $tenantId);
        }

        return $this->generateAll($service);
    }

    private function generateForTenant(SitemapService $service, int $tenantId): int
    {
        $tenant = DB::selectOne("SELECT id, name, slug FROM tenants WHERE id = ? AND is_active = 1", [$tenantId]);

        if (!$tenant) {
            $this->error("Tenant {$tenantId} not found or inactive.");
            return self::FAILURE;
        }

        $this->info("Generating sitemap for tenant: {$tenant->name} (ID: {$tenant->id})...");

        // Clear cache first so we regenerate fresh
        $service->clearCache($tenantId);

        $xml = $service->generateForTenant($tenantId);
        $urlCount = substr_count($xml, '<url>');

        $this->info("  Generated {$urlCount} URLs.");

        Log::info('Sitemap generated', [
            'tenant_id' => $tenantId,
            'tenant_name' => $tenant->name,
            'url_count' => $urlCount,
        ]);

        return self::SUCCESS;
    }

    private function generateAll(SitemapService $service): int
    {
        $tenants = DB::select("SELECT id, name, slug FROM tenants WHERE is_active = 1 ORDER BY id");

        if (empty($tenants)) {
            $this->warn('No active tenants found.');
            return self::SUCCESS;
        }

        $this->info("Generating sitemaps for " . count($tenants) . " active tenant(s)...\n");

        // Clear all caches
        $service->clearCache();

        $totalUrls = 0;

        foreach ($tenants as $tenant) {
            $xml = $service->generateForTenant((int) $tenant->id);
            $urlCount = substr_count($xml, '<url>');
            $totalUrls += $urlCount;

            $slug = $tenant->slug ?: 'main';
            $this->line("  [{$slug}] {$tenant->name}: {$urlCount} URLs");
        }

        // Generate the index
        $service->generateIndex();

        $this->newLine();
        $this->info("Sitemap index generated with " . count($tenants) . " tenant sitemap(s).");
        $this->info("Total URLs across all tenants: {$totalUrls}");

        Log::info('All sitemaps generated', [
            'tenant_count' => count($tenants),
            'total_urls' => $totalUrls,
        ]);

        return self::SUCCESS;
    }

    private function handleStats(SitemapService $service): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId !== null) {
            return $this->showStatsForTenant($service, (int) $tenantId);
        }

        $tenants = DB::select("SELECT id, name, slug FROM tenants WHERE is_active = 1 ORDER BY id");

        if (empty($tenants)) {
            $this->warn('No active tenants found.');
            return self::SUCCESS;
        }

        $this->info("Sitemap statistics for " . count($tenants) . " active tenant(s):\n");

        $grandTotal = 0;

        foreach ($tenants as $tenant) {
            $stats = $service->getStats((int) $tenant->id);
            $slug = $tenant->slug ?: 'main';

            $this->line("  [{$slug}] {$tenant->name}: {$stats['total_urls']} total URLs");

            foreach ($stats['content_types'] as $type => $count) {
                if ($count > 0) {
                    $this->line("    - {$type}: {$count}");
                }
            }

            $grandTotal += $stats['total_urls'];
            $this->newLine();
        }

        $this->info("Grand total: {$grandTotal} URLs across all tenants.");

        return self::SUCCESS;
    }

    private function showStatsForTenant(SitemapService $service, int $tenantId): int
    {
        $tenant = DB::selectOne("SELECT id, name, slug FROM tenants WHERE id = ? AND is_active = 1", [$tenantId]);

        if (!$tenant) {
            $this->error("Tenant {$tenantId} not found or inactive.");
            return self::FAILURE;
        }

        $stats = $service->getStats($tenantId);
        $slug = $tenant->slug ?: 'main';

        $this->info("Sitemap statistics for [{$slug}] {$tenant->name}:\n");
        $this->line("  Total URLs: {$stats['total_urls']}");

        foreach ($stats['content_types'] as $type => $count) {
            $this->line("  - {$type}: {$count}");
        }

        return self::SUCCESS;
    }

    private function handleClear(SitemapService $service): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId !== null) {
            $service->clearCache((int) $tenantId);
            $this->info("Sitemap cache cleared for tenant {$tenantId}.");
        } else {
            $cleared = $service->clearCache();
            $this->info("Sitemap cache cleared ({$cleared} entries).");
        }

        return self::SUCCESS;
    }
}
