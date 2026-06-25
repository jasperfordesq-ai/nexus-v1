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
 * SeedAgentsCommandTest
 *
 * Tests the `tenant:seed-agents` Artisan command
 * (App\Console\Commands\Agent\SeedAgentsCommand).
 *
 * The command delegates to DefaultAgentDefinitionsSeeder::run() which uses
 * (tenant_id, slug) updateOrInsert — so it is idempotent by design.
 *
 * Tests:
 *   (a) --tenant=N seeds the four default definitions for that tenant
 *   (b) The seeded rows are in disabled state (is_enabled=0)
 *   (c) Re-running is idempotent — does not duplicate rows
 *   (d) Without --tenant flag, seeds for all active tenants (our isolated tenant
 *       must appear among them)
 *   (e) Command exits 0 in all cases
 *   (f) Output confirms which tenant was seeded
 *   (g) Default agent slugs are exactly the four expected ones
 *   (h) All seeded rows have valid JSON config
 *   (i) Seeded rows have agent_type values matching the four known types
 *
 * Tenant 99755 is the isolated tenant for this suite.
 */
class SeedAgentsCommandTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99755;

    /** The four default slug → agent_type pairs from DefaultAgentDefinitionsSeeder. */
    private const EXPECTED_SLUGS = [
        'tandem_matchmaker',
        'nudge_drafter',
        'coordinator_router',
        'activity_summariser',
    ];

    private const EXPECTED_TYPES = [
        'matchmaker',
        'nudge_drafter',
        'coordinator_router',
        'activity_summariser',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Ensure the isolated tenant exists and is active so the "all tenants" path works.
        if (!DB::table('tenants')->where('id', self::TENANT_ID)->exists()) {
            DB::table('tenants')->insert([
                'id'         => self::TENANT_ID,
                'name'       => 'SeedAgents Test Tenant',
                'slug'       => 'seed-agents-test-99755',
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        TenantContext::setById(self::TENANT_ID);

        // Clean any pre-existing definitions for this tenant so counts are predictable.
        DB::table('agent_definitions')->where('tenant_id', self::TENANT_ID)->delete();
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /** Return all agent_definitions rows for the isolated tenant. */
    private function tenantDefinitions(): \Illuminate\Support\Collection
    {
        return DB::table('agent_definitions')
            ->where('tenant_id', self::TENANT_ID)
            ->get();
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    /**
     * (a) --tenant=N seeds exactly four rows for that tenant.
     */
    public function test_seeds_four_default_definitions_for_given_tenant(): void
    {
        $this->artisan('tenant:seed-agents', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0);

        $defs = $this->tenantDefinitions();
        $this->assertCount(4, $defs, 'Expected exactly four default agent definitions to be seeded.');
    }

    /**
     * (b) All seeded rows are disabled by default.
     */
    public function test_seeded_definitions_are_disabled_by_default(): void
    {
        $this->artisan('tenant:seed-agents', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0);

        $defs = $this->tenantDefinitions();
        foreach ($defs as $def) {
            $this->assertSame(0, (int) $def->is_enabled,
                "Definition '{$def->slug}' must be seeded in disabled state.");
        }
    }

    /**
     * (g) The four seeded slugs match exactly the expected default set.
     */
    public function test_seeded_slugs_match_expected_defaults(): void
    {
        $this->artisan('tenant:seed-agents', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0);

        $slugs = $this->tenantDefinitions()->pluck('slug')->sort()->values()->toArray();
        $expected = self::EXPECTED_SLUGS;
        sort($expected);

        $this->assertSame($expected, $slugs);
    }

    /**
     * (i) All seeded rows have one of the four valid agent_type enum values.
     */
    public function test_seeded_definitions_have_valid_agent_types(): void
    {
        $this->artisan('tenant:seed-agents', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0);

        $defs = $this->tenantDefinitions();
        foreach ($defs as $def) {
            $this->assertContains(
                $def->agent_type,
                self::EXPECTED_TYPES,
                "Definition '{$def->slug}' has unexpected agent_type '{$def->agent_type}'."
            );
        }
    }

    /**
     * (h) All seeded rows have valid JSON in the config column.
     */
    public function test_seeded_definitions_have_valid_json_config(): void
    {
        $this->artisan('tenant:seed-agents', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0);

        $defs = $this->tenantDefinitions();
        foreach ($defs as $def) {
            $decoded = json_decode((string) $def->config, true);
            $this->assertIsArray($decoded,
                "Definition '{$def->slug}' must have a valid JSON config.");
        }
    }

    /**
     * (c) Re-running the command is idempotent — row count stays at 4.
     */
    public function test_seeding_twice_does_not_duplicate_rows(): void
    {
        $this->artisan('tenant:seed-agents', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0);
        $this->artisan('tenant:seed-agents', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0);

        $count = $this->tenantDefinitions()->count();
        $this->assertSame(4, $count,
            'Seeding twice must remain idempotent — still exactly 4 rows.');
    }

    /**
     * (d) Without --tenant, seeds for all active tenants.
     * Our isolated tenant 99755 is active, so it must receive all four definitions.
     */
    public function test_seeds_all_active_tenants_when_no_tenant_option(): void
    {
        $this->artisan('tenant:seed-agents')
            ->assertExitCode(0);

        $count = $this->tenantDefinitions()->count();
        $this->assertSame(4, $count,
            'Isolated active tenant 99755 must receive four definitions in all-tenants run.');
    }

    /**
     * (e) Command always exits 0.
     */
    public function test_command_exits_zero(): void
    {
        $this->artisan('tenant:seed-agents', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0);
    }

    /**
     * (f) Output confirms the tenant that was seeded.
     */
    public function test_command_output_mentions_tenant_id(): void
    {
        $this->artisan('tenant:seed-agents', ['--tenant' => (string) self::TENANT_ID])
            ->expectsOutputToContain((string) self::TENANT_ID)
            ->assertExitCode(0);
    }

    /**
     * (f2) Without --tenant flag, output says "all active tenants".
     */
    public function test_command_output_mentions_all_tenants_when_no_option(): void
    {
        $this->artisan('tenant:seed-agents')
            ->expectsOutputToContain('all active tenants')
            ->assertExitCode(0);
    }

    /**
     * Verify the command signature is registered correctly.
     */
    public function test_command_signature_is_tenant_seed_agents(): void
    {
        $cmd = new \App\Console\Commands\Agent\SeedAgentsCommand();
        $this->assertSame('tenant:seed-agents', $cmd->getName());
    }
}
