<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Jobs;

use App\Core\TenantContext;
use App\Services\FederationAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederationInitialSyncJob — fired once when a partnership transitions to active.
 *
 * For internal federation (shared DB, multiple tenants) there is nothing to
 * "push" — partner data is already accessible via SQL. This job:
 *  1. Confirms the partnership is still active before doing any work.
 *  2. Records a bilateral audit entry so both tenants have a clear start-of-sync
 *     timestamp in their audit trails.
 *  3. Pre-counts opted-in members and active listings and logs the snapshot
 *     counts — useful for post-approval dashboards and debugging.
 *
 * A small delay (10 s) is set by the dispatcher to let the HTTP approval
 * response finish before the job runs.
 */
class FederationInitialSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'federation';

    /** Retry on transient DB failures. */
    public int $tries = 3;

    /** 5 minutes — may scan large tenant tables. */
    public int $timeout = 300;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $partnerTenantId,
        public readonly int $partnershipId,
    ) {}

    public function handle(): void
    {
        // Confirm the partnership is still active — it may have been suspended
        // or terminated between approval and when this job was dequeued.
        $partnership = DB::table('federation_partnerships')
            ->where('id', $this->partnershipId)
            ->where('status', 'active')
            ->first();

        if (! $partnership) {
            Log::info('FederationInitialSyncJob: partnership no longer active, skipping', [
                'partnership_id' => $this->partnershipId,
            ]);
            return;
        }

        $memberCount  = $this->countOptedInMembers($this->tenantId);
        $listingCount = $this->countActiveListings($this->tenantId);

        $partnerMemberCount  = $this->countOptedInMembers($this->partnerTenantId);
        $partnerListingCount = $this->countActiveListings($this->partnerTenantId);

        // Write an audit entry from each side so both tenants see the event.
        TenantContext::setById($this->tenantId);
        FederationAuditService::log(
            'partnership_initial_sync_complete',
            $this->tenantId,
            $this->partnerTenantId,
            null,
            [
                'partnership_id'        => $this->partnershipId,
                'opted_in_members'      => $memberCount,
                'active_listings'       => $listingCount,
                'partner_members'       => $partnerMemberCount,
                'partner_listings'      => $partnerListingCount,
            ]
        );

        TenantContext::setById($this->partnerTenantId);
        FederationAuditService::log(
            'partnership_initial_sync_complete',
            $this->partnerTenantId,
            $this->tenantId,
            null,
            [
                'partnership_id'        => $this->partnershipId,
                'opted_in_members'      => $partnerMemberCount,
                'active_listings'       => $partnerListingCount,
                'partner_members'       => $memberCount,
                'partner_listings'      => $listingCount,
            ]
        );

        Log::info('FederationInitialSyncJob: completed', [
            'partnership_id'        => $this->partnershipId,
            'tenant_id'             => $this->tenantId,
            'partner_tenant_id'     => $this->partnerTenantId,
            'opted_in_members'      => $memberCount,
            'active_listings'       => $listingCount,
            'partner_members'       => $partnerMemberCount,
            'partner_listings'      => $partnerListingCount,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function countOptedInMembers(int $tenantId): int
    {
        return (int) DB::table('users')
            ->join('federation_user_settings', 'federation_user_settings.user_id', '=', 'users.id')
            ->where('users.tenant_id', $tenantId)
            ->where('users.status', 'active')
            ->where('federation_user_settings.federation_optin', 1)
            ->where('federation_user_settings.profile_visible_federated', 1)
            ->count();
    }

    private function countActiveListings(int $tenantId): int
    {
        return (int) DB::table('listings')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();
    }
}
