<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrerenderContentInvalidator
{
    /**
     * Queue a complete authoritative generation after tenant routing changes
     * (domain, slug, hierarchy, activation, or deletion). A tenant-scoped job
     * cannot remove snapshots at the tenant's former host/prefix.
     */
    public function refreshAll(): ?int
    {
        try {
            return $this->refreshAllOrFail();
        } catch (\Throwable $e) {
            Log::warning('Authoritative prerender refresh enqueue failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /** Queue an authoritative rebuild or propagate the failure to the caller. */
    public function refreshAllOrFail(bool $quarantineOwnershipChanges = true): int
    {
        $result = app(PrerenderService::class)->enqueueAuthoritativeRebuildIntent(null);
        if ($quarantineOwnershipChanges) {
            DB::afterCommit(function (): void {
                try {
                    $result = app(PrerenderService::class)->quarantineMismatchedSnapshotOwnership();
                    if (($result['quarantined'] ?? 0) > 0) {
                        Log::warning('Quarantined prerender snapshots with obsolete tenant ownership', $result);
                    }
                } catch (\Throwable $e) {
                    Log::critical('Post-commit prerender ownership quarantine failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }
        $this->clearCachesAfterCommit();
        return (int) $result['job_id'];
    }

    public function refreshTenant(
        int $tenantId,
        bool $force = true,
        bool $purgeUnexpected = false
    ): ?int
    {
        try {
            return $this->refreshTenantOrFail($tenantId, $force, $purgeUnexpected);
        } catch (\Throwable $e) {
            Log::warning('Prerender tenant refresh enqueue failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /** Queue a tenant rebuild or propagate the failure to a transactional caller. */
    public function refreshTenantOrFail(
        int $tenantId,
        bool $force = true,
        bool $purgeUnexpected = false
    ): int {
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant id must be positive for prerender refresh');
        }

        $prerender = app(PrerenderService::class);
        // Never delete filesystem snapshots inside a caller's database
        // transaction. A later enqueue/commit failure cannot roll those bytes
        // back. Exact orphan cleanup is handled by the drift reconciler and
        // authoritative publication after durable job intent exists.

        $jobId = $prerender->enqueueJob(
            $tenantId,
            null,
            $force,
            false,
            null,
            PrerenderService::PRIORITY_NORMAL
        );
        $this->clearCachesAfterCommit($tenantId);
        return $jobId;
    }

    private function clearCachesAfterCommit(?int $tenantId = null): void
    {
        DB::afterCommit(function () use ($tenantId): void {
            try {
                app(SitemapService::class)->clearCache($tenantId);
                Cache::forget('prerender:summary:inventory');
            } catch (\Throwable $e) {
                Log::warning('Post-commit prerender cache invalidation failed', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * @param list<string> $routes
     */
    public function refreshRoutes(int $tenantId, array $routes, bool $enqueueRecache = true): int
    {
        if ($tenantId <= 0 || $routes === []) {
            return 0;
        }

        try {
            app(SitemapService::class)->clearCache($tenantId);
            Cache::forget('prerender:summary:inventory');

            return app(PrerenderService::class)->invalidateRoutes($tenantId, $routes, $enqueueRecache);
        } catch (\Throwable $e) {
            Log::warning('Prerender content invalidation failed', [
                'tenant_id' => $tenantId,
                'routes' => $routes,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function refreshVolunteerOpportunity(int $tenantId, int $opportunityId): int
    {
        return $this->refreshRoutes($tenantId, [
            '/volunteering',
            "/volunteering/opportunities/{$opportunityId}",
        ]);
    }

    public function refreshVolunteerOrganisation(int $tenantId, int $organisationId): int
    {
        return $this->refreshRoutes($tenantId, [
            '/organisations',
            "/organisations/{$organisationId}",
        ]);
    }
}
