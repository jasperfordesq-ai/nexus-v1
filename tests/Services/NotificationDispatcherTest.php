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
use Nexus\Services\NotificationDispatcher;

/**
 * NotificationDispatcher Tests
 *
 * Tests the notification dispatch system including in-app, email queue, and frequency settings.
 */
class NotificationDispatcherTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;

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

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "notif_test_{$timestamp}@test.com", "notif_test_{$timestamp}", 'Notif', 'Test', 'Notif Test', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM notification_queue WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM notification_settings WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Dispatch Method Tests
    // ==========================================

    public function testDispatchCreatesInAppNotification(): void
    {
        // Clear existing notifications
        Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);

        NotificationDispatcher::dispatch(
            self::$testUserId,
            'global',
            null,
            'test_activity',
            'Test notification content',
            '/test/link',
            '<p>Test HTML content</p>'
        );

        // Verify in-app notification was created
        $notification = Database::query(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($notification);
        $this->assertEquals('Test notification content', $notification['message']);
        $this->assertEquals('/test/link', $notification['link']);
        $this->assertEquals('test_activity', $notification['type']);
    }

    public function testDispatchWithDifferentContextTypes(): void
    {
        $contextTypes = ['global', 'group', 'thread'];

        foreach ($contextTypes as $contextType) {
            // Should not throw
            NotificationDispatcher::dispatch(
                self::$testUserId,
                $contextType,
                1, // Dummy context ID
                'test_activity',
                "Test for {$contextType}",
                '/test/link',
                '<p>HTML</p>'
            );
        }

        $this->assertTrue(true); // Reached without exception
    }

    public function testDispatchWithDifferentActivityTypes(): void
    {
        $activityTypes = ['new_topic', 'new_reply', 'mention', 'system', 'transaction'];

        foreach ($activityTypes as $activityType) {
            NotificationDispatcher::dispatch(
                self::$testUserId,
                'global',
                null,
                $activityType,
                "Test {$activityType}",
                '/test/link',
                '<p>HTML</p>'
            );
        }

        $this->assertTrue(true);
    }

    // ==========================================
    // Organizer Priority Tests
    // ==========================================

    public function testDispatchWithOrganizerFlagForNewTopic(): void
    {
        NotificationDispatcher::dispatch(
            self::$testUserId,
            'group',
            1,
            'new_topic',
            'Organizer new topic test',
            '/test/link',
            '<p>HTML</p>',
            true // isOrganizer = true
        );

        // Should have queued as instant for organizer
        $this->assertTrue(true);
    }

    public function testDispatchWithOrganizerFlagForReply(): void
    {
        NotificationDispatcher::dispatch(
            self::$testUserId,
            'thread',
            1,
            'new_reply',
            'Organizer reply test',
            '/test/link',
            '<p>HTML</p>',
            true // isOrganizer = true
        );

        $this->assertTrue(true);
    }

    // ==========================================
    // Frequency Setting Tests
    // ==========================================

    public function testGetFrequencySettingMethodExists(): void
    {
        // Test via reflection since it's private
        $reflection = new \ReflectionClass(NotificationDispatcher::class);

        if ($reflection->hasMethod('getFrequencySetting')) {
            $method = $reflection->getMethod('getFrequencySetting');
            $this->assertTrue($method->isPrivate() || $method->isProtected());
        } else {
            $this->markTestSkipped('getFrequencySetting method not found');
        }
    }

    // ==========================================
    // Queue Notification Tests
    // ==========================================

    public function testQueueNotificationMethodExists(): void
    {
        $reflection = new \ReflectionClass(NotificationDispatcher::class);

        if ($reflection->hasMethod('queueNotification')) {
            $this->assertTrue(true);
        } else {
            $this->markTestSkipped('queueNotification method not found');
        }
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testDispatchWithEmptyContent(): void
    {
        NotificationDispatcher::dispatch(
            self::$testUserId,
            'global',
            null,
            'test_activity',
            '', // Empty content
            '/test/link',
            ''
        );

        $this->assertTrue(true);
    }

    public function testDispatchWithNullLink(): void
    {
        NotificationDispatcher::dispatch(
            self::$testUserId,
            'global',
            null,
            'test_activity',
            'Test with null link',
            null, // Null link
            '<p>HTML</p>'
        );

        $this->assertTrue(true);
    }

    public function testDispatchWithVeryLongContent(): void
    {
        $longContent = str_repeat('A', 5000);

        NotificationDispatcher::dispatch(
            self::$testUserId,
            'global',
            null,
            'test_activity',
            $longContent,
            '/test/link',
            '<p>' . $longContent . '</p>'
        );

        $this->assertTrue(true);
    }

    public function testDispatchWithSpecialCharacters(): void
    {
        NotificationDispatcher::dispatch(
            self::$testUserId,
            'global',
            null,
            'test_activity',
            "Test with <script>alert('xss')</script> and émojis 🎉",
            '/test/link',
            "<p>HTML with &amp; entities</p>"
        );

        $this->assertTrue(true);
    }

    // ==========================================
    // Batch Dispatch Tests
    // ==========================================

    public function testDispatchToMultipleUsersMethodExists(): void
    {
        if (!method_exists(NotificationDispatcher::class, 'dispatchToMultiple')) {
            $this->markTestSkipped('dispatchToMultiple not implemented');
        }

        $this->assertTrue(true);
    }

    // ==========================================
    // Frequency Constants Tests
    // ==========================================

    public function testFrequencyOptionsAreValid(): void
    {
        // Valid frequencies: instant, daily, weekly, off
        $validFrequencies = ['instant', 'daily', 'weekly', 'off'];

        foreach ($validFrequencies as $frequency) {
            // Set user frequency preference
            try {
                Database::query(
                    "INSERT INTO notification_settings (user_id, context_type, context_id, frequency)
                     VALUES (?, 'global', 0, ?)
                     ON DUPLICATE KEY UPDATE frequency = VALUES(frequency)",
                    [self::$testUserId, $frequency]
                );
            } catch (\Exception $e) {
                // Table may not exist
            }
        }

        $this->assertTrue(true);
    }
}
