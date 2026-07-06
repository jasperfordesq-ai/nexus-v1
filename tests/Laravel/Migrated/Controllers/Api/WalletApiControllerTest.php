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
 * Migrated from: App\Tests\Controllers\Api\WalletApiControllerTest
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

        TenantContext::setById(static::$legacyTestTenantId);

        $senderToken = $this->uniqueWalletToken('sender');
        $receiverToken = $this->uniqueWalletToken('receiver');

        // Create test sender
        $sender = $this->createUser([
            'email'    => $senderToken . '@test.com',
            'username' => $senderToken,
            'first_name' => 'Wallet',
            'last_name'  => 'Sender',
            'balance'    => $this->initialBalance,
        ]);
        $this->testSenderId = $sender['id'];

        // Create test receiver
        $receiver = $this->createUser([
            'email'    => $receiverToken . '@test.com',
            'username' => $receiverToken,
            'first_name' => 'Wallet',
            'last_name'  => 'Receiver',
            'balance'    => 0,
        ]);
        $this->testReceiverId = $receiver['id'];
    }

    private function uniqueWalletToken(string $role): string
    {
        return sprintf(
            'test_wallet_%s_%s_%d_%d',
            $role,
            getmypid(),
            (int) (microtime(true) * 1000000),
            random_int(1000, PHP_INT_MAX)
        );
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
        $user = User::withoutGlobalScopes()->findOrFail($this->testSenderId);

        $this->assertNotNull($user->balance, 'User should have balance field');
        $this->assertEquals((float) $this->initialBalance, (float) $user->balance, 'Balance should match initial value');
    }

    /**
     * Test balance after receiving credits
     */
    public function testBalanceAfterReceiving(): void
    {
        $amount = 25;

        Transaction::create([
            'tenant_id'   => static::$legacyTestTenantId,
            'sender_id'   => $this->testSenderId,
            'receiver_id' => $this->testReceiverId,
            'amount'      => $amount,
            'description' => 'Balance test',
        ]);

        // Update balances manually (mirrors legacy Transaction::create behaviour)
        DB::table('users')->where('id', $this->testSenderId)->decrement('balance', $amount);
        DB::table('users')->where('id', $this->testReceiverId)->increment('balance', $amount);

        $receiver = User::withoutGlobalScopes()->findOrFail($this->testReceiverId);
        $this->assertEquals($amount, (float) $receiver->balance, 'Receiver balance should equal transferred amount');
    }

    // ===== Transfer Validation Tests =====
    //
    // Validation of zero/negative amounts, insufficient funds, self-transfer, and
    // cross-tenant transfers is covered with REAL assertions (exceptions + balance
    // rollback) at the service layer by:
    //   - Tests\Laravel\Feature\Services\WalletServiceTest
    //   - Tests\Laravel\Feature\Services\WalletServiceEdgeCasesTest
    //
    // The three methods previously here (testTransferRequiresPositiveAmount,
    // testTransferFailsWithInsufficientFunds, testCannotTransferToSelf) were
    // arithmetic tautologies (e.g. assertLessThanOrEqual(0, 0)) that never called
    // WalletService::transfer() and could not detect a regression. Removed to
    // avoid false confidence.

    // ===== User Lookup Tests =====

    /**
     * Test finding user by ID
     */
    public function testFindUserById(): void
    {
        $user = User::withoutGlobalScopes()->find($this->testReceiverId);

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
                'tenant_id'   => static::$legacyTestTenantId,
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
