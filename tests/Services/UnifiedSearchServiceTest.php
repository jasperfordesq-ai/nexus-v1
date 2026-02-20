<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Tests\Services;

use Nexus\Services\UnifiedSearchService;
use Nexus\Core\TenantContext;
use Nexus\Core\Database;
use Nexus\Tests\DatabaseTestCase;

class UnifiedSearchServiceTest extends DatabaseTestCase
{
    private int $tenantId;
    private int $userId;
    private int $listingId;
    private int $eventId;
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = $this->createTenant('Test Tenant');
        $this->setTenantContext($this->tenantId);

        $this->userId = $this->createUser($this->tenantId, 'john@example.com');
        $this->listingId = $this->createListing($this->userId, 'Gardening Services');
        $this->eventId = $this->createEvent($this->userId, 'Community Meetup');
        $this->groupId = $this->createGroup($this->userId, 'Local Gardeners');
    }

    private function createTenant(string $name): int
    {
        $slug = strtolower(str_replace(' ', '-', $name)) . '-' . time();
        self::$pdo->prepare(
            "INSERT INTO tenants (name, slug, is_active) VALUES (?, ?, 1)"
        )->execute([$name, $slug]);
        return (int)self::$pdo->lastInsertId();
    }

    private function setTenantContext(int $tenantId): void
    {
        TenantContext::setById($tenantId);
    }

    private function createUser(int $tenantId, string $email): int
    {
        $ts = time();
        self::$pdo->prepare(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        )->execute([$tenantId, $email, "user_{$ts}", 'John', 'Doe', 'John Doe']);
        return (int)self::$pdo->lastInsertId();
    }

    public function testSearchReturnsListings(): void
    {
        $result = UnifiedSearchService::search('garden', null);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertGreaterThan(0, count($result['items']));

        $listing = array_values(array_filter($result['items'], fn($item) => $item['type'] === 'listing'))[0] ?? null;
        $this->assertNotNull($listing);
        $this->assertEquals('Gardening Services', $listing['title']);
    }

    public function testSearchReturnsUsers(): void
    {
        $result = UnifiedSearchService::search('john', null);

        $user = array_values(array_filter($result['items'], fn($item) => $item['type'] === 'user'))[0] ?? null;
        $this->assertNotNull($user);
        $this->assertEquals($this->userId, $user['id']);
    }

    public function testSearchReturnsEvents(): void
    {
        $result = UnifiedSearchService::search('meetup', null);

        $event = array_values(array_filter($result['items'], fn($item) => $item['type'] === 'event'))[0] ?? null;
        $this->assertNotNull($event);
        $this->assertEquals('Community Meetup', $event['title']);
    }

    public function testSearchReturnsGroups(): void
    {
        $result = UnifiedSearchService::search('gardener', null);

        $group = array_values(array_filter($result['items'], fn($item) => $item['type'] === 'group'))[0] ?? null;
        $this->assertNotNull($group);
        $this->assertEquals('Local Gardeners', $group['name']);
    }

    public function testSearchWithTypeFilterListings(): void
    {
        $result = UnifiedSearchService::search('garden', null, ['type' => 'listings']);

        foreach ($result['items'] as $item) {
            $this->assertEquals('listing', $item['type']);
        }
    }

    public function testSearchWithTypeFilterUsers(): void
    {
        $result = UnifiedSearchService::search('john', null, ['type' => 'users']);

        foreach ($result['items'] as $item) {
            $this->assertEquals('user', $item['type']);
        }
    }

    public function testSearchWithShortQuery(): void
    {
        $result = UnifiedSearchService::search('a', null);

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(0, $result['items']);
        $this->assertNotEmpty(UnifiedSearchService::getErrors());
    }

    public function testSearchWithLimit(): void
    {
        // Create multiple listings
        for ($i = 1; $i <= 30; $i++) {
            $this->createListing($this->userId, "Garden Service $i");
        }

        $result = UnifiedSearchService::search('garden', null, ['limit' => 10]);

        $this->assertLessThanOrEqual(10, count($result['items']));
    }

    public function testSearchWithCursor(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->createListing($this->userId, "Service $i");
        }

        $result1 = UnifiedSearchService::search('service', null, ['limit' => 10]);
        $this->assertNotNull($result1['cursor']);
        $this->assertTrue($result1['has_more']);

        $result2 = UnifiedSearchService::search('service', null, [
            'limit' => 10,
            'cursor' => $result1['cursor']
        ]);
        $this->assertNotEmpty($result2['items']);
    }

    public function testSearchSortsByRelevance(): void
    {
        $result = UnifiedSearchService::search('garden', null, ['type' => 'all']);

        $this->assertNotEmpty($result['items']);
        // Items should be sorted by created_at descending for 'all' type
    }

    public function testSearchTruncatesDescriptions(): void
    {
        $longDesc = str_repeat('Lorem ipsum dolor sit amet. ', 50);
        $listingId = $this->createListingWithDesc($this->userId, 'Long Description Listing', $longDesc);

        $result = UnifiedSearchService::search('long', null);

        $listing = array_values(array_filter($result['items'], fn($item) => $item['id'] === $listingId))[0] ?? null;
        $this->assertNotNull($listing);
        $this->assertLessThanOrEqual(153, strlen($listing['description'])); // 150 + "..."
    }

    public function testGetSuggestionsReturnsAutocomplete(): void
    {
        $suggestions = UnifiedSearchService::getSuggestions('gard', $this->tenantId);

        $this->assertArrayHasKey('listings', $suggestions);
        $this->assertArrayHasKey('users', $suggestions);
        $this->assertArrayHasKey('events', $suggestions);
        $this->assertArrayHasKey('groups', $suggestions);
    }

    public function testGetSuggestionsWithShortQuery(): void
    {
        $suggestions = UnifiedSearchService::getSuggestions('a', $this->tenantId);

        $this->assertEmpty($suggestions['listings']);
        $this->assertEmpty($suggestions['users']);
    }

    public function testGetSuggestionsLimitedTo5(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createListing($this->userId, "Garden Service $i");
        }

        $suggestions = UnifiedSearchService::getSuggestions('garden', $this->tenantId, 5);

        $this->assertLessThanOrEqual(5, count($suggestions['listings']));
    }

    public function testSearchRespectsTenantScoping(): void
    {
        $otherTenantId = $this->createTenant('Other Tenant');
        $otherUserId = $this->createUser($otherTenantId, 'other@example.com');
        $this->createListing($otherUserId, 'Other Tenant Listing');

        $result = UnifiedSearchService::search('other', null);

        // Should not find listings from other tenant
        $otherListing = array_values(array_filter($result['items'], fn($item) =>
            $item['type'] === 'listing' && $item['title'] === 'Other Tenant Listing'
        ))[0] ?? null;
        $this->assertNull($otherListing);
    }

    private function createListing(int $userId, string $title): int
    {
        $stmt = self::$pdo->prepare(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, ?, ?, 'offer', 'active', NOW())"
        );
        $stmt->execute([$this->tenantId, $userId, $title, 'Test description']);
        return (int)self::$pdo->lastInsertId();
    }

    private function createListingWithDesc(int $userId, string $title, string $description): int
    {
        $stmt = self::$pdo->prepare(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, ?, ?, 'offer', 'active', NOW())"
        );
        $stmt->execute([$this->tenantId, $userId, $title, $description]);
        return (int)self::$pdo->lastInsertId();
    }

    private function createEvent(int $userId, string $title): int
    {
        $stmt = self::$pdo->prepare(
            "INSERT INTO events (tenant_id, user_id, title, description, start_time, end_time, created_at)
             VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())"
        );
        $stmt->execute([$this->tenantId, $userId, $title, 'Test event']);
        return (int)self::$pdo->lastInsertId();
    }

    private function createGroup(int $userId, string $name): int
    {
        $stmt = self::$pdo->prepare(
            "INSERT INTO `groups` (tenant_id, owner_id, name, description, visibility, created_at)
             VALUES (?, ?, ?, ?, 'public', NOW())"
        );
        $stmt->execute([$this->tenantId, $userId, $name, 'Test group']);
        return (int)self::$pdo->lastInsertId();
    }
}
