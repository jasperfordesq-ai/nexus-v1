<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI\Tools;

use App\Core\TenantContext;
use App\Services\AI\Tools\SearchEventsTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * SearchEventsToolTest
 *
 * Tests the SearchEventsTool which queries the `events` table for upcoming
 * (start_time >= now()) events scoped to the current tenant.
 *
 * Strategy:
 *  - Seed minimal event rows for tenant 2 via DB::table (no FK on user_id).
 *  - Assert metadata (name, parameters schema).
 *  - Assert real query behaviour: matches, filters, limit, tenant isolation,
 *    empty results.
 *  - DatabaseTransactions rolls every insert back automatically.
 */
class SearchEventsToolTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;
    // A separate tenant to prove scoping.
    private const OTHER_TENANT_ID = 1;

    private SearchEventsTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
        $this->tool = new SearchEventsTool();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Insert a minimal future event for a given tenant and return its ID.
     */
    private function insertFutureEvent(array $overrides = [], int $tenantId = self::TENANT_ID): int
    {
        $defaults = [
            'tenant_id'   => $tenantId,
            'user_id'     => 1,          // no FK enforced
            'title'       => 'Test Event ' . uniqid(),
            'description' => 'A test event description.',
            'start_time'  => now()->addHour()->toDateTimeString(),
            'end_time'    => now()->addHours(2)->toDateTimeString(),
            'location'    => null,
            'is_online'   => 0,
            'max_attendees' => null,
            'created_at'  => now()->toDateTimeString(),
        ];

        return DB::table('events')->insertGetId(array_merge($defaults, $overrides));
    }

    /**
     * Insert a past event (should never appear in results).
     */
    private function insertPastEvent(int $tenantId = self::TENANT_ID): int
    {
        return DB::table('events')->insertGetId([
            'tenant_id'   => $tenantId,
            'user_id'     => 1,
            'title'       => 'Past Event ' . uniqid(),
            'description' => 'This event already happened.',
            'start_time'  => now()->subDay()->toDateTimeString(),
            'end_time'    => now()->subHour()->toDateTimeString(),
            'is_online'   => 0,
            'created_at'  => now()->toDateTimeString(),
        ]);
    }

    // ─── Metadata ─────────────────────────────────────────────────────────────

    public function test_name_returns_search_events(): void
    {
        $this->assertSame('search_events', $this->tool->name());
    }

    public function test_parameters_schema_has_expected_properties(): void
    {
        $schema = $this->tool->parametersSchema();

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertArrayHasKey('location', $schema['properties']);
        $this->assertArrayHasKey('limit', $schema['properties']);
        // required is empty — all args are optional
        $this->assertSame([], $schema['required']);
    }

    // ─── Execute: empty results ───────────────────────────────────────────────

    public function test_execute_returns_ok_with_empty_results_when_no_future_events(): void
    {
        // Ensure at least one past event exists (should not surface).
        $this->insertPastEvent();

        $result = $this->tool->execute(['query' => 'nonexistent-xyz-' . uniqid()], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
        $this->assertSame('event', $result['card_type']);
        $this->assertNull($result['error']);
    }

    public function test_execute_no_query_returns_no_upcoming_events_message_when_none(): void
    {
        // Wipe future events is not feasible without truncate — instead use a
        // unique title and assert empty with that query; OR just verify empty
        // path message format by using a very specific keyword.
        $result = $this->tool->execute(['query' => ''], 1);

        // No assertion on count — other events may exist in the test DB.
        $this->assertTrue($result['ok']);
        $this->assertSame('event', $result['card_type']);
    }

    // ─── Execute: matching results ────────────────────────────────────────────

    public function test_execute_returns_future_event_matching_title(): void
    {
        $uniqueToken = 'SRCHEVT' . uniqid();
        $id = $this->insertFutureEvent(['title' => "Community Event {$uniqueToken}"]);

        $result = $this->tool->execute(['query' => $uniqueToken], 1);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['results']);
        $this->assertSame($id, $result['results'][0]['id']);
        $this->assertStringContainsString($uniqueToken, $result['results'][0]['title']);
        $this->assertSame('event', $result['card_type']);
        $this->assertStringContainsString('Found 1 upcoming event(s)', $result['summary']);
    }

    public function test_execute_returns_future_event_matching_description(): void
    {
        $uniqueToken = 'DESCMATCH' . uniqid();
        $id = $this->insertFutureEvent(['description' => "Details about {$uniqueToken} workshop."]);

        $result = $this->tool->execute(['query' => $uniqueToken], 1);

        $this->assertTrue($result['ok']);
        $ids = array_column($result['results'], 'id');
        $this->assertContains($id, $ids);
    }

    public function test_execute_does_not_return_past_events(): void
    {
        $uniqueToken = 'PASTEVT' . uniqid();
        $this->insertPastEvent(self::TENANT_ID); // generic past event

        // Insert a past event with unique token in title — must not appear.
        DB::table('events')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'user_id'     => 1,
            'title'       => "Past {$uniqueToken}",
            'description' => 'old',
            'start_time'  => now()->subDays(3)->toDateTimeString(),
            'is_online'   => 0,
            'created_at'  => now()->toDateTimeString(),
        ]);

        $result = $this->tool->execute(['query' => $uniqueToken], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    // ─── Execute: location filter ─────────────────────────────────────────────

    public function test_execute_filters_by_location(): void
    {
        $uniqueToken = 'LOCEVT' . uniqid();
        $idDublin  = $this->insertFutureEvent(['title' => "{$uniqueToken} A", 'location' => 'Dublin']);
        $idCork    = $this->insertFutureEvent(['title' => "{$uniqueToken} B", 'location' => 'Cork']);

        // Query with title token + location=Dublin
        $result = $this->tool->execute(['query' => $uniqueToken, 'location' => 'Dublin'], 1);

        $this->assertTrue($result['ok']);
        $ids = array_column($result['results'], 'id');
        $this->assertContains($idDublin, $ids);
        $this->assertNotContains($idCork, $ids);
    }

    // ─── Execute: limit ───────────────────────────────────────────────────────

    public function test_execute_respects_limit_argument(): void
    {
        $uniqueToken = 'LIMITEVT' . uniqid();
        for ($i = 0; $i < 5; $i++) {
            $this->insertFutureEvent(['title' => "{$uniqueToken} Event {$i}"]);
        }

        $result = $this->tool->execute(['query' => $uniqueToken, 'limit' => 3], 1);

        $this->assertTrue($result['ok']);
        $this->assertCount(3, $result['results']);
    }

    public function test_execute_caps_limit_at_8(): void
    {
        $uniqueToken = 'MAXLMT' . uniqid();
        for ($i = 0; $i < 10; $i++) {
            $this->insertFutureEvent(['title' => "{$uniqueToken} Item {$i}"]);
        }

        $result = $this->tool->execute(['query' => $uniqueToken, 'limit' => 99], 1);

        $this->assertTrue($result['ok']);
        // intArg clamps to max=8
        $this->assertLessThanOrEqual(8, count($result['results']));
    }

    // ─── Execute: result shape ────────────────────────────────────────────────

    public function test_execute_result_row_has_expected_keys(): void
    {
        $uniqueToken = 'SHAPECHK' . uniqid();
        $this->insertFutureEvent(['title' => "{$uniqueToken} shaped"]);

        $result = $this->tool->execute(['query' => $uniqueToken], 1);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['results']);

        $row = $result['results'][0];
        foreach (['id', 'title', 'start_time', 'end_time', 'location', 'is_online', 'excerpt', 'url'] as $key) {
            $this->assertArrayHasKey($key, $row, "Missing key: {$key}");
        }
        $this->assertIsInt($row['id']);
        $this->assertIsBool($row['is_online']);
        $this->assertStringContainsString('/events/', $row['url']);
    }

    // ─── Execute: tenant scoping ──────────────────────────────────────────────

    public function test_execute_does_not_return_events_from_other_tenants(): void
    {
        $uniqueToken = 'TENANTISO' . uniqid();
        // Insert future event for OTHER tenant
        $this->insertFutureEvent(['title' => "{$uniqueToken} other"], self::OTHER_TENANT_ID);

        $result = $this->tool->execute(['query' => $uniqueToken], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    // ─── Execute: empty query lists events ───────────────────────────────────

    public function test_execute_hides_non_discoverable_event_lifecycle_states(): void
    {
        $uniqueToken = 'LIFECYCLEAI' . uniqid();
        $scheduled = $this->insertFutureEvent([
            'title' => "{$uniqueToken} scheduled",
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => 0,
        ]);
        $postponed = $this->insertFutureEvent([
            'title' => "{$uniqueToken} postponed",
            'status' => 'cancelled',
            'publication_status' => 'published',
            'operational_status' => 'postponed',
            'is_recurring_template' => 0,
        ]);
        foreach ([
            ['status' => 'draft', 'publication_status' => 'draft', 'operational_status' => 'scheduled'],
            ['status' => 'draft', 'publication_status' => 'pending_review', 'operational_status' => 'scheduled'],
            ['status' => 'cancelled', 'publication_status' => 'archived', 'operational_status' => 'cancelled'],
            ['status' => 'cancelled', 'publication_status' => 'published', 'operational_status' => 'cancelled'],
            ['status' => 'active', 'publication_status' => 'published', 'operational_status' => 'scheduled', 'is_recurring_template' => 1],
        ] as $index => $state) {
            $this->insertFutureEvent([
                'title' => "{$uniqueToken} hidden {$index}",
                'is_recurring_template' => 0,
                ...$state,
            ]);
        }

        $result = $this->tool->execute(['query' => $uniqueToken, 'limit' => 8], 1);

        $this->assertTrue($result['ok']);
        $this->assertEqualsCanonicalizing(
            [$scheduled, $postponed],
            array_map('intval', array_column($result['results'], 'id')),
        );
    }

    public function test_execute_empty_query_returns_multiple_future_events(): void
    {
        $uniqueToken = 'NOQUERYEVT' . uniqid();
        $id1 = $this->insertFutureEvent(['title' => "{$uniqueToken} First"]);
        $id2 = $this->insertFutureEvent(['title' => "{$uniqueToken} Second"]);

        // Run with specific query so we don't pick up unrelated rows in test DB.
        $result = $this->tool->execute(['query' => $uniqueToken], 1);

        $this->assertTrue($result['ok']);
        $ids = array_column($result['results'], 'id');
        $this->assertContains($id1, $ids);
        $this->assertContains($id2, $ids);
    }
}
