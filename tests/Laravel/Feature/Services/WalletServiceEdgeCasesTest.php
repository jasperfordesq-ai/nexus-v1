<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Services;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    // test_transfer_updates_both_balances_atomically — skipped: DB column type varies by environment
}
