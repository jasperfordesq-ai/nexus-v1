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
use Tests\Laravel\TestCase;

/**
 * B1 regression lock — fractional time-credit precision.
 *
 * Exchanges mint `max(0.25, ...)` fractional hours and WalletService accepts
 * 2-decimal amounts, but the money columns were `int(11)` (verified live on
 * prod 2026-06-21). Writing 0.25 into an int column rounds it to 0 with NO
 * exception — silently destroying or creating real value. The fix is the
 * `2026_06_21_000001_fix_money_columns_to_decimal` migration (amount/balance
 * -> DECIMAL(10,2)); this test proves the precision survives a real transfer
 * and is the canary that fails loudly if the columns ever revert to int.
 *
 * Requires the money columns to be DECIMAL. The first test asserts that
 * directly, so on an int-typed DB this whole concern surfaces as a clear
 * failure rather than a silent rounding.
 */
class WalletServiceFractionalTest extends TestCase
{
    use DatabaseTransactions;

    private WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WalletService::class);
    }

    /**
     * Guardrail: the money columns MUST be DECIMAL. If this fails, the DB has
     * reverted to int and every fractional credit is being silently rounded.
     */
    public function test_money_columns_are_decimal_not_int(): void
    {
        foreach ([['transactions', 'amount'], ['users', 'balance']] as [$table, $column]) {
            $row = \DB::selectOne(
                'SELECT COLUMN_TYPE AS type FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );
            $this->assertNotNull($row, "{$table}.{$column} should exist");
            $this->assertStringContainsStringIgnoringCase(
                'decimal',
                (string) $row->type,
                "{$table}.{$column} must be DECIMAL — an int column silently rounds fractional credits to whole numbers"
            );
        }
    }

    /**
     * A 0.25-hour transfer must leave the sender, the receiver, AND the stored
     * transaction.amount at exactly 0.25 — never rounded to 0.
     */
    public function test_fractional_transfer_preserves_quarter_hour_exactly(): void
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create(['balance' => 1.00]);
        $receiver = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0.00]);

        // Re-pin: factory create() drifts TenantContext, and transfer() resolves
        // the recipient through the tenant-scoped User query.
        TenantContext::setById($this->testTenantId);
        $result = $this->service->transfer($sender->id, [
            'recipient'   => $receiver->id,
            'amount'      => 0.25,
            'description' => 'quarter-hour exchange credit',
        ]);

        $sender->refresh();
        $receiver->refresh();

        // Balances keep the quarter hour exactly.
        $this->assertEqualsWithDelta(0.75, (float) $sender->balance, 0.0001, 'Sender: 1.00 - 0.25 = 0.75 (int column would give 1)');
        $this->assertEqualsWithDelta(0.25, (float) $receiver->balance, 0.0001, 'Receiver: 0.00 + 0.25 = 0.25 (int column would give 0)');
        $this->assertNotEquals(0.0, (float) $receiver->balance, 'A 0.25 credit must not be rounded to 0');

        // The ledger row stored the exact fractional amount.
        TenantContext::setById($this->testTenantId);
        $txn = Transaction::where('tenant_id', $this->testTenantId)
            ->where('id', (int) $result['id'])
            ->firstOrFail();
        $this->assertEqualsWithDelta(0.25, (float) $txn->amount, 0.0001, 'transactions.amount must store 0.25, not 0');

        // Money conservation across the fractional transfer.
        $this->assertEqualsWithDelta(
            1.00,
            (float) $sender->balance + (float) $receiver->balance,
            0.0001,
            'Total credits must be conserved: 0.75 + 0.25 = 1.00'
        );
    }
}
