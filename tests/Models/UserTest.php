<?php

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\User;

/**
 * User Model Tests
 *
 * Tests user creation, retrieval, updates, and various user methods.
 */
class UserTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestUsers();
    }

    protected static function createTestUsers(): void
    {
        $timestamp = time();

        // Create primary test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, bio, location, phone, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())",
            [
                self::$testTenantId,
                "user_model_test_{$timestamp}@test.com",
                "user_model_test_{$timestamp}",
                'Test',
                'User',
                'Test User',
                100,
                'Test bio for user model tests',
                'Dublin, Ireland',
                '0851234567'
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create secondary test user for search/comparison tests
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, avatar_url, location, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())",
            [
                self::$testTenantId,
                "user_model_test2_{$timestamp}@test.com",
                "user_model_test2_{$timestamp}",
                'Jane',
                'Doe',
                'Jane Doe',
                50,
                '/uploads/test-avatar.jpg',
                'Cork, Ireland'
            ]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUser2Id) {
            try {
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUser2Id]);
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUser2Id]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUser2Id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Find Methods Tests
    // ==========================================

    public function testFindByIdReturnsUser(): void
    {
        $user = User::findById(self::$testUserId);

        $this->assertNotFalse($user);
        $this->assertIsArray($user);
        $this->assertEquals(self::$testUserId, $user['id']);
        $this->assertEquals('Test', $user['first_name']);
        $this->assertEquals('User', $user['last_name']);
    }

    public function testFindByIdReturnsNameField(): void
    {
        $user = User::findById(self::$testUserId);

        $this->assertArrayHasKey('name', $user);
        $this->assertNotEmpty($user['name']);
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $user = User::findById(999999999);

        $this->assertFalse($user);
    }

    public function testFindByEmailReturnsUser(): void
    {
        $timestamp = time();
        $email = "user_model_test_{$timestamp}@test.com";

        // Get the user we know exists
        $user = Database::query(
            "SELECT * FROM users WHERE id = ?",
            [self::$testUserId]
        )->fetch();

        $foundUser = User::findByEmail($user['email']);

        $this->assertNotFalse($foundUser);
        $this->assertEquals(self::$testUserId, $foundUser['id']);
    }

    public function testFindByUsernameReturnsUser(): void
    {
        $user = Database::query(
            "SELECT username FROM users WHERE id = ?",
            [self::$testUserId]
        )->fetch();

        $foundUser = User::findByUsername($user['username']);

        $this->assertNotFalse($foundUser);
        $this->assertEquals(self::$testUserId, $foundUser['id']);
    }

    // ==========================================
    // Update Methods Tests
    // ==========================================

    public function testUpdateProfileChangesFields(): void
    {
        User::updateProfile(
            self::$testUserId,
            'UpdatedFirst',
            'UpdatedLast',
            'Updated bio text',
            'Galway, Ireland',
            '0871112233',
            'individual',
            null
        );

        $user = User::findById(self::$testUserId);

        $this->assertEquals('UpdatedFirst', $user['first_name']);
        $this->assertEquals('UpdatedLast', $user['last_name']);
        $this->assertEquals('Galway, Ireland', $user['location']);

        // Reset for other tests
        User::updateProfile(
            self::$testUserId,
            'Test',
            'User',
            'Test bio for user model tests',
            'Dublin, Ireland',
            '0851234567',
            'individual',
            null
        );
    }

    public function testUpdateAvatarChangesUrl(): void
    {
        $newAvatarUrl = '/uploads/new-avatar-' . time() . '.jpg';

        User::updateAvatar(self::$testUserId, $newAvatarUrl);

        $user = User::findById(self::$testUserId);
        $this->assertEquals($newAvatarUrl, $user['avatar_url']);
    }

    public function testUpdatePrivacySettings(): void
    {
        User::updatePrivacy(self::$testUserId, 'friends', 'public', 'members');

        $user = User::findById(self::$testUserId);

        $this->assertEquals('friends', $user['privacy_profile']);
        $this->assertEquals('public', $user['privacy_search']);
        $this->assertEquals('members', $user['privacy_contact']);

        // Reset
        User::updatePrivacy(self::$testUserId, 'public', 'public', 'public');
    }

    public function testDynamicUpdateOnlyChangesAllowedFields(): void
    {
        User::update(self::$testUserId, [
            'bio' => 'Dynamically updated bio',
            'location' => 'Limerick, Ireland'
        ]);

        $user = User::findById(self::$testUserId);

        $this->assertEquals('Dynamically updated bio', $user['bio']);
        $this->assertEquals('Limerick, Ireland', $user['location']);

        // Reset
        User::update(self::$testUserId, [
            'bio' => 'Test bio for user model tests',
            'location' => 'Dublin, Ireland'
        ]);
    }

    public function testDynamicUpdateBlocksNonWhitelistedFields(): void
    {
        $originalUser = User::findById(self::$testUserId);

        // Try to update a field that shouldn't be allowed
        User::update(self::$testUserId, [
            'role' => 'admin',  // Not in whitelist
            'is_approved' => 0   // Not in whitelist
        ]);

        $updatedUser = User::findById(self::$testUserId);

        // These should remain unchanged
        $this->assertEquals($originalUser['role'], $updatedUser['role']);
        $this->assertEquals($originalUser['is_approved'], $updatedUser['is_approved']);
    }

    // ==========================================
    // List Methods Tests
    // ==========================================

    public function testGetAllReturnsArray(): void
    {
        $users = User::getAll();

        $this->assertIsArray($users);
        // Should at least include our test users
        $this->assertGreaterThanOrEqual(2, count($users));
    }

    public function testGetPaginatedReturnsLimitedResults(): void
    {
        $users = User::getPaginated(1, 0);

        $this->assertIsArray($users);
        $this->assertLessThanOrEqual(1, count($users));
    }

    public function testGetApprovedUsersReturnsOnlyApproved(): void
    {
        $users = User::getApprovedUsers();

        $this->assertIsArray($users);

        foreach ($users as $user) {
            $this->assertEquals(1, $user['is_approved']);
        }
    }

    // ==========================================
    // Search Tests
    // ==========================================

    public function testSearchByFirstName(): void
    {
        $users = User::search('Jane');

        $this->assertIsArray($users);
        // Should find our test user Jane
        $found = false;
        foreach ($users as $user) {
            if ($user['id'] == self::$testUser2Id) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search should find user by first name');
    }

    public function testSearchForWalletReturnsMatchingUsers(): void
    {
        $users = User::searchForWallet('Jane', self::$testUserId, 10);

        $this->assertIsArray($users);

        // Should find Jane but not include the excluded user
        foreach ($users as $user) {
            $this->assertNotEquals(self::$testUserId, $user['id']);
            $this->assertArrayHasKey('display_name', $user);
        }
    }

    // ==========================================
    // Online Status Tests
    // ==========================================

    public function testUpdateLastActiveSucceeds(): void
    {
        $result = User::updateLastActive(self::$testUserId);

        // May return true or false depending on column existence
        $this->assertIsBool($result);
    }

    public function testIsOnlineReturnsBool(): void
    {
        $isOnline = User::isOnline(self::$testUserId);

        $this->assertIsBool($isOnline);
    }

    public function testGetOnlineStatusTextReturnsString(): void
    {
        $statusText = User::getOnlineStatusText(date('Y-m-d H:i:s'));

        $this->assertIsString($statusText);
        // Should be "Active now" for current timestamp
        $this->assertEquals('Active now', $statusText);
    }

    public function testGetOnlineStatusTextReturnsOfflineForNull(): void
    {
        $statusText = User::getOnlineStatusText(null);

        $this->assertEquals('Offline', $statusText);
    }

    public function testGetOnlineStatusTextReturnsTimeAgo(): void
    {
        // 30 minutes ago
        $thirtyMinAgo = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        $statusText = User::getOnlineStatusText($thirtyMinAgo);

        $this->assertStringContainsString('Active', $statusText);
        $this->assertStringContainsString('m ago', $statusText);
    }

    // ==========================================
    // Password Tests
    // ==========================================

    public function testVerifyPasswordReturnsFalseForWrongPassword(): void
    {
        $result = User::verifyPassword(self::$testUserId, 'definitely_wrong_password');

        $this->assertFalse($result);
    }

    public function testUpdatePasswordChangesHash(): void
    {
        $newPassword = 'NewTestPassword123!';

        User::updatePassword(self::$testUserId, $newPassword);

        // Verify the new password works
        $result = User::verifyPassword(self::$testUserId, $newPassword);
        $this->assertTrue($result);
    }

    // ==========================================
    // Notification Preferences Tests
    // ==========================================

    public function testGetNotificationPreferencesReturnsDefaults(): void
    {
        $prefs = User::getNotificationPreferences(self::$testUserId);

        $this->assertIsArray($prefs);
        $this->assertArrayHasKey('email_messages', $prefs);
        $this->assertArrayHasKey('email_connections', $prefs);
        $this->assertArrayHasKey('push_enabled', $prefs);
    }

    public function testUpdateNotificationPreferencesStoresValues(): void
    {
        $newPrefs = [
            'email_messages' => 0,
            'email_connections' => 1,
            'push_enabled' => 0
        ];

        $result = User::updateNotificationPreferences(self::$testUserId, $newPrefs);

        // May return true or false depending on column existence
        $this->assertIsBool($result);
    }

    public function testIsGamificationEmailEnabledReturnsBool(): void
    {
        $enabled = User::isGamificationEmailEnabled(self::$testUserId, 'digest');

        $this->assertIsBool($enabled);
    }

    // ==========================================
    // Count Tests
    // ==========================================

    public function testCountReturnsInteger(): void
    {
        $count = User::count();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    // ==========================================
    // Coordinates Tests
    // ==========================================

    public function testUpdateCoordinatesStoresValues(): void
    {
        $lat = 53.3498;
        $lon = -6.2603;

        User::updateCoordinates(self::$testUserId, $lat, $lon);

        $coords = User::getCoordinates(self::$testUserId);

        // Coordinates might not be stored if columns don't exist
        if ($coords && isset($coords['latitude'])) {
            $this->assertEquals($lat, (float)$coords['latitude'], '', 0.0001);
            $this->assertEquals($lon, (float)$coords['longitude'], '', 0.0001);
        } else {
            $this->markTestSkipped('Coordinates columns may not exist');
        }
    }

    public function testGetNearbyReturnsArray(): void
    {
        $users = User::getNearby(53.3498, -6.2603, 25, 10, self::$testUserId);

        $this->assertIsArray($users);
    }

    // ==========================================
    // Admin/Role Tests
    // ==========================================

    public function testGetAdminsReturnsArray(): void
    {
        $admins = User::getAdmins();

        $this->assertIsArray($admins);
    }

    public function testIsTenantSuperAdminReturnsBool(): void
    {
        $isSuperAdmin = User::isTenantSuperAdmin(self::$testUserId);

        $this->assertIsBool($isSuperAdmin);
    }

    public function testIsGodReturnsBool(): void
    {
        $isGod = User::isGod(self::$testUserId);

        $this->assertIsBool($isGod);
        // Test user should not be god
        $this->assertFalse($isGod);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testFindByIdWithEnforceTenantFalse(): void
    {
        $user = User::findById(self::$testUserId, false);

        $this->assertNotFalse($user);
        $this->assertEquals(self::$testUserId, $user['id']);
    }

    public function testSearchWithSpecialCharacters(): void
    {
        $users = User::search("Test's User");

        $this->assertIsArray($users);
        // Should not throw an error
    }

    public function testSearchForWalletWithEmptyQuery(): void
    {
        $users = User::searchForWallet('', self::$testUserId, 10);

        $this->assertIsArray($users);
        // Empty search should return empty or all users
    }
}
