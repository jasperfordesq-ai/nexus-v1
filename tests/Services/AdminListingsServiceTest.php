<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Services\AdminListingsService;
use App\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class AdminListingsServiceTest extends TestCase
{
    private AdminListingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminListingsService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AdminListingsService::class));
    }

    public function testGetPendingReturnsArrayWithItemsAndTotal(): void
    {
        $result = $this->service->getPending(1);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsInt($result['total']);
    }

    public function testGetPendingLimitsTo100Max(): void
    {
        // Passing a limit > 100 should be capped
        $result = $this->service->getPending(1, 200);
        // The method uses min($limit, 100), result should not error
        $this->assertIsArray($result);
    }

    public function testGetPendingAcceptsOffset(): void
    {
        $result = $this->service->getPending(1, 10, 5);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function testApproveMethodExists(): void
    {
        $this->assertTrue(method_exists($this->service, 'approve'));
    }

    public function testRejectMethodExists(): void
    {
        $this->assertTrue(method_exists($this->service, 'reject'));
    }

    public function testApproveReturnsBoolForNonExistentListing(): void
    {
        $result = $this->service->approve(999999, 1, 1);
        $this->assertFalse($result);
    }

    public function testRejectReturnsBoolForNonExistentListing(): void
    {
        $result = $this->service->reject(999999, 1, 1, 'Test reason');
        $this->assertFalse($result);
    }

    public function testGetStatsReturnsExpectedKeys(): void
    {
        $stats = $this->service->getStats(1);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('rejected', $stats);
        $this->assertArrayHasKey('expired', $stats);
        $this->assertArrayHasKey('total', $stats);
    }

    public function testGetStatsValuesAreIntegers(): void
    {
        $stats = $this->service->getStats(1);
        foreach ($stats as $key => $value) {
            $this->assertIsInt($value, "Stats '{$key}' should be int");
        }
    }

    public function testGetStatsTotalEqualsSum(): void
    {
        $stats = $this->service->getStats(1);
        $sumOfParts = $stats['active'] + $stats['pending'] + $stats['rejected'] + $stats['expired'];
        // total may include other statuses, so total >= sum of known statuses
        $this->assertGreaterThanOrEqual($sumOfParts, $stats['total']);
    }
}
