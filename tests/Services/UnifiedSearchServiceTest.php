<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Services\UnifiedSearchService;
use Nexus\Core\TenantContext;
use Nexus\Core\Database;
use Nexus\Tests\DatabaseTestCase;

/**
 * UnifiedSearchService Tests
 *
 * Tests unified search across listings, users, events, and groups.
 */
class UnifiedSearchServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testListingId = null;
    protected static ?int $testEventId = null;
    protected static ?int $testGroupId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $ts = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "searchsvc_{$ts}@test.com", "searchsvc_{$ts}", 'SearchJohn', 'SearchDoe', 'SearchJohn SearchDoe']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, ?, ?, 'offer', 'active', NOW())",
            [self::$testTenantId, self::$testUserId, "SearchGardening Services {$ts}", 'Test description for search']
        );
        self::$testListingId = (int)Database::getInstance()->lastInsertId();

        // Create test event
        Database::query(
            "INSERT INTO events (tenant_id, user_id, title, description, start_time, end_time, created_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 8 DAY), NOW())",
            [self::$testTenantId, self::$testUserId, "SearchCommunity Meetup {$ts}", 'Test event for search']
        );
        self::$testEventId = (int)Database::getInstance()->lastInsertId();

        // Create test group
        Database::query(
            "INSERT INTO `groups` (tenant_id, owner_id, name, description, visibility, created_at)
             VALUES (?, ?, ?, ?, 'public', NOW())",
            [self::$testTenantId, self::$testUserId, "SearchLocal Gardeners {$ts}", 'Test group for search']
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testGroupId) {
            try {
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testGroupId]);
            } catch (\Exception $e) {}
        }
        if (self::$testEventId) {
            try {
                Database::query("DELETE FROM events WHERE id = ?", [self::$testEventId]);
            } catch (\Exception $e) {}
        }
        if (self::$testListingId) {
            try {
                Database::query("DELETE FROM listings WHERE id = ?", [self::$testListingId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Search Structure Tests
    // ==========================================

    public function testSearchReturnsValidStructure(): void
    {
        $result = UnifiedSearchService::search('search', null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testSearchReturnsListings(): void
    {
        $result = UnifiedSearchService::search('SearchGardening', null);

        $this->assertArrayHasKey('items', $result);
        $this->assertGreaterThan(0, count($result['items']));

        $listing = array_values(array_filter($result['items'], fn($item) => $item['type'] === 'listing'))[0] ?? null;
        $this->assertNotNull($listing, 'Expected to find a listing in search results');
    }

    public function testSearchReturnsUsers(): void
    {
        $result = UnifiedSearchService::search('SearchJohn', null);

        $this->assertArrayHasKey('items', $result);
        $user = array_values(array_filter($result['items'], fn($item) => $item['type'] === 'user'))[0] ?? null;
        $this->assertNotNull($user, 'Expected to find a user in search results');
    }

    public function testSearchReturnsEvents(): void
    {
        $result = UnifiedSearchService::search('SearchCommunity', null);

        $this->assertArrayHasKey('items', $result);
        $event = array_values(array_filter($result['items'], fn($item) => $item['type'] === 'event'))[0] ?? null;
        $this->assertNotNull($event, 'Expected to find an event in search results');
    }

    public function testSearchReturnsGroups(): void
    {
        $result = UnifiedSearchService::search('SearchLocal', null);

        $this->assertArrayHasKey('items', $result);
        $group = array_values(array_filter($result['items'], fn($item) => $item['type'] === 'group'))[0] ?? null;
        $this->assertNotNull($group, 'Expected to find a group in search results');
    }

    // ==========================================
    // Filter Tests
    // ==========================================

    public function testSearchWithTypeFilterListings(): void
    {
        $result = UnifiedSearchService::search('SearchGardening', null, ['type' => 'listings']);

        foreach ($result['items'] as $item) {
            $this->assertEquals('listing', $item['type']);
        }
    }

    public function testSearchWithTypeFilterUsers(): void
    {
        $result = UnifiedSearchService::search('SearchJohn', null, ['type' => 'users']);

        foreach ($result['items'] as $item) {
            $this->assertEquals('user', $item['type']);
        }
    }

    // ==========================================
    // Validation Tests
    // ==========================================

    public function testSearchWithShortQuery(): void
    {
        $result = UnifiedSearchService::search('a', null);

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(0, $result['items']);
        $this->assertNotEmpty(UnifiedSearchService::getErrors());
    }

    // ==========================================
    // Suggestions Tests
    // ==========================================

    public function testGetSuggestionsReturnsStructure(): void
    {
        $suggestions = UnifiedSearchService::getSuggestions('SearchGardening', self::$testTenantId);

        $this->assertIsArray($suggestions);
        $this->assertArrayHasKey('listings', $suggestions);
        $this->assertArrayHasKey('users', $suggestions);
    }

    public function testGetSuggestionsWithShortQuery(): void
    {
        $suggestions = UnifiedSearchService::getSuggestions('a', self::$testTenantId);

        $this->assertEmpty($suggestions['listings']);
        $this->assertEmpty($suggestions['users']);
    }
}
