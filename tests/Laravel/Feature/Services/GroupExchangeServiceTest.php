<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Services;

use App\Core\TenantContext;
use App\Services\GroupExchangeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * H3 — money-path regression tests for GroupExchangeService::complete().
 *
 * The runtime guards were already correct (atomic status-claim, a single
 * DB::transaction, a balance>=amount debit guard), but there was ZERO test that
 * the credits conserve, that a double-complete is idempotent, or that an
 * insufficient receiver balance rolls the whole completion back. These add that.
 *
 * Whole-hour amounts only (custom split with explicit hours) so the assertions
 * are exact on both int and decimal money columns.
 */
class GroupExchangeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private GroupExchangeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GroupExchangeService::class);
        TenantContext::setById($this->testTenantId);
    }

    private function makeUser(float $balance): int
    {
        $id = (int) DB::table('users')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'first_name' => 'GX',
            'last_name'  => 'Member',
            'email'      => 'gx.' . uniqid('', true) . '@example.com',
            'username'   => 'gx_' . substr(md5(uniqid('', true)), 0, 12),
            'password'   => password_hash('password', PASSWORD_BCRYPT),
            'balance'    => $balance,
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        TenantContext::setById($this->testTenantId);

        return $id;
    }

    /**
     * @param array<int, array{user_id:int, role:string, hours:int|float}> $participants
     */
    private function seedExchange(array $participants, string $status = 'pending_confirmation'): int
    {
        $organizer = $this->makeUser(0);

        $exchangeId = (int) DB::table('group_exchanges')->insertGetId([
            'tenant_id'    => $this->testTenantId,
            'title'        => 'Barn raising',
            'organizer_id' => $organizer,
            'status'       => $status,
            'split_type'   => 'custom',
            'total_hours'  => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        foreach ($participants as $p) {
            DB::table('group_exchange_participants')->insert([
                'group_exchange_id' => $exchangeId,
                'user_id'           => $p['user_id'],
                'role'              => $p['role'],
                'hours'             => $p['hours'],
                'confirmed'         => 1,
                'confirmed_at'      => now(),
                'created_at'        => now(),
            ]);
        }

        return $exchangeId;
    }

    private function balanceOf(int $userId): float
    {
        return (float) DB::table('users')->where('id', $userId)->value('balance');
    }

    private function exchangeCreditCount(int $providerId): int
    {
        return (int) DB::table('transactions')
            ->where('tenant_id', $this->testTenantId)
            ->where('receiver_id', $providerId)
            ->where('transaction_type', 'exchange')
            ->count();
    }

    public function test_complete_conserves_credits_and_is_idempotent(): void
    {
        $provider = $this->makeUser(0);
        $receiver = $this->makeUser(10);
        $exchangeId = $this->seedExchange([
            ['user_id' => $provider, 'role' => 'provider', 'hours' => 6],
            ['user_id' => $receiver, 'role' => 'receiver', 'hours' => 6],
        ]);

        $totalBefore = $this->balanceOf($provider) + $this->balanceOf($receiver); // 10

        TenantContext::setById($this->testTenantId);
        $r1 = $this->service->complete($exchangeId);
        $this->assertTrue($r1['success'] ?? false, 'first complete must succeed: ' . json_encode($r1));

        // Provider +6, receiver −6.
        $this->assertEqualsWithDelta(6, $this->balanceOf($provider), 0.001, 'provider credited 6');
        $this->assertEqualsWithDelta(4, $this->balanceOf($receiver), 0.001, 'receiver debited 6 (10 -> 4)');

        // Conservation across the two wallets — nothing minted or burned.
        $this->assertEqualsWithDelta(
            $totalBefore,
            $this->balanceOf($provider) + $this->balanceOf($receiver),
            0.001,
            'group exchange must conserve total credits'
        );
        $this->assertSame(1, $this->exchangeCreditCount($provider), 'exactly one credit ledger row');

        // Idempotency: a second complete() must NOT move money again.
        TenantContext::setById($this->testTenantId);
        $r2 = $this->service->complete($exchangeId);
        $this->assertFalse($r2['success'] ?? true, 'second complete must be a no-op');

        $this->assertEqualsWithDelta(6, $this->balanceOf($provider), 0.001, 'provider not credited twice');
        $this->assertEqualsWithDelta(4, $this->balanceOf($receiver), 0.001, 'receiver not debited twice');
        $this->assertSame(1, $this->exchangeCreditCount($provider), 'no second credit ledger row');
    }

    public function test_complete_rolls_back_on_insufficient_receiver_balance(): void
    {
        $provider = $this->makeUser(0);
        $receiver = $this->makeUser(3); // less than the 6 required
        $exchangeId = $this->seedExchange([
            ['user_id' => $provider, 'role' => 'provider', 'hours' => 6],
            ['user_id' => $receiver, 'role' => 'receiver', 'hours' => 6],
        ]);

        TenantContext::setById($this->testTenantId);
        try {
            $this->service->complete($exchangeId);
            $this->fail('Expected a RuntimeException for insufficient receiver balance');
        } catch (\RuntimeException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        // Whole completion rolled back: no credit, no debit, no ledger row,
        // and the atomic status-claim was rolled back too (not 'completed').
        $this->assertEqualsWithDelta(0, $this->balanceOf($provider), 0.001, 'provider must not be credited');
        $this->assertEqualsWithDelta(3, $this->balanceOf($receiver), 0.001, 'receiver must not be debited');
        $this->assertSame(0, $this->exchangeCreditCount($provider), 'no credit ledger row on rollback');

        $status = DB::table('group_exchanges')->where('id', $exchangeId)->value('status');
        $this->assertNotSame('completed', $status, 'the status-claim must roll back with the failed money movement');
    }
}
