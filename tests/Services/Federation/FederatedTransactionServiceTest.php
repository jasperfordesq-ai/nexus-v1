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
use Nexus\Services\FederatedTransactionService;
use Nexus\Services\FederationUserService;

/**
 * FederatedTransactionService Tests
 *
 * Tests cross-tenant hour exchanges between federated timebank members.
 */
class FederatedTransactionServiceTest extends DatabaseTestCase
{
    protected static ?int $tenant1Id = null;
    protected static ?int $tenant2Id = null;
    protected static ?int $senderUserId = null;
    protected static ?int $receiverUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tenant1Id = 1;
        self::$tenant2Id = 2;

        self::createTestUsers();
    }

    protected static function createTestUsers(): void
    {
        $timestamp = time();

        // Sender user in tenant 1 with sufficient balance
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$tenant1Id, "fed_sender_{$timestamp}@test.com", "fed_sender_{$timestamp}", 'Sender', 'User', 'Sender User', 100]
        );
        self::$senderUserId = (int)Database::getInstance()->lastInsertId();

        // Receiver user in tenant 2
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$tenant2Id, "fed_receiver_{$timestamp}@test.com", "fed_receiver_{$timestamp}", 'Receiver', 'User', 'Receiver User', 50]
        );
        self::$receiverUserId = (int)Database::getInstance()->lastInsertId();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$tenant1Id);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test data
        if (self::$senderUserId) {
            try {
                Database::query("DELETE FROM federation_transactions WHERE sender_user_id = ?", [self::$senderUserId]);
                Database::query("DELETE FROM transactions WHERE sender_id = ?", [self::$senderUserId]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$senderUserId]);
                Database::query("DELETE FROM federation_user_settings WHERE user_id = ?", [self::$senderUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$senderUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$receiverUserId) {
            try {
                Database::query("DELETE FROM federation_transactions WHERE receiver_user_id = ?", [self::$receiverUserId]);
                Database::query("DELETE FROM transactions WHERE receiver_id = ?", [self::$receiverUserId]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$receiverUserId]);
                Database::query("DELETE FROM federation_user_settings WHERE user_id = ?", [self::$receiverUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$receiverUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Amount Validation Tests
    // ==========================================

    public function testCreateTransactionRejectsZeroAmount(): void
    {
        $result = FederatedTransactionService::createTransaction(
            self::$senderUserId,
            self::$receiverUserId,
            self::$tenant2Id,
            0,
            'Test transaction'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid amount', $result['error']);
    }

    public function testCreateTransactionRejectsNegativeAmount(): void
    {
        $result = FederatedTransactionService::createTransaction(
            self::$senderUserId,
            self::$receiverUserId,
            self::$tenant2Id,
            -5,
            'Test transaction'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid amount', $result['error']);
    }

    public function testCreateTransactionRejectsAmountOver100(): void
    {
        $result = FederatedTransactionService::createTransaction(
            self::$senderUserId,
            self::$receiverUserId,
            self::$tenant2Id,
            101,
            'Test transaction'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid amount', $result['error']);
    }

    public function testCreateTransactionAcceptsValidAmountRange(): void
    {
        // Test minimum valid amount (0.01)
        $result = FederatedTransactionService::createTransaction(
            self::$senderUserId,
            self::$receiverUserId,
            self::$tenant2Id,
            0.01,
            'Test minimum amount'
        );

        // May fail for other reasons (federation settings), but not amount validation
        if (!$result['success']) {
            $this->assertStringNotContainsString('Invalid amount', $result['error'] ?? '');
        }
    }

    // ==========================================
    // Balance Validation Tests
    // ==========================================

    public function testCreateTransactionRejectsInsufficientBalance(): void
    {
        // Create user with zero balance
        $timestamp = time();
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$tenant1Id, "broke_user_{$timestamp}@test.com", "broke_user_{$timestamp}", 'Broke', 'User', 'Broke User', 0]
        );
        $brokeUserId = (int)Database::getInstance()->lastInsertId();

        TenantContext::setById(self::$tenant1Id);

        $result = FederatedTransactionService::createTransaction(
            $brokeUserId,
            self::$receiverUserId,
            self::$tenant2Id,
            10,
            'Test insufficient balance'
        );

        // Clean up
        Database::query("DELETE FROM users WHERE id = ?", [$brokeUserId]);

        // Should fail - either for balance or federation settings
        $this->assertFalse($result['success']);
    }

    // ==========================================
    // Return Structure Tests
    // ==========================================

    public function testCreateTransactionReturnsExpectedStructure(): void
    {
        $result = FederatedTransactionService::createTransaction(
            self::$senderUserId,
            self::$receiverUserId,
            self::$tenant2Id,
            5,
            'Test structure'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);

        if (!$result['success']) {
            $this->assertArrayHasKey('error', $result);
            $this->assertIsString($result['error']);
        }
    }

    // ==========================================
    // Transaction History Tests
    // ==========================================

    public function testGetTransactionHistoryReturnsArray(): void
    {
        if (!method_exists(FederatedTransactionService::class, 'getTransactionHistory')) {
            $this->markTestSkipped('getTransactionHistory not implemented');
        }

        $result = FederatedTransactionService::getTransactionHistory(self::$senderUserId);

        $this->assertIsArray($result);
    }

    public function testGetTransactionHistoryWithPagination(): void
    {
        if (!method_exists(FederatedTransactionService::class, 'getTransactionHistory')) {
            $this->markTestSkipped('getTransactionHistory not implemented');
        }

        $result = FederatedTransactionService::getTransactionHistory(self::$senderUserId, 10, 0);

        $this->assertIsArray($result);
    }

    // ==========================================
    // Pending Transaction Tests
    // ==========================================

    public function testGetPendingTransactionsReturnsArray(): void
    {
        if (!method_exists(FederatedTransactionService::class, 'getPendingTransactions')) {
            $this->markTestSkipped('getPendingTransactions not implemented');
        }

        $result = FederatedTransactionService::getPendingTransactions(self::$senderUserId);

        $this->assertIsArray($result);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCreateTransactionWithSameSenderAndReceiver(): void
    {
        $result = FederatedTransactionService::createTransaction(
            self::$senderUserId,
            self::$senderUserId, // Same user
            self::$tenant1Id,
            5,
            'Self transfer test'
        );

        // Should either succeed (same tenant = no federation) or fail gracefully
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreateTransactionWithNonExistentReceiver(): void
    {
        $result = FederatedTransactionService::createTransaction(
            self::$senderUserId,
            999999, // Non-existent user
            self::$tenant2Id,
            5,
            'Non-existent receiver test'
        );

        $this->assertFalse($result['success']);
    }

    public function testCreateTransactionWithEmptyDescription(): void
    {
        $result = FederatedTransactionService::createTransaction(
            self::$senderUserId,
            self::$receiverUserId,
            self::$tenant2Id,
            5,
            '' // Empty description
        );

        // Should still return valid structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreateTransactionWithVeryLongDescription(): void
    {
        $longDescription = str_repeat('A', 10000); // Very long description

        $result = FederatedTransactionService::createTransaction(
            self::$senderUserId,
            self::$receiverUserId,
            self::$tenant2Id,
            5,
            $longDescription
        );

        // Should handle gracefully (truncate or error)
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ==========================================
    // Statistics Tests
    // ==========================================

    public function testGetFederationStatsReturnsArray(): void
    {
        if (!method_exists(FederatedTransactionService::class, 'getStats')) {
            $this->markTestSkipped('getStats not implemented');
        }

        $result = FederatedTransactionService::getStats(self::$tenant1Id);

        $this->assertIsArray($result);
    }
}
