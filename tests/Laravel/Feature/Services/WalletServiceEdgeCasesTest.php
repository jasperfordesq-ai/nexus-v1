<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Services;

use App\Core\TenantContext;
use App\Events\TransactionCompleted;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\Laravel\TestCase;

/**
 * Edge-case tests for WalletService — transfer amount caps, precision validation,
 * banned user rejection, and email-based recipient lookup.
 *
 * These supplement WalletServiceTest by covering validation paths that
 * are critical for financial safety but were previously untested.
 */
class WalletServiceEdgeCasesTest extends TestCase
{
    use DatabaseTransactions;

    private WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WalletService::class);
    }

    // ------------------------------------------------------------------
    //  TRANSFER AMOUNT CAP
    // ------------------------------------------------------------------

    public function test_transfer_fails_when_amount_exceeds_1000_hours(): void
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create([
            'balance' => 2000.00,
        ]);
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Transfer amount cannot exceed 1000 hours');

        $this->service->transfer($sender->id, [
            'recipient' => $receiver->id,
            'amount' => 1001.0,
            'description' => 'Over the cap',
        ]);
    }

    public function test_transfer_succeeds_at_exactly_1000_hours(): void
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create([
            'balance' => 1000.00,
        ]);
        $receiver = User::factory()->forTenant($this->testTenantId)->create([
            'balance' => 0.00,
        ]);

        // Re-pin: factory create() drifts TenantContext; transfer() resolves the
        // recipient through the tenant-scoped User query (see WalletServiceTest).
        TenantContext::setById($this->testTenantId);
        $result = $this->service->transfer($sender->id, [
            'recipient' => $receiver->id,
            'amount' => 1000.0,
            'description' => 'Maximum transfer',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);

        $sender->refresh();
        $receiver->refresh();
        $this->assertEquals(0.00, (float) $sender->balance);
        $this->assertEquals(1000.00, (float) $receiver->balance);
    }

    // ------------------------------------------------------------------
    //  DECIMAL PRECISION VALIDATION
    // ------------------------------------------------------------------

    public function test_transfer_fails_with_more_than_2_decimal_places(): void
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create([
            'balance' => 20.00,
        ]);
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must have at most 2 decimal places');

        $this->service->transfer($sender->id, [
            'recipient' => $receiver->id,
            'amount' => 1.123,
            'description' => 'Too precise',
        ]);
    }

    // test_transfer_succeeds_with_2_decimal_places — skipped: DB column type varies by environment

    // ------------------------------------------------------------------
    //  BANNED/SUSPENDED USER REJECTION
    // ------------------------------------------------------------------

    public function test_transfer_fails_to_banned_user(): void
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create([
            'balance' => 20.00,
        ]);
        $receiver = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'banned',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Recipient account is not active');

        TenantContext::setById($this->testTenantId);
        $this->service->transfer($sender->id, [
            'recipient' => $receiver->id,
            'amount' => 1.0,
            'description' => 'Transfer to banned user',
        ]);
    }

    public function test_transfer_fails_to_suspended_user(): void
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create([
            'balance' => 20.00,
        ]);
        $receiver = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'suspended',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Recipient account is not active');

        TenantContext::setById($this->testTenantId);
        $this->service->transfer($sender->id, [
            'recipient' => $receiver->id,
            'amount' => 1.0,
            'description' => 'Transfer to suspended user',
        ]);
    }

    // test_transfer_fails_to_deactivated_user — skipped: 'deactivated' status not used in this system

    // ------------------------------------------------------------------
    //  EMAIL-BASED RECIPIENT LOOKUP
    // ------------------------------------------------------------------

    public function test_transfer_resolves_recipient_by_email(): void
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create([
            'balance' => 20.00,
        ]);
        $receiverEmail = 'wallet_test_' . uniqid() . '@example.com';
        $receiver = User::factory()->forTenant($this->testTenantId)->create([
            'email' => $receiverEmail,
            'balance' => 0.00,
        ]);

        TenantContext::setById($this->testTenantId);
        $result = $this->service->transfer($sender->id, [
            'recipient' => $receiverEmail,
            'amount' => 2.0,
            'description' => 'Transfer by email',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);

        $receiver->refresh();
        $this->assertEquals(2.00, (float) $receiver->balance);
    }

    // ------------------------------------------------------------------
    //  BALANCE ATOMICITY — Double spend prevention
    // ------------------------------------------------------------------

    /**
     * Money conservation: a chain of transfers among three users must never
     * create or destroy credits. The platform's whole reason to exist is that
     * 1 hour given == 1 hour received — this asserts that invariant directly.
     *
     * Whole-hour amounts only: nexus_test stores balance as INT (prod is
     * decimal(10,2)), so fractional values round and break exact assertions.
     * This is why the original atomicity test was skipped — whole amounts fix it.
     */
    public function test_transfer_conserves_total_credits_across_a_chain(): void
    {
        $alice = User::factory()->forTenant($this->testTenantId)->create(['balance' => 10]);
        $bob   = User::factory()->forTenant($this->testTenantId)->create(['balance' => 5]);
        $carol = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);

        $totalBefore = (float) $alice->balance + (float) $bob->balance + (float) $carol->balance;

        // transfer() must run with the tenant pinned: model create observers
        // (user + transaction) drift TenantContext, and the recipient is resolved
        // through the tenant-scoped User query. Re-pin before EACH call — mirrors
        // the per-call re-pin pattern in WalletServiceTest.
        TenantContext::setById($this->testTenantId);
        $this->service->transfer($alice->id, ['recipient' => $bob->id, 'amount' => 4.0, 'description' => 'chain-1']); // Bob: 5 + 4 = 9

        TenantContext::setById($this->testTenantId);
        $this->service->transfer($bob->id, ['recipient' => $carol->id, 'amount' => 6.0, 'description' => 'chain-2']); // Bob: 9 - 6 = 3, Carol: 0 + 6 = 6

        TenantContext::setById($this->testTenantId);
        $this->service->transfer($carol->id, ['recipient' => $alice->id, 'amount' => 2.0, 'description' => 'chain-3']); // Carol: 6 - 2 = 4, Alice: 10 - 4 + 2 = 8

        $alice->refresh();
        $bob->refresh();
        $carol->refresh();

        // Exact final balances — guards against double-crediting or a missed debit.
        $this->assertEqualsWithDelta(8.0, (float) $alice->balance, 0.001, 'Alice: 10 - 4 + 2');
        $this->assertEqualsWithDelta(3.0, (float) $bob->balance, 0.001, 'Bob: 5 + 4 - 6');
        $this->assertEqualsWithDelta(4.0, (float) $carol->balance, 0.001, 'Carol: 0 + 6 - 2');

        // The invariant: total credits in the tenant are unchanged.
        $this->assertEqualsWithDelta(
            $totalBefore,
            (float) $alice->balance + (float) $bob->balance + (float) $carol->balance,
            0.001,
            'Total credits must be conserved across the whole chain of transfers'
        );
    }

    /**
     * A transfer that fails mid-flight must leave NO partial state — the sender
     * is not debited, the receiver is not credited, and no transaction row is
     * written. This proves the DB::transaction rollback actually protects the
     * money path (regression guard if anyone reorders the debit/insert or moves
     * work outside the transaction).
     */
    public function test_failed_transfer_leaves_balances_and_ledger_unchanged(): void
    {
        $sender   = User::factory()->forTenant($this->testTenantId)->create(['balance' => 3]);
        $receiver = User::factory()->forTenant($this->testTenantId)->create(['balance' => 7]);

        TenantContext::setById($this->testTenantId);

        $txnCountBefore = Transaction::where('tenant_id', $this->testTenantId)
            ->where(function ($q) use ($sender, $receiver) {
                $q->where('sender_id', $sender->id)->orWhere('receiver_id', $receiver->id);
            })
            ->count();

        // Over-spend: sender has 3, tries to send 50 -> must throw and roll back.
        try {
            $this->service->transfer($sender->id, [
                'recipient'   => $receiver->id,
                'amount'      => 50.0,
                'description' => 'should fail and roll back',
            ]);
            $this->fail('Transfer with insufficient balance should have thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient balance', $e->getMessage());
        }

        $sender->refresh();
        $receiver->refresh();

        // No partial debit/credit persisted.
        $this->assertEqualsWithDelta(3.0, (float) $sender->balance, 0.001, 'Sender balance must be untouched by a failed transfer');
        $this->assertEqualsWithDelta(7.0, (float) $receiver->balance, 0.001, 'Receiver balance must be untouched by a failed transfer');

        // No phantom transaction row.
        $txnCountAfter = Transaction::where('tenant_id', $this->testTenantId)
            ->where(function ($q) use ($sender, $receiver) {
                $q->where('sender_id', $sender->id)->orWhere('receiver_id', $receiver->id);
            })
            ->count();
        $this->assertSame($txnCountBefore, $txnCountAfter, 'A failed transfer must not create a transaction row');
    }

    /**
     * J1 seam (PRODUCTION-READINESS §3): the post-commit notification / event
     * dispatch runs OUTSIDE the DB::transaction() that moves the money. That is
     * deliberate and safe — money is committed first, side-effects are
     * best-effort. This locks the property: a failure in the post-commit path
     * (here a throwing TransactionCompleted listener) must NOT roll back or
     * corrupt the already-committed transfer. It would fail if a future refactor
     * moved event() inside the transaction or dropped the surrounding try/catch.
     */
    public function test_post_commit_notification_failure_does_not_roll_back_the_transfer(): void
    {
        $sender   = User::factory()->forTenant($this->testTenantId)->create(['balance' => 10]);
        $receiver = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);

        TenantContext::setById($this->testTenantId);

        // Force the post-commit notification/event path to blow up.
        Event::listen(TransactionCompleted::class, function (): void {
            throw new \RuntimeException('post-commit listener boom — must not roll back committed money');
        });

        // Must NOT throw — the wallet swallows post-commit side-effect failures.
        $result = $this->service->transfer($sender->id, [
            'recipient'   => $receiver->id,
            'amount'      => 4.0,
            'description' => 'post-commit failure isolation',
        ]);

        $sender->refresh();
        $receiver->refresh();

        // Money moved and was recorded despite the throwing listener.
        $this->assertEqualsWithDelta(6.0, (float) $sender->balance, 0.001, 'Sender must still be debited');
        $this->assertEqualsWithDelta(4.0, (float) $receiver->balance, 0.001, 'Receiver must still be credited');
        $this->assertNotEmpty($result, 'Transfer must return a completed result');

        // Re-pin: the post-commit event dispatch drifts TenantContext, which the
        // Transaction model's global tenant scope would otherwise resolve against.
        TenantContext::setById($this->testTenantId);
        $this->assertSame(1, Transaction::where('tenant_id', $this->testTenantId)
            ->where('sender_id', $sender->id)
            ->where('receiver_id', $receiver->id)
            ->where('status', 'completed')
            ->count(), 'The committed transaction row must persist');
    }
}
