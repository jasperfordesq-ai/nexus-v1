<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\Agent\Agents;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG61 — Coordinator router agent.
 *
 * Looks at unassigned help requests + open hour reviews, then suggests
 * which coordinator should handle each. Picks the coordinator with the
 * lightest current load. Each suggestion becomes a proposal for admin
 * approval.
 */
final class CoordinatorRouterAgent extends BaseAgent
{
    public function run(): array
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('caring_help_requests')) {
            return $this->emptyResult('required tables missing');
        }

        $maxProposals = (int) ($this->config['max_proposals_per_run'] ?? 30);

        // Active coordinators with their current open-request load
        $coordinators = DB::table('users')
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'active')
            ->whereIn('role', ['admin', 'coordinator', 'broker'])
            ->select(['id', 'name'])
            ->get();

        if ($coordinators->isEmpty()) {
            return $this->emptyResult('no coordinators available');
        }

        // Compute open-load per coordinator
        $loads = [];
        foreach ($coordinators as $c) {
            $loads[$c->id] = (int) DB::table('caring_help_requests')
                ->where('tenant_id', $this->tenantId)
                ->where('assigned_to', $c->id)
                ->whereIn('status', ['pending', 'in_progress', 'open'])
                ->count();
        }

        // Find unassigned help requests
        $requests = DB::table('caring_help_requests')
            ->where('tenant_id', $this->tenantId)
            ->whereNull('assigned_to')
            ->whereIn('status', ['pending', 'open'])
            ->orderBy('created_at')
            ->limit($maxProposals)
            ->get();

        $created = 0;
        foreach ($requests as $req) {
            // Pick coordinator with the lightest current load
            asort($loads);
            $assignedId = (int) array_key_first($loads);
            if (!$assignedId) {
                break;
            }

            $coordName = $coordinators->firstWhere('id', $assignedId)->name ?? 'Coordinator';

            $this->createProposal(
                type: 'route_help_request',
                data: [
                    'request_id'           => (int) $req->id,
                    'coordinator_id'       => $assignedId,
                    'coordinator_name'     => $coordName,
                    'request_summary'      => mb_substr((string) ($req->description ?? $req->title ?? ''), 0, 200),
                ],
                reasoning: sprintf(
                    'Coordinator %s has the lightest open load (%d). Routing this help request to them for fastest response.',
                    $coordName,
                    $loads[$assignedId],
                ),
                confidence: 0.75,
                targetUserId: $assignedId,
            );

            // Bump simulated load so next request goes elsewhere
            $loads[$assignedId]++;
            $created++;
        }

        return [
            'proposals_created' => $created,
            'summary'           => "Suggested coordinators for {$created} unassigned help request(s).",
            'llm_input_tokens'  => $this->totalInputTokens,
            'llm_output_tokens' => $this->totalOutputTokens,
            'cost_cents'        => 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyResult(string $reason): array
    {
        return [
            'proposals_created' => 0,
            'summary'           => "No proposals: {$reason}",
            'llm_input_tokens'  => 0,
            'llm_output_tokens' => 0,
            'cost_cents'        => 0,
        ];
    }
}
