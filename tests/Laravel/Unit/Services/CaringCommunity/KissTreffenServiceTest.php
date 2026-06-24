<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\CaringCommunity;

use App\Core\TenantContext;
use App\Services\CaringCommunity\KissTreffenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use RuntimeException;
use Tests\Laravel\TestCase;

/**
 * KissTreffenServiceTest
 *
 * Covers:
 *   - isAvailable: returns true when the table exists
 *   - list: returns formatted rows joined to events/users for the right tenant
 *   - getByEventId: happy path and RuntimeException when not found
 *   - upsert: happy path (insert + update), invalid type guard, non-existent event guard
 *   - recordMinutes: happy path, missing URL guard, non-existent treffen guard
 *   - quorum logic: quorum met/unmet/null when quorum_required is set
 */
class KissTreffenServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private KissTreffenService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
        $this->svc = new KissTreffenService();
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /**
     * Insert a minimal user (organizer) and return its id.
     */
    private function insertUser(int $tenantId = self::TENANT_ID): int
    {
        $uid = uniqid('kt', true);
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => $tenantId,
            'name'       => 'KT Organizer ' . $uid,
            'first_name' => 'KT',
            'last_name'  => 'Organizer',
            'email'      => 'kt.' . $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Insert a minimal event row and return its id.
     */
    private function insertEvent(
        int $tenantId = self::TENANT_ID,
        string $status = 'active',
        ?int $organizerId = null
    ): int {
        if ($organizerId === null) {
            $organizerId = $this->insertUser($tenantId);
        }

        return (int) DB::table('events')->insertGetId([
            'tenant_id'   => $tenantId,
            'user_id'     => $organizerId,
            'title'       => 'KT Test Event ' . uniqid(),
            'description' => 'A test KISS-Treffen event.',
            'start_time'  => now()->addDays(7)->format('Y-m-d H:i:s'),
            'end_time'    => now()->addDays(7)->addHours(2)->format('Y-m-d H:i:s'),
            'status'      => $status,
            'created_at'  => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Insert a caring_kiss_treffen row linked to an existing event.
     */
    private function insertTreffen(
        int $eventId,
        int $tenantId = self::TENANT_ID,
        string $type = 'monthly_stamm',
        ?int $quorumRequired = null
    ): int {
        return (int) DB::table('caring_kiss_treffen')->insertGetId([
            'tenant_id'       => $tenantId,
            'event_id'        => $eventId,
            'treffen_type'    => $type,
            'members_only'    => 1,
            'quorum_required' => $quorumRequired,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    // ── isAvailable ────────────────────────────────────────────────────────────

    public function test_isAvailable_returns_true_when_table_exists(): void
    {
        $this->assertTrue($this->svc->isAvailable());
    }

    // ── list ───────────────────────────────────────────────────────────────────

    public function test_list_returns_formatted_rows_for_correct_tenant(): void
    {
        $eventId = $this->insertEvent(self::TENANT_ID);
        $this->insertTreffen($eventId, self::TENANT_ID);

        $rows = $this->svc->list(self::TENANT_ID);

        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);

        $eventIds = array_column(array_column($rows, 'event'), 'id');
        $this->assertContains($eventId, $eventIds);
    }

    public function test_list_row_has_expected_shape(): void
    {
        $eventId = $this->insertEvent(self::TENANT_ID);
        $this->insertTreffen($eventId);

        $rows = $this->svc->list(self::TENANT_ID, 10);

        $row = array_values(array_filter($rows, fn ($r) => $r['event']['id'] === $eventId))[0];

        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('tenant_id', $row);
        $this->assertArrayHasKey('event_id', $row);
        $this->assertArrayHasKey('treffen_type', $row);
        $this->assertArrayHasKey('members_only', $row);
        $this->assertArrayHasKey('quorum', $row);
        $this->assertArrayHasKey('event', $row);

        $this->assertArrayHasKey('title', $row['event']);
        $this->assertArrayHasKey('start_time', $row['event']);
        $this->assertArrayHasKey('status', $row['event']);
        $this->assertArrayHasKey('required', $row['quorum']);
        $this->assertArrayHasKey('current', $row['quorum']);
        $this->assertArrayHasKey('met', $row['quorum']);
    }

    public function test_list_excludes_cancelled_events(): void
    {
        $cancelledEventId = $this->insertEvent(self::TENANT_ID, 'cancelled');
        $this->insertTreffen($cancelledEventId);

        $rows = $this->svc->list(self::TENANT_ID);

        // cancelled events (not in ['active','draft']) must be excluded
        $eventIds = array_column(array_column($rows, 'event'), 'id');
        $this->assertNotContains($cancelledEventId, $eventIds);
    }

    // ── getByEventId ───────────────────────────────────────────────────────────

    public function test_getByEventId_returns_formatted_row_for_existing_treffen(): void
    {
        $eventId = $this->insertEvent(self::TENANT_ID);
        $this->insertTreffen($eventId, self::TENANT_ID, 'annual_general_assembly');

        $result = $this->svc->getByEventId(self::TENANT_ID, $eventId);

        $this->assertSame($eventId, $result['event_id']);
        $this->assertSame('annual_general_assembly', $result['treffen_type']);
        $this->assertSame(self::TENANT_ID, $result['tenant_id']);
    }

    public function test_getByEventId_throws_when_treffen_not_found(): void
    {
        $this->expectException(RuntimeException::class);

        $this->svc->getByEventId(self::TENANT_ID, 999999999);
    }

    // ── upsert ────────────────────────────────────────────────────────────────

    public function test_upsert_creates_new_treffen_row(): void
    {
        $eventId = $this->insertEvent(self::TENANT_ID);

        $result = $this->svc->upsert(self::TENANT_ID, $eventId, [
            'treffen_type'   => 'governance_circle',
            'members_only'   => true,
            'quorum_required' => 5,
        ]);

        $this->assertSame($eventId, $result['event_id']);
        $this->assertSame('governance_circle', $result['treffen_type']);
        $this->assertTrue($result['members_only']);
        $this->assertSame(5, $result['quorum']['required']);
    }

    public function test_upsert_updates_existing_treffen_row(): void
    {
        $eventId = $this->insertEvent(self::TENANT_ID);
        $this->insertTreffen($eventId, self::TENANT_ID, 'monthly_stamm');

        // Update to a different type
        $result = $this->svc->upsert(self::TENANT_ID, $eventId, [
            'treffen_type' => 'cooperative_workshop',
        ]);

        $this->assertSame('cooperative_workshop', $result['treffen_type']);

        // Only one row should exist (updateOrInsert)
        $count = DB::table('caring_kiss_treffen')
            ->where('tenant_id', self::TENANT_ID)
            ->where('event_id', $eventId)
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_upsert_defaults_treffen_type_to_monthly_stamm(): void
    {
        $eventId = $this->insertEvent(self::TENANT_ID);

        $result = $this->svc->upsert(self::TENANT_ID, $eventId, []);

        $this->assertSame('monthly_stamm', $result['treffen_type']);
    }

    public function test_upsert_throws_for_invalid_treffen_type(): void
    {
        $eventId = $this->insertEvent(self::TENANT_ID);

        $this->expectException(InvalidArgumentException::class);

        $this->svc->upsert(self::TENANT_ID, $eventId, [
            'treffen_type' => 'not_a_valid_type',
        ]);
    }

    public function test_upsert_throws_when_event_does_not_exist(): void
    {
        $this->expectException(RuntimeException::class);

        $this->svc->upsert(self::TENANT_ID, 999999999, [
            'treffen_type' => 'other',
        ]);
    }

    public function test_upsert_stores_fondation_header_and_coordinator_notes(): void
    {
        $eventId = $this->insertEvent(self::TENANT_ID);

        $result = $this->svc->upsert(self::TENANT_ID, $eventId, [
            'treffen_type'      => 'other',
            'fondation_header'  => 'Agenda Header',
            'coordinator_notes' => 'Please bring voting cards.',
        ]);

        $this->assertSame('Agenda Header', $result['fondation_header']);
        $this->assertSame('Please bring voting cards.', $result['coordinator_notes']);
    }

    // ── recordMinutes ──────────────────────────────────────────────────────────

    public function test_recordMinutes_sets_minutes_url_and_uploaded_at(): void
    {
        $eventId   = $this->insertEvent(self::TENANT_ID);
        $actorId   = $this->insertUser(self::TENANT_ID);
        $this->insertTreffen($eventId);

        $result = $this->svc->recordMinutes(self::TENANT_ID, $eventId, $actorId, [
            'minutes_document_url' => 'https://docs.example.test/minutes.pdf',
        ]);

        $this->assertSame('https://docs.example.test/minutes.pdf', $result['minutes_document_url']);
        $this->assertNotNull($result['minutes_uploaded_at']);
        $this->assertSame($actorId, $result['minutes_uploaded_by']);
    }

    public function test_recordMinutes_throws_when_url_is_missing(): void
    {
        $eventId = $this->insertEvent(self::TENANT_ID);
        $actorId = $this->insertUser(self::TENANT_ID);
        $this->insertTreffen($eventId);

        $this->expectException(InvalidArgumentException::class);

        $this->svc->recordMinutes(self::TENANT_ID, $eventId, $actorId, []);
    }

    public function test_recordMinutes_throws_when_treffen_does_not_exist(): void
    {
        $actorId = $this->insertUser(self::TENANT_ID);

        $this->expectException(RuntimeException::class);

        $this->svc->recordMinutes(self::TENANT_ID, 999999999, $actorId, [
            'minutes_document_url' => 'https://docs.example.test/minutes.pdf',
        ]);
    }

    // ── quorum logic ──────────────────────────────────────────────────────────

    public function test_quorum_met_is_null_when_quorum_required_is_null(): void
    {
        $eventId = $this->insertEvent(self::TENANT_ID);
        $this->insertTreffen($eventId, self::TENANT_ID, 'monthly_stamm', null);

        $result = $this->svc->getByEventId(self::TENANT_ID, $eventId);

        $this->assertNull($result['quorum']['required']);
        $this->assertNull($result['quorum']['met']);
    }

    public function test_quorum_met_is_false_when_rsvp_count_is_below_required(): void
    {
        $organizerId = $this->insertUser(self::TENANT_ID);
        $eventId     = $this->insertEvent(self::TENANT_ID, 'active', $organizerId);
        $this->insertTreffen($eventId, self::TENANT_ID, 'governance_circle', 3);

        // Insert 2 RSVPs with 'going' — below quorum of 3
        $userId1 = $this->insertUser(self::TENANT_ID);
        $userId2 = $this->insertUser(self::TENANT_ID);
        DB::table('event_rsvps')->insertOrIgnore([
            ['tenant_id' => self::TENANT_ID, 'event_id' => $eventId, 'user_id' => $userId1, 'status' => 'going', 'created_at' => now()],
            ['tenant_id' => self::TENANT_ID, 'event_id' => $eventId, 'user_id' => $userId2, 'status' => 'going', 'created_at' => now()],
        ]);

        $result = $this->svc->getByEventId(self::TENANT_ID, $eventId);

        $this->assertSame(3, $result['quorum']['required']);
        $this->assertSame(2, $result['quorum']['current']);
        $this->assertFalse($result['quorum']['met']);
    }

    public function test_quorum_met_is_true_when_rsvp_count_meets_required(): void
    {
        $organizerId = $this->insertUser(self::TENANT_ID);
        $eventId     = $this->insertEvent(self::TENANT_ID, 'active', $organizerId);
        $this->insertTreffen($eventId, self::TENANT_ID, 'annual_general_assembly', 2);

        // Insert 3 RSVPs: 2 'going' + 1 'attended' — both count toward quorum
        $u1 = $this->insertUser(self::TENANT_ID);
        $u2 = $this->insertUser(self::TENANT_ID);
        $u3 = $this->insertUser(self::TENANT_ID);
        DB::table('event_rsvps')->insertOrIgnore([
            ['tenant_id' => self::TENANT_ID, 'event_id' => $eventId, 'user_id' => $u1, 'status' => 'going',    'created_at' => now()],
            ['tenant_id' => self::TENANT_ID, 'event_id' => $eventId, 'user_id' => $u2, 'status' => 'attended', 'created_at' => now()],
            ['tenant_id' => self::TENANT_ID, 'event_id' => $eventId, 'user_id' => $u3, 'status' => 'going',    'created_at' => now()],
        ]);

        $result = $this->svc->getByEventId(self::TENANT_ID, $eventId);

        $this->assertSame(2, $result['quorum']['required']);
        $this->assertGreaterThanOrEqual(2, $result['quorum']['current']);
        $this->assertTrue($result['quorum']['met']);
    }
}
