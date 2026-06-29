<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class MemberPremiumAdminFinanceControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableDonationSupport($this->testTenantId);
        DB::table('donation_disputes')->whereIn('tenant_id', [$this->testTenantId, 999])->delete();
        DB::table('vol_donations')->whereIn('tenant_id', [$this->testTenantId, 999])->delete();
        DB::table('member_subscriptions')->whereIn('tenant_id', [$this->testTenantId, 999])->delete();
    }

    public function test_finance_overview_requires_admin(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $this->apiGet('/v2/admin/member-premium/finance/overview')
            ->assertStatus(403);
    }

    public function test_finance_overview_returns_tenant_scoped_routing_totals(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $this->insertDonation($this->testTenantId, [
            'amount' => 10.00,
            'status' => 'completed',
            'payment_route' => 'platform_default',
            'stripe_account_id' => null,
            'stripe_payment_intent_id' => 'pi_admin_finance_platform',
            'gift_aid_claim_status' => 'ready',
        ]);
        $this->insertDonation($this->testTenantId, [
            'amount' => 25.00,
            'status' => 'completed',
            'payment_route' => 'tenant_connect',
            'stripe_account_id' => 'acct_test_tenant',
            'stripe_payment_intent_id' => 'pi_admin_finance_connect',
        ]);
        $this->insertDonation(999, [
            'amount' => 99.00,
            'status' => 'completed',
            'payment_route' => 'tenant_connect',
            'stripe_account_id' => 'acct_other_tenant',
            'stripe_payment_intent_id' => 'pi_admin_finance_other',
        ]);

        DB::table('donation_disputes')->insert([
            'tenant_id' => $this->testTenantId,
            'stripe_dispute_id' => 'dp_admin_finance_open',
            'payment_intent_id' => 'pi_admin_finance_connect',
            'amount' => 2500,
            'currency' => 'GBP',
            'status' => 'needs_response',
            'payment_route' => 'tenant_connect',
            'stripe_account_id' => 'acct_test_tenant',
            'payload' => json_encode(['id' => 'dp_admin_finance_open']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiGet('/v2/admin/member-premium/finance/overview');

        $response->assertStatus(200);
        $response->assertJsonPath('data.overview.totals.completed_cents', 3500);
        $response->assertJsonPath('data.overview.routing.platform_fallback_cents', 1000);
        $response->assertJsonPath('data.overview.routing.tenant_connect_cents', 2500);
        $response->assertJsonPath('data.overview.gift_aid.ready_cents', 1000);
        $response->assertJsonPath('data.overview.disputes.open_count', 1);
    }

    public function test_gift_aid_export_returns_tenant_scoped_csv(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $this->insertDonation($this->testTenantId, [
            'amount' => 15.00,
            'status' => 'completed',
            'stripe_payment_intent_id' => 'pi_admin_gift_aid',
            'gift_aid_claim_status' => 'ready',
            'gift_aid_declaration_name' => 'Ada Lovelace',
            'gift_aid_address_line1' => '1 Example Street',
            'gift_aid_postcode' => 'SW1A 1AA',
            'gift_aid_country' => 'GB',
            'gift_aid_consented_at' => now(),
        ]);
        $this->insertDonation(999, [
            'amount' => 99.00,
            'status' => 'completed',
            'stripe_payment_intent_id' => 'pi_admin_gift_aid_other',
            'gift_aid_claim_status' => 'ready',
            'gift_aid_declaration_name' => 'Other Tenant',
        ]);

        $response = $this->apiGet('/v2/admin/member-premium/finance/gift-aid-export');

        $response->assertStatus(200);
        $csv = (string) $response->streamedContent();
        $this->assertStringContainsString('Ada Lovelace', $csv);
        $this->assertStringContainsString('SW1A 1AA', $csv);
        $this->assertStringNotContainsString('Other Tenant', $csv);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function insertDonation(int $tenantId, array $overrides): void
    {
        DB::table('vol_donations')->insert(array_merge([
            'tenant_id' => $tenantId,
            'user_id' => null,
            'opportunity_id' => null,
            'giving_day_id' => null,
            'amount' => 10.00,
            'currency' => 'GBP',
            'payment_method' => 'stripe',
            'payment_reference' => null,
            'payment_route' => 'platform_default',
            'stripe_account_id' => null,
            'stripe_payment_intent_id' => uniqid('pi_admin_finance_', true),
            'message' => null,
            'is_anonymous' => false,
            'status' => 'completed',
            'fund_code' => 'general',
            'gift_aid_claim_status' => 'not_eligible',
            'created_at' => now(),
        ], $overrides));
    }

    private function enableDonationSupport(int $tenantId): void
    {
        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['member_premium'] = true;

        DB::table('tenants')->where('id', $tenantId)->update([
            'features' => json_encode($features),
        ]);

        TenantContext::setById($tenantId);
    }
}
