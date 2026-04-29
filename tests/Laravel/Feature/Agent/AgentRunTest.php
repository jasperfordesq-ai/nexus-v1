<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Agent;

use App\Services\Agent\AgentRunner;
use App\Services\CaringTandemMatchingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * AG61 — verifies a manually triggered agent definition writes both
 * agent_runs and agent_proposals rows.
 */
class AgentRunTest extends TestCase
{
    use DatabaseTransactions;

    public function test_run_inserts_run_row_and_proposals(): void
    {
        if (!Schema::hasTable('agent_definitions') || !Schema::hasTable('agent_proposals')) {
            $this->markTestSkipped('AG61 tables missing.');
        }

        // Stub the matching service so we don't need real user fixtures.
        $stub = new class extends CaringTandemMatchingService {
            public function suggestTandems(int $tenantId, ?int $limit = 20): array
            {
                return [
                    [
                        'supporter' => ['id' => 101, 'name' => 'Alice'],
                        'recipient' => ['id' => 202, 'name' => 'Bob'],
                        'score'     => 0.82,
                        'signals'   => ['distance' => 1.0, 'language' => 1.0],
                        'reason'    => 'Both prefer English and live nearby.',
                    ],
                    [
                        'supporter' => ['id' => 103, 'name' => 'Carol'],
                        'recipient' => ['id' => 204, 'name' => 'Dave'],
                        'score'     => 0.65,
                        'signals'   => ['distance' => 0.7],
                        'reason'    => 'Reasonable distance match.',
                    ],
                ];
            }
        };
        $this->app->instance(CaringTandemMatchingService::class, $stub);

        $defId = DB::table('agent_definitions')->insertGetId([
            'tenant_id'   => $this->testTenantId,
            'slug'        => 'test_tandem_matchmaker_' . uniqid(),
            'name'        => 'Test Matchmaker',
            'description' => null,
            'agent_type'  => 'matchmaker',
            'config'      => json_encode(['max_proposals_per_run' => 10, 'min_score' => 0.4]),
            'is_enabled'  => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $result = AgentRunner::run($defId, 'manual', null);

        $this->assertArrayHasKey('run_id', $result, json_encode($result));
        $this->assertSame(2, (int) ($result['proposals_created'] ?? 0));

        $run = DB::table('agent_runs')->where('id', $result['run_id'])->first();
        $this->assertNotNull($run);
        $this->assertSame('completed', $run->status);
        $this->assertSame(2, (int) $run->proposals_generated);

        $proposals = DB::table('agent_proposals')
            ->where('run_id', $result['run_id'])
            ->where('tenant_id', $this->testTenantId)
            ->get();
        $this->assertCount(2, $proposals);
        $this->assertSame('create_tandem', $proposals[0]->proposal_type);
        $this->assertSame('pending_review', $proposals[0]->status);
    }
}
