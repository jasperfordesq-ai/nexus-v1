<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use Nexus\Models\Transaction;
use Nexus\Models\User;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class TransactionTest extends TestCase
{
    private static $testSenderId;
    private static $testReceiverId;
    private static $testTenantId = 1;
    private static $initialBalance = 100;

    public static function setUpBeforeClass(): void
    {
        // Set up test tenant context
        TenantContext::setById(self::$testTenantId);

        // Create test sender
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, 'test_sender@test.com', 'Test', 'Sender', 'Test Sender', ?, 1, NOW())",
            [self::$testTenantId, self::$initialBalance]
        );
        self::$testSenderId = Database::getInstance()->lastInsertId();

        // Create test receiver
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, 'test_receiver@test.com', 'Test', 'Receiver', 'Test Receiver', 0, 1, NOW())",
            [self::$testTenantId]
        );
        self::$testReceiverId = Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test data in correct order (foreign key constraints)
        if (self::$testSenderId && self::$testReceiverId) {
            Database::query(
                "DELETE FROM transactions WHERE sender_id = ? OR receiver_id = ?",
                [self::$testSenderId, self::$testSenderId]
            );
            Database::query(
                "DELETE FROM transactions WHERE sender_id = ? OR receiver_id = ?",
                [self::$testReceiverId, self::$testReceiverId]
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

    /**
     * Test successful transaction creation
     */
    public function testCreateTransaction(): void
    {
        $amount = 10;
        $description = 'Test transaction ' . time();

        $transactionId = Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            $amount,
            $description
        );

        $this->assertNotEmpty($transactionId, 'Transaction should return an ID');
        $this->assertIsNumeric($transactionId, 'Transaction ID should be numeric');
    }

    /**
     * Test that sender balance decreases after transaction
     */
    public function testSenderBalanceDecreases(): void
    {
        $amount = 15;

        Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            $amount,
            'Balance decrease test'
        );

        $sender = User::findById(self::$testSenderId);

        $this->assertEquals(
            self::$initialBalance - $amount,
            $sender['balance'],
            'Sender balance should decrease by transaction amount'
        );
    }

    /**
     * Test that receiver balance increases after transaction
     */
    public function testReceiverBalanceIncreases(): void
    {
        $amount = 20;

        Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            $amount,
            'Balance increase test'
        );

        $receiver = User::findById(self::$testReceiverId);

        $this->assertEquals(
            $amount,
            $receiver['balance'],
            'Receiver balance should increase by transaction amount'
        );
    }

    /**
     * Test transaction history retrieval
     */
    public function testGetHistory(): void
    {
        $description = 'History test ' . time();

        Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            5,
            $description
        );

        $history = Transaction::getHistory(self::$testSenderId);

        $this->assertIsArray($history, 'History should be an array');
        $this->assertNotEmpty($history, 'History should not be empty after transaction');

        // Check that our transaction is in the history
        $found = false;
        foreach ($history as $trx) {
            if ($trx['description'] === $description) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Transaction should appear in sender history');
    }

    /**
     * Test transaction appears in receiver history too
     */
    public function testTransactionAppearsInReceiverHistory(): void
    {
        $description = 'Receiver history test ' . time();

        Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            5,
            $description
        );

        $history = Transaction::getHistory(self::$testReceiverId);

        $found = false;
        foreach ($history as $trx) {
            if ($trx['description'] === $description) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Transaction should appear in receiver history');
    }

    /**
     * Test countForUser returns correct count
     */
    public function testCountForUser(): void
    {
        // Get initial count
        $initialCount = Transaction::countForUser(self::$testSenderId);

        // Create a new transaction
        Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            5,
            'Count test'
        );

        $newCount = Transaction::countForUser(self::$testSenderId);

        $this->assertEquals(
            $initialCount + 1,
            $newCount,
            'Transaction count should increase by 1'
        );
    }

    /**
     * Test getTotalEarned calculates correctly
     */
    public function testGetTotalEarned(): void
    {
        // Get initial earned amount for receiver
        $initialEarned = Transaction::getTotalEarned(self::$testReceiverId);

        $amount = 25;
        Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            $amount,
            'Total earned test'
        );

        $newEarned = Transaction::getTotalEarned(self::$testReceiverId);

        $this->assertEquals(
            $initialEarned + $amount,
            $newEarned,
            'Total earned should increase by transaction amount'
        );
    }

    /**
     * Test soft delete for sender
     */
    public function testSoftDeleteForSender(): void
    {
        $description = 'Delete test sender ' . time();

        $transactionId = Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            5,
            $description
        );

        // Delete for sender
        Transaction::delete($transactionId, self::$testSenderId);

        // Transaction should not appear in sender's history
        $senderHistory = Transaction::getHistory(self::$testSenderId);
        $foundInSender = false;
        foreach ($senderHistory as $trx) {
            if ($trx['id'] == $transactionId) {
                $foundInSender = true;
                break;
            }
        }
        $this->assertFalse($foundInSender, 'Deleted transaction should not appear in sender history');

        // But should still appear in receiver's history
        $receiverHistory = Transaction::getHistory(self::$testReceiverId);
        $foundInReceiver = false;
        foreach ($receiverHistory as $trx) {
            if ($trx['id'] == $transactionId) {
                $foundInReceiver = true;
                break;
            }
        }
        $this->assertTrue($foundInReceiver, 'Deleted transaction should still appear in receiver history');
    }

    /**
     * Test soft delete for receiver
     */
    public function testSoftDeleteForReceiver(): void
    {
        $description = 'Delete test receiver ' . time();

        $transactionId = Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            5,
            $description
        );

        // Delete for receiver
        Transaction::delete($transactionId, self::$testReceiverId);

        // Transaction should not appear in receiver's history
        $receiverHistory = Transaction::getHistory(self::$testReceiverId);
        $foundInReceiver = false;
        foreach ($receiverHistory as $trx) {
            if ($trx['id'] == $transactionId) {
                $foundInReceiver = true;
                break;
            }
        }
        $this->assertFalse($foundInReceiver, 'Deleted transaction should not appear in receiver history');

        // But should still appear in sender's history
        $senderHistory = Transaction::getHistory(self::$testSenderId);
        $foundInSender = false;
        foreach ($senderHistory as $trx) {
            if ($trx['id'] == $transactionId) {
                $foundInSender = true;
                break;
            }
        }
        $this->assertTrue($foundInSender, 'Deleted transaction should still appear in sender history');
    }

    /**
     * Test transaction history includes user names
     */
    public function testHistoryIncludesUserNames(): void
    {
        Transaction::create(
            self::$testSenderId,
            self::$testReceiverId,
            5,
            'Name test'
        );

        $history = Transaction::getHistory(self::$testSenderId);
        $latestTransaction = $history[0];

        $this->assertArrayHasKey('sender_name', $latestTransaction, 'History should include sender name');
        $this->assertArrayHasKey('receiver_name', $latestTransaction, 'History should include receiver name');
        $this->assertNotEmpty($latestTransaction['sender_name'], 'Sender name should not be empty');
        $this->assertNotEmpty($latestTransaction['receiver_name'], 'Receiver name should not be empty');
    }

    /**
     * Test transaction rollback on failure
     * This tests that if something goes wrong, balances are not changed
     */
    public function testTransactionAtomicity(): void
    {
        $senderBefore = User::findById(self::$testSenderId);
        $receiverBefore = User::findById(self::$testReceiverId);

        // Try to create a transaction with invalid receiver (should fail)
        try {
            Transaction::create(
                self::$testSenderId,
                999999999, // Non-existent user
                10,
                'Atomicity test'
            );
        } catch (\Exception $e) {
            // Expected to fail
        }

        $senderAfter = User::findById(self::$testSenderId);
        $receiverAfter = User::findById(self::$testReceiverId);

        $this->assertEquals(
            $senderBefore['balance'],
            $senderAfter['balance'],
            'Sender balance should not change on failed transaction'
        );
        $this->assertEquals(
            $receiverBefore['balance'],
            $receiverAfter['balance'],
            'Receiver balance should not change on failed transaction'
        );
    }
}
