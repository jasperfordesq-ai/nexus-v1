<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\TenantSettingsService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression: the per-tenant wallet.max_transfer limit must be a single source
 * of truth. Previously WalletController::config() advertised the tenant setting
 * to the UI while WalletService::transfer() hardcoded the 1000 ceiling, so a
 * stricter tenant cap was shown but silently not enforced (a direct API call
 * could exceed it). Both now resolve through WalletService::maxTransferAmount().
 *
 * Whole-hour amounts only (balance/amount can be INT-typed on nexus_test).
 */
class WalletMaxTransferTest extends TestCase
{
    use DatabaseTransactions;

    private WalletService $service;
    private TenantSettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WalletService::class);
        $this->settings = app(TenantSettingsService::class);
        Cache::flush();
        // Start from a clean slate so a leftover setting can't skew assertions.
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'wallet.max_transfer')
            ->delete();
        $this->settings->clearCacheForTenant($this->testTenantId);
    }

    public function test_transfer_rejects_amount_above_tenant_configured_max(): void
    {
        $this->settings->set($this->testTenantId, 'wallet.max_transfer', '50', 'integer');
        [$sender, $receiver] = $this->makePair(100, 0);

        TenantContext::setById($this->testTenantId);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(__('api.wallet_transfer_amount_max', ['max' => 50]));

        $this->service->transfer($sender->id, [
            'recipient'   => $receiver->id,
            'amount'      => 60,
            'description' => 'over the tenant cap',
        ]);
    }

    public function test_transfer_allows_amount_up_to_tenant_configured_max(): void
    {
        $this->settings->set($this->testTenantId, 'wallet.max_transfer', '50', 'integer');
        [$sender, $receiver] = $this->makePair(100, 0);

        TenantContext::setById($this->testTenantId);
        $result = $this->service->transfer($sender->id, [
            'recipient'   => $receiver->id,
            'amount'      => 40,
            'description' => 'within the tenant cap',
        ]);

        $this->assertArrayHasKey('id', $result);
    }

    public function test_config_advertises_the_same_cap_transfer_enforces(): void
    {
        // The heart of the bug: config() and transfer() must agree.
        $this->settings->set($this->testTenantId, 'wallet.max_transfer', '50', 'integer');
        $this->assertSame(50.0, $this->service->maxTransferAmount($this->testTenantId));
    }

    public function test_default_cap_is_the_platform_ceiling_when_unset(): void
    {
        // No setting → platform ceiling (matches the historical hardcoded 1000).
        $this->assertSame(1000.0, $this->service->maxTransferAmount($this->testTenantId));
    }

    public function test_tenant_setting_cannot_raise_the_cap_above_the_platform_ceiling(): void
    {
        // A setting above the ceiling is clamped DOWN, never honoured upward.
        $this->settings->set($this->testTenantId, 'wallet.max_transfer', '5000', 'integer');
        $this->assertSame(1000.0, $this->service->maxTransferAmount($this->testTenantId));

        [$sender, $receiver] = $this->makePair(100, 0);
        TenantContext::setById($this->testTenantId);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->transfer($sender->id, [
            'recipient'   => $receiver->id,
            'amount'      => 1500,
            'description' => 'above the platform ceiling',
        ]);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function makePair(float $senderBalance, float $receiverBalance): array
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create(['balance' => $senderBalance]);
        $receiver = User::factory()->forTenant($this->testTenantId)->create(['balance' => $receiverBalance]);

        return [$sender, $receiver];
    }
}
