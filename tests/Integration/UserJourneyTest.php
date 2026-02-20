<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Integration;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\User;
use Nexus\Services\TokenService;

/**
 * User Journey Integration Test
 *
 * Tests complete user lifecycle workflows:
 * - Registration → verification → profile completion → login
 * - Profile updates → password changes → logout
 * - Password reset flow
 */
class UserJourneyTest extends DatabaseTestCase
{
    private static int $testTenantId = 2; // hour-timebank tenant
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    protected function tearDown(): void
    {
        // Clean up created users
        foreach ($this->createdUserIds as $userId) {
            try {
                Database::query("DELETE FROM api_tokens WHERE user_id = ?", [$userId]);
                Database::query("DELETE FROM email_verifications WHERE user_id = ?", [$userId]);
                Database::query("DELETE FROM password_resets WHERE user_id = ?", [$userId]);
                Database::query("DELETE FROM users WHERE id = ?", [$userId]);
            } catch (\Exception $e) {
                // Ignore cleanup errors in rollback
            }
        }
        parent::tearDown();
    }

    /**
     * Test: Complete user registration and login flow
     */
    public function testCompleteRegistrationAndLoginFlow(): void
    {
        $timestamp = time();
        $email = "journey_test_{$timestamp}@example.com";
        $password = 'SecurePass123!';

        // Step 1: Register new user
        $userData = [
            'tenant_id' => self::$testTenantId,
            'email' => $email,
            'username' => "journey_user_{$timestamp}",
            'first_name' => 'Journey',
            'last_name' => 'TestUser',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_approved' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
            [
                $userData['tenant_id'],
                $userData['email'],
                $userData['username'],
                $userData['first_name'],
                $userData['last_name'],
                $userData['first_name'] . ' ' . $userData['last_name'],
                $userData['password_hash'],
                $userData['is_approved'],
                $userData['created_at']
            ]
        );

        $userId = (int)Database::lastInsertId();
        $this->createdUserIds[] = $userId;
        $this->assertGreaterThan(0, $userId, 'User should be created with valid ID');

        // Step 2: Verify user exists in database
        $user = User::findById($userId);
        $this->assertNotNull($user, 'User should exist in database');
        $this->assertEquals($email, $user['email'], 'Email should match');
        $this->assertEquals($userData['first_name'], $user['first_name'], 'First name should match');

        // Step 3: Simulate email verification
        $verificationToken = bin2hex(random_bytes(32));
        Database::query(
            "INSERT INTO email_verifications (user_id, token, expires_at, created_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())",
            [$userId, hash('sha256', $verificationToken)]
        );

        // Verify token was created
        $stmt = Database::query(
            "SELECT * FROM email_verifications WHERE user_id = ? AND expires_at > NOW()",
            [$userId]
        );
        $verification = $stmt->fetch();
        $this->assertNotFalse($verification, 'Verification token should exist');

        // Simulate verification process
        Database::query(
            "UPDATE users SET email_verified_at = NOW() WHERE id = ?",
            [$userId]
        );

        // Step 4: Login and generate access token
        $user = User::findById($userId);
        $this->assertNotNull($user['email_verified_at'], 'Email should be verified');

        // Verify password
        $this->assertTrue(
            password_verify($password, $user['password_hash']),
            'Password should verify correctly'
        );

        // Generate auth token
        $accessToken = TokenService::generateAccessToken($userId, self::$testTenantId);
        $this->assertNotEmpty($accessToken, 'Access token should be generated');

        // Step 5: Verify token grants access
        $tokenHash = hash('sha256', $accessToken);
        $stmt = Database::query(
            "SELECT * FROM api_tokens WHERE token = ? AND user_id = ? AND type = 'access'",
            [$tokenHash, $userId]
        );
        $storedToken = $stmt->fetch();
        $this->assertNotFalse($storedToken, 'Token should be stored in database');
        $this->assertEquals($userId, $storedToken['user_id'], 'Token should belong to user');
    }

    /**
     * Test: Profile update flow
     */
    public function testProfileUpdateFlow(): void
    {
        $timestamp = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 0)",
            [
                self::$testTenantId,
                "profile_test_{$timestamp}@example.com",
                "profile_user_{$timestamp}",
                'Profile',
                'Test',
                'Profile Test',
                password_hash('password', PASSWORD_DEFAULT)
            ]
        );

        $userId = (int)Database::lastInsertId();
        $this->createdUserIds[] = $userId;

        // Step 1: Read current profile
        $user = User::findById($userId);
        $this->assertEquals('Profile', $user['first_name']);
        $this->assertNull($user['bio'] ?? null);

        // Step 2: Update profile fields
        Database::query(
            "UPDATE users SET bio = ?, location = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            ['This is my bio', 'Dublin, Ireland', $userId, self::$testTenantId]
        );

        // Step 3: Verify updates persisted
        $updatedUser = User::findById($userId);
        $this->assertEquals('This is my bio', $updatedUser['bio']);
        $this->assertEquals('Dublin, Ireland', $updatedUser['location']);
        $this->assertNotNull($updatedUser['updated_at']);
    }

    /**
     * Test: Password change flow
     */
    public function testPasswordChangeFlow(): void
    {
        $timestamp = time();
        $originalPassword = 'OldPassword123!';
        $newPassword = 'NewPassword456!';

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 0)",
            [
                self::$testTenantId,
                "password_test_{$timestamp}@example.com",
                "password_user_{$timestamp}",
                'Password',
                'Test',
                'Password Test',
                password_hash($originalPassword, PASSWORD_DEFAULT)
            ]
        );

        $userId = (int)Database::lastInsertId();
        $this->createdUserIds[] = $userId;

        // Step 1: Verify original password works
        $user = User::findById($userId);
        $this->assertTrue(
            password_verify($originalPassword, $user['password_hash']),
            'Original password should verify'
        );

        // Step 2: Change password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        Database::query(
            "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$newHash, $userId, self::$testTenantId]
        );

        // Step 3: Verify new password works and old one doesn't
        $updatedUser = User::findById($userId);
        $this->assertTrue(
            password_verify($newPassword, $updatedUser['password_hash']),
            'New password should verify'
        );
        $this->assertFalse(
            password_verify($originalPassword, $updatedUser['password_hash']),
            'Old password should no longer verify'
        );
    }

    /**
     * Test: Password reset flow
     */
    public function testPasswordResetFlow(): void
    {
        $timestamp = time();
        $email = "reset_test_{$timestamp}@example.com";
        $originalPassword = 'OldPassword123!';

        // Step 1: Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 0)",
            [
                self::$testTenantId,
                $email,
                "reset_user_{$timestamp}",
                'Reset',
                'Test',
                'Reset Test',
                password_hash($originalPassword, PASSWORD_DEFAULT)
            ]
        );

        $userId = (int)Database::lastInsertId();
        $this->createdUserIds[] = $userId;

        // Step 2: Generate password reset token
        $resetToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $resetToken);

        Database::query(
            "INSERT INTO password_resets (email, token, tenant_id, expires_at, created_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())",
            [$email, $tokenHash, self::$testTenantId]
        );

        // Step 3: Verify reset token exists
        $stmt = Database::query(
            "SELECT * FROM password_resets WHERE email = ? AND tenant_id = ? AND expires_at > NOW()",
            [$email, self::$testTenantId]
        );
        $reset = $stmt->fetch();
        $this->assertNotFalse($reset, 'Password reset token should exist');
        $this->assertTrue(
            hash_equals($tokenHash, $reset['token']),
            'Token hash should match'
        );

        // Step 4: Reset password using token
        $newPassword = 'ResetPassword789!';
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        Database::query(
            "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ? AND tenant_id = ?",
            [$newHash, $email, self::$testTenantId]
        );

        // Mark token as used
        Database::query(
            "DELETE FROM password_resets WHERE email = ? AND tenant_id = ?",
            [$email, self::$testTenantId]
        );

        // Step 5: Verify new password works
        $user = User::findById($userId);
        $this->assertTrue(
            password_verify($newPassword, $user['password_hash']),
            'New password should verify after reset'
        );

        // Step 6: Verify token was consumed
        $stmt = Database::query(
            "SELECT * FROM password_resets WHERE email = ? AND tenant_id = ?",
            [$email, self::$testTenantId]
        );
        $this->assertFalse($stmt->fetch(), 'Reset token should be consumed after use');
    }

    /**
     * Test: User logout flow (token revocation)
     */
    public function testUserLogoutFlow(): void
    {
        $timestamp = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 0)",
            [
                self::$testTenantId,
                "logout_test_{$timestamp}@example.com",
                "logout_user_{$timestamp}",
                'Logout',
                'Test',
                'Logout Test',
                password_hash('password', PASSWORD_DEFAULT)
            ]
        );

        $userId = (int)Database::lastInsertId();
        $this->createdUserIds[] = $userId;

        // Step 1: Generate auth token (login)
        $accessToken = TokenService::generateAccessToken($userId, self::$testTenantId);
        $tokenHash = hash('sha256', $accessToken);

        // Step 2: Verify token exists
        $stmt = Database::query(
            "SELECT * FROM api_tokens WHERE token = ? AND user_id = ? AND type = 'access'",
            [$tokenHash, $userId]
        );
        $this->assertNotFalse($stmt->fetch(), 'Token should exist after login');

        // Step 3: Logout (revoke token)
        Database::query(
            "DELETE FROM api_tokens WHERE token = ? AND user_id = ?",
            [$tokenHash, $userId]
        );

        // Step 4: Verify token no longer exists
        $stmt = Database::query(
            "SELECT * FROM api_tokens WHERE token = ? AND user_id = ?",
            [$tokenHash, $userId]
        );
        $this->assertFalse($stmt->fetch(), 'Token should be revoked after logout');
    }
}
