<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\Agent;

use App\Services\Agent\Agents\ActivitySummariserAgent;
use App\Services\Agent\Agents\BaseAgent;
use App\Services\Agent\Agents\CoordinatorRouterAgent;
use App\Services\Agent\Agents\NudgeDrafterAgent;
use App\Services\Agent\Agents\TandemMatchmakerAgent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AG61 — KI-Agenten orchestrator.
 *
 * Loads an agent_definitions row, invokes the right Agents\* class, and
 * persists run + proposals + token cost. All actions remain proposals
 * (status=pending_review) until an admin approves them via AgentExecutor.
 */
final class AgentRunner
{
    /**
     * Map of agent_type -> concrete class.
     *
     * @var array<string, class-string<BaseAgent>>
     */
    private const AGENT_CLASSES = [
        'matchmaker'           => TandemMatchmakerAgent::class,
        'nudge_drafter'        => NudgeDrafterAgent::class,
        'coordinator_router'   => CoordinatorRouterAgent::class,
        'activity_summariser'  => ActivitySummariserAgent::class,
    ];

    /**
     * Run a single agent definition. Returns a summary array.
     *
     * @return array<string,mixed>
     */
    public static function run(int $definitionId, string $triggeredBy = 'schedule', ?int $triggeredByUserId = null): array
    {
        if (!Schema::hasTable('agent_definitions') || !Schema::hasTable('agent_runs')) {
            return ['error' => 'Agent tables not available'];
        }

        $definition = DB::table('agent_definitions')->where('id', $definitionId)->first();
        if (!$definition) {
            return ['error' => "Agent definition {$definitionId} not found"];
        }

        if (!$definition->is_enabled) {
            return ['skipped' => true, 'reason' => 'agent disabled'];
        }

        $agentType = (string) $definition->agent_type;
        $cls       = self::AGENT_CLASSES[$agentType] ?? null;

        if (!$cls) {
            return ['error' => "Unknown agent_type: {$agentType}"];
        }

        $tenantId = (int) $definition->tenant_id;
        $config   = is_string($definition->config ?? null)
            ? (json_decode((string) $definition->config, true) ?? [])
            : (array) ($definition->config ?? []);

        // Insert run row
        $runId = DB::table('agent_runs')->insertGetId([
            'tenant_id'           => $tenantId,
            'agent_type'          => self::mapAgentTypeToLegacy($agentType),
            'agent_definition_id' => $definitionId,
            'status'              => 'running',
            'triggered_by'        => $triggeredBy,
            'triggered_by_user_id' => $triggeredByUserId,
            'input_context'       => json_encode(['definition_slug' => $definition->slug]),
            'proposals_generated' => 0,
            'proposals_applied'   => 0,
            'started_at'          => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        try {
            /** @var BaseAgent $agent */
            $agent = new $cls($tenantId, $runId, $definitionId, $config);
            $result = $agent->run();

            DB::table('agent_runs')->where('id', $runId)->update([
                'status'              => 'completed',
                'output_summary'      => $result['summary'] ?? null,
                'proposals_generated' => (int) ($result['proposals_created'] ?? 0),
                'llm_input_tokens'    => (int) ($result['llm_input_tokens'] ?? 0),
                'llm_output_tokens'   => (int) ($result['llm_output_tokens'] ?? 0),
                'cost_cents'          => (int) ($result['cost_cents'] ?? 0),
                'completed_at'        => now(),
                'updated_at'          => now(),
            ]);

            DB::table('agent_definitions')->where('id', $definitionId)->update([
                'last_run_at' => now(),
                'updated_at'  => now(),
            ]);

            return [
                'run_id'             => $runId,
                'tenant_id'          => $tenantId,
                'agent_type'         => $agentType,
                'proposals_created'  => (int) ($result['proposals_created'] ?? 0),
                'llm_input_tokens'   => (int) ($result['llm_input_tokens'] ?? 0),
                'llm_output_tokens'  => (int) ($result['llm_output_tokens'] ?? 0),
                'cost_cents'         => (int) ($result['cost_cents'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::error("AgentRunner [{$agentType}] failed: " . $e->getMessage());

            DB::table('agent_runs')->where('id', $runId)->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
                'updated_at'    => now(),
            ]);

            return [
                'run_id'    => $runId,
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Maps the AG61 agent_type slug to the legacy enum used in agent_runs.
     * Keeps both systems compatible while migrating.
     */
    private static function mapAgentTypeToLegacy(string $agentType): string
    {
        return match ($agentType) {
            'matchmaker'          => 'tandem_matching',
            'nudge_drafter'       => 'nudge_dispatch',
            'coordinator_router'  => 'help_routing',
            'activity_summariser' => 'activity_summary',
            default               => 'activity_summary',
        };
    }
}
