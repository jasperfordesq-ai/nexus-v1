<?php

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

/**
 * Notification Model Tests
 *
 * Tests notification creation, retrieval, and push notification integration.
 */
class NotificationTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestUser();
    }

    protected static function createTestUser(): void
    {
        $timestamp = time();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "notif_model_test_{$timestamp}@test.com", "notif_model_test_{$timestamp}", 'Notif', 'Model', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Clear notifications before each test
        Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
    }

    // ==========================================
    // Create Notification Tests
    // ==========================================

    public function testCreateNotificationBasic(): void
    {
        Notification::create(
            self::$testUserId,
            'Test notification message',
            '/test/link',
            'system'
        );

        $notification = Database::query(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($notification);
        $this->assertEquals(self::$testUserId, $notification['user_id']);
        $this->assertEquals('Test notification message', $notification['message']);
        $this->assertEquals('/test/link', $notification['link']);
        $this->assertEquals('system', $notification['type']);
    }

    public function testCreateNotificationWithoutPush(): void
    {
        Notification::create(
            self::$testUserId,
            'No push notification',
            '/test/link',
            'system',
            false // sendPush = false
        );

        $notification = Database::query(
            "SELECT * FROM notifications WHERE user_id = ? AND message = ?",
            [self::$testUserId, 'No push notification']
        )->fetch();

        $this->assertNotFalse($notification);
    }

    public function testCreateNotificationWithNullLink(): void
    {
        Notification::create(
            self::$testUserId,
            'Notification without link',
            null,
            'system'
        );

        $notification = Database::query(
            "SELECT * FROM notifications WHERE user_id = ? AND message = ?",
            [self::$testUserId, 'Notification without link']
        )->fetch();

        $this->assertNotFalse($notification);
        $this->assertNull($notification['link']);
    }

    // ==========================================
    // Notification Type Tests
    // ==========================================

    public function testCreateNotificationWithDifferentTypes(): void
    {
        $types = ['system', 'message', 'transaction', 'event', 'reminder', 'mention', 'achievement'];

        foreach ($types as $type) {
            Notification::create(
                self::$testUserId,
                "Test {$type} notification",
                '/test',
                $type,
                false // Don't send push for tests
            );
        }

        $count = Database::query(
            "SELECT COUNT(*) as c FROM notifications WHERE user_id = ?",
            [self::$testUserId]
        )->fetch()['c'];

        $this->assertEquals(count($types), (int)$count);
    }

    // ==========================================
    // Get Notifications Tests
    // ==========================================

    public function testGetForUserMethodExists(): void
    {
        if (!method_exists(Notification::class, 'getForUser')) {
            $this->markTestSkipped('getForUser not implemented');
        }

        $result = Notification::getForUser(self::$testUserId);
        $this->assertIsArray($result);
    }

    public function testGetUnreadCountMethodExists(): void
    {
        if (!method_exists(Notification::class, 'getUnreadCount')) {
            $this->markTestSkipped('getUnreadCount not implemented');
        }

        $result = Notification::getUnreadCount(self::$testUserId);
        $this->assertIsInt($result);
    }

    // ==========================================
    // Mark Read Tests
    // ==========================================

    public function testMarkAsReadMethodExists(): void
    {
        if (!method_exists(Notification::class, 'markAsRead')) {
            $this->markTestSkipped('markAsRead not implemented');
        }

        // Create a notification first
        Notification::create(self::$testUserId, 'To be marked read', '/test', 'system', false);

        $notification = Database::query(
            "SELECT id FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        Notification::markAsRead($notification['id']);

        $updated = Database::query(
            "SELECT * FROM notifications WHERE id = ?",
            [$notification['id']]
        )->fetch();

        $this->assertNotNull($updated['read_at']);
    }

    public function testMarkAllAsReadMethodExists(): void
    {
        if (!method_exists(Notification::class, 'markAllAsRead')) {
            $this->markTestSkipped('markAllAsRead not implemented');
        }

        // Create multiple notifications
        for ($i = 0; $i < 3; $i++) {
            Notification::create(self::$testUserId, "Notification {$i}", '/test', 'system', false);
        }

        Notification::markAllAsRead(self::$testUserId);

        $unreadCount = Database::query(
            "SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND read_at IS NULL",
            [self::$testUserId]
        )->fetch()['c'];

        $this->assertEquals(0, (int)$unreadCount);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteNotificationMethodExists(): void
    {
        if (!method_exists(Notification::class, 'delete')) {
            $this->markTestSkipped('delete not implemented');
        }

        Notification::create(self::$testUserId, 'To be deleted', '/test', 'system', false);

        $notification = Database::query(
            "SELECT id FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        Notification::delete($notification['id']);

        $deleted = Database::query(
            "SELECT * FROM notifications WHERE id = ?",
            [$notification['id']]
        )->fetch();

        $this->assertFalse($deleted);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCreateNotificationWithVeryLongMessage(): void
    {
        $longMessage = str_repeat('A', 1000);

        Notification::create(
            self::$testUserId,
            $longMessage,
            '/test',
            'system',
            false
        );

        $notification = Database::query(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($notification);
        // Message should be stored (possibly truncated)
        $this->assertNotEmpty($notification['message']);
    }

    public function testCreateNotificationWithSpecialCharacters(): void
    {
        $specialMessage = "Test with <script>alert('xss')</script> & émojis 🎉 and \"quotes\"";

        Notification::create(
            self::$testUserId,
            $specialMessage,
            '/test',
            'system',
            false
        );

        $notification = Database::query(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($notification);
        $this->assertStringContainsString('xss', $notification['message']);
    }

    // ==========================================
    // Type Mapping Tests
    // ==========================================

    public function testMapNotificationTypeMethodExists(): void
    {
        $reflection = new \ReflectionClass(Notification::class);

        if ($reflection->hasMethod('mapNotificationType')) {
            $method = $reflection->getMethod('mapNotificationType');
            $method->setAccessible(true);

            $result = $method->invoke(null, 'message');
            $this->assertIsString($result);
        } else {
            $this->markTestSkipped('mapNotificationType not implemented');
        }
    }

    public function testGenerateTitleMethodExists(): void
    {
        $reflection = new \ReflectionClass(Notification::class);

        if ($reflection->hasMethod('generateTitle')) {
            $method = $reflection->getMethod('generateTitle');
            $method->setAccessible(true);

            $result = $method->invoke(null, 'message');
            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        } else {
            $this->markTestSkipped('generateTitle not implemented');
        }
    }
}
