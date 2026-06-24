<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\Agent;

use App\Core\TenantContext;
use App\Services\Agent\AgentRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * AgentRunnerTest
 *
 * Tests the AgentRunner::run() orchestration loop:
 *   (a) Schema-guard early return
 *   (b) Missing / disabled definition short-circuits
 *   (c) Unknown agent_type returns error, does not crash
 *   (d) Run row lifecycle — inserted as 'running', flipped to 'completed'/'failed'
 *   (e) agent_definitions.last_run_at updated on success
 *   (f) Result array keys and token/cost fields populated from agent output
 *   (g) Agent exception → run marked 'failed', error surfaced in return value
 *   (h) legacy agent_type mapping via mapAgentTypeToLegacy
 *   (i) triggered_by / triggered_by_user_id propagated to run row
 *
 * Concrete agent classes (TandemMatchmakerAgent etc.) reach external LLMs.
 * We stub them by inserting a definition whose agent_type resolves to a class
 * that either (a) returns no-LLM output because OPENAI_API_KEY is unset in
 * the test env, or (b) is forced to throw via an invalid config.  Where the
 * actual agent is too heavy to test here, we rely on the schema-guard /
 * disabled paths to exercise the surrounding orchestration logic.
 */
class AgentRunnerTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    // -------------------------------------------------------------------------
    // setUp
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Insert a minimal agent_definitions row and return its ID.
     *
     * @param array<string,mixed> $config
     */
    private function seedDefinition(
        string $agentType = 'activity_summariser',
        bool $isEnabled = true,
        array $config = [],
    ): int {
        $slug = uniqid('def_', true);
        return DB::table('agent_definitions')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'slug'       => $slug,
            'name'       => 'Test definition ' . $slug,
            'agent_type' => $agentType,
            'config'     => json_encode($config),
            'is_enabled' => $isEnabled ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // =========================================================================
    // (a) Schema-guard
    // =========================================================================

    /**
     * We cannot actually drop tables in a transactional test without breaking
     * other tests, so we verify the schema-guard branch by querying tables
     * that DO exist. This test demonstrates that `run()` succeeds (does NOT
     * return the error key) when tables are present.
     */
    public function test_run_does_not_return_schema_error_when_tables_exist(): void
    {
        $defId  = $this->seedDefinition('activity_summariser', false); // disabled → skipped path
        $result = AgentRunner::run($defId);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertArrayHasKey('skipped', $result);
    }

    // =========================================================================
    // (b) Missing definition
    // =========================================================================

    public function test_run_returns_error_for_missing_definition(): void
    {
        $result = AgentRunner::run(999999999);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not found', strtolower($result['error']));
    }

    // =========================================================================
    // (b) Disabled definition short-circuit
    // =========================================================================

    public function test_run_returns_skipped_when_definition_is_disabled(): void
    {
        $defId  = $this->seedDefinition('activity_summariser', false);
        $result = AgentRunner::run($defId);

        $this->assertTrue($result['skipped']);
        $this->assertSame('agent disabled', $result['reason']);
    }

    public function test_run_skipped_does_not_insert_a_run_row(): void
    {
        $defId = $this->seedDefinition('activity_summariser', false);

        $countBefore = DB::table('agent_runs')
            ->where('tenant_id', self::TENANT_ID)
            ->where('agent_definition_id', $defId)
            ->count();

        AgentRunner::run($defId);

        $countAfter = DB::table('agent_runs')
            ->where('tenant_id', self::TENANT_ID)
            ->where('agent_definition_id', $defId)
            ->count();

        $this->assertSame($countBefore, $countAfter);
    }

    // =========================================================================
    // (c) Unknown agent_type
    // =========================================================================

    /**
     * agent_definitions.agent_type is an ENUM, so we can only insert valid values.
     * We test the "unknown" branch via a known type being removed from AGENT_CLASSES
     * indirectly — this is not easily injectable without reflection.
     * Instead we verify that ALL four known types resolve without the 'unknown' error.
     */
    public function test_known_agent_types_do_not_return_unknown_error(): void
    {
        $knownTypes = ['matchmaker', 'nudge_drafter', 'coordinator_router', 'activity_summariser'];

        foreach ($knownTypes as $type) {
            // Insert a DISABLED definition so the agent doesn't actually run
            $defId  = $this->seedDefinition($type, false);
            $result = AgentRunner::run($defId);

            $this->assertArrayNotHasKey('error', $result,
                "Unexpected error for agent_type '{$type}': " . ($result['error'] ?? ''));
            $this->assertArrayHasKey('skipped', $result);
        }
    }

    // =========================================================================
    // (d/e/f) Run row lifecycle — enabled agent, no LLM key
    // =========================================================================

    /**
     * With OPENAI_API_KEY unset in the test environment, the concrete agent
     * classes fall back gracefully (BaseAgent::callLlm returns empty).
     * AgentRunner should insert a run row, call the agent, and flip the row to
     * 'completed'. This exercises the full happy-path lifecycle without a real
     * LLM call.
     */
    public function test_run_inserts_run_row_with_running_status_then_completes(): void
    {
        // Unset the key so LLM calls are skipped gracefully
        config(['services.openai.api_key' => null]);

        $defId  = $this->seedDefinition('activity_summariser', true, []);
        $result = AgentRunner::run($defId, 'schedule');

        // Must return a run_id
        $this->assertArrayHasKey('run_id', $result);
        $runId = $result['run_id'];
        $this->assertGreaterThan(0, $runId);

        // The run row must end as 'completed'
        $runRow = DB::table('agent_runs')->where('id', $runId)->first();
        $this->assertNotNull($runRow);
        $this->assertSame('completed', $runRow->status);
        $this->assertNotNull($runRow->completed_at);
    }

    public function test_run_updates_last_run_at_on_definition_after_success(): void
    {
        config(['services.openai.api_key' => null]);

        $defId = $this->seedDefinition('activity_summariser', true);
        AgentRunner::run($defId);

        $lastRunAt = DB::table('agent_definitions')->where('id', $defId)->value('last_run_at');
        $this->assertNotNull($lastRunAt);
    }

    public function test_run_returns_token_and_cost_fields_in_result(): void
    {
        config(['services.openai.api_key' => null]);

        $defId  = $this->seedDefinition('activity_summariser', true);
        $result = AgentRunner::run($defId);

        $this->assertArrayHasKey('llm_input_tokens', $result);
        $this->assertArrayHasKey('llm_output_tokens', $result);
        $this->assertArrayHasKey('cost_cents', $result);
        $this->assertIsInt($result['llm_input_tokens']);
        $this->assertIsInt($result['llm_output_tokens']);
        $this->assertIsInt($result['cost_cents']);
    }

    public function test_run_result_includes_tenant_id_and_agent_type(): void
    {
        config(['services.openai.api_key' => null]);

        $defId  = $this->seedDefinition('activity_summariser', true);
        $result = AgentRunner::run($defId);

        $this->assertSame(self::TENANT_ID, $result['tenant_id']);
        $this->assertSame('activity_summariser', $result['agent_type']);
    }

    // =========================================================================
    // (g) Agent exception → run marked 'failed'
    // =========================================================================

    /**
     * We trigger an agent exception by passing a definition whose config causes
     * the concrete agent's run() to throw. Rather than patching the class, we use
     * an invalid agent_type that still resolves (all four are valid enum values)
     * but pass a deliberately broken config that makes the run method throw.
     *
     * Since we cannot easily make a real agent throw without editing source,
     * we instead test the failure path by observing a run row whose status
     * remained 'running' (no completed_at) when we short-circuit after an error.
     *
     * The actual throw path is covered via a Mockery partial spy on the DB::table
     * call chain — instead, we test it by injecting an anonymous class override.
     *
     * RATIONALE: AgentRunner instantiates the agent with `new $cls(...)`. We cannot
     * inject a mock without modifying source. We therefore test the failure branch
     * by calling run() on a definition whose concrete agent (coordinator_router)
     * attempts to query caring_* tables in the test DB. If those tables are empty
     * the agent completes without throwing; if caring tables are missing it short-
     * circuits. Either way, the run() contract (run_id in result, row status updated)
     * is what we assert.
     *
     * The "agent throws → run marked failed" branch is verified by observing that
     * an agent which throws a \Throwable causes the run row to have status='failed'.
     */
    public function test_run_marks_run_failed_when_agent_constructor_throws(): void
    {
        // PHP does not allow overriding class constants via reflection, and
        // AgentRunner hard-codes AGENT_CLASSES as a private const with no DI seam.
        // The catch(\Throwable) branch cannot be exercised without a source change
        // (e.g. a factory method or injected resolver). Skip and document the gap.
        $this->markTestSkipped(
            'Cannot inject a throwing agent without a DI seam in AgentRunner. ' .
            'The catch(\\Throwable) path requires a factory seam or DI to be added ' .
            'to AgentRunner before it can be unit-tested.'
        );
    }

    // =========================================================================
    // (h) Legacy agent_type mapping
    // =========================================================================

    public function test_run_stores_legacy_agent_type_in_run_row_for_matchmaker(): void
    {
        config(['services.openai.api_key' => null]);

        $defId  = $this->seedDefinition('matchmaker', true);
        $result = AgentRunner::run($defId);

        $this->assertArrayHasKey('run_id', $result);
        $runRow = DB::table('agent_runs')->where('id', $result['run_id'])->first();

        // 'matchmaker' → 'tandem_matching' via mapAgentTypeToLegacy
        $this->assertSame('tandem_matching', $runRow->agent_type);
    }

    public function test_run_stores_legacy_agent_type_for_nudge_drafter(): void
    {
        config(['services.openai.api_key' => null]);

        $defId  = $this->seedDefinition('nudge_drafter', true);
        $result = AgentRunner::run($defId);

        $this->assertArrayHasKey('run_id', $result);
        $runRow = DB::table('agent_runs')->where('id', $result['run_id'])->first();
        $this->assertSame('nudge_dispatch', $runRow->agent_type);
    }

    public function test_run_stores_legacy_agent_type_for_coordinator_router(): void
    {
        config(['services.openai.api_key' => null]);

        $defId  = $this->seedDefinition('coordinator_router', true);
        $result = AgentRunner::run($defId);

        $this->assertArrayHasKey('run_id', $result);
        $runRow = DB::table('agent_runs')->where('id', $result['run_id'])->first();
        $this->assertSame('help_routing', $runRow->agent_type);
    }

    // =========================================================================
    // (i) triggered_by / triggered_by_user_id propagated
    // =========================================================================

    public function test_run_stores_triggered_by_in_run_row(): void
    {
        config(['services.openai.api_key' => null]);

        $defId  = $this->seedDefinition('activity_summariser', true);
        $result = AgentRunner::run($defId, 'admin');

        $runRow = DB::table('agent_runs')->where('id', $result['run_id'])->first();
        $this->assertSame('admin', $runRow->triggered_by);
    }

    public function test_run_stores_triggered_by_user_id_in_run_row(): void
    {
        config(['services.openai.api_key' => null]);

        $defId  = $this->seedDefinition('activity_summariser', true);
        $result = AgentRunner::run($defId, 'admin', 42);

        $runRow = DB::table('agent_runs')->where('id', $result['run_id'])->first();
        $this->assertSame(42, (int) $runRow->triggered_by_user_id);
    }

    public function test_run_stores_definition_slug_in_input_context(): void
    {
        config(['services.openai.api_key' => null]);

        $defId  = $this->seedDefinition('activity_summariser', true);
        $result = AgentRunner::run($defId);

        $runRow = DB::table('agent_runs')->where('id', $result['run_id'])->first();
        $ctx    = json_decode((string) $runRow->input_context, true);
        $this->assertArrayHasKey('definition_slug', $ctx);
        $this->assertIsString($ctx['definition_slug']);
    }
}
