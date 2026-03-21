<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationSearchService;
use Illuminate\Support\Facades\Cache;

class FederationSearchServiceTest extends TestCase
{
    private FederationSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FederationSearchService();
    }

    public function test_searchMembers_returns_empty_for_no_partner_tenants(): void
    {
        $result = $this->service->searchMembers([], []);
        $this->assertEquals([], $result['members']);
        $this->assertEquals(0, $result['total']);
        $this->assertFalse($result['has_more']);
    }

    public function test_cachedSearchMembers_returns_empty_for_no_tenants(): void
    {
        $result = $this->service->cachedSearchMembers([], []);
        $this->assertEquals([], $result['members']);
    }

    public function test_cachedSearchMembers_returns_cached_result(): void
    {
        $cached = ['members' => [['id' => 1]], 'total' => 1, 'has_more' => false, 'filters_applied' => []];
        Cache::shouldReceive('get')->andReturn($cached);

        $result = $this->service->cachedSearchMembers([1, 2], []);
        $this->assertTrue($result['from_cache']);
        $this->assertCount(1, $result['members']);
    }

    public function test_cachedSearchMembers_falls_through_when_cache_unavailable(): void
    {
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('Cache down'));
        Cache::shouldReceive('put')->andThrow(new \RuntimeException('Cache down'));

        $this->markTestIncomplete('Requires DB mocking for full searchMembers query');
    }
}
