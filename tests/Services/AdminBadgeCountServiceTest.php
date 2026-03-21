<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Services\AdminBadgeCountService;
use App\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class AdminBadgeCountServiceTest extends TestCase
{
    private AdminBadgeCountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminBadgeCountService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AdminBadgeCountService::class));
    }

    public function testGetCountsReturnsArray(): void
    {
        $this->assertIsArray($this->service->getCounts());
    }

    public function testGetCountsReturnsCachedResultOnSecondCall(): void
    {
        $first = $this->service->getCounts();
        $second = $this->service->getCounts();
        $this->assertSame($first, $second);
    }

    public function testClearCacheResetsInternalState(): void
    {
        // Force population
        $this->service->getCounts();

        // Clear cache
        $this->service->clearCache();

        // After clearCache, the private $cachedCounts should be null
        $ref = new \ReflectionClass($this->service);
        $prop = $ref->getProperty('cachedCounts');
        $prop->setAccessible(true);
        $this->assertNull($prop->getValue($this->service));
    }

    public function testGetCountReturnsZeroForUnknownKey(): void
    {
        $this->assertSame(0, $this->service->getCount('nonexistent_key'));
    }

    public function testGetCountReturnsIntegerValue(): void
    {
        $result = $this->service->getCount('pending_users');
        $this->assertIsInt($result);
    }

    public function testGetCountsContainsExpectedKeys(): void
    {
        $counts = $this->service->getCounts();
        $expectedKeys = [
            'pending_users',
            'pending_listings',
            'pending_orgs',
            'fraud_alerts',
            'gdpr_requests',
            '404_errors',
            'pending_exchanges',
            'unreviewed_messages',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $counts, "Missing key: {$key}");
        }
    }

    public function testGetCountDelegatesToGetCounts(): void
    {
        // getCounts populates the cache, getCount reads from it
        $counts = $this->service->getCounts();
        foreach ($counts as $key => $value) {
            $this->assertSame($value, $this->service->getCount($key));
        }
    }

    public function testAllCountValuesAreIntegers(): void
    {
        $counts = $this->service->getCounts();
        foreach ($counts as $key => $value) {
            $this->assertIsInt($value, "Value for {$key} should be int");
        }
    }
}
