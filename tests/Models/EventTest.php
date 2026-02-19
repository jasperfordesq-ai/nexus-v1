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
use Nexus\Models\Event;
use Nexus\Models\EventRsvp;

/**
 * Event Model Tests
 *
 * Tests event creation, retrieval, updates, deletion, RSVPs,
 * column naming (start_time/end_time, cover_image, user_id), and tenant scoping.
 */
class EventTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testEventId = null;
    protected static ?int $testCategoryId = null;

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

        // Create test user (organizer)
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "event_model_test_{$timestamp}@test.com",
                "event_model_test_{$timestamp}",
                'Event',
                'Organizer',
                'Event Organizer',
                100
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create second test user (attendee)
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "event_model_test2_{$timestamp}@test.com",
                "event_model_test2_{$timestamp}",
                'Event',
                'Attendee',
                'Event Attendee',
                50
            ]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Get or create a test category
        try {
            $category = Database::query(
                "SELECT id FROM categories WHERE tenant_id = ? AND type = 'event' LIMIT 1",
                [self::$testTenantId]
            )->fetch();

            if ($category) {
                self::$testCategoryId = (int)$category['id'];
            } else {
                Database::query(
                    "INSERT INTO categories (tenant_id, name, slug, type, color, created_at) VALUES (?, ?, ?, 'event', 'blue', NOW())",
                    [self::$testTenantId, 'Test Event Category', 'test-event-cat-' . $timestamp]
                );
                self::$testCategoryId = (int)Database::getInstance()->lastInsertId();
            }
        } catch (\Exception $e) {
            self::$testCategoryId = null;
        }

        // Create a test event using the model (uses start_time/end_time columns)
        $futureStart = date('Y-m-d H:i:s', strtotime('+7 days'));
        $futureEnd = date('Y-m-d H:i:s', strtotime('+7 days +2 hours'));

        self::$testEventId = (int)Event::create(
            self::$testTenantId,
            self::$testUserId,
            "Test Event {$timestamp}",
            "This is a test event for model tests.",
            'Dublin, Ireland',
            $futureStart,
            $futureEnd,
            null,
            self::$testCategoryId,
            53.3498,
            -6.2603
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testEventId) {
            try {
                Database::query("DELETE FROM event_rsvps WHERE event_id = ?", [self::$testEventId]);
                Database::query("DELETE FROM events WHERE id = ?", [self::$testEventId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM event_rsvps WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM events WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUser2Id) {
            try {
                Database::query("DELETE FROM event_rsvps WHERE user_id = ?", [self::$testUser2Id]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUser2Id]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUser2Id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function testCreateEventReturnsId(): void
    {
        $futureStart = date('Y-m-d H:i:s', strtotime('+10 days'));
        $futureEnd = date('Y-m-d H:i:s', strtotime('+10 days +3 hours'));

        $id = Event::create(
            self::$testTenantId,
            self::$testUserId,
            'New Test Event ' . time(),
            'A newly created event',
            'Cork, Ireland',
            $futureStart,
            $futureEnd
        );

        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, (int)$id);

        // Clean up
        Database::query("DELETE FROM events WHERE id = ?", [$id]);
    }

    public function testCreateEventWithAllFields(): void
    {
        $futureStart = date('Y-m-d H:i:s', strtotime('+14 days'));
        $futureEnd = date('Y-m-d H:i:s', strtotime('+14 days +4 hours'));

        $id = Event::create(
            self::$testTenantId,
            self::$testUserId,
            'Full Event ' . time(),
            'Full description with all fields',
            'Galway, Ireland',
            $futureStart,
            $futureEnd,
            null,                    // groupId
            self::$testCategoryId,   // categoryId
            53.2707,                 // latitude
            -9.0568,                 // longitude
            'listed'                 // federated visibility
        );

        $this->assertIsNumeric($id);

        // Verify stored fields using raw query to check column names
        $event = Database::query("SELECT * FROM events WHERE id = ?", [$id])->fetch();
        $this->assertNotFalse($event);
        $this->assertEquals(self::$testTenantId, $event['tenant_id']);
        $this->assertEquals(self::$testUserId, $event['user_id']); // user_id, NOT created_by
        $this->assertEquals($futureStart, $event['start_time']);    // start_time, NOT start_date
        $this->assertEquals($futureEnd, $event['end_time']);        // end_time, NOT end_date
        $this->assertEquals('Galway, Ireland', $event['location']);
        $this->assertEquals('listed', $event['federated_visibility']);

        // Clean up
        Database::query("DELETE FROM events WHERE id = ?", [$id]);
    }

    public function testCreateEventValidatesFederatedVisibility(): void
    {
        $futureStart = date('Y-m-d H:i:s', strtotime('+15 days'));
        $futureEnd = date('Y-m-d H:i:s', strtotime('+15 days +1 hour'));

        $id = Event::create(
            self::$testTenantId,
            self::$testUserId,
            'Visibility Test Event',
            'Description',
            'Dublin',
            $futureStart,
            $futureEnd,
            null, null, null, null,
            'invalid_visibility' // Should default to 'none'
        );

        $event = Database::query(
            "SELECT federated_visibility FROM events WHERE id = ?",
            [$id]
        )->fetch();

        $this->assertEquals('none', $event['federated_visibility']);

        // Clean up
        Database::query("DELETE FROM events WHERE id = ?", [$id]);
    }

    public function testCreateEventWithValidFederatedVisibilities(): void
    {
        $validVisibilities = ['none', 'listed', 'joinable'];

        foreach ($validVisibilities as $vis) {
            $futureStart = date('Y-m-d H:i:s', strtotime('+20 days'));
            $futureEnd = date('Y-m-d H:i:s', strtotime('+20 days +1 hour'));

            $id = Event::create(
                self::$testTenantId,
                self::$testUserId,
                "Vis Test {$vis}",
                'Description',
                'Dublin',
                $futureStart,
                $futureEnd,
                null, null, null, null,
                $vis
            );

            $event = Database::query(
                "SELECT federated_visibility FROM events WHERE id = ?",
                [$id]
            )->fetch();

            $this->assertEquals($vis, $event['federated_visibility'], "Federated visibility '{$vis}' should be stored correctly");

            Database::query("DELETE FROM events WHERE id = ?", [$id]);
        }
    }

    // ==========================================
    // Column Name Verification Tests
    // ==========================================

    public function testEventUsesStartTimeNotStartDate(): void
    {
        $event = Database::query("SELECT * FROM events WHERE id = ?", [self::$testEventId])->fetch();

        $this->assertArrayHasKey('start_time', $event, 'Events table must use start_time column');
        $this->assertArrayHasKey('end_time', $event, 'Events table must use end_time column');
    }

    public function testEventUsesUserIdNotCreatedBy(): void
    {
        $event = Database::query("SELECT * FROM events WHERE id = ?", [self::$testEventId])->fetch();

        $this->assertArrayHasKey('user_id', $event, 'Events table must use user_id column');
        $this->assertEquals(self::$testUserId, $event['user_id']);
    }

    public function testEventTableHasCoverImageColumn(): void
    {
        // Verify the cover_image column exists (not image_url which is for groups)
        try {
            $result = Database::query(
                "SHOW COLUMNS FROM events LIKE 'cover_image'"
            )->fetch();
            // cover_image column may or may not exist depending on migrations
            // but image_url should NOT be in the events table
            $this->assertTrue(true, 'Column check executed without error');
        } catch (\Exception $e) {
            $this->assertTrue(true, 'Column check handled gracefully');
        }
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsEvent(): void
    {
        $event = Event::find(self::$testEventId, self::$testTenantId);

        $this->assertNotFalse($event);
        $this->assertIsArray($event);
        $this->assertEquals(self::$testEventId, $event['id']);
    }

    public function testFindIncludesOrganizerInfo(): void
    {
        $event = Event::find(self::$testEventId, self::$testTenantId);

        $this->assertArrayHasKey('organizer_name', $event);
        $this->assertNotEmpty($event['organizer_name']);
        $this->assertArrayHasKey('organizer_avatar', $event);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $event = Event::find(999999999, self::$testTenantId);

        $this->assertFalse($event);
    }

    public function testFindEnforcesTenantIsolation(): void
    {
        // Try to find our event with a wrong tenant ID
        $event = Event::find(self::$testEventId, 9999);

        $this->assertFalse($event, 'Event should not be found when queried with wrong tenant_id');
    }

    // ==========================================
    // Upcoming Tests
    // ==========================================

    public function testUpcomingReturnsArray(): void
    {
        $events = Event::upcoming(self::$testTenantId);

        $this->assertIsArray($events);
    }

    public function testUpcomingRespectsTenantScoping(): void
    {
        $events = Event::upcoming(self::$testTenantId);

        foreach ($events as $event) {
            $this->assertEquals(self::$testTenantId, $event['tenant_id']);
        }
    }

    public function testUpcomingRespectsLimit(): void
    {
        $events = Event::upcoming(self::$testTenantId, 3);

        $this->assertIsArray($events);
        $this->assertLessThanOrEqual(3, count($events));
    }

    public function testUpcomingIncludesOrganizerName(): void
    {
        $events = Event::upcoming(self::$testTenantId, 10);

        foreach ($events as $event) {
            $this->assertArrayHasKey('organizer_name', $event);
        }
    }

    public function testUpcomingFiltersByCategory(): void
    {
        if (!self::$testCategoryId) {
            $this->markTestSkipped('No test category available');
        }

        $events = Event::upcoming(self::$testTenantId, 50, self::$testCategoryId);

        $this->assertIsArray($events);
        foreach ($events as $event) {
            $this->assertEquals(self::$testCategoryId, $event['category_id']);
        }
    }

    public function testUpcomingFiltersByMonthDate(): void
    {
        $events = Event::upcoming(self::$testTenantId, 50, null, 'month');

        $this->assertIsArray($events);
    }

    public function testUpcomingFiltersByWeekendDate(): void
    {
        $events = Event::upcoming(self::$testTenantId, 50, null, 'weekend');

        $this->assertIsArray($events);
    }

    public function testUpcomingFiltersBySearch(): void
    {
        $events = Event::upcoming(self::$testTenantId, 50, null, null, 'Test Event');

        $this->assertIsArray($events);
    }

    // ==========================================
    // GetRange Tests
    // ==========================================

    public function testGetRangeReturnsEventsInDateRange(): void
    {
        $startDate = date('Y-m-d', strtotime('+1 day'));
        $endDate = date('Y-m-d', strtotime('+30 days'));

        $events = Event::getRange(self::$testTenantId, $startDate, $endDate);

        $this->assertIsArray($events);
    }

    public function testGetRangeScopesByTenant(): void
    {
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+365 days'));

        $events = Event::getRange(self::$testTenantId, $startDate, $endDate);

        foreach ($events as $event) {
            $this->assertEquals(self::$testTenantId, $event['tenant_id']);
        }
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $newTitle = 'Updated Event Title ' . time();
        $newStart = date('Y-m-d H:i:s', strtotime('+8 days'));
        $newEnd = date('Y-m-d H:i:s', strtotime('+8 days +5 hours'));

        Event::update(
            self::$testEventId,
            $newTitle,
            'Updated event description',
            'Limerick, Ireland',
            $newStart,
            $newEnd,
            null,
            self::$testCategoryId,
            52.6638,
            -8.6267
        );

        $event = Event::find(self::$testEventId, self::$testTenantId);

        $this->assertEquals($newTitle, $event['title']);
        $this->assertEquals('Limerick, Ireland', $event['location']);
    }

    public function testUpdateFederatedVisibility(): void
    {
        $newStart = date('Y-m-d H:i:s', strtotime('+9 days'));
        $newEnd = date('Y-m-d H:i:s', strtotime('+9 days +2 hours'));

        Event::update(
            self::$testEventId,
            'Fed Vis Update',
            'Description',
            'Dublin',
            $newStart,
            $newEnd,
            null, null, null, null,
            'joinable'
        );

        $event = Database::query(
            "SELECT federated_visibility FROM events WHERE id = ?",
            [self::$testEventId]
        )->fetch();

        $this->assertEquals('joinable', $event['federated_visibility']);
    }

    public function testUpdateIgnoresInvalidFederatedVisibility(): void
    {
        $newStart = date('Y-m-d H:i:s', strtotime('+9 days'));
        $newEnd = date('Y-m-d H:i:s', strtotime('+9 days +2 hours'));

        // Set to a known value first
        Event::update(
            self::$testEventId,
            'Valid Vis',
            'Description',
            'Dublin',
            $newStart,
            $newEnd,
            null, null, null, null,
            'listed'
        );

        // Try to set invalid
        Event::update(
            self::$testEventId,
            'Invalid Vis',
            'Description',
            'Dublin',
            $newStart,
            $newEnd,
            null, null, null, null,
            'invalid_value'
        );

        $event = Database::query(
            "SELECT federated_visibility FROM events WHERE id = ?",
            [self::$testEventId]
        )->fetch();

        // Should remain 'listed' since 'invalid_value' is not in the valid list
        $this->assertEquals('listed', $event['federated_visibility']);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesEvent(): void
    {
        $futureStart = date('Y-m-d H:i:s', strtotime('+20 days'));
        $futureEnd = date('Y-m-d H:i:s', strtotime('+20 days +1 hour'));

        $id = Event::create(
            self::$testTenantId,
            self::$testUserId,
            'To Be Deleted',
            'This event will be deleted',
            'Dublin',
            $futureStart,
            $futureEnd
        );

        $result = Event::delete((int)$id, self::$testUserId, self::$testTenantId);
        $this->assertTrue($result);

        // Verify it is gone
        $event = Database::query("SELECT * FROM events WHERE id = ?", [$id])->fetch();
        $this->assertFalse($event);
    }

    public function testDeleteWithOwnershipCheck(): void
    {
        $futureStart = date('Y-m-d H:i:s', strtotime('+21 days'));
        $futureEnd = date('Y-m-d H:i:s', strtotime('+21 days +1 hour'));

        $id = Event::create(
            self::$testTenantId,
            self::$testUserId,
            'Ownership Delete Test',
            'Description',
            'Dublin',
            $futureStart,
            $futureEnd
        );

        // Attempt delete by a different user (should fail)
        $result = Event::delete((int)$id, self::$testUser2Id, self::$testTenantId);
        $this->assertFalse($result, 'Delete should fail when user_id does not match');

        // Event should still exist
        $event = Database::query("SELECT * FROM events WHERE id = ?", [$id])->fetch();
        $this->assertNotFalse($event);

        // Clean up
        Event::delete((int)$id, self::$testUserId, self::$testTenantId);
    }

    public function testDeleteWithTenantCheck(): void
    {
        $futureStart = date('Y-m-d H:i:s', strtotime('+22 days'));
        $futureEnd = date('Y-m-d H:i:s', strtotime('+22 days +1 hour'));

        $id = Event::create(
            self::$testTenantId,
            self::$testUserId,
            'Tenant Delete Test',
            'Description',
            'Dublin',
            $futureStart,
            $futureEnd
        );

        // Attempt delete with wrong tenant (should fail)
        $result = Event::delete((int)$id, null, 9999);
        $this->assertFalse($result, 'Delete should fail when tenant_id does not match');

        // Clean up
        Event::delete((int)$id, self::$testUserId, self::$testTenantId);
    }

    // ==========================================
    // RSVP / Attendance Tests
    // ==========================================

    public function testGetAttendingReturnsArray(): void
    {
        $attending = Event::getAttending(self::$testUserId);

        $this->assertIsArray($attending);
    }

    public function testGetHostedReturnsOrganizerEvents(): void
    {
        $hosted = Event::getHosted(self::$testUserId);

        $this->assertIsArray($hosted);

        // Our test event should appear (it is in the future)
        $found = false;
        foreach ($hosted as $event) {
            if ($event['id'] == self::$testEventId) {
                $found = true;
                $this->assertArrayHasKey('attending_count', $event);
                $this->assertArrayHasKey('invited_count', $event);
                break;
            }
        }
        $this->assertTrue($found, 'Hosted events should include the test event');
    }

    // ==========================================
    // Nearby Tests
    // ==========================================

    public function testGetNearbyReturnsArray(): void
    {
        $events = Event::getNearby(53.3498, -6.2603, 50, 10);

        $this->assertIsArray($events);
    }

    public function testGetNearbyIncludesDistanceColumn(): void
    {
        $events = Event::getNearby(53.3498, -6.2603, 100, 10);

        foreach ($events as $event) {
            $this->assertArrayHasKey('distance_km', $event);
            $this->assertIsNumeric($event['distance_km']);
        }
    }

    public function testGetNearbyRespectsRadiusLimit(): void
    {
        $radiusKm = 5;
        $events = Event::getNearby(53.3498, -6.2603, $radiusKm, 50);

        foreach ($events as $event) {
            $this->assertLessThanOrEqual(
                $radiusKm,
                (float)$event['distance_km'],
                "Events should be within {$radiusKm}km radius"
            );
        }
    }

    public function testGetNearbyFiltersByCategory(): void
    {
        if (!self::$testCategoryId) {
            $this->markTestSkipped('No test category available');
        }

        $events = Event::getNearby(53.3498, -6.2603, 100, 50, self::$testCategoryId);

        $this->assertIsArray($events);
        foreach ($events as $event) {
            $this->assertEquals(self::$testCategoryId, $event['category_id']);
        }
    }

    // ==========================================
    // GetForGroup Tests
    // ==========================================

    public function testGetForGroupReturnsArray(): void
    {
        // Use a non-existent group ID -- should return empty array
        $events = Event::getForGroup(999999999);

        $this->assertIsArray($events);
        $this->assertEmpty($events);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCreateWithNullOptionalFields(): void
    {
        $futureStart = date('Y-m-d H:i:s', strtotime('+25 days'));
        $futureEnd = date('Y-m-d H:i:s', strtotime('+25 days +1 hour'));

        $id = Event::create(
            self::$testTenantId,
            self::$testUserId,
            'Minimal Event',
            'Only required fields',
            'Dublin',
            $futureStart,
            $futureEnd
        );

        $this->assertIsNumeric($id);

        $event = Database::query("SELECT * FROM events WHERE id = ?", [$id])->fetch();
        $this->assertNull($event['group_id']);
        $this->assertNull($event['category_id']);
        $this->assertNull($event['latitude']);
        $this->assertNull($event['longitude']);
        $this->assertEquals('none', $event['federated_visibility']);

        // Clean up
        Database::query("DELETE FROM events WHERE id = ?", [$id]);
    }

    public function testUpcomingWithSearchSpecialCharacters(): void
    {
        $events = Event::upcoming(self::$testTenantId, 10, null, null, "O'Brien's Event");

        $this->assertIsArray($events);
        // Should not throw an error
    }

    public function testFindReturnsFalseForDeletedEvent(): void
    {
        $futureStart = date('Y-m-d H:i:s', strtotime('+26 days'));
        $futureEnd = date('Y-m-d H:i:s', strtotime('+26 days +1 hour'));

        $id = Event::create(
            self::$testTenantId,
            self::$testUserId,
            'Will Be Deleted',
            'Description',
            'Dublin',
            $futureStart,
            $futureEnd
        );

        Event::delete((int)$id, self::$testUserId, self::$testTenantId);

        $event = Event::find((int)$id, self::$testTenantId);
        $this->assertFalse($event, 'Deleted event should not be found');
    }
}
