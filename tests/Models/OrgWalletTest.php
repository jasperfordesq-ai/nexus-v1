<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use Nexus\Models\OrgWallet;
use Nexus\Models\OrgMember;
use Nexus\Models\OrgTransaction;
use Nexus\Models\User;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class OrgWalletTest extends TestCase
{
    private static $testOwnerId;
    private static $testMemberId;
    private static $testOrgId;
    private static $testTenantId = 1;
    private static $initialUserBalance = 100;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        // Use unique emails with timestamp to avoid conflicts
        $timestamp = time() . rand(1000, 9999);

        // Create test owner
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, 'Org', 'Owner', 'Org Owner', ?, 1, NOW())",
            [self::$testTenantId, "org_owner_{$timestamp}@test.com", self::$initialUserBalance]
        );
        self::$testOwnerId = Database::getInstance()->lastInsertId();

        // Create test member
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, 'Org', 'Member', 'Org Member', ?, 1, NOW())",
            [self::$testTenantId, "org_member_{$timestamp}@test.com", self::$initialUserBalance]
        );
        self::$testMemberId = Database::getInstance()->lastInsertId();

        // Create test organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, status, created_at)
             VALUES (?, ?, 'Test Org', 'Test organization', 'active', NOW())",
            [self::$testTenantId, self::$testOwnerId]
        );
        self::$testOrgId = Database::getInstance()->lastInsertId();

        // Add owner as owner member
        OrgMember::add(self::$testOrgId, self::$testOwnerId, 'owner', 'active');

        // Add member as member
        OrgMember::add(self::$testOrgId, self::$testMemberId, 'member', 'active');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testOrgId) {
            // Clean up in correct order
            Database::query("DELETE FROM org_transactions WHERE organization_id = ?", [self::$testOrgId]);
            Database::query("DELETE FROM org_transfer_requests WHERE organization_id = ?", [self::$testOrgId]);
            Database::query("DELETE FROM org_wallets WHERE organization_id = ?", [self::$testOrgId]);
            Database::query("DELETE FROM org_members WHERE organization_id = ?", [self::$testOrgId]);
            Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
        }
        if (self::$testOwnerId && self::$testMemberId) {
            Database::query("DELETE FROM notifications WHERE user_id IN (?, ?)", [self::$testOwnerId, self::$testMemberId]);
            Database::query("DELETE FROM activity_log WHERE user_id IN (?, ?)", [self::$testOwnerId, self::$testMemberId]);
            Database::query("DELETE FROM users WHERE id IN (?, ?)", [self::$testOwnerId, self::$testMemberId]);
        }
    }

    protected function setUp(): void
    {
        // Reset user balances
        Database::query(
            "UPDATE users SET balance = ? WHERE id IN (?, ?)",
            [self::$initialUserBalance, self::$testOwnerId, self::$testMemberId]
        );

        // Reset org wallet balance
        Database::query(
            "UPDATE org_wallets SET balance = 0 WHERE organization_id = ?",
            [self::$testOrgId]
        );
    }

    /**
     * Test getOrCreate creates wallet if not exists
     */
    public function testGetOrCreateCreatesWallet(): void
    {
        $wallet = OrgWallet::getOrCreate(self::$testOrgId);

        $this->assertIsArray($wallet);
        $this->assertEquals(self::$testOrgId, $wallet['organization_id']);
        $this->assertArrayHasKey('balance', $wallet);
    }

    /**
     * Test getBalance returns zero for new wallet
     */
    public function testGetBalanceReturnsZeroForNewWallet(): void
    {
        $balance = OrgWallet::getBalance(self::$testOrgId);

        $this->assertEquals(0.0, $balance);
    }

    /**
     * Test credit adds to balance
     */
    public function testCreditAddsToBalance(): void
    {
        $amount = 50.0;

        OrgWallet::credit(self::$testOrgId, $amount);
        $balance = OrgWallet::getBalance(self::$testOrgId);

        $this->assertEquals($amount, $balance);
    }

    /**
     * Test credit with invalid amount throws exception
     */
    public function testCreditWithZeroAmountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OrgWallet::credit(self::$testOrgId, 0);
    }

    /**
     * Test credit with negative amount throws exception
     */
    public function testCreditWithNegativeAmountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OrgWallet::credit(self::$testOrgId, -10);
    }

    /**
     * Test debit subtracts from balance
     */
    public function testDebitSubtractsFromBalance(): void
    {
        // First add some balance
        OrgWallet::credit(self::$testOrgId, 100);

        // Then debit
        OrgWallet::debit(self::$testOrgId, 30);
        $balance = OrgWallet::getBalance(self::$testOrgId);

        $this->assertEquals(70.0, $balance);
    }

    /**
     * Test debit with insufficient balance throws exception
     */
    public function testDebitWithInsufficientBalanceThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient');

        OrgWallet::debit(self::$testOrgId, 1000);
    }

    /**
     * Test depositFromUser transfers from user to org
     */
    public function testDepositFromUserTransfersCredits(): void
    {
        $amount = 25;

        $transactionId = OrgWallet::depositFromUser(
            self::$testOwnerId,
            self::$testOrgId,
            $amount,
            'Test deposit'
        );

        $this->assertNotEmpty($transactionId);

        // Check org balance increased
        $orgBalance = OrgWallet::getBalance(self::$testOrgId);
        $this->assertEquals($amount, $orgBalance);

        // Check user balance decreased
        $user = User::findById(self::$testOwnerId);
        $this->assertEquals(self::$initialUserBalance - $amount, $user['balance']);
    }

    /**
     * Test depositFromUser fails with insufficient user balance
     */
    public function testDepositFromUserFailsWithInsufficientBalance(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient');

        OrgWallet::depositFromUser(
            self::$testOwnerId,
            self::$testOrgId,
            1000, // More than user has
            'Should fail'
        );
    }

    /**
     * Test withdrawToUser transfers from org to user
     */
    public function testWithdrawToUserTransfersCredits(): void
    {
        // First fund the org wallet
        OrgWallet::credit(self::$testOrgId, 50);

        $amount = 20;
        $transactionId = OrgWallet::withdrawToUser(
            self::$testOrgId,
            self::$testMemberId,
            $amount,
            'Test withdrawal'
        );

        $this->assertNotEmpty($transactionId);

        // Check org balance decreased
        $orgBalance = OrgWallet::getBalance(self::$testOrgId);
        $this->assertEquals(30.0, $orgBalance);

        // Check user balance increased
        $user = User::findById(self::$testMemberId);
        $this->assertEquals(self::$initialUserBalance + $amount, $user['balance']);
    }

    /**
     * Test withdrawToUser fails with insufficient org balance
     */
    public function testWithdrawToUserFailsWithInsufficientBalance(): void
    {
        $this->expectException(\Exception::class);

        OrgWallet::withdrawToUser(
            self::$testOrgId,
            self::$testMemberId,
            1000,
            'Should fail'
        );
    }

    /**
     * Test transaction history is recorded
     */
    public function testTransactionHistoryIsRecorded(): void
    {
        $description = 'History test ' . time();

        OrgWallet::depositFromUser(
            self::$testOwnerId,
            self::$testOrgId,
            10,
            $description
        );

        $history = OrgWallet::getTransactionHistory(self::$testOrgId);

        $this->assertIsArray($history);
        $this->assertNotEmpty($history);

        // Find our transaction
        $found = false;
        foreach ($history as $txn) {
            if ($txn['description'] === $description) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Transaction should appear in history');
    }

    /**
     * Test getTotalReceived calculates correctly
     */
    public function testGetTotalReceivedCalculatesCorrectly(): void
    {
        // Get initial total before this test
        $initialTotal = OrgWallet::getTotalReceived(self::$testOrgId);

        $amount1 = 15;
        $amount2 = 25;

        OrgWallet::depositFromUser(self::$testOwnerId, self::$testOrgId, $amount1, 'Deposit 1');
        OrgWallet::depositFromUser(self::$testMemberId, self::$testOrgId, $amount2, 'Deposit 2');

        $totalReceived = OrgWallet::getTotalReceived(self::$testOrgId);

        $this->assertEquals($initialTotal + $amount1 + $amount2, $totalReceived);
    }

    /**
     * Test getTotalPaidOut calculates correctly
     */
    public function testGetTotalPaidOutCalculatesCorrectly(): void
    {
        // Get initial total before this test
        $initialTotal = OrgWallet::getTotalPaidOut(self::$testOrgId);

        // Fund the wallet first
        OrgWallet::credit(self::$testOrgId, 100);

        $amount1 = 10;
        $amount2 = 20;

        OrgWallet::withdrawToUser(self::$testOrgId, self::$testOwnerId, $amount1, 'Payout 1');
        OrgWallet::withdrawToUser(self::$testOrgId, self::$testMemberId, $amount2, 'Payout 2');

        $totalPaidOut = OrgWallet::getTotalPaidOut(self::$testOrgId);

        $this->assertEquals($initialTotal + $amount1 + $amount2, $totalPaidOut);
    }

    /**
     * Test transaction atomicity - rollback on failure
     */
    public function testTransactionAtomicity(): void
    {
        $userBefore = User::findById(self::$testOwnerId);
        $orgBalanceBefore = OrgWallet::getBalance(self::$testOrgId);

        // Try deposit more than user has - should fail and rollback
        try {
            OrgWallet::depositFromUser(
                self::$testOwnerId,
                self::$testOrgId,
                99999,
                'Should fail'
            );
        } catch (\Exception $e) {
            // Expected
        }

        $userAfter = User::findById(self::$testOwnerId);
        $orgBalanceAfter = OrgWallet::getBalance(self::$testOrgId);

        $this->assertEquals($userBefore['balance'], $userAfter['balance'], 'User balance should not change on failed transaction');
        $this->assertEquals($orgBalanceBefore, $orgBalanceAfter, 'Org balance should not change on failed transaction');
    }
}
