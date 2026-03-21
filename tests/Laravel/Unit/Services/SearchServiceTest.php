<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SearchService;
use App\Models\User;
use App\Models\Listing;
use App\Models\Event;
use App\Models\Group;
use Mockery;

class SearchServiceTest extends TestCase
{
    private SearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SearchService(
            new User(),
            new Listing(),
            new Event(),
            new Group(),
        );
    }

    // ── isAvailable ──

    public function test_isAvailable_returns_false(): void
    {
        $this->assertFalse($this->service->isAvailable());
    }

    // ── suggestions ──

    public function test_suggestions_returns_empty_for_short_term(): void
    {
        $result = $this->service->suggestions('a');
        $this->assertEquals([], $result['listings']);
        $this->assertEquals([], $result['users']);
        $this->assertEquals([], $result['events']);
        $this->assertEquals([], $result['groups']);
    }

    // ── search ──

    public function test_search_returns_array_keys_for_all_types(): void
    {
        $result = $this->service->search('test', null, 5);
        $this->assertArrayHasKey('users', $result);
        $this->assertArrayHasKey('listings', $result);
        $this->assertArrayHasKey('events', $result);
        $this->assertArrayHasKey('groups', $result);
    }

    public function test_search_filters_by_single_type(): void
    {
        $result = $this->service->search('test', 'users', 5);
        $this->assertArrayHasKey('users', $result);
        $this->assertArrayNotHasKey('listings', $result);
        $this->assertArrayNotHasKey('events', $result);
        $this->assertArrayNotHasKey('groups', $result);
    }

    // ── unifiedSearch ──

    public function test_unifiedSearch_returns_expected_structure(): void
    {
        $result = $this->service->unifiedSearch('test', null, ['limit' => 5]);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('query', $result);
        $this->assertEquals('test', $result['query']);
    }

    public function test_unifiedSearch_caps_limit_at_50(): void
    {
        $result = $this->service->unifiedSearch('test', null, ['limit' => 200]);
        // Should not crash, limit is internally capped
        $this->assertIsArray($result['items']);
    }

    // ── trending ──

    public function test_trending_returns_array(): void
    {
        $result = $this->service->trending();
        $this->assertIsArray($result);
    }

    // ── indexListing / removeListing ──

    public function test_indexListing_is_noop(): void
    {
        $listing = Mockery::mock(Listing::class);
        $this->service->indexListing($listing);
        $this->assertTrue(true); // no exception
    }

    public function test_removeListing_is_noop(): void
    {
        $this->service->removeListing(1);
        $this->assertTrue(true);
    }
}
