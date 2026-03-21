<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\VolunteerDonationService;

class VolunteerDonationServiceTest extends TestCase
{
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
