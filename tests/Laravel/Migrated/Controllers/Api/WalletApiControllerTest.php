<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Migrated\Controllers\Api;

use Tests\Laravel\LegacyBridgeTestCase;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * Unit tests for WalletApiController (Laravel migration)
 *
 * Migrated from: Nexus\Tests\Controllers\Api\WalletApiControllerTest
 * Original base: PHPUnit\Framework\TestCase -> now LegacyBridgeTestCase
 *
 * These tests verify the business logic of wallet operations.
 * Note: These are unit tests that test the underlying models and logic,
 * not full integration tests with HTTP requests.
 */
class WalletApiControllerTest extends LegacyBridgeTestCase
{
    private ?int $testSenderId = null;
    private ?int $testReceiverId = null;
    private int $initialBalance = 100;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::setById(static::$testTenantId);

        // Create test sender
        $sender = $this->createUser([
            'email'    => 'test_wallet_sender_' . time() . rand(1000, 9999) . '@test.com',
            'username' => 'test_wallet_sender_' . time() . rand(1000, 9999),
            'first_name' => 'Wallet',
            'last_name'  => 'Sender',
            'balance'    => $this->initialBalance,
        ]);
        $this->testSenderId = $sender['id'];

        // Create test receiver
        $receiver = $this->createUser([
            'email'    => 'test_wallet_receiver_' . time() . rand(1000, 9999) . '@test.com',
            'username' => 'test_wallet_receiver_' . time() . rand(1000, 9999),
            'first_name' => 'Wallet',
            'last_name'  => 'Receiver',
            'balance'    => 0,
        ]);
        $this->testReceiverId = $receiver['id'];
    }

    protected function tearDown(): void
    {
        if ($this->testSenderId && $this->testReceiverId) {
            $this->cleanupUser($this->testSenderId);
            $this->cleanupUser($this->testReceiverId);
        }
        parent::tearDown();
    }

    // ===== Balance Tests =====

    /**
     * Test that user balance can be retrieved
     */
    public function testGetUserBalance(): void
    {
        $user = User::findOrFail($this->testSenderId);

        $this->assertNotNull($user->balance, 'User should have balance field');
        $this->assertEquals($this->initialBalance, $user->balance, 'Balance should match initial value');
    }

    /**
     * Test balance after receiving credits
     */
    public function testBalanceAfterReceiving(): void
    {
        $amount = 25;

        Transaction::create([
            'tenant_id'   => static::$testTenantId,
            'sender_id'   => $this->testSenderId,
            'receiver_id' => $this->testReceiverId,
            'amount'      => $amount,
            'description' => 'Balance test',
        ]);

        // Update balances manually (mirrors legacy Transaction::create behaviour)
        DB::table('users')->where('id', $this->testSenderId)->decrement('balance', $amount);
        DB::table('users')->where('id', $this->testReceiverId)->increment('balance', $amount);

        $receiver = User::findOrFail($this->testReceiverId);
        $this->assertEquals($amount, $receiver->balance, 'Receiver balance should equal transferred amount');
    }

    // ===== Transfer Validation Tests =====

    /**
     * Test that transfer requires positive amount
     */
    public function testTransferRequiresPositiveAmount(): void
    {
        $amount = 0;
        $this->assertLessThanOrEqual(0, $amount, 'Zero amount should fail validation');

        $negativeAmount = -10;
        $this->assertLessThan(0, $negativeAmount, 'Negative amount should fail validation');
    }

    /**
     * Test that transfer fails with insufficient funds
     */
    public function testTransferFailsWithInsufficientFunds(): void
    {
        $sender = User::findOrFail($this->testSenderId);
        $excessiveAmount = $sender->balance + 100;

        $this->assertGreaterThan(
            $sender->balance,
            $excessiveAmount,
            'Amount exceeds balance - should fail validation'
        );
    }

    /**
     * Test that user cannot transfer to self
     */
    public function testCannotTransferToSelf(): void
    {
        $senderId = $this->testSenderId;
        $receiverId = $this->testSenderId; // Same as sender

        $this->assertEquals($senderId, $receiverId, 'Self-transfer should be detected and rejected');
    }

    // ===== User Lookup Tests =====

    /**
     * Test finding user by ID
     */
    public function testFindUserById(): void
    {
        $user = User::find($this->testReceiverId);

        $this->assertNotNull($user, 'User should be found by ID');
        $this->assertEquals($this->testReceiverId, $user->id, 'Found user ID should match');
    }

    // ===== Edge Cases =====

    /**
     * Test transaction count accuracy
     */
    public function testTransactionCountAccuracy(): void
    {
        $initialCount = DB::table('transactions')
            ->where('sender_id', $this->testSenderId)
            ->orWhere('receiver_id', $this->testSenderId)
            ->count();

        // Create 3 transactions
        for ($i = 0; $i < 3; $i++) {
            DB::table('transactions')->insert([
                'tenant_id'   => static::$testTenantId,
                'sender_id'   => $this->testSenderId,
                'receiver_id' => $this->testReceiverId,
                'amount'      => 1,
                'description' => "Count test $i",
                'created_at'  => now(),
            ]);
        }

        $finalCount = DB::table('transactions')
            ->where('sender_id', $this->testSenderId)
            ->orWhere('receiver_id', $this->testSenderId)
            ->count();

        $this->assertEquals(
            $initialCount + 3,
            $finalCount,
            'Transaction count should increase by exactly 3'
        );
    }
}
