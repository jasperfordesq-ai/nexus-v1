<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\EventRsvp;

/**
 * EventRsvp Model Tests
 *
 * Tests RSVP creation, upsert logic, status retrieval, attendee listing,
 * count tracking, and capacity-related queries.
 */
class EventRsvpTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUserId2 = null;
    protected static ?int $testUserId3 = null;
    protected static ?int $testEventId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $timestamp = time();

        // Create test users
        $users = [
            ["rsvp_test_user1_{$timestamp}@test.com", "rsvp_user1_{$timestamp}", 'RSVP', 'User1', 'RSVP User1'],
            ["rsvp_test_user2_{$timestamp}@test.com", "rsvp_user2_{$timestamp}", 'RSVP', 'User2', 'RSVP User2'],
            ["rsvp_test_user3_{$timestamp}@test.com", "rsvp_user3_{$timestamp}", 'RSVP', 'User3', 'RSVP User3'],
        ];

        $userIds = [];
        foreach ($users as $user) {
            Database::query(
                "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
                [self::$testTenantId, $user[0], $user[1], $user[2], $user[3], $user[4]]
            );
            $userIds[] = (int)Database::getInstance()->lastInsertId();
        }

        self::$testUserId = $userIds[0];
        self::$testUserId2 = $userIds[1];
        self::$testUserId3 = $userIds[2];

        // Create test event
        Database::query(
            "INSERT INTO events (tenant_id, user_id, title, description, start_time, end_time, location, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'published', NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                "RSVP Test Event {$timestamp}",
                'An event for testing RSVPs',
                date('Y-m-d H:i:s', strtotime('+7 days')),
                date('Y-m-d H:i:s', strtotime('+7 days +2 hours')),
                'Dublin, Ireland'
            ]
        );
        self::$testEventId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        $userIds = array_filter([self::$testUserId, self::$testUserId2, self::$testUserId3]);
        if (!empty($userIds)) {
            try {
                if (self::$testEventId) {
                    Database::query("DELETE FROM event_rsvps WHERE event_id = ?", [self::$testEventId]);
                    Database::query("DELETE FROM events WHERE id = ?", [self::$testEventId]);
                }
                foreach ($userIds as $uid) {
                    Database::query("DELETE FROM activity_log WHERE user_id = ?", [$uid]);
                    Database::query("DELETE FROM notifications WHERE user_id = ?", [$uid]);
                    Database::query("DELETE FROM users WHERE id = ?", [$uid]);
                }
            } catch (\Exception $e) {
            }
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);

        // Clean RSVPs for consistent test state
        try {
            Database::query("DELETE FROM event_rsvps WHERE event_id = ?", [self::$testEventId]);
        } catch (\Exception $e) {
        }
    }

    // ==========================================
    // RSVP Creation Tests
    // ==========================================

    public function testRsvpCreatesNewEntry(): void
    {
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'going');

        $status = EventRsvp::getUserStatus(self::$testEventId, self::$testUserId);
        $this->assertEquals('going', $status);
    }

    public function testRsvpWithDifferentStatuses(): void
    {
        $statuses = ['going', 'interested', 'not_going', 'invited'];

        foreach ($statuses as $status) {
            EventRsvp::rsvp(self::$testEventId, self::$testUserId, $status);

            $result = EventRsvp::getUserStatus(self::$testEventId, self::$testUserId);
            $this->assertEquals($status, $result, "RSVP status should be '{$status}'");
        }
    }

    public function testRsvpUpsertUpdatesExistingEntry(): void
    {
        // Create initial RSVP
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'going');
        $this->assertEquals('going', EventRsvp::getUserStatus(self::$testEventId, self::$testUserId));

        // Update the RSVP (upsert)
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'not_going');
        $this->assertEquals('not_going', EventRsvp::getUserStatus(self::$testEventId, self::$testUserId));

        // Verify only one RSVP row exists (not a duplicate)
        $count = Database::query(
            "SELECT COUNT(*) as c FROM event_rsvps WHERE event_id = ? AND user_id = ?",
            [self::$testEventId, self::$testUserId]
        )->fetch()['c'];

        $this->assertEquals(1, (int)$count, 'Upsert should not create duplicate RSVP entries');
    }

    // ==========================================
    // RSVP Cancellation (Status Change) Tests
    // ==========================================

    public function testRsvpCancellationViaStatusChange(): void
    {
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'going');
        $this->assertEquals('going', EventRsvp::getUserStatus(self::$testEventId, self::$testUserId));

        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'not_going');
        $this->assertEquals('not_going', EventRsvp::getUserStatus(self::$testEventId, self::$testUserId));
    }

    // ==========================================
    // Get User Status Tests
    // ==========================================

    public function testGetUserStatusReturnsNullWhenNoRsvp(): void
    {
        $status = EventRsvp::getUserStatus(self::$testEventId, 999999999);

        $this->assertNull($status);
    }

    public function testGetUserStatusReturnsCorrectStatus(): void
    {
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'going');

        $status = EventRsvp::getUserStatus(self::$testEventId, self::$testUserId);
        $this->assertEquals('going', $status);
    }

    // ==========================================
    // Get Attendees Tests
    // ==========================================

    public function testGetAttendeesReturnsGoingUsers(): void
    {
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'going');
        EventRsvp::rsvp(self::$testEventId, self::$testUserId2, 'going');
        EventRsvp::rsvp(self::$testEventId, self::$testUserId3, 'not_going');

        $attendees = EventRsvp::getAttendees(self::$testEventId);

        $this->assertIsArray($attendees);
        $this->assertCount(2, $attendees);

        $attendeeIds = array_column($attendees, 'user_id');
        $this->assertContains(self::$testUserId, $attendeeIds);
        $this->assertContains(self::$testUserId2, $attendeeIds);
        $this->assertNotContains(self::$testUserId3, $attendeeIds);
    }

    public function testGetAttendeesIncludesUserInfo(): void
    {
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'going');

        $attendees = EventRsvp::getAttendees(self::$testEventId);

        $this->assertNotEmpty($attendees);
        $this->assertArrayHasKey('name', $attendees[0]);
        $this->assertArrayHasKey('avatar_url', $attendees[0]);
    }

    public function testGetAttendeesReturnsEmptyForNoAttendees(): void
    {
        $attendees = EventRsvp::getAttendees(self::$testEventId);

        $this->assertIsArray($attendees);
        $this->assertEmpty($attendees);
    }

    public function testGetAttendeesIncludesAttendedStatus(): void
    {
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'attended');

        $attendees = EventRsvp::getAttendees(self::$testEventId);

        $this->assertCount(1, $attendees);
        $this->assertEquals(self::$testUserId, $attendees[0]['user_id']);
    }

    // ==========================================
    // Get Invited Tests
    // ==========================================

    public function testGetInvitedReturnsInvitedUsers(): void
    {
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'invited');
        EventRsvp::rsvp(self::$testEventId, self::$testUserId2, 'going');

        $invited = EventRsvp::getInvited(self::$testEventId);

        $this->assertIsArray($invited);
        $this->assertCount(1, $invited);
        $this->assertEquals(self::$testUserId, $invited[0]['user_id']);
    }

    public function testGetInvitedReturnsEmptyWhenNoInvited(): void
    {
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'going');

        $invited = EventRsvp::getInvited(self::$testEventId);

        $this->assertIsArray($invited);
        $this->assertEmpty($invited);
    }

    // ==========================================
    // Count / Capacity Tests
    // ==========================================

    public function testGetCountReturnsCorrectCount(): void
    {
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'going');
        EventRsvp::rsvp(self::$testEventId, self::$testUserId2, 'going');
        EventRsvp::rsvp(self::$testEventId, self::$testUserId3, 'not_going');

        $goingCount = EventRsvp::getCount(self::$testEventId, 'going');
        $this->assertEquals(2, (int)$goingCount);
    }

    public function testGetCountDefaultsToGoingStatus(): void
    {
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'going');
        EventRsvp::rsvp(self::$testEventId, self::$testUserId2, 'interested');

        $count = EventRsvp::getCount(self::$testEventId);
        $this->assertEquals(1, (int)$count);
    }

    public function testGetCountFiltersByStatus(): void
    {
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'going');
        EventRsvp::rsvp(self::$testEventId, self::$testUserId2, 'interested');
        EventRsvp::rsvp(self::$testEventId, self::$testUserId3, 'not_going');

        $this->assertEquals(1, (int)EventRsvp::getCount(self::$testEventId, 'going'));
        $this->assertEquals(1, (int)EventRsvp::getCount(self::$testEventId, 'interested'));
        $this->assertEquals(1, (int)EventRsvp::getCount(self::$testEventId, 'not_going'));
    }

    public function testGetCountReturnsZeroForNoRsvps(): void
    {
        $count = EventRsvp::getCount(self::$testEventId, 'going');
        $this->assertEquals(0, (int)$count);
    }

    public function testCapacityCanBeCheckedViaCount(): void
    {
        $maxCapacity = 2;

        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'going');
        EventRsvp::rsvp(self::$testEventId, self::$testUserId2, 'going');

        $currentCount = (int)EventRsvp::getCount(self::$testEventId, 'going');

        $this->assertEquals($maxCapacity, $currentCount);
        $this->assertGreaterThanOrEqual($maxCapacity, $currentCount, 'Event should be at or over capacity');
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testRsvpForNonExistentEventDoesNotThrowOnInsert(): void
    {
        // The model does not validate event existence, so this should just insert
        // (foreign key constraints may or may not be enforced)
        $this->expectNotToPerformAssertions();

        try {
            EventRsvp::rsvp(999999999, self::$testUserId, 'going');
        } catch (\Exception $e) {
            // Foreign key constraint may prevent this; either way the model is fine
            $this->assertStringContainsString('foreign', strtolower($e->getMessage()) . strtolower($e->getCode()));
        }
    }

    public function testMultipleUsersCanRsvpToSameEvent(): void
    {
        EventRsvp::rsvp(self::$testEventId, self::$testUserId, 'going');
        EventRsvp::rsvp(self::$testEventId, self::$testUserId2, 'going');
        EventRsvp::rsvp(self::$testEventId, self::$testUserId3, 'going');

        $count = (int)EventRsvp::getCount(self::$testEventId, 'going');
        $this->assertEquals(3, $count);
    }
}
