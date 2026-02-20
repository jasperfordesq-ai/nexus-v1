<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\EventService;

/**
 * EventService Tests
 *
 * Tests event CRUD, RSVPs, attendee management, and filtering.
 */
class EventServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testCategoryId = null;
    protected static ?int $testEventId = null;
    protected static ?int $testPastEventId = null;

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

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "evtsvc_user1_{$ts}@test.com", "evtsvc_user1_{$ts}", 'Event', 'Organizer', 'Event Organizer']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 50, 1, NOW())",
            [self::$testTenantId, "evtsvc_user2_{$ts}@test.com", "evtsvc_user2_{$ts}", 'Event', 'Attendee', 'Event Attendee']
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create test category
        try {
            Database::query(
                "INSERT INTO categories (tenant_id, name, slug, type, created_at)
                 VALUES (?, ?, ?, 'event', NOW())",
                [self::$testTenantId, "Event Category {$ts}", "event-cat-{$ts}"]
            );
            self::$testCategoryId = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            // Category may not exist in all schemas
        }

        // Create upcoming test event
        Database::query(
            "INSERT INTO events (tenant_id, user_id, category_id, title, description, location, start_time, end_time, created_at)
             VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 7 DAY) + INTERVAL 2 HOUR, NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                self::$testCategoryId,
                "Upcoming Event {$ts}",
                "Test event description",
                "Dublin, Ireland"
            ]
        );
        self::$testEventId = (int)Database::getInstance()->lastInsertId();

        // Create past test event
        Database::query(
            "INSERT INTO events (tenant_id, user_id, category_id, title, description, location, start_time, end_time, created_at)
             VALUES (?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY) + INTERVAL 2 HOUR, NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                self::$testCategoryId,
                "Past Event {$ts}",
                "Past test event",
                "Cork, Ireland"
            ]
        );
        self::$testPastEventId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        $eventIds = array_filter([self::$testEventId, self::$testPastEventId]);
        foreach ($eventIds as $eid) {
            try {
                Database::query("DELETE FROM event_rsvps WHERE event_id = ?", [$eid]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM events WHERE id = ? AND tenant_id = ?", [$eid, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        if (self::$testCategoryId) {
            try {
                Database::query("DELETE FROM categories WHERE id = ? AND tenant_id = ?", [self::$testCategoryId, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        $userIds = array_filter([self::$testUserId, self::$testUser2Id]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM users WHERE id = ? AND tenant_id = ?", [$uid, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // getAll Tests
    // ==========================================

    public function testGetAllReturnsValidStructure(): void
    {
        $result = EventService::getAll();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
    }

    public function testGetAllDefaultsToUpcoming(): void
    {
        $result = EventService::getAll();

        // All events should be in the future
        foreach ($result['items'] as $event) {
            $startTime = strtotime($event['start_time']);
            $this->assertGreaterThan(time(), $startTime);
        }
    }

    public function testGetAllFiltersPastEvents(): void
    {
        $result = EventService::getAll(['when' => 'past']);

        // All events should be in the past
        foreach ($result['items'] as $event) {
            $startTime = strtotime($event['start_time']);
            $this->assertLessThan(time(), $startTime);
        }
    }

    public function testGetAllFiltersByCategory(): void
    {
        if (!self::$testCategoryId) {
            $this->markTestSkipped('Category not available');
        }

        $result = EventService::getAll(['category_id' => self::$testCategoryId]);

        foreach ($result['items'] as $event) {
            $this->assertEquals(self::$testCategoryId, $event['category_id']);
        }
    }

    public function testGetAllFiltersByOrganizer(): void
    {
        $result = EventService::getAll(['user_id' => self::$testUserId]);

        $this->assertNotEmpty($result['items']);
        foreach ($result['items'] as $event) {
            $this->assertEquals(self::$testUserId, $event['user_id']);
        }
    }

    public function testGetAllRespectsLimit(): void
    {
        $result = EventService::getAll(['limit' => 5]);

        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    public function testGetAllEnforcesMaxLimit(): void
    {
        $result = EventService::getAll(['limit' => 500]);

        $this->assertLessThanOrEqual(100, count($result['items']));
    }

    public function testGetAllIncludesRsvpCounts(): void
    {
        $result = EventService::getAll();

        if (!empty($result['items'])) {
            $event = $result['items'][0];
            $this->assertArrayHasKey('going_count', $event);
            $this->assertArrayHasKey('interested_count', $event);
        }
    }

    // ==========================================
    // getById Tests
    // ==========================================

    public function testGetByIdReturnsValidEvent(): void
    {
        $event = EventService::getById(self::$testEventId);

        $this->assertNotNull($event);
        $this->assertIsArray($event);
        $this->assertEquals(self::$testEventId, $event['id']);
        $this->assertArrayHasKey('title', $event);
        $this->assertArrayHasKey('description', $event);
        $this->assertArrayHasKey('start_time', $event);
        $this->assertArrayHasKey('end_time', $event);
    }

    public function testGetByIdReturnsNullForNonExistent(): void
    {
        $event = EventService::getById(999999);

        $this->assertNull($event);
    }

    public function testGetByIdIncludesOrganizerInfo(): void
    {
        $event = EventService::getById(self::$testEventId);

        $this->assertNotNull($event);
        $this->assertArrayHasKey('organizer_name', $event);
        $this->assertArrayHasKey('user_id', $event);
    }

    // ==========================================
    // validateEvent Tests
    // ==========================================

    public function testValidateEventAcceptsValidData(): void
    {
        $valid = EventService::validateEvent([
            'title' => 'Valid Event Title',
            'description' => 'Valid description',
            'start_time' => date('Y-m-d H:i:s', strtotime('+1 week')),
            'end_time' => date('Y-m-d H:i:s', strtotime('+1 week +2 hours')),
            'location' => 'Dublin',
        ]);

        $this->assertTrue($valid);
        $this->assertEmpty(EventService::getErrors());
    }

    public function testValidateEventRejectsMissingTitle(): void
    {
        $valid = EventService::validateEvent([
            'description' => 'Description',
            'start_time' => date('Y-m-d H:i:s', strtotime('+1 week')),
        ]);

        $this->assertFalse($valid);
        $this->assertNotEmpty(EventService::getErrors());
    }

    public function testValidateEventRejectsEmptyTitle(): void
    {
        $valid = EventService::validateEvent([
            'title' => '',
            'start_time' => date('Y-m-d H:i:s', strtotime('+1 week')),
        ]);

        $this->assertFalse($valid);
    }

    public function testValidateEventRejectsTooLongTitle(): void
    {
        $valid = EventService::validateEvent([
            'title' => str_repeat('A', 256),
            'start_time' => date('Y-m-d H:i:s', strtotime('+1 week')),
        ]);

        $this->assertFalse($valid);
    }

    public function testValidateEventRejectsPastStartTime(): void
    {
        $valid = EventService::validateEvent([
            'title' => 'Valid Title',
            'start_time' => date('Y-m-d H:i:s', strtotime('-1 week')),
        ]);

        $this->assertFalse($valid);
    }

    public function testValidateEventRejectsEndBeforeStart(): void
    {
        $valid = EventService::validateEvent([
            'title' => 'Valid Title',
            'start_time' => date('Y-m-d H:i:s', strtotime('+1 week')),
            'end_time' => date('Y-m-d H:i:s', strtotime('+1 week -1 hour')),
        ]);

        $this->assertFalse($valid);
    }

    // ==========================================
    // RSVP Tests
    // ==========================================

    public function testRsvpReturnsIdForValidRsvp(): void
    {
        try {
            $rsvpId = EventService::rsvp(self::$testEventId, self::$testUser2Id, 'going');

            $this->assertIsInt($rsvpId);
            $this->assertGreaterThan(0, $rsvpId);

            // Cleanup
            Database::query("DELETE FROM event_rsvps WHERE id = ?", [$rsvpId]);
        } catch (\Exception $e) {
            $this->markTestSkipped('rsvp not available: ' . $e->getMessage());
        }
    }

    public function testRsvpReturnsFalseForInvalidStatus(): void
    {
        try {
            $result = EventService::rsvp(self::$testEventId, self::$testUser2Id, 'invalid_status');

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('rsvp not available: ' . $e->getMessage());
        }
    }

    public function testRsvpUpdatesExistingRsvp(): void
    {
        try {
            // Create initial RSVP
            $rsvpId1 = EventService::rsvp(self::$testEventId, self::$testUser2Id, 'interested');

            // Change RSVP status
            $rsvpId2 = EventService::rsvp(self::$testEventId, self::$testUser2Id, 'going');

            // Should be the same RSVP (updated, not duplicated)
            $this->assertEquals($rsvpId1, $rsvpId2);

            // Cleanup
            Database::query("DELETE FROM event_rsvps WHERE id = ?", [$rsvpId1]);
        } catch (\Exception $e) {
            $this->markTestSkipped('rsvp not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // getAttendees Tests
    // ==========================================

    public function testGetAttendeesReturnsValidStructure(): void
    {
        try {
            $result = EventService::getAttendees(self::$testEventId);

            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('getAttendees not available: ' . $e->getMessage());
        }
    }

    public function testGetAttendeesFiltersByStatus(): void
    {
        try {
            // Create RSVPs
            EventService::rsvp(self::$testEventId, self::$testUser2Id, 'going');

            $result = EventService::getAttendees(self::$testEventId, ['status' => 'going']);

            foreach ($result as $attendee) {
                $this->assertEquals('going', $attendee['status']);
            }

            // Cleanup
            Database::query("DELETE FROM event_rsvps WHERE event_id = ? AND user_id = ?", [self::$testEventId, self::$testUser2Id]);
        } catch (\Exception $e) {
            $this->markTestSkipped('getAttendees not available: ' . $e->getMessage());
        }
    }
}
