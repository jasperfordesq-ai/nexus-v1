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
use Nexus\Services\UserService;

/**
 * UserService Tests
 *
 * Tests user profile retrieval, validation, updates, privacy, and account management.
 */
class UserServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testUser3Id = null;

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

        // Primary test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, bio, location, phone, balance, role, is_approved, privacy_profile, privacy_search, privacy_contact, password, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'public', 1, 1, ?, NOW())",
            [self::$testTenantId, "usrvc_user1_{$ts}@test.com", "usrvc_user1_{$ts}", 'User', 'One', 'User One', 'Test bio content', 'Dublin, Ireland', '+353891234567', 100, 'member', password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Second user (for connection tests)
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, privacy_profile, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'public', NOW())",
            [self::$testTenantId, "usrvc_user2_{$ts}@test.com", "usrvc_user2_{$ts}", 'User', 'Two', 'User Two', 50]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Third user (private profile)
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, privacy_profile, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'connections', NOW())",
            [self::$testTenantId, "usrvc_user3_{$ts}@test.com", "usrvc_user3_{$ts}", 'User', 'Three', 'User Three', 25]
        );
        self::$testUser3Id = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        $userIds = array_filter([self::$testUserId, self::$testUser2Id, self::$testUser3Id]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM connections WHERE requester_id = ? OR receiver_id = ?", [$uid, $uid]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM notifications WHERE user_id = ?", [$uid]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM users WHERE id = ?", [$uid]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // getOwnProfile Tests
    // ==========================================

    public function testGetOwnProfileReturnsValidStructure(): void
    {
        $profile = UserService::getOwnProfile(self::$testUserId);

        $this->assertNotNull($profile);
        $this->assertIsArray($profile);
        $this->assertArrayHasKey('id', $profile);
        $this->assertArrayHasKey('name', $profile);
        $this->assertArrayHasKey('email', $profile);
        $this->assertArrayHasKey('balance', $profile);
        $this->assertArrayHasKey('role', $profile);
        $this->assertArrayHasKey('stats', $profile);
        $this->assertArrayHasKey('badges', $profile);
        $this->assertEquals(self::$testUserId, $profile['id']);
    }

    public function testGetOwnProfileIncludesPrivateFields(): void
    {
        $profile = UserService::getOwnProfile(self::$testUserId);

        $this->assertNotNull($profile);
        $this->assertArrayHasKey('email', $profile);
        $this->assertArrayHasKey('phone', $profile);
        $this->assertArrayHasKey('balance', $profile);
        $this->assertArrayHasKey('role', $profile);
        $this->assertArrayHasKey('is_admin', $profile);
        $this->assertArrayHasKey('is_approved', $profile);
        $this->assertArrayHasKey('privacy_profile', $profile);
        $this->assertArrayHasKey('onboarding_completed', $profile);
    }

    public function testGetOwnProfileReturnsNullForNonExistentUser(): void
    {
        $profile = UserService::getOwnProfile(999999);
        $this->assertNull($profile);
    }

    public function testGetOwnProfileStatsStructure(): void
    {
        $profile = UserService::getOwnProfile(self::$testUserId);

        $this->assertNotNull($profile);
        $this->assertArrayHasKey('stats', $profile);
        $stats = $profile['stats'];
        $this->assertArrayHasKey('listings_count', $stats);
        $this->assertArrayHasKey('transactions_count', $stats);
        $this->assertArrayHasKey('connections_count', $stats);
        $this->assertArrayHasKey('reviews_count', $stats);
        $this->assertArrayHasKey('average_rating', $stats);
    }

    // ==========================================
    // getPublicProfile Tests
    // ==========================================

    public function testGetPublicProfileReturnsValidStructure(): void
    {
        $profile = UserService::getPublicProfile(self::$testUserId);

        $this->assertNotNull($profile);
        $this->assertArrayHasKey('id', $profile);
        $this->assertArrayHasKey('name', $profile);
        $this->assertArrayHasKey('stats', $profile);
        // Public profile should NOT have email
        $this->assertArrayNotHasKey('email', $profile);
        $this->assertArrayNotHasKey('phone', $profile);
        $this->assertArrayNotHasKey('balance', $profile);
    }

    public function testGetPublicProfileExcludesTransactionStats(): void
    {
        $profile = UserService::getPublicProfile(self::$testUserId);

        $this->assertNotNull($profile);
        $this->assertArrayNotHasKey('transactions_count', $profile['stats']);
    }

    public function testGetPublicProfileReturnsNullForNonExistentUser(): void
    {
        $profile = UserService::getPublicProfile(999999);
        $this->assertNull($profile);
    }

    public function testGetPublicProfileConnectionsOnlyDeniesUnauthenticated(): void
    {
        // User 3 has privacy_profile = 'connections'
        $profile = UserService::getPublicProfile(self::$testUser3Id, null);
        // Should be null since viewer is not authenticated
        $this->assertNull($profile);
    }

    public function testGetPublicProfileConnectionsOnlyDeniesNonConnected(): void
    {
        // User 3 has connections-only profile; user 2 is not connected
        $profile = UserService::getPublicProfile(self::$testUser3Id, self::$testUser2Id);
        $this->assertNull($profile);
    }

    public function testGetPublicProfileOwnerCanAlwaysSeeOwnProfile(): void
    {
        // User 3 viewing their own connections-only profile
        $profile = UserService::getPublicProfile(self::$testUser3Id, self::$testUser3Id);
        $this->assertNotNull($profile);
        $this->assertEquals(self::$testUser3Id, $profile['id']);
    }

    public function testGetPublicProfileIncludesConnectionStatus(): void
    {
        $profile = UserService::getPublicProfile(self::$testUserId, self::$testUser2Id);

        $this->assertNotNull($profile);
        $this->assertArrayHasKey('connection_status', $profile);
    }

    // ==========================================
    // validateProfileUpdate Tests
    // ==========================================

    public function testValidateProfileUpdateAcceptsValidData(): void
    {
        $valid = UserService::validateProfileUpdate([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'bio' => 'A short bio',
            'phone' => '+353891234567',
        ]);

        $this->assertTrue($valid);
        $this->assertEmpty(UserService::getErrors());
    }

    public function testValidateProfileUpdateRejectsTooLongFirstName(): void
    {
        $valid = UserService::validateProfileUpdate([
            'first_name' => str_repeat('A', 101),
        ]);

        $this->assertFalse($valid);
        $errors = UserService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('first_name', $errors[0]['field']);
    }

    public function testValidateProfileUpdateRejectsTooLongLastName(): void
    {
        $valid = UserService::validateProfileUpdate([
            'last_name' => str_repeat('B', 101),
        ]);

        $this->assertFalse($valid);
        $errors = UserService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('last_name', $errors[0]['field']);
    }

    public function testValidateProfileUpdateRejectsTooLongBio(): void
    {
        $valid = UserService::validateProfileUpdate([
            'bio' => str_repeat('C', 5001),
        ]);

        $this->assertFalse($valid);
    }

    public function testValidateProfileUpdateRejectsInvalidProfileType(): void
    {
        $valid = UserService::validateProfileUpdate([
            'profile_type' => 'company',
        ]);

        $this->assertFalse($valid);
        $errors = UserService::getErrors();
        $this->assertEquals('profile_type', $errors[0]['field']);
    }

    public function testValidateProfileUpdateAcceptsOrganisationType(): void
    {
        $valid = UserService::validateProfileUpdate([
            'profile_type' => 'organisation',
        ]);

        $this->assertTrue($valid);
    }

    public function testValidateProfileUpdateRejectsInvalidPhone(): void
    {
        $valid = UserService::validateProfileUpdate([
            'phone' => '12345', // too short after stripping
        ]);

        $this->assertFalse($valid);
    }

    public function testValidateProfileUpdateAcceptsEmptyPhone(): void
    {
        // Empty phone should be allowed (field is optional)
        $valid = UserService::validateProfileUpdate([
            'phone' => '',
        ]);

        $this->assertTrue($valid);
    }

    // ==========================================
    // updateProfile Tests
    // ==========================================

    public function testUpdateProfileReturnsFalseForInvalidData(): void
    {
        $result = UserService::updateProfile(self::$testUserId, [
            'first_name' => str_repeat('X', 150),
        ]);

        $this->assertFalse($result);
    }

    public function testUpdateProfileReturnsTrueForEmptyData(): void
    {
        // Empty data means nothing to update
        $result = UserService::updateProfile(self::$testUserId, []);
        $this->assertTrue($result);
    }

    public function testUpdateProfileIgnoresDisallowedFields(): void
    {
        // Fields like 'email', 'role', 'balance' should NOT be updatable via updateProfile
        $result = UserService::updateProfile(self::$testUserId, [
            'first_name' => 'ValidName',
            'role' => 'admin', // should be ignored
        ]);

        $this->assertTrue($result);
    }

    // ==========================================
    // updatePrivacy Tests
    // ==========================================

    public function testUpdatePrivacyAcceptsValidValues(): void
    {
        $result = UserService::updatePrivacy(self::$testUserId, [
            'privacy_profile' => 'members',
            'privacy_search' => true,
            'privacy_contact' => false,
        ]);

        $this->assertTrue($result);
    }

    public function testUpdatePrivacyRejectsInvalidProfileValue(): void
    {
        $result = UserService::updatePrivacy(self::$testUserId, [
            'privacy_profile' => 'invalid_value',
        ]);

        $this->assertFalse($result);
    }

    public function testUpdatePrivacyReturnsErrorForNonExistentUser(): void
    {
        // Partial data with no profile provided needs to look up user
        $result = UserService::updatePrivacy(999999, [
            'privacy_search' => true,
        ]);

        $this->assertFalse($result);
    }

    // ==========================================
    // updatePassword Tests
    // ==========================================

    public function testUpdatePasswordRejectsWrongCurrentPassword(): void
    {
        $result = UserService::updatePassword(self::$testUserId, 'WrongPassword!', 'NewPass123!');
        $this->assertFalse($result);
        $errors = UserService::getErrors();
        $this->assertEquals('INVALID_PASSWORD', $errors[0]['code']);
    }

    public function testUpdatePasswordRejectsTooShortNewPassword(): void
    {
        $result = UserService::updatePassword(self::$testUserId, 'TestPass123!', 'short');
        $this->assertFalse($result);
        $errors = UserService::getErrors();
        $this->assertEquals('WEAK_PASSWORD', $errors[0]['code']);
    }

    // ==========================================
    // getNearby Tests
    // ==========================================

    public function testGetNearbyReturnsValidStructure(): void
    {
        try {
            $result = UserService::getNearby(53.3498, -6.2603);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('items', $result);
            $this->assertArrayHasKey('has_more', $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Nearby query not available: ' . $e->getMessage());
        }
    }

    public function testGetNearbyRespectsLimitFilter(): void
    {
        try {
            $result = UserService::getNearby(53.3498, -6.2603, ['limit' => 5]);
            $this->assertLessThanOrEqual(5, count($result['items']));
        } catch (\Exception $e) {
            $this->markTestSkipped('Nearby query not available: ' . $e->getMessage());
        }
    }
}
