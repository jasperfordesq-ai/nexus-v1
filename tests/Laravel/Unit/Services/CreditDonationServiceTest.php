<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CreditDonationService;
use App\Models\User;
use App\Models\CreditDonation;
use Illuminate\Support\Facades\DB;
use Mockery;

class CreditDonationServiceTest extends TestCase
{
    private CreditDonationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CreditDonationService();
    }

    public function test_donate_returns_false_when_amount_zero(): void
    {
        $result = $this->service->donate(2, 1, 2, 0);
        $this->assertFalse($result);
    }

    public function test_donate_returns_false_when_amount_negative(): void
    {
        $result = $this->service->donate(2, 1, 2, -5);
        $this->assertFalse($result);
    }

    public function test_donate_returns_false_when_same_user(): void
    {
        $result = $this->service->donate(2, 1, 1, 10);
        $this->assertFalse($result);
    }

    public function test_donate_returns_false_when_donor_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_donate_returns_false_when_insufficient_balance(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_donate_returns_false_when_recipient_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getDonations_sent_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getTotalDonated_returns_float(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }
}
