<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\StartingBalanceService;
use App\Services\TenantSettingsService;
use Illuminate\Support\Facades\DB;
use Mockery;

class StartingBalanceServiceTest extends TestCase
{
    // ── getStartingBalance ──

    public function test_getStartingBalance_returns_float(): void
    {
        $result = StartingBalanceService::getStartingBalance();
        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(0.0, $result);
    }

    // ── setStartingBalance ──

    public function test_setStartingBalance_clamps_to_zero(): void
    {
        // Mock TenantSettingsService instance to capture value via container binding
        $mock = Mockery::mock(TenantSettingsService::class);
        $mock->shouldReceive('set')->withArgs(function ($tid, $key, $val, $type) {
            return (float) $val >= 0.0;
        })->once();
        $this->app->instance(TenantSettingsService::class, $mock);

        StartingBalanceService::setStartingBalance(-10.0);
    }

    // ── applyToNewUser ──

    public function test_applyToNewUser_returns_success_when_balance_zero(): void
    {
        // Mock TenantSettingsService to return '0' for starting balance
        $mock = Mockery::mock(TenantSettingsService::class);
        $mock->shouldReceive('get')->andReturn('0');
        $this->app->instance(TenantSettingsService::class, $mock);

        // We can test indirectly - if starting balance is 0, should return early
        $result = StartingBalanceService::applyToNewUser(1);
        $this->assertTrue($result['success']);
        $this->assertIsFloat($result['amount']);
    }

    public function test_applyToNewUser_skips_if_already_applied(): void
    {
        DB::shouldReceive('selectOne')->andReturn((object) ['id' => 1]);

        // Mock TenantSettingsService to return non-zero starting balance
        $mock = Mockery::mock(TenantSettingsService::class);
        $mock->shouldReceive('get')->andReturn('10');
        $this->app->instance(TenantSettingsService::class, $mock);

        $result = StartingBalanceService::applyToNewUser(1);
        // If already applied or balance is 0, success is true
        $this->assertTrue($result['success']);
    }
}
