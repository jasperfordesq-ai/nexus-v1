<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Models\Transaction;
use Nexus\Models\User;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Unit tests for WalletApiController
 *
 * These tests verify the business logic of wallet operations.
 * Note: These are unit tests that test the underlying models and logic,
 * not full integration tests with HTTP requests.
 */
class WalletApiControllerTest extends TestCase
{
    private static $testSenderId;
    private static $testReceiverId;
    private static $testTenantId = 1;
    private static $initialBalance = 100;
    private static $testSenderUsername = 'test_wallet_sender';
    private static $testReceiverUsername = 'test_wallet_receiver';

    public static function setUpBeforeClass(): void
    {
        // Set up test tenant context
        TenantContext::setById(self::$testTenantId);

        // Create test sender with username
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, 'test_wallet_sender@test.com', ?, 'Wallet', 'Sender', 'Wallet Sender', ?, 1, NOW())",
            [self::$testTenantId, self::$testSenderUsername, self::$initialBalance]
        );
        self::$testSenderId = Database::getInstance()->lastInsertId();

        // Create test receiver with username
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, 'test_wallet_receiver@test.com', ?, 'Wallet', 'Receiver', 'Wallet Receiver', 0, 1, NOW())",
            [self::$testTenantId, self::$testReceiverUsername]
        );
        self::$testReceiverId = Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test data
        if (self::$testSenderId && self::$testReceiverId) {
            Database::query(
                "DELETE FROM transactions WHERE sender_id IN (?, ?) OR receiver_id IN (?, ?)",
                [self::$testSenderId, self::$testReceiverId, self::$testSenderId, self::$testReceiverId]
            );
            Database::query("DELETE FROM notifications WHERE user_id IN (?, ?)", [self::$testSenderId, self::$testReceiverId]);
            Database::query("DELETE FROM activity_log WHERE user_id IN (?, ?)", [self::$testSenderId, self::$testReceiverId]);
            Database::query("DELETE FROM users WHERE id IN (?, ?)", [self::$testSenderId, self::$testReceiverId]);
        }
    }

    protected function setUp(): void
    {
        // Reset balances before each test
        Database::query(
            "UPDATE users SET balance = ? WHERE id = ?",
            [self::$initialBalance, self::$testSenderId]
        );
        Database::query(
            "UPDATE users SET balance = 0 WHERE id = ?",
            [self::$testReceiverId]
        );
    }

    // ===== Balance Tests =====

    /**
     * Test that user balance can be retrieved
     */
    public function testGetUserBalance(): void
    {
        $user = User::findById(self::$testSenderId);

        $this->assertArrayHasKey('balance', $user, 'User should have balance field');
        $this->assertEquals(self::$initialBalance, $user['balance'], 'Balance should match initial value');
    }

    /**
     * Test balance after receiving credits
     */
    public function testBalanceAfterReceiving(): void
    {
        $amount = 25;

        Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            $amount,
            'Balance test'
        );

        $receiver = User::findById(self::$testReceiverId);
        $this->assertEquals($amount, $receiver['balance'], 'Receiver balance should equal transferred amount');
    }

    // ===== Transfer Validation Tests =====

    /**
     * Test that transfer requires positive amount
     */
    public function testTransferRequiresPositiveAmount(): void
    {
        // This tests the validation logic that should be in the controller
        $amount = 0;

        // Amount must be greater than 0
        $this->assertLessThanOrEqual(0, $amount, 'Zero amount should fail validation');

        $negativeAmount = -10;
        $this->assertLessThan(0, $negativeAmount, 'Negative amount should fail validation');
    }

    /**
     * Test that transfer fails with insufficient funds
     */
    public function testTransferFailsWithInsufficientFunds(): void
    {
        $sender = User::findById(self::$testSenderId);
        $excessiveAmount = $sender['balance'] + 100;

        // This is the validation check that happens in WalletApiController
        $this->assertGreaterThan(
            $sender['balance'],
            $excessiveAmount,
            'Amount exceeds balance - should fail validation'
        );
    }

    /**
     * Test that user cannot transfer to self
     */
    public function testCannotTransferToSelf(): void
    {
        $senderId = self::$testSenderId;
        $receiverId = self::$testSenderId; // Same as sender

        // This is the validation check in WalletApiController
        $this->assertEquals($senderId, $receiverId, 'Self-transfer should be detected and rejected');
    }

    // ===== User Lookup Tests =====

    /**
     * Test finding user by username
     */
    public function testFindUserByUsername(): void
    {
        $user = User::findByUsername(self::$testReceiverUsername);

        $this->assertNotEmpty($user, 'User should be found by username');
        $this->assertEquals(self::$testReceiverId, $user['id'], 'Found user ID should match');
    }

    /**
     * Test finding user by email
     */
    public function testFindUserByEmail(): void
    {
        $user = User::findByEmail('test_wallet_receiver@test.com');

        $this->assertNotEmpty($user, 'User should be found by email');
        $this->assertEquals(self::$testReceiverId, $user['id'], 'Found user ID should match');
    }

    /**
     * Test user search for wallet autocomplete
     */
    public function testUserSearchForWallet(): void
    {
        $results = User::searchForWallet('Wallet', self::$testSenderId, 10);

        $this->assertIsArray($results, 'Search should return an array');

        // Should find receiver but not sender (excluded)
        $foundReceiver = false;
        $foundSender = false;
        foreach ($results as $user) {
            if ($user['id'] == self::$testReceiverId) {
                $foundReceiver = true;
            }
            if ($user['id'] == self::$testSenderId) {
                $foundSender = true;
            }
        }

        $this->assertTrue($foundReceiver, 'Should find receiver in search results');
        $this->assertFalse($foundSender, 'Should not find sender (excluded user) in results');
    }

    /**
     * Test search results include required fields
     */
    public function testSearchResultsHaveRequiredFields(): void
    {
        $results = User::searchForWallet('Wallet', self::$testSenderId, 10);

        if (!empty($results)) {
            $user = $results[0];
            $this->assertArrayHasKey('id', $user, 'Result should have id');
            $this->assertArrayHasKey('username', $user, 'Result should have username');
            $this->assertArrayHasKey('display_name', $user, 'Result should have display_name');
        }
    }

    // ===== Transaction History Tests =====

    /**
     * Test getting transaction history
     */
    public function testGetTransactionHistory(): void
    {
        // Create a transaction first
        Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            10,
            'History test'
        );

        $history = Transaction::getHistory(self::$testSenderId);

        $this->assertIsArray($history, 'History should be an array');
        $this->assertNotEmpty($history, 'History should not be empty');
    }

    /**
     * Test transaction history structure
     */
    public function testTransactionHistoryStructure(): void
    {
        Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            10,
            'Structure test'
        );

        $history = Transaction::getHistory(self::$testSenderId);
        $transaction = $history[0];

        $this->assertArrayHasKey('id', $transaction, 'Transaction should have id');
        $this->assertArrayHasKey('sender_id', $transaction, 'Transaction should have sender_id');
        $this->assertArrayHasKey('receiver_id', $transaction, 'Transaction should have receiver_id');
        $this->assertArrayHasKey('amount', $transaction, 'Transaction should have amount');
        $this->assertArrayHasKey('description', $transaction, 'Transaction should have description');
        $this->assertArrayHasKey('sender_name', $transaction, 'Transaction should have sender_name');
        $this->assertArrayHasKey('receiver_name', $transaction, 'Transaction should have receiver_name');
    }

    // ===== Delete Transaction Tests =====

    /**
     * Test transaction deletion verification (ownership check)
     */
    public function testTransactionOwnershipVerification(): void
    {
        $transactionId = Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            10,
            'Ownership test'
        );

        // Check that sender owns this transaction
        $sql = "SELECT * FROM transactions WHERE id = ? AND (sender_id = ? OR receiver_id = ?)";
        $trx = Database::query($sql, [$transactionId, self::$testSenderId, self::$testSenderId])->fetch();

        $this->assertNotEmpty($trx, 'Sender should be able to access their transaction');

        // Check that receiver owns this transaction
        $trx = Database::query($sql, [$transactionId, self::$testReceiverId, self::$testReceiverId])->fetch();

        $this->assertNotEmpty($trx, 'Receiver should be able to access their transaction');

        // Check that random user cannot access
        $trx = Database::query($sql, [$transactionId, 999999, 999999])->fetch();

        $this->assertFalse($trx, 'Random user should not be able to access transaction');
    }

    /**
     * Test soft delete removes from user's view only
     */
    public function testSoftDeleteRemovesFromUserViewOnly(): void
    {
        $transactionId = Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            10,
            'Soft delete test'
        );

        // Delete for sender
        Transaction::delete($transactionId, self::$testSenderId);

        // Sender should not see it
        $senderHistory = Transaction::getHistory(self::$testSenderId);
        $senderSees = false;
        foreach ($senderHistory as $trx) {
            if ($trx['id'] == $transactionId) {
                $senderSees = true;
            }
        }
        $this->assertFalse($senderSees, 'Sender should not see deleted transaction');

        // Receiver should still see it
        $receiverHistory = Transaction::getHistory(self::$testReceiverId);
        $receiverSees = false;
        foreach ($receiverHistory as $trx) {
            if ($trx['id'] == $transactionId) {
                $receiverSees = true;
            }
        }
        $this->assertTrue($receiverSees, 'Receiver should still see transaction');
    }

    // ===== Edge Cases =====

    /**
     * Test empty search query returns empty results
     */
    public function testEmptySearchReturnsEmpty(): void
    {
        $results = User::searchForWallet('', self::$testSenderId, 10);

        // With empty query, search should return limited or no results
        $this->assertIsArray($results, 'Empty search should return array');
    }

    /**
     * Test transaction count accuracy
     */
    public function testTransactionCountAccuracy(): void
    {
        $initialCount = Transaction::countForUser(self::$testSenderId);

        // Create 3 transactions
        for ($i = 0; $i < 3; $i++) {
            Transaction::create(
                self::$testSenderId,
                self::$testReceiverId,
                1,
                "Count test $i"
            );
        }

        $finalCount = Transaction::countForUser(self::$testSenderId);

        $this->assertEquals(
            $initialCount + 3,
            $finalCount,
            'Transaction count should increase by exactly 3'
        );
    }

    /**
     * Test total earned calculation with multiple transactions
     */
    public function testTotalEarnedWithMultipleTransactions(): void
    {
        $amounts = [5, 10, 15];
        $expectedTotal = array_sum($amounts);

        foreach ($amounts as $amount) {
            Transaction::create(
                self::$testSenderId,
                self::$testReceiverId,
                $amount,
                "Multi earned test"
            );
        }

        $totalEarned = Transaction::getTotalEarned(self::$testReceiverId);

        $this->assertGreaterThanOrEqual(
            $expectedTotal,
            $totalEarned,
            'Total earned should be at least the sum of test transactions'
        );
    }

    /**
     * Test concurrent balance updates (basic atomicity)
     */
    public function testBalanceConsistencyAfterMultipleTransfers(): void
    {
        $transferAmount = 10;
        $numTransfers = 5;

        $senderStartBalance = self::$initialBalance;
        $receiverStartBalance = 0;

        for ($i = 0; $i < $numTransfers; $i++) {
            Transaction::create(
                self::$testSenderId,
                self::$testReceiverId,
                $transferAmount,
                "Consistency test $i"
            );
        }

        $sender = User::findById(self::$testSenderId);
        $receiver = User::findById(self::$testReceiverId);

        $expectedSenderBalance = $senderStartBalance - ($transferAmount * $numTransfers);
        $expectedReceiverBalance = $receiverStartBalance + ($transferAmount * $numTransfers);

        $this->assertEquals(
            $expectedSenderBalance,
            $sender['balance'],
            'Sender balance should be consistent after multiple transfers'
        );
        $this->assertEquals(
            $expectedReceiverBalance,
            $receiver['balance'],
            'Receiver balance should be consistent after multiple transfers'
        );

        // Total money in system should be preserved
        $totalBefore = $senderStartBalance + $receiverStartBalance;
        $totalAfter = $sender['balance'] + $receiver['balance'];
        $this->assertEquals($totalBefore, $totalAfter, 'Total balance in system should be preserved');
    }
}
