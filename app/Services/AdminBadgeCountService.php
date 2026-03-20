<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AdminBadgeCountService — Provides badge counts for admin sidebar navigation.
 *
 * Counts pending approvals, alerts, and other actionable items for the current tenant.
 * Results are cached for the request lifetime.
 */
class AdminBadgeCountService
{
    private ?array $cachedCounts = null;

    /**
     * Get all badge counts for the current tenant.
     * Results are cached for the request lifetime.
     */
    public function getCounts(): array
    {
        if ($this->cachedCounts !== null) {
            return $this->cachedCounts;
        }

        $tenantId = TenantContext::getId();
        $counts = [];

        try {
            $counts['pending_users'] = $this->countPendingUsers($tenantId);
            $counts['pending_listings'] = $this->countPendingListings($tenantId);
            $counts['pending_orgs'] = $this->countPendingOrganizations($tenantId);
            $counts['fraud_alerts'] = $this->countFraudAlerts($tenantId);
            $counts['gdpr_requests'] = $this->countGdprRequests($tenantId);
            $counts['404_errors'] = $this->count404Errors();
            $counts['pending_exchanges'] = $this->countPendingExchanges($tenantId);
            $counts['unreviewed_messages'] = $this->countUnreviewedMessages($tenantId);
        } catch (\Exception $e) {
            Log::warning('AdminBadgeCountService error: ' . $e->getMessage());
        }

        $this->cachedCounts = $counts;
        return $counts;
    }

    /**
     * Get a specific badge count.
     */
    public function getCount(string $key): int
    {
        $counts = $this->getCounts();
        return $counts[$key] ?? 0;
    }

    /**
     * Clear cached counts (useful after an action that changes counts).
     */
    public function clearCache(): void
    {
        $this->cachedCounts = null;
    }

    private function countPendingUsers(int $tenantId): int
    {
        try {
            return (int) DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function countPendingListings(int $tenantId): int
    {
        try {
            return (int) DB::table('listings')
                ->where('tenant_id', $tenantId)
                ->where(function ($query) {
                    $query->where('status', 'pending')
                          ->orWhereNull('status')
                          ->orWhere('status', '');
                })
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function countPendingOrganizations(int $tenantId): int
    {
        try {
            return (int) DB::table('vol_organizations')
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function countFraudAlerts(int $tenantId): int
    {
        try {
            return (int) DB::table('fraud_alerts')
                ->where('tenant_id', $tenantId)
                ->where('status', 'new')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function countGdprRequests(int $tenantId): int
    {
        try {
            return (int) DB::table('gdpr_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function count404Errors(): int
    {
        try {
            return (int) DB::table('error_404_log')
                ->where('resolved', 0)
                ->where('last_seen_at', '>', DB::raw('DATE_SUB(NOW(), INTERVAL 7 DAY)'))
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function countPendingExchanges(int $tenantId): int
    {
        try {
            return (int) DB::table('exchange_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending_broker')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function countUnreviewedMessages(int $tenantId): int
    {
        try {
            return (int) DB::table('broker_message_copies')
                ->where('tenant_id', $tenantId)
                ->whereNull('reviewed_at')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
