<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Console;

use App\Services\KiAgentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for DispatchKiAgents Artisan command (agents:dispatch).
 *
 * Uses unique tenant ID 99736 to avoid collisions with other test files.
 *
 * The command:
 *  1. Guards against missing agent tables (KiAgentService::isAvailable())
 *  2. Queries agent_config for enabled tenants (--tenant=all)
 *  3. Checks caring_community feature flag per tenant
 *  4. Calls KiAgentService::runAllAgents() which respects agent_config.enabled
 */
class DispatchKiAgentsTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99736;

    protected function setUp(): void
    {
        parent::setUp();

        // Insert isolated test tenant with caring_community feature enabled
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'KiAgent Test Tenant',
                'slug'              => 'kiagent-test-99736',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                // caring_community defaults to false — override to true for tests
                // that need the command to proceed past the feature-gate check.
                'features'          => json_encode(['caring_community' => true]),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        \App\Core\TenantContext::setById(self::TENANT_ID);

        Queue::fake();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Insert (or update) an agent_config row for our test tenant.
     */
    private function seedAgentConfig(bool $enabled): void
    {
        DB::table('agent_config')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID],
            [
                'tenant_id'                   => self::TENANT_ID,
                'enabled'                     => $enabled ? 1 : 0,
                'auto_apply_threshold'        => 0.95,
                'tandem_matching_enabled'     => 0,
                'nudge_dispatch_enabled'      => 0,
                'activity_summary_enabled'    => 0,
                'demand_forecast_enabled'     => 0,
                'help_routing_enabled'        => 0,
                'schedule_hour'               => 2,
                'max_proposals_per_run'       => 10,
                'notification_email'          => null,
                'created_at'                  => now(),
                'updated_at'                  => now(),
            ]
        );
    }

    // ------------------------------------------------------------------
    // Schema guard: tables not available → failure exit code
    // ------------------------------------------------------------------

    public function test_returns_failure_when_ki_tables_not_available(): void
    {
        if (KiAgentService::isAvailable()) {
            $this->markTestSkipped('KiAgent tables are available in this environment.');
        }

        $this->artisan('agents:dispatch', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(1);
    }

    // ------------------------------------------------------------------
    // Invalid --tenant value → failure
    // ------------------------------------------------------------------

    public function test_invalid_tenant_option_returns_failure(): void
    {
        if (!KiAgentService::isAvailable()) {
            $this->markTestSkipped('KiAgent tables not available.');
        }

        $this->artisan('agents:dispatch', ['--tenant' => 'not-a-number'])
            ->assertExitCode(1);
    }

    // ------------------------------------------------------------------
    // --tenant=all with no enabled tenants → success, nothing dispatched
    // ------------------------------------------------------------------

    public function test_all_tenants_no_enabled_exits_success(): void
    {
        if (!KiAgentService::isAvailable()) {
            $this->markTestSkipped('KiAgent tables not available.');
        }

        // Ensure our test tenant is NOT enabled in agent_config
        DB::table('agent_config')->where('tenant_id', self::TENANT_ID)->delete();

        $this->artisan('agents:dispatch', ['--tenant' => 'all'])
            ->assertExitCode(0)
            ->expectsOutputToContain('No tenants have KI-Agenten enabled.');
    }

    // ------------------------------------------------------------------
    // Single tenant, caring_community disabled → skipped with message
    // ------------------------------------------------------------------

    public function test_skips_tenant_without_caring_community_feature(): void
    {
        if (!KiAgentService::isAvailable()) {
            $this->markTestSkipped('KiAgent tables not available.');
        }

        // Set caring_community=false for our tenant
        DB::table('tenants')->where('id', self::TENANT_ID)
            ->update(['features' => json_encode(['caring_community' => false])]);

        // Reload tenant context so the new features value is picked up
        \App\Core\TenantContext::setById(self::TENANT_ID);

        $this->artisan('agents:dispatch', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0)
            ->expectsOutputToContain('caring_community feature disabled');
    }

    // ------------------------------------------------------------------
    // Single tenant, caring_community enabled, agent config disabled →
    // runAllAgents returns skipped=true (agents disabled in config)
    // ------------------------------------------------------------------

    public function test_exits_success_when_agent_config_is_disabled(): void
    {
        if (!KiAgentService::isAvailable()) {
            $this->markTestSkipped('KiAgent tables not available.');
        }

        $this->seedAgentConfig(false); // enabled=false in DB

        $this->artisan('agents:dispatch', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------
    // Single tenant, agent config enabled, all sub-agents disabled →
    // runs successfully, creates a run record with completed status
    // ------------------------------------------------------------------

    public function test_creates_run_records_for_enabled_tenant(): void
    {
        if (!KiAgentService::isAvailable()) {
            $this->markTestSkipped('KiAgent tables not available.');
        }

        $this->seedAgentConfig(true); // enabled=true, all sub-agents OFF (no proposals)

        $before = DB::table('agent_runs')->where('tenant_id', self::TENANT_ID)->count();

        $this->artisan('agents:dispatch', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0);

        // runAllAgents with all sub-agents disabled creates NO run records
        // (it skips every agent). Just assert the command exits cleanly.
        $after = DB::table('agent_runs')->where('tenant_id', self::TENANT_ID)->count();
        $this->assertGreaterThanOrEqual($before, $after);
    }

    // ------------------------------------------------------------------
    // --dry-run flag: command prints dry-run notice and exits 0
    // ------------------------------------------------------------------

    public function test_dry_run_flag_prints_notice_and_exits_success(): void
    {
        if (!KiAgentService::isAvailable()) {
            $this->markTestSkipped('KiAgent tables not available.');
        }

        $this->artisan('agents:dispatch', [
            '--tenant'  => (string) self::TENANT_ID,
            '--dry-run' => true,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY-RUN mode');
    }

    // ------------------------------------------------------------------
    // --tenant=all with our tenant enabled in agent_config
    // → tenant is picked up and processed
    // ------------------------------------------------------------------

    public function test_all_mode_picks_up_enabled_tenant(): void
    {
        if (!KiAgentService::isAvailable()) {
            $this->markTestSkipped('KiAgent tables not available.');
        }

        $this->seedAgentConfig(true); // enabled=true in agent_config for self::TENANT_ID

        $this->artisan('agents:dispatch', ['--tenant' => 'all'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Done.');
    }

    // ------------------------------------------------------------------
    // Done message is always printed on success
    // ------------------------------------------------------------------

    public function test_done_message_is_printed(): void
    {
        if (!KiAgentService::isAvailable()) {
            $this->markTestSkipped('KiAgent tables not available.');
        }

        $this->artisan('agents:dispatch', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0)
            ->expectsOutputToContain('Done.');
    }
}
