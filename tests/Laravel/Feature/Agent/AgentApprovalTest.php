<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Agent;

use App\Services\Agent\AgentExecutor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * AG61 — verifies approving a tandem proposal materialises a
 * caring_support_relationships row via AgentExecutor.
 */
class AgentApprovalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approving_tandem_creates_relationship(): void
    {
        if (!Schema::hasTable('agent_proposals') || !Schema::hasTable('caring_support_relationships')) {
            $this->markTestSkipped('Required tables missing.');
        }

        // Create a parent run so the proposal FK is valid.
        $runId = DB::table('agent_runs')->insertGetId([
            'tenant_id'           => $this->testTenantId,
            'agent_type'          => 'tandem_matching',
            'status'              => 'completed',
            'triggered_by'        => 'manual',
            'proposals_generated' => 1,
            'proposals_applied'   => 0,
            'started_at'          => now(),
            'completed_at'        => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $supporterId = 9001;
        $recipientId = 9002;

        $proposalId = DB::table('agent_proposals')->insertGetId([
            'tenant_id'        => $this->testTenantId,
            'run_id'           => $runId,
            'proposal_type'    => 'create_tandem',
            'subject_user_id'  => $supporterId,
            'target_user_id'   => $recipientId,
            'proposal_data'    => json_encode([
                'supporter_id' => $supporterId,
                'recipient_id' => $recipientId,
            ]),
            'status'           => 'pending_review',
            'confidence_score' => 0.85,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $reviewerId = 1;
        $result = AgentExecutor::approve($proposalId, $this->testTenantId, $reviewerId, 'Looks good');

        $this->assertSame('approved', $result['status'] ?? null);

        $rel = DB::table('caring_support_relationships')
            ->where('tenant_id', $this->testTenantId)
            ->where('supporter_id', $supporterId)
            ->where('recipient_id', $recipientId)
            ->first();

        $this->assertNotNull($rel, 'Approving a tandem proposal must create a caring_support_relationships row.');

        $run = DB::table('agent_runs')->where('id', $runId)->first();
        $this->assertSame(1, (int) $run->proposals_applied);

        if (Schema::hasTable('agent_decisions')) {
            $decision = DB::table('agent_decisions')->where('proposal_id', $proposalId)->first();
            $this->assertNotNull($decision);
            $this->assertSame('approve', $decision->decision);
        }
    }
}
