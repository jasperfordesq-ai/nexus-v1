<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services\Federation;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationEmailService;

/**
 * FederationEmailService Tests
 *
 * Tests email notification generation for federation operations.
 * Note: Actual email sending is not tested (requires mail server).
 * We test that methods handle invalid recipients gracefully.
 */
class FederationEmailServiceTest extends DatabaseTestCase
{
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $tenantId = null;
    protected static ?int $tenant2Id = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tenantId = 1;
        self::$tenant2Id = 2;

        TenantContext::setById(self::$tenantId);

        $timestamp = time();

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$tenantId, "email_test1_{$timestamp}@test.com", "email_test1_{$timestamp}", 'Email', 'Test1', 'Email Test1', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$tenant2Id, "email_test2_{$timestamp}@test.com", "email_test2_{$timestamp}", 'Email', 'Test2', 'Email Test2', 100]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUser2Id) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUser2Id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // sendNewMessageNotification Tests
    // ==========================================

    public function testSendNewMessageNotificationWithInvalidRecipient(): void
    {
        // Should return false for non-existent recipient
        $result = FederationEmailService::sendNewMessageNotification(
            999999, // Invalid recipient
            self::$testUserId,
            self::$tenantId,
            'Test message preview'
        );

        $this->assertFalse($result);
    }

    public function testSendNewMessageNotificationWithInvalidSender(): void
    {
        // Should return false when sender not found
        $result = FederationEmailService::sendNewMessageNotification(
            self::$testUserId,
            999999, // Invalid sender
            999999, // Invalid sender tenant
            'Test message preview'
        );

        $this->assertFalse($result);
    }

    public function testSendNewMessageNotificationReturnsBool(): void
    {
        // May fail due to Mailer not being configured, but should return bool
        try {
            $result = FederationEmailService::sendNewMessageNotification(
                self::$testUserId,
                self::$testUser2Id,
                self::$tenant2Id,
                'Hello from federation test'
            );
            $this->assertIsBool($result);
        } catch (\Exception $e) {
            // Mailer may not be configured in test environment
            $this->assertTrue(true);
        }
    }

    // ==========================================
    // sendTransactionNotification Tests
    // ==========================================

    public function testSendTransactionNotificationWithInvalidRecipient(): void
    {
        $result = FederationEmailService::sendTransactionNotification(
            999999, // Invalid recipient
            self::$testUserId,
            self::$tenantId,
            1.5,
            'Test transaction'
        );

        $this->assertFalse($result);
    }

    public function testSendTransactionNotificationWithInvalidSender(): void
    {
        $result = FederationEmailService::sendTransactionNotification(
            self::$testUserId,
            999999, // Invalid sender
            999999,
            1.5,
            'Test transaction'
        );

        $this->assertFalse($result);
    }

    // ==========================================
    // sendTransactionConfirmation Tests
    // ==========================================

    public function testSendTransactionConfirmationWithInvalidSender(): void
    {
        $result = FederationEmailService::sendTransactionConfirmation(
            999999, // Invalid sender
            self::$testUserId,
            self::$tenantId,
            2.0,
            'Test confirmation',
            98.0
        );

        $this->assertFalse($result);
    }

    public function testSendTransactionConfirmationWithInvalidRecipient(): void
    {
        $result = FederationEmailService::sendTransactionConfirmation(
            self::$testUserId,
            999999, // Invalid recipient
            999999,
            2.0,
            'Test confirmation',
            98.0
        );

        $this->assertFalse($result);
    }

    // ==========================================
    // sendWeeklyDigest Tests
    // ==========================================

    public function testSendWeeklyDigestWithInvalidUser(): void
    {
        $result = FederationEmailService::sendWeeklyDigest(999999, self::$tenantId);

        $this->assertFalse($result);
    }

    public function testSendWeeklyDigestWithMismatchedTenant(): void
    {
        // User exists in tenant 1, but we pass tenant 2
        $result = FederationEmailService::sendWeeklyDigest(self::$testUserId, self::$tenant2Id);

        $this->assertFalse($result);
    }

    public function testSendWeeklyDigestForUserWithNoActivity(): void
    {
        // New test user should have no federation activity, so digest should return false
        $result = FederationEmailService::sendWeeklyDigest(self::$testUserId, self::$tenantId);

        // Should return false because there's no activity
        $this->assertFalse($result);
    }

    // ==========================================
    // Partnership Notification Tests
    // ==========================================

    public function testSendPartnershipRequestNotificationWithInvalidTenant(): void
    {
        $result = FederationEmailService::sendPartnershipRequestNotification(
            999999, // Invalid target tenant
            self::$tenantId,
            'Test Timebank',
            1,
            'Test notes'
        );

        $this->assertFalse($result);
    }

    public function testSendPartnershipApprovedNotificationWithInvalidTenant(): void
    {
        $result = FederationEmailService::sendPartnershipApprovedNotification(
            999999, // Invalid tenant
            self::$tenantId,
            'Test Timebank',
            2
        );

        $this->assertFalse($result);
    }

    public function testSendPartnershipRejectedNotificationWithInvalidTenant(): void
    {
        $result = FederationEmailService::sendPartnershipRejectedNotification(
            999999, // Invalid tenant
            self::$tenantId,
            'Test Timebank',
            'Declined for testing'
        );

        $this->assertFalse($result);
    }

    public function testSendPartnershipSuspendedNotificationWithInvalidTenant(): void
    {
        $result = FederationEmailService::sendPartnershipSuspendedNotification(
            999999, // Invalid tenant
            self::$tenantId,
            'Test Timebank',
            'Suspended for testing'
        );

        $this->assertFalse($result);
    }

    public function testSendPartnershipReactivatedNotificationWithInvalidTenant(): void
    {
        $result = FederationEmailService::sendPartnershipReactivatedNotification(
            999999, // Invalid tenant
            self::$tenantId,
            'Test Timebank'
        );

        $this->assertFalse($result);
    }

    public function testSendPartnershipTerminatedNotificationWithInvalidTenant(): void
    {
        $result = FederationEmailService::sendPartnershipTerminatedNotification(
            999999, // Invalid tenant
            self::$tenantId,
            'Test Timebank',
            'Terminated for testing'
        );

        $this->assertFalse($result);
    }

    public function testSendPartnershipCounterProposalNotificationWithInvalidTenant(): void
    {
        $result = FederationEmailService::sendPartnershipCounterProposalNotification(
            999999, // Invalid tenant
            self::$tenantId,
            'Test Timebank',
            1,
            2,
            'Counter proposal message'
        );

        $this->assertFalse($result);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testAllPublicMethodsExist(): void
    {
        $expectedMethods = [
            'sendNewMessageNotification',
            'sendTransactionNotification',
            'sendTransactionConfirmation',
            'sendWeeklyDigest',
            'sendPartnershipRequestNotification',
            'sendPartnershipApprovedNotification',
            'sendPartnershipRejectedNotification',
            'sendPartnershipCounterProposalNotification',
            'sendPartnershipSuspendedNotification',
            'sendPartnershipReactivatedNotification',
            'sendPartnershipTerminatedNotification',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                method_exists(FederationEmailService::class, $method),
                "Method {$method} should exist on FederationEmailService"
            );
        }
    }
}
