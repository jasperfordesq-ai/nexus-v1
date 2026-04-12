<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Models\VolOrganization;
use App\Services\VolOrgWalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class VolOrgWalletServiceTest extends TestCase
{
    use DatabaseTransactions;

    // -------- Smoke tests (kept) --------

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\VolOrgWalletService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(\App\Services\VolOrgWalletService::class);
        foreach (['getBalance', 'getTransactions', 'getWalletSummary', 'depositFromUser', 'payVolunteer', 'adminAdjustment'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Not public: {$m}");
        }
    }

    // -------- Helpers --------

    private function makeOrg(int $tenantId = 2, float $balance = 0, ?int $ownerId = null): int
    {
        $owner = $ownerId ?? User::factory()->forTenant($tenantId)->create(['balance' => 0])->id;
        $id = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $owner,
            'name' => 'Org ' . uniqid(),
            'slug' => 'org-' . uniqid(),
            'status' => 'active',
            'balance' => $balance,
            'created_at' => now(),
        ]);
        return (int) $id;
    }

    // -------- Deep tests --------

    public function test_deposit_increments_balance_and_writes_audit_row(): void
    {
        $user = User::factory()->forTenant(2)->create(['balance' => 100]);
        $orgId = $this->makeOrg(2, 50.00);

        $result = VolOrgWalletService::depositFromUser($user->id, $orgId, 25.0);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertEquals(75.0, $result['new_balance']);

        $this->assertEquals(75.00, (float) DB::table('vol_organizations')->where('id', $orgId)->value('balance'));
        $this->assertEquals(75, (int) DB::table('users')->where('id', $user->id)->value('balance'));

        $tx = DB::table('vol_org_transactions')->where('vol_organization_id', $orgId)->orderByDesc('id')->first();
        $this->assertNotNull($tx);
        $this->assertEquals('deposit', $tx->type);
        $this->assertEquals(25.00, (float) $tx->amount);
        $this->assertEquals(75.00, (float) $tx->balance_after);
        $this->assertEquals(2, (int) $tx->tenant_id);
    }

    public function test_deposit_rejects_zero_and_negative_amounts(): void
    {
        $user = User::factory()->forTenant(2)->create(['balance' => 100]);
        $orgId = $this->makeOrg(2, 0);

        $this->assertFalse(VolOrgWalletService::depositFromUser($user->id, $orgId, 0)['success']);
        $this->assertFalse(VolOrgWalletService::depositFromUser($user->id, $orgId, -5)['success']);

        $this->assertEquals(0.00, (float) DB::table('vol_organizations')->where('id', $orgId)->value('balance'));
        $this->assertEquals(0, DB::table('vol_org_transactions')->where('vol_organization_id', $orgId)->count());
    }

    public function test_deposit_rejects_insufficient_balance(): void
    {
        $user = User::factory()->forTenant(2)->create(['balance' => 5]);
        $orgId = $this->makeOrg(2, 0);

        $result = VolOrgWalletService::depositFromUser($user->id, $orgId, 50);

        $this->assertFalse($result['success']);
        // Rollback: nothing changed
        $this->assertEquals(0.00, (float) DB::table('vol_organizations')->where('id', $orgId)->value('balance'));
        $this->assertEquals(5, (int) DB::table('users')->where('id', $user->id)->value('balance'));
        $this->assertEquals(0, DB::table('vol_org_transactions')->where('vol_organization_id', $orgId)->count());
    }

    public function test_pay_volunteer_debits_org_credits_volunteer_and_writes_audit(): void
    {
        $admin = User::factory()->forTenant(2)->create();
        $volunteer = User::factory()->forTenant(2)->create(['balance' => 10]);
        $orgId = $this->makeOrg(2, 100.0, $admin->id);

        $result = VolOrgWalletService::payVolunteer($orgId, $volunteer->id, 20.0, $admin->id, 'Test pay');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertEquals(80.0, $result['new_balance']);

        $this->assertEquals(80.00, (float) DB::table('vol_organizations')->where('id', $orgId)->value('balance'));
        $this->assertEquals(30, (int) DB::table('users')->where('id', $volunteer->id)->value('balance'));

        $tx = DB::table('vol_org_transactions')->where('vol_organization_id', $orgId)->orderByDesc('id')->first();
        $this->assertEquals('volunteer_payment', $tx->type);
        $this->assertEquals(-20.00, (float) $tx->amount);
        $this->assertEquals(80.00, (float) $tx->balance_after);

        // Paired audit in main transactions table
        $this->assertTrue(
            DB::table('transactions')
                ->where('tenant_id', 2)
                ->where('receiver_id', $volunteer->id)
                ->where('amount', 20)
                ->where('transaction_type', 'volunteer')
                ->exists()
        );
    }

    public function test_pay_volunteer_rejects_when_org_balance_insufficient(): void
    {
        $admin = User::factory()->forTenant(2)->create();
        $volunteer = User::factory()->forTenant(2)->create(['balance' => 0]);
        $orgId = $this->makeOrg(2, 5.0, $admin->id);

        $result = VolOrgWalletService::payVolunteer($orgId, $volunteer->id, 50.0, $admin->id);

        $this->assertFalse($result['success']);
        $this->assertEquals(5.00, (float) DB::table('vol_organizations')->where('id', $orgId)->value('balance'));
        $this->assertEquals(0, (int) DB::table('users')->where('id', $volunteer->id)->value('balance'));
        $this->assertEquals(0, DB::table('vol_org_transactions')->where('vol_organization_id', $orgId)->count());
    }

    public function test_admin_adjustment_positive_and_negative_track_balance_after(): void
    {
        $admin = User::factory()->forTenant(2)->create();
        $orgId = $this->makeOrg(2, 100.0, $admin->id);

        $up = VolOrgWalletService::adminAdjustment($orgId, 50.0, $admin->id, 'top up');
        $this->assertTrue($up['success']);
        $this->assertEquals(150.0, $up['new_balance']);

        $down = VolOrgWalletService::adminAdjustment($orgId, -30.0, $admin->id, 'correction');
        $this->assertTrue($down['success']);
        $this->assertEquals(120.0, $down['new_balance']);

        // Cannot adjust into negative
        $bad = VolOrgWalletService::adminAdjustment($orgId, -500.0, $admin->id, 'too much');
        $this->assertFalse($bad['success']);
        $this->assertEquals(120.00, (float) DB::table('vol_organizations')->where('id', $orgId)->value('balance'));

        // Zero rejected
        $this->assertFalse(VolOrgWalletService::adminAdjustment($orgId, 0, $admin->id, 'no-op')['success']);
    }

    public function test_tenant_isolation_prevents_cross_tenant_access(): void
    {
        // Org in tenant 999
        $otherOwner = User::factory()->forTenant(999)->create(['balance' => 100]);
        $otherOrgId = $this->makeOrg(999, 100.0, $otherOwner->id);

        // Current context is tenant 2
        TenantContext::setById(2);
        $user2 = User::factory()->forTenant(2)->create(['balance' => 100]);

        // getBalance returns empty for cross-tenant org
        $bal = VolOrgWalletService::getBalance($otherOrgId);
        $this->assertEquals(0, $bal['balance']);
        $this->assertEquals('', $bal['name']);

        // depositFromUser rejects cross-tenant org
        $result = VolOrgWalletService::depositFromUser($user2->id, $otherOrgId, 10);
        $this->assertFalse($result['success']);

        // Org balance in tenant 999 is unchanged
        $this->assertEquals(100.00, (float) DB::table('vol_organizations')->where('id', $otherOrgId)->value('balance'));
    }

    public function test_get_transactions_returns_paginated_items(): void
    {
        $user = User::factory()->forTenant(2)->create(['balance' => 1000]);
        $orgId = $this->makeOrg(2, 0, $user->id);

        for ($i = 0; $i < 3; $i++) {
            VolOrgWalletService::depositFromUser($user->id, $orgId, 10);
        }

        $result = VolOrgWalletService::getTransactions($orgId, ['limit' => 20]);
        $this->assertCount(3, $result['items']);
        $this->assertFalse($result['has_more']);
        $this->assertEquals('deposit', $result['items'][0]['type']);
    }
}
