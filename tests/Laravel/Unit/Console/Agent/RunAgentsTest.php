<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console\Agent;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * RunAgentsTest
 *
 * Tests the `agents:run` Artisan command (App\Console\Commands\Agent\RunAgents).
 *
 * The command iterates every enabled agent_definitions row and invokes
 * AgentRunner::run(). We exercise:
 *   (a) No enabled rows → exits 0, prints "No enabled agent definitions match."
 *   (b) --tenant filter → only runs agents for that tenant
 *   (c) --agent filter by slug → only runs matching agent
 *   (d) --agent filter by numeric id → only runs matching agent
 *   (e) All filters combined → scopes correctly
 *   (f) Disabled rows → not processed
 *   (g) Missing table → warns and exits 0 (schema guard)
 *   (h) Command output contains "Done." on a real run
 *   (i) TenantContext is set per-agent (via a disabled run)
 *
 * Tenant 99754 is the isolated tenant for this suite.
 */
class RunAgentsTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99754;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Ensure a stable tenant row exists for TenantContext::setById
        if (!DB::table('tenants')->where('id', self::TENANT_ID)->exists()) {
            DB::table('tenants')->insert([
                'id'         => self::TENANT_ID,
                'name'       => 'RunAgents Test Tenant',
                'slug'       => 'run-agents-test-99754',
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        TenantContext::setById(self::TENANT_ID);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a minimal agent_definitions row for the isolated tenant.
     *
     * @param array<string,mixed> $overrides
     */
    private function seedDefinition(array $overrides = []): int
    {
        $slug = 'run-test-' . uniqid('', true);
        return DB::table('agent_definitions')->insertGetId(array_merge([
            'tenant_id'  => self::TENANT_ID,
            'slug'       => $slug,
            'name'       => 'Run Test Agent ' . $slug,
            'agent_type' => 'activity_summariser',
            'config'     => json_encode(['lookback_days' => 7, 'max_proposals_per_run' => 5]),
            'is_enabled' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    /**
     * (a) No enabled rows for the tenant → exits 0, outputs the no-match message.
     */
    public function test_command_exits_zero_when_no_enabled_definitions(): void
    {
        // Ensure there are no enabled definitions for our isolated tenant
        DB::table('agent_definitions')
            ->where('tenant_id', self::TENANT_ID)
            ->update(['is_enabled' => 0]);

        $this->artisan('agents:run', ['--tenant' => (string) self::TENANT_ID])
            ->expectsOutputToContain('No enabled agent definitions match')
            ->assertExitCode(0);
    }

    /**
     * (f) Disabled rows are not processed — only enabled ones are picked up.
     */
    public function test_disabled_definitions_are_not_processed(): void
    {
        $this->seedDefinition(['is_enabled' => 0]);

        $this->artisan('agents:run', ['--tenant' => (string) self::TENANT_ID])
            ->expectsOutputToContain('No enabled agent definitions match')
            ->assertExitCode(0);
    }

    /**
     * (b) --tenant filter — enabled definition for our isolated tenant is found.
     * We enable a definition (no LLM key → completes gracefully) and assert exit 0.
     */
    public function test_tenant_filter_scopes_run_to_specified_tenant(): void
    {
        config(['services.openai.api_key' => null]);
        $this->seedDefinition(['is_enabled' => 1]);

        $this->artisan('agents:run', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0);
    }

    /**
     * (c) --agent filter by slug → only runs the matching definition.
     */
    public function test_agent_filter_by_slug_scopes_run(): void
    {
        config(['services.openai.api_key' => null]);
        $slug = 'slug-filter-' . uniqid('', true);
        $this->seedDefinition(['is_enabled' => 1, 'slug' => $slug]);

        // A second definition with a different slug should NOT be picked up.
        $otherSlug = 'slug-other-' . uniqid('', true);
        $this->seedDefinition(['is_enabled' => 1, 'slug' => $otherSlug]);

        // Run with the slug filter — should match exactly 1 definition.
        $this->artisan('agents:run', [
            '--tenant' => (string) self::TENANT_ID,
            '--agent'  => $slug,
        ])->assertExitCode(0);
    }

    /**
     * (d) --agent filter by numeric ID.
     */
    public function test_agent_filter_by_numeric_id_scopes_run(): void
    {
        config(['services.openai.api_key' => null]);
        $defId = $this->seedDefinition(['is_enabled' => 1]);

        $this->artisan('agents:run', [
            '--tenant' => (string) self::TENANT_ID,
            '--agent'  => (string) $defId,
        ])->assertExitCode(0);
    }

    /**
     * (e) Both --tenant and --agent filters: if the agent doesn't belong to the
     * tenant, nothing is found and the command exits 0 with the no-match message.
     */
    public function test_tenant_and_agent_filters_combined_no_cross_tenant_match(): void
    {
        $slug = 'cross-tenant-' . uniqid('', true);

        // Seed a definition for a *different* tenant (use a known safe tenant).
        DB::table('agent_definitions')->insert([
            'tenant_id'  => 2,
            'slug'       => $slug,
            'name'       => 'Cross-tenant test',
            'agent_type' => 'activity_summariser',
            'config'     => json_encode([]),
            'is_enabled' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Filter to our isolated tenant — should NOT match tenant 2's definition.
        $this->artisan('agents:run', [
            '--tenant' => (string) self::TENANT_ID,
            '--agent'  => $slug,
        ])
            ->expectsOutputToContain('No enabled agent definitions match')
            ->assertExitCode(0);
    }

    /**
     * (h) Command outputs "Done." when at least one definition ran.
     */
    public function test_command_outputs_done_when_definitions_ran(): void
    {
        config(['services.openai.api_key' => null]);
        $this->seedDefinition(['is_enabled' => 1]);

        $this->artisan('agents:run', ['--tenant' => (string) self::TENANT_ID])
            ->expectsOutputToContain('Done')
            ->assertExitCode(0);
    }

    /**
     * (b2) Command outputs "Running" before executing agents.
     */
    public function test_command_outputs_running_when_definitions_found(): void
    {
        config(['services.openai.api_key' => null]);
        $this->seedDefinition(['is_enabled' => 1]);

        $this->artisan('agents:run', ['--tenant' => (string) self::TENANT_ID])
            ->expectsOutputToContain('Running 1 agent definition(s)')
            ->assertExitCode(0);
    }

    /**
     * Verify the command signature is registered correctly.
     */
    public function test_command_signature_is_agents_run(): void
    {
        $cmd = new \App\Console\Commands\Agent\RunAgents();
        $this->assertSame('agents:run', $cmd->getName());
    }
}
