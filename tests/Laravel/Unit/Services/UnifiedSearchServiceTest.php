<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\SearchService;
use App\Core\TenantContext;
use App\Core\Database;
use App\Models\User;
use App\Models\Listing;
use App\Models\Event;
use App\Models\Group;
use Tests\Laravel\TestCase;

/**
 * Unified search tests.
 *
 * Exercises SearchService::unifiedSearch() / ::suggestions() — the real
 * cross-entity search across listings, users, events, and groups. The former
 * standalone `App\Services\UnifiedSearchService` was a dead delegation stub
 * (returned empty arrays + logged warnings) and was deleted during the Laravel
 * migration's 45-stub cleanup; this suite is repointed at the live service.
 *
 * Meilisearch is not running in the test environment, so SearchService falls
 * back to its SQL LIKE path — that is the code under test here.
 */
class UnifiedSearchServiceTest extends \Tests\Laravel\TestCase
{
    protected int $tenantId = 2;
    protected ?int $testUserId = null;
    protected ?int $testListingId = null;
    protected ?int $testEventId = null;
    protected ?int $testGroupId = null;
    protected string $token;
    protected SearchService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        // Factories/observers can reset TenantContext to tenant 1; pin tenant 2.
        TenantContext::setById($this->tenantId);

        $this->svc = new SearchService(new User(), new Listing(), new Event(), new Group());

        // Unique token so LIKE searches resolve to *our* seeded rows only.
        $this->token = 'Searchz' . substr(md5(uniqid('', true)), 0, 8);
        $this->createTestData();
    }

    protected function createTestData(): void
    {
        $t = $this->token;

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, status, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'active', 1, NOW())",
            [$this->tenantId, "{$t}@test.com", $t, "{$t}John", 'SearchDoe', "{$t}John SearchDoe"]
        );
        $this->testUserId = (int) Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, moderation_status, created_at)
             VALUES (?, ?, ?, ?, 'offer', 'active', 'approved', NOW())",
            [$this->tenantId, $this->testUserId, "{$t}Gardening Services", 'Test description for search']
        );
        $this->testListingId = (int) Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO events (tenant_id, user_id, title, description, start_time, end_time, created_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 8 DAY), NOW())",
            [$this->tenantId, $this->testUserId, "{$t}Community Meetup", 'Test event for search']
        );
        $this->testEventId = (int) Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO `groups` (tenant_id, owner_id, name, description, visibility, created_at)
             VALUES (?, ?, ?, ?, 'public', NOW())",
            [$this->tenantId, $this->testUserId, "{$t}Local Gardeners", 'Test group for search']
        );
        $this->testGroupId = (int) Database::getInstance()->lastInsertId();
    }

    protected function tearDown(): void
    {
        foreach ([
            ['groups', $this->testGroupId],
            ['events', $this->testEventId],
            ['listings', $this->testListingId],
            ['users', $this->testUserId],
        ] as [$table, $id]) {
            if ($id) {
                try {
                    Database::query("DELETE FROM `{$table}` WHERE id = ?", [$id]);
                } catch (\Exception $e) {
                }
            }
        }

        parent::tearDown();
    }

    // ==========================================
    // Structure
    // ==========================================

    public function testSearchReturnsValidStructure(): void
    {
        $result = $this->svc->unifiedSearch($this->token, null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('query', $result);
        $this->assertSame($this->token, $result['query']);
    }

    public function testSearchReturnsListings(): void
    {
        $result = $this->svc->unifiedSearch($this->token . 'Gardening', null);

        $listing = array_values(array_filter(
            $result['items'],
            fn ($item) => $item['type'] === 'listing'
        ))[0] ?? null;
        $this->assertNotNull($listing, 'Expected to find a listing in search results');
    }

    public function testSearchReturnsUsers(): void
    {
        $result = $this->svc->unifiedSearch($this->token . 'John', null);

        $user = array_values(array_filter(
            $result['items'],
            fn ($item) => $item['type'] === 'user'
        ))[0] ?? null;
        $this->assertNotNull($user, 'Expected to find a user in search results');
    }

    public function testSearchReturnsEvents(): void
    {
        $result = $this->svc->unifiedSearch($this->token . 'Community', null);

        $event = array_values(array_filter(
            $result['items'],
            fn ($item) => $item['type'] === 'event'
        ))[0] ?? null;
        $this->assertNotNull($event, 'Expected to find an event in search results');
    }

    public function testSearchReturnsGroups(): void
    {
        $result = $this->svc->unifiedSearch($this->token . 'Local', null);

        $group = array_values(array_filter(
            $result['items'],
            fn ($item) => $item['type'] === 'group'
        ))[0] ?? null;
        $this->assertNotNull($group, 'Expected to find a group in search results');
    }

    // ==========================================
    // Type filters
    // ==========================================

    public function testSearchWithTypeFilterListings(): void
    {
        $result = $this->svc->unifiedSearch($this->token . 'Gardening', null, ['type' => 'listings']);

        $this->assertNotEmpty($result['items']);
        foreach ($result['items'] as $item) {
            $this->assertEquals('listing', $item['type']);
        }
    }

    public function testSearchWithTypeFilterUsers(): void
    {
        $result = $this->svc->unifiedSearch($this->token . 'John', null, ['type' => 'users']);

        $this->assertNotEmpty($result['items']);
        foreach ($result['items'] as $item) {
            $this->assertEquals('user', $item['type']);
        }
    }

    // ==========================================
    // Limit handling
    // ==========================================

    public function testSearchCapsLimit(): void
    {
        // Internally capped at 50 — must not crash on an oversized request.
        $result = $this->svc->unifiedSearch($this->token, null, ['limit' => 500]);
        $this->assertIsArray($result['items']);
    }

    // ==========================================
    // Suggestions
    // ==========================================

    public function testGetSuggestionsReturnsStructure(): void
    {
        $suggestions = $this->svc->suggestions($this->token . 'Gardening');

        $this->assertIsArray($suggestions);
        $this->assertArrayHasKey('listings', $suggestions);
        $this->assertArrayHasKey('users', $suggestions);
        $this->assertArrayHasKey('events', $suggestions);
        $this->assertArrayHasKey('groups', $suggestions);
    }

    public function testGetSuggestionsWithShortQuery(): void
    {
        $suggestions = $this->svc->suggestions('a');

        $this->assertEmpty($suggestions['listings']);
        $this->assertEmpty($suggestions['users']);
        $this->assertEmpty($suggestions['events']);
        $this->assertEmpty($suggestions['groups']);
    }
}
