<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Services;

use App\Core\TenantContext;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Laravel\TestCase;

/**
 * Anti-double-submit regression tests for WalletService::transfer.
 *
 * The transfer's lockForUpdate prevents over-spend below zero, but NOT duplicate
 * INTENT: a double-click or network retry of a sufficient-balance amount would
 * otherwise create two real, legitimate-looking debits. The idempotency guard
 * (re-implemented from the federation H6 pattern) collapses a duplicate to ONE
 * debit by replaying the original transaction. These tests prove exactly one
 * debit for a double-submit, that the guard does NOT over-collapse genuinely
 * distinct transfers, and that a FAILED transfer releases its claim so a
 * legitimate retry is not blocked.
 *
 * Whole-hour amounts only (balance/amount can be INT-typed on nexus_test).
 */
class WalletServiceDoubleSubmitTest extends TestCase
{
    use DatabaseTransactions;

    private WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WalletService::class);
        // DatabaseTransactions rolls back the DB but not the array cache; flush so
        // an idempotency claim cannot leak between tests.
        Cache::flush();
    }

    public function test_double_submit_with_same_idempotency_key_debits_once(): void
    {
        [$sender, $receiver] = $this->makePair(25, 0);

        TenantContext::setById($this->testTenantId);
        $first = $this->service->transfer($sender->id, [
            'recipient'       => $receiver->id,
            'amount'          => 10,
            'description'     => 'Helped move a sofa',
            'idempotency_key' => 'dup-key-1',
        ]);

        // Immediate resubmit of the identical request (double-click / retry).
        TenantContext::setById($this->testTenantId);
        $second = $this->service->transfer($sender->id, [
            'recipient'       => $receiver->id,
            'amount'          => 10,
            'description'     => 'Helped move a sofa',
            'idempotency_key' => 'dup-key-1',
        ]);

        // Idempotent replay returns the SAME transaction, not a new debit.
        $this->assertSame($first['id'], $second['id'], 'Replay must return the original transaction');

        // Money moved exactly once.
        // Re-pin: the post-commit TransactionCompleted listeners can drift the
        // active tenant, and the assertions below use tenant-scoped queries.
        TenantContext::setById($this->testTenantId);
        $sender->refresh();
        $receiver->refresh();
        $this->assertEquals(15.0, (float) $sender->balance, 'Sender must be debited exactly once');
        $this->assertEquals(10.0, (float) $receiver->balance, 'Receiver must be credited exactly once');

        // Exactly one ledger row despite two submits.
        $this->assertSame(1, Transaction::query()
            ->where('sender_id', $sender->id)
            ->where('receiver_id', $receiver->id)
            ->count(), 'A double-submit must create exactly one transaction');
    }

    public function test_double_submit_same_content_without_key_debits_once(): void
    {
        [$sender, $receiver] = $this->makePair(25, 0);

        // No client key — the 120s content fingerprint must still catch the
        // accidental double-click (same recipient + amount + description).
        $payload = [
            'recipient'   => $receiver->id,
            'amount'      => 10,
            'description' => 'Garden help',
        ];

        TenantContext::setById($this->testTenantId);
        $first = $this->service->transfer($sender->id, $payload);
        TenantContext::setById($this->testTenantId);
        $second = $this->service->transfer($sender->id, $payload);

        $this->assertSame($first['id'], $second['id']);

        // Re-pin: the post-commit TransactionCompleted listeners can drift the
        // active tenant, and the assertions below use tenant-scoped queries.
        TenantContext::setById($this->testTenantId);
        $sender->refresh();
        $receiver->refresh();
        $this->assertEquals(15.0, (float) $sender->balance);
        $this->assertEquals(10.0, (float) $receiver->balance);
        $this->assertSame(1, Transaction::query()
            ->where('sender_id', $sender->id)
            ->where('receiver_id', $receiver->id)
            ->count());
    }

    public function test_distinct_transfers_with_different_keys_are_not_collapsed(): void
    {
        [$sender, $receiver] = $this->makePair(25, 0);

        TenantContext::setById($this->testTenantId);
        $this->service->transfer($sender->id, [
            'recipient'       => $receiver->id,
            'amount'          => 10,
            'description'     => 'Helped move a sofa',
            'idempotency_key' => 'key-A',
        ]);

        // Same content but a DISTINCT key — a real second transfer, must NOT be
        // swallowed as a duplicate.
        TenantContext::setById($this->testTenantId);
        $this->service->transfer($sender->id, [
            'recipient'       => $receiver->id,
            'amount'          => 10,
            'description'     => 'Helped move a sofa',
            'idempotency_key' => 'key-B',
        ]);

        // Re-pin: the post-commit TransactionCompleted listeners can drift the
        // active tenant, and the assertions below use tenant-scoped queries.
        TenantContext::setById($this->testTenantId);
        $sender->refresh();
        $receiver->refresh();
        $this->assertEquals(5.0, (float) $sender->balance, 'Two distinct transfers must both debit');
        $this->assertEquals(20.0, (float) $receiver->balance);
        $this->assertSame(2, Transaction::query()
            ->where('sender_id', $sender->id)
            ->where('receiver_id', $receiver->id)
            ->count());
    }

    public function test_failed_transfer_releases_claim_so_a_retry_is_not_blocked(): void
    {
        // Sender starts with too little; first attempt fails on insufficient
        // balance. The claim must be released so a funded retry with the SAME key
        // succeeds rather than being rejected as a duplicate.
        [$sender, $receiver] = $this->makePair(5, 0);

        TenantContext::setById($this->testTenantId);
        try {
            $this->service->transfer($sender->id, [
                'recipient'       => $receiver->id,
                'amount'          => 10,
                'description'     => 'Too much',
                'idempotency_key' => 'retry-key',
            ]);
            $this->fail('Expected an insufficient-balance failure.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient', $e->getMessage());
        }

        // Fund the sender and retry with the SAME key — must go through.
        $sender->forceFill(['balance' => 15])->save();
        TenantContext::setById($this->testTenantId);
        $result = $this->service->transfer($sender->id, [
            'recipient'       => $receiver->id,
            'amount'          => 10,
            'description'     => 'Too much',
            'idempotency_key' => 'retry-key',
        ]);

        $this->assertArrayHasKey('id', $result);
        // Re-pin: the post-commit TransactionCompleted listeners can drift the
        // active tenant, and the assertions below use tenant-scoped queries.
        TenantContext::setById($this->testTenantId);
        $sender->refresh();
        $receiver->refresh();
        $this->assertEquals(5.0, (float) $sender->balance);
        $this->assertEquals(10.0, (float) $receiver->balance);
        $this->assertSame(1, Transaction::query()
            ->where('sender_id', $sender->id)
            ->where('receiver_id', $receiver->id)
            ->count());
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function makePair(float $senderBalance, float $receiverBalance): array
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create(['balance' => $senderBalance]);
        $receiver = User::factory()->forTenant($this->testTenantId)->create(['balance' => $receiverBalance]);

        return [$sender, $receiver];
    }
}
