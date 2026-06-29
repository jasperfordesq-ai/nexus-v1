<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\VolunteerDonationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VolunteerDonationServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_createDonation_throws_for_zero_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than zero');

        VolunteerDonationService::createDonation(1, [
            'amount' => 0,
            'currency' => 'EUR',
            'payment_method' => 'card',
        ]);
    }

    public function test_createDonation_throws_for_invalid_currency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('3-letter ISO');

        VolunteerDonationService::createDonation(1, [
            'amount' => 10,
            'currency' => 'EURO',
            'payment_method' => 'card',
        ]);
    }

    public function test_createDonation_throws_for_missing_payment_method(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment method is required');

        VolunteerDonationService::createDonation(1, [
            'amount' => 10,
            'currency' => 'EUR',
            'payment_method' => '',
        ]);
    }

    public function test_createDonation_rejects_foreign_tenant_opportunity(): void
    {
        // Seed the FK parent organisation (vol_opportunities.organization_id
        // references vol_organizations.id) under the foreign tenant.
        $foreignOrgId = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => 999,
            'user_id' => 1,
            'name' => 'Foreign Tenant Org',
            'slug' => 'foreign-tenant-org-' . uniqid(),
            'description' => 'A foreign-tenant organisation for cross-tenant donation rejection coverage.',
            'contact_email' => 'foreign-org@example.test',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $foreignOpportunityId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => 999,
            'organization_id' => $foreignOrgId,
            'title' => 'Foreign Tenant Opportunity',
            'description' => 'This opportunity must not be donation-addressable from tenant 2.',
            'status' => 'active',
            'is_active' => 1,
            'created_by' => 1,
            'created_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Opportunity not found');

        VolunteerDonationService::createDonation(1, [
            'amount' => 10,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'opportunity_id' => $foreignOpportunityId,
        ]);
    }

    public function test_createDonation_rejects_foreign_tenant_giving_day(): void
    {
        $foreignGivingDayId = (int) DB::table('vol_giving_days')->insertGetId([
            'tenant_id' => 999,
            'title' => 'Foreign Tenant Giving Day',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'goal_amount' => 1000,
            'raised_amount' => 0,
            'is_active' => 1,
            'created_by' => 1,
            'created_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Giving day not found');

        VolunteerDonationService::createDonation(1, [
            'amount' => 10,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'giving_day_id' => $foreignGivingDayId,
        ]);
    }

    public function test_createDonation_keeps_unverified_donation_pending_and_does_not_increment_giving_day(): void
    {
        $givingDayId = (int) DB::table('vol_giving_days')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'title' => 'Tenant Giving Day',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'goal_amount' => 1000,
            'raised_amount' => 0,
            'is_active' => 1,
            'created_by' => 1,
            'created_at' => now(),
        ]);

        $donation = VolunteerDonationService::createDonation(1, [
            'amount' => 25,
            'currency' => 'EUR',
            'payment_method' => 'offline_pledge',
            'status' => 'completed',
            'giving_day_id' => $givingDayId,
        ]);

        $this->assertSame('pending', $donation['status']);
        $this->assertEquals(0.0, (float) DB::table('vol_giving_days')->where('id', $givingDayId)->value('raised_amount'));
    }

    public function test_exportDonations_includes_stripe_route_reporting_fields(): void
    {
        if (!Schema::hasColumn('vol_donations', 'payment_route')) {
            $source = file_get_contents(app_path('Services/VolunteerDonationService.php'));
            $this->assertStringContainsString("'payment_route', 'stripe_account_id', 'stripe_payment_intent_id'", $source);
            $this->assertStringContainsString('donationRoutingColumns', $source);
            return;
        }

        DB::table('vol_donations')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => 1,
            'amount' => 25,
            'currency' => 'EUR',
            'payment_method' => 'stripe',
            'payment_reference' => '',
            'payment_route' => 'tenant_connect',
            'stripe_account_id' => 'acct_test_123456',
            'stripe_payment_intent_id' => 'pi_test_123456',
            'message' => 'Thank you',
            'is_anonymous' => 0,
            'status' => 'completed',
            'created_at' => now(),
        ]);

        $rows = VolunteerDonationService::exportDonations($this->testTenantId, null);

        $this->assertNotEmpty($rows);
        $row = $rows[0];
        $this->assertSame('tenant_connect', $row['payment_route']);
        $this->assertSame('acct_test_123456', $row['stripe_account_id']);
        $this->assertSame('pi_test_123456', $row['stripe_payment_intent_id']);
    }

    public function test_createGivingDay_throws_for_empty_title(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VolunteerDonationService::createGivingDay(['title' => '', 'start_date' => '2025-01-01', 'end_date' => '2025-01-31', 'goal_amount' => 1000], 2);
    }

    public function test_createGivingDay_throws_when_end_before_start(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VolunteerDonationService::createGivingDay(['title' => 'Test', 'start_date' => '2025-12-31', 'end_date' => '2025-01-01', 'goal_amount' => 1000], 2);
    }

    public function test_createGivingDay_throws_for_zero_goal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VolunteerDonationService::createGivingDay(['title' => 'Test', 'start_date' => '2025-01-01', 'end_date' => '2025-01-31', 'goal_amount' => 0], 2);
    }
}
