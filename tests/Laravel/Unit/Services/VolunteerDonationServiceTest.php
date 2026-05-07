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
        $foreignOpportunityId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => 999,
            'organization_id' => 1,
            'title' => 'Foreign Tenant Opportunity',
            'description' => 'This opportunity must not be donation-addressable from tenant 2.',
            'status' => 'active',
            'is_active' => 1,
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
