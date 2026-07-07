<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PrerenderContentInvalidator
{
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
