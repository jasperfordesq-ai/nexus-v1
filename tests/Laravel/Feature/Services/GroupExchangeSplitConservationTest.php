<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\GroupExchangeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression: a group exchange's computed split must CONSERVE credits — the sum
 * of provider shares (credited) must equal the sum of receiver shares (debited),
 * both equal to total_hours. calculateSplit rounded each participant's share to
 * 2dp independently per role, so when the provider count != receiver count the
 * two role-totals drifted apart (e.g. total 10h, 2 providers → 5.00+5.00 = 10.00
 * credited, 3 receivers → 3.33×3 = 9.99 debited → 0.01 minted from nothing; the
 * reverse count destroys 0.01). complete() credits providers and debits
 * receivers, so this minted/destroyed real credits. The last participant in each
 * role now absorbs the rounding remainder so each role sums to total_hours exactly.
 */
class GroupExchangeSplitConservationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @param array<int, array{role: string, weight?: float}> $participants
     */
    private function makeExchange(string $splitType, float $totalHours, array $participants): int
    {
        $tid = $this->testTenantId;

        $organizer = User::factory()->forTenant($tid)->create();
        DB::table('users')->where('id', $organizer->id)->update(['tenant_id' => $tid]);

        $exId = (int) DB::table('group_exchanges')->insertGetId([
            'tenant_id'    => $tid,
            'title'        => 'Split conservation test',
            'description'  => 'Exercises calculateSplit conservation.',
            'organizer_id' => $organizer->id,
            'status'       => 'pending',
            'split_type'   => $splitType,
            'total_hours'  => $totalHours,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        foreach ($participants as $p) {
            $u = User::factory()->forTenant($tid)->create();
            DB::table('users')->where('id', $u->id)->update(['tenant_id' => $tid]);
            DB::table('group_exchange_participants')->insert([
                'group_exchange_id' => $exId,
                'user_id'           => $u->id,
                'role'              => $p['role'],
                'weight'            => $p['weight'] ?? 1,
                'hours'             => 0,
                'confirmed'         => 1,
                'created_at'        => now(),
            ]);
        }

        return $exId;
    }

    private function split(int $exId): array
    {
        return TenantContext::runForTenant($this->testTenantId, fn () =>
            app(GroupExchangeService::class)->calculateSplit($exId));
    }

    public function test_equal_split_conserves_with_uneven_role_counts(): void
    {
        // 2 providers + 3 receivers, total 10h.
        $exId = $this->makeExchange('equal', 10.0, [
            ['role' => 'provider'], ['role' => 'provider'],
            ['role' => 'receiver'], ['role' => 'receiver'], ['role' => 'receiver'],
        ]);

        $split = $this->split($exId);
        $providerSum = collect($split)->where('role', 'provider')->sum('hours');
        $receiverSum = collect($split)->where('role', 'receiver')->sum('hours');

        $this->assertEqualsWithDelta(10.0, $providerSum, 0.0001, 'provider shares must sum to total_hours');
        $this->assertEqualsWithDelta(10.0, $receiverSum, 0.0001, 'receiver shares must sum to total_hours');
        $this->assertEqualsWithDelta($providerSum, $receiverSum, 0.0001, 'provider credits must equal receiver debits (conservation)');
    }

    public function test_weighted_split_conserves(): void
    {
        // 3 providers + 2 receivers (equal weights), total 10h.
        $exId = $this->makeExchange('weighted', 10.0, [
            ['role' => 'provider', 'weight' => 1], ['role' => 'provider', 'weight' => 1], ['role' => 'provider', 'weight' => 1],
            ['role' => 'receiver', 'weight' => 1], ['role' => 'receiver', 'weight' => 1],
        ]);

        $split = $this->split($exId);
        $providerSum = collect($split)->where('role', 'provider')->sum('hours');
        $receiverSum = collect($split)->where('role', 'receiver')->sum('hours');

        $this->assertEqualsWithDelta(10.0, $providerSum, 0.0001, 'weighted provider shares must sum to total_hours');
        $this->assertEqualsWithDelta(10.0, $receiverSum, 0.0001, 'weighted receiver shares must sum to total_hours');
        $this->assertEqualsWithDelta($providerSum, $receiverSum, 0.0001, 'conservation must hold for weighted splits');
    }
}
