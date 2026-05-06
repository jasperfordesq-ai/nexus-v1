<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\VolunteerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class VolunteerServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(2);
    }

    public function test_getOpportunities_returns_expected_structure(): void
    {
        $result = VolunteerService::getOpportunities();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        $this->assertNull(VolunteerService::getById(2147483647));
    }

    public function test_verifyHours_auto_pay_keeps_fractional_remainder_in_org_wallet(): void
    {
        $admin = User::factory()->forTenant(2)->create(['balance' => 0]);
        $volunteer = User::factory()->forTenant(2)->create(['balance' => 0]);

        $orgId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => 2,
            'user_id' => $admin->id,
            'name' => 'Fractional Hours Org',
            'slug' => 'fractional-hours-org-' . uniqid(),
            'description' => 'A volunteer organisation used for wallet regression coverage.',
            'contact_email' => 'fractional@example.test',
            'status' => 'active',
            'auto_pay_enabled' => 1,
            'balance' => 10.00,
            'created_at' => now(),
        ]);

        $opportunityId = DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => 2,
            'organization_id' => $orgId,
            'title' => 'Fractional Hours Opportunity',
            'description' => 'Help with a fractional shift.',
            'is_active' => 1,
            'status' => 'open',
            'created_by' => $admin->id,
            'created_at' => now(),
        ]);

        $logId = DB::table('vol_logs')->insertGetId([
            'tenant_id' => 2,
            'user_id' => $volunteer->id,
            'organization_id' => $orgId,
            'opportunity_id' => $opportunityId,
            'date_logged' => now()->subDay()->toDateString(),
            'hours' => 2.75,
            'description' => 'Fractional shift',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->assertTrue(VolunteerService::verifyHours($logId, $admin->id, 'approve'));

        $this->assertEquals(8.00, (float) DB::table('vol_organizations')->where('id', $orgId)->value('balance'));
        $this->assertEquals(2, (int) DB::table('users')->where('id', $volunteer->id)->value('balance'));

        $orgTx = DB::table('vol_org_transactions')->where('vol_log_id', $logId)->first();
        $this->assertNotNull($orgTx);
        $this->assertEquals(-2.00, (float) $orgTx->amount);
        $this->assertEquals(8.00, (float) $orgTx->balance_after);

        $mainTx = DB::table('transactions')
            ->where('tenant_id', 2)
            ->where('receiver_id', $volunteer->id)
            ->where('transaction_type', 'volunteer')
            ->latest('id')
            ->first();
        $this->assertNotNull($mainTx);
        $this->assertEquals(2, (int) $mainTx->amount);
    }
}
