<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\PollRankingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Unit tests for PollRankingService.
 *
 * NOTE: These were previously written against a mock-only implementation
 * (DB::shouldReceive(...)). The service was since reworked to call
 * Poll::findOrFail() (real Eloquent + tenant scope) before touching the
 * query builder, so the pure-mock approach can no longer exercise the code.
 * They now seed real poll/option rows in the (transactional) test DB.
 */
class PollRankingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PollRankingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PollRankingService();
    }

    /**
     * Seed a ranked poll with two options and return [pollId, optionAId, optionBId].
     *
     * @return array{0:int,1:int,2:int}
     */
    private function seedPoll(): array
    {
        // Re-pin tenant context: factories/observers elsewhere can reset it.
        TenantContext::setById($this->testTenantId);

        $pollId = (int) DB::table('polls')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'user_id'    => 1,
            'question'   => 'Favourite option?',
            'poll_type'  => 'ranked',
            'is_active'  => 1,
            'created_at' => now(),
        ]);

        $optionA = (int) DB::table('poll_options')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'poll_id'   => $pollId,
            'label'     => 'Option A',
        ]);

        $optionB = (int) DB::table('poll_options')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'poll_id'   => $pollId,
            'label'     => 'Option B',
        ]);

        return [$pollId, $optionA, $optionB];
    }

    public function test_submitRanking_returns_false_if_already_voted(): void
    {
        [$pollId, $optionA] = $this->seedPoll();

        DB::table('poll_rankings')->insert([
            'tenant_id'  => $this->testTenantId,
            'poll_id'    => $pollId,
            'user_id'    => 42,
            'option_id'  => $optionA,
            'rank'       => 1,
            'created_at' => now(),
        ]);

        $result = $this->service->submitRanking($pollId, 42, [
            ['option_id' => $optionA, 'rank' => 1],
        ]);

        $this->assertFalse($result);
    }

    public function test_submitRanking_inserts_rankings_and_returns_true(): void
    {
        [$pollId, $optionA, $optionB] = $this->seedPoll();

        $rankings = [
            ['option_id' => $optionA, 'rank' => 1],
            ['option_id' => $optionB, 'rank' => 2],
        ];

        $result = $this->service->submitRanking($pollId, 7, $rankings);

        $this->assertTrue($result);
        $this->assertSame(2, DB::table('poll_rankings')
            ->where('poll_id', $pollId)
            ->where('user_id', 7)
            ->count());
    }

    public function test_getUserRankings_returns_null_when_empty(): void
    {
        [$pollId] = $this->seedPoll();

        $result = $this->service->getUserRankings($pollId, 999);

        $this->assertNull($result);
    }

    public function test_calculateResults_returns_structure(): void
    {
        [$pollId] = $this->seedPoll();

        $result = $this->service->calculateResults($pollId);

        $this->assertArrayHasKey('total_voters', $result);
        $this->assertArrayHasKey('results', $result);
    }
}
