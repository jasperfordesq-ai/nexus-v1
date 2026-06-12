<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Federation;

use App\Core\TenantContext;
use App\Services\FederationExternalPartnerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Partner-departure data lifecycle (2026-06-12): deleting an external
 * federation partner previously left all of its imported mirror data
 * (listings, members, events, groups, volunteering, connections) visible
 * forever. Now the mirrors are removed and imported opportunities are
 * deactivated; messages/transactions/reviews are deliberately retained.
 */
class ExternalPartnerDepartureCleanupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_partner_delete_removes_mirrors_and_deactivates_imported_opportunities(): void
    {
        TenantContext::setById($this->testTenantId);

        $partnerId = (int) DB::table('federation_external_partners')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Departing Partner',
            'base_url' => 'https://93.184.216.34',
            'created_at' => now(),
        ]);

        DB::table('federation_listings')->insert([
            'tenant_id' => $this->testTenantId,
            'external_partner_id' => $partnerId,
            'external_id' => 'ext-listing-1',
            'title' => 'Imported listing',
            'created_at' => now(),
        ]);
        $oppId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'title' => 'Imported opportunity',
            'description' => 'x',
            'is_active' => 1,
            'is_federated' => 1,
            'external_partner_id' => $partnerId,
            'created_at' => now(),
        ]);

        $result = FederationExternalPartnerService::delete($partnerId, $this->testTenantId, 1);

        $this->assertTrue($result['success']);
        $this->assertFalse(
            DB::table('federation_listings')
                ->where('external_partner_id', $partnerId)
                ->where('tenant_id', $this->testTenantId)
                ->exists(),
            'Imported mirror listings must be removed when the partner departs'
        );
        $this->assertSame(
            0,
            (int) DB::table('vol_opportunities')->where('id', $oppId)->value('is_active'),
            'Imported opportunities must be deactivated (not deleted) on partner departure'
        );
        $this->assertFalse(
            DB::table('federation_external_partners')->where('id', $partnerId)->exists()
        );
    }
}
