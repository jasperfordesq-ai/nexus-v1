<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\OrgWalletService;
use Illuminate\Support\Facades\DB;
use Mockery;

class OrgWalletServiceTest extends TestCase
{
    private OrgWalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrgWalletService();
    }

    // ── getBalance ──

    public function test_getBalance_returns_zero_when_org_not_found(): void
    {
        DB::shouldReceive('table->where->where->first')->andReturnNull();
        $result = $this->service->getBalance(9999, $this->testTenantId);
        $this->assertEquals(0.0, $result['balance']);
        $this->assertEquals(9999, $result['org_id']);
    }

    public function test_getBalance_returns_balance_and_name(): void
    {
        $org = (object) ['id' => 1, 'name' => 'Test Org', 'balance' => '25.50'];
        DB::shouldReceive('table->where->where->first')->andReturn($org);
        $result = $this->service->getBalance(1, $this->testTenantId);
        $this->assertEquals(25.50, $result['balance']);
        $this->assertEquals('Test Org', $result['name']);
    }

    // ── getTransactions ──

    public function test_getTransactions_returns_items_and_total(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->twice()->andReturnSelf();
        $mockQuery->shouldReceive('count')->andReturn(2);
        $mockQuery->shouldReceive('orderByDesc->offset->limit->get->map->all')->andReturn([]);

        DB::shouldReceive('table')->with('org_transactions')->andReturn($mockQuery);

        $result = $this->service->getTransactions(1, $this->testTenantId);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
    }

    // ── transfer ──

    public function test_transfer_rejects_zero_amount(): void
    {
        $result = $this->service->transfer(1, 2, 0, $this->testTenantId);
        $this->assertFalse($result);
    }

    public function test_transfer_rejects_same_org(): void
    {
        $result = $this->service->transfer(1, 1, 10.0, $this->testTenantId);
        $this->assertFalse($result);
    }

    public function test_transfer_rejects_negative_amount(): void
    {
        $result = $this->service->transfer(1, 2, -5.0, $this->testTenantId);
        $this->assertFalse($result);
    }

    // ── depositToOrg ──

    public function test_depositToOrg_rejects_zero_amount(): void
    {
        $result = OrgWalletService::depositToOrg(1, 1, 0);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('greater than zero', $result['message']);
    }

    public function test_depositToOrg_rejects_non_member(): void
    {
        DB::shouldReceive('table->where->where->where->first')->andReturnNull();
        $result = OrgWalletService::depositToOrg(1, 1, 10.0);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not an active member', $result['message']);
    }

    // ── createTransferRequest ──

    public function test_createTransferRequest_rejects_zero_amount(): void
    {
        $result = OrgWalletService::createTransferRequest(1, 1, 2, 0);
        $this->assertFalse($result['success']);
    }

    // ── cancelRequest ──

    public function test_cancelRequest_fails_when_not_found(): void
    {
        DB::shouldReceive('table->where->first')->andReturnNull();
        $result = OrgWalletService::cancelRequest(999, 1);
        $this->assertFalse($result['success']);
    }

    public function test_cancelRequest_fails_for_wrong_requester(): void
    {
        $request = (object) ['id' => 1, 'status' => 'pending', 'requester_id' => 5];
        DB::shouldReceive('table->where->first')->andReturn($request);
        $result = OrgWalletService::cancelRequest(1, 99);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Only the requester', $result['message']);
    }

    // ── getWalletSummary ──

    public function test_getWalletSummary_returns_expected_keys(): void
    {
        DB::shouldReceive('table->where->first')->andReturnNull();
        DB::shouldReceive('table->where->where->sum')->andReturn(0);
        DB::shouldReceive('table->where->where->sum')->andReturn(0);
        DB::shouldReceive('table->where->count')->andReturn(0);
        DB::shouldReceive('table->where->where->count')->andReturn(0);

        $result = OrgWalletService::getWalletSummary(1);
        $this->assertArrayHasKey('balance', $result);
        $this->assertArrayHasKey('total_received', $result);
        $this->assertArrayHasKey('total_paid_out', $result);
        $this->assertArrayHasKey('transaction_count', $result);
        $this->assertArrayHasKey('pending_requests', $result);
    }
}
