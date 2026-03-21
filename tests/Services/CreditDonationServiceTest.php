<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\CreditDonationService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CreditDonationService Tests
 *
 * Tests credit donation workflow: donate, getDonations, getTotalDonated.
 */
class CreditDonationServiceTest extends TestCase
{
    private function svc(): CreditDonationService
    {
        return new CreditDonationService();
    }

    public function test_donate_rejects_zero_amount(): void
    {
        $result = $this->svc()->donate(2, 1, 2, 0.0);
        $this->assertFalse($result, 'Zero amount should be rejected');
    }

    public function test_donate_rejects_negative_amount(): void
    {
        $result = $this->svc()->donate(2, 1, 2, -5.0);
        $this->assertFalse($result, 'Negative amount should be rejected');
    }

    public function test_donate_rejects_self_donation(): void
    {
        $result = $this->svc()->donate(2, 1, 1, 10.0);
        $this->assertFalse($result, 'Self-donation should be rejected');
    }

    public function test_donate_rejects_nonexistent_donor(): void
    {
        $result = $this->svc()->donate(2, 999999, 1, 10.0);
        $this->assertFalse($result, 'Nonexistent donor should be rejected');
    }

    public function test_get_donations_returns_array(): void
    {
        $result = $this->svc()->getDonations(2, 999999, 'sent');
        $this->assertIsArray($result);
    }

    public function test_get_donations_supports_sent_direction(): void
    {
        $result = $this->svc()->getDonations(2, 999999, 'sent');
        $this->assertIsArray($result);
    }

    public function test_get_donations_supports_received_direction(): void
    {
        $result = $this->svc()->getDonations(2, 999999, 'received');
        $this->assertIsArray($result);
    }

    public function test_get_total_donated_returns_float(): void
    {
        $result = $this->svc()->getTotalDonated(2, 999999);
        $this->assertIsFloat($result);
    }

    public function test_get_total_donated_returns_zero_for_nonexistent_user(): void
    {
        $result = $this->svc()->getTotalDonated(2, 999999);
        $this->assertSame(0.0, $result);
    }
}
