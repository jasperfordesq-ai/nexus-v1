<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\AdminBadgeCountService;
use Illuminate\Support\Facades\DB;

class AdminBadgeCountServiceTest extends TestCase
{
    private AdminBadgeCountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminBadgeCountService();
    }

    public function test_getCounts_returns_expected_keys(): void
    {
        $this->markTestIncomplete('Requires integration test — many DB::table calls with different tables');
    }

    public function test_getCount_returns_zero_for_unknown_key(): void
    {
        // Force cached counts with an empty array
        $reflection = new \ReflectionClass($this->service);
        $prop = $reflection->getProperty('cachedCounts');
        $prop->setAccessible(true);
        $prop->setValue($this->service, ['pending_users' => 5]);

        $this->assertSame(0, $this->service->getCount('nonexistent'));
    }

    public function test_getCount_returns_cached_value(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $prop = $reflection->getProperty('cachedCounts');
        $prop->setAccessible(true);
        $prop->setValue($this->service, ['pending_users' => 7]);

        $this->assertSame(7, $this->service->getCount('pending_users'));
    }

    public function test_clearCache_resets_cached_counts(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $prop = $reflection->getProperty('cachedCounts');
        $prop->setAccessible(true);
        $prop->setValue($this->service, ['pending_users' => 5]);

        $this->service->clearCache();

        $this->assertNull($prop->getValue($this->service));
    }

    public function test_getCounts_caches_result_for_request_lifetime(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $prop = $reflection->getProperty('cachedCounts');
        $prop->setAccessible(true);
        $prop->setValue($this->service, ['pending_users' => 3, 'fraud_alerts' => 1]);

        // Second call should return cached value without DB queries
        $counts = $this->service->getCounts();
        $this->assertSame(3, $counts['pending_users']);
        $this->assertSame(1, $counts['fraud_alerts']);
    }
}
