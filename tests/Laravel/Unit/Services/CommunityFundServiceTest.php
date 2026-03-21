<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CommunityFundService;
use App\Models\CommunityFundAccount;
use Illuminate\Support\Facades\DB;
use Mockery;

class CommunityFundServiceTest extends TestCase
{
    public function test_adminDeposit_returns_error_when_amount_zero_or_negative(): void
    {
        $result = CommunityFundService::adminDeposit(1, 0);
        $this->assertFalse($result['success']);
        $this->assertSame('Amount must be greater than 0', $result['error']);

        $result2 = CommunityFundService::adminDeposit(1, -5);
        $this->assertFalse($result2['success']);
    }

    public function test_adminWithdraw_returns_error_when_amount_zero_or_negative(): void
    {
        $result = CommunityFundService::adminWithdraw(1, 2, 0);
        $this->assertFalse($result['success']);
    }

    public function test_receiveDonation_returns_error_when_amount_zero(): void
    {
        $result = CommunityFundService::receiveDonation(1, 0);
        $this->assertFalse($result['success']);
    }

    public function test_getBalance_returns_expected_keys(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getOrCreateFund_creates_when_none_exists(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_adminWithdraw_returns_error_for_insufficient_balance(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }
}
