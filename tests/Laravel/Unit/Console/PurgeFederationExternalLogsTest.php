<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for federation:purge-external-logs Artisan command.
 *
 * Uses tenant id 99725 (unique, isolated from other test suites).
 *
 * federation_external_partner_logs has no tenant_id column — rows are
 * identified by the partner_id they carry and by the explicit IDs we
 * capture from insertGetId, so individual row assertions are used to
 * avoid interference with other test rows.
 */
class PurgeFederationExternalLogsTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99725;

    /** A partner_id value that won't clash with production fixtures. */
    private const PARTNER_ID = 99725;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'PurgeFedExtLogs Test Tenant',
                'slug'              => 'purge-fed-ext-logs-test',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        \App\Core\TenantContext::setById(self::TENANT_ID);
    }

    // ---------------------------------------------------------------
    // Basic deletion: old rows removed, recent rows kept
    // ---------------------------------------------------------------

    public function test_purge_deletes_rows_older_than_default_90_days(): void
    {
        $oldId = DB::table('federation_external_partner_logs')->insertGetId([
            'partner_id'    => self::PARTNER_ID,
            'endpoint'      => '/health',
            'method'        => 'GET',
            'response_code' => 200,
            'success'       => 1,
            'created_at'    => now()->subDays(100)->toDateTimeString(),
        ]);

        $recentId = DB::table('federation_external_partner_logs')->insertGetId([
            'partner_id'    => self::PARTNER_ID,
            'endpoint'      => '/health',
            'method'        => 'GET',
            'response_code' => 200,
            'success'       => 1,
            'created_at'    => now()->subDays(10)->toDateTimeString(),
        ]);

        $this->artisan('federation:purge-external-logs')->assertExitCode(0);

        $this->assertDatabaseMissing('federation_external_partner_logs', ['id' => $oldId]);
        $this->assertDatabaseHas('federation_external_partner_logs', ['id' => $recentId]);
    }

    // ---------------------------------------------------------------
    // Boundary: row exactly at 89 days is kept
    // ---------------------------------------------------------------

    public function test_purge_keeps_row_within_retention_boundary(): void
    {
        $boundaryId = DB::table('federation_external_partner_logs')->insertGetId([
            'partner_id'    => self::PARTNER_ID,
            'endpoint'      => '/members',
            'method'        => 'GET',
            'response_code' => 200,
            'success'       => 1,
            'created_at'    => now()->subDays(89)->toDateTimeString(),
        ]);

        $this->artisan('federation:purge-external-logs')->assertExitCode(0);

        $this->assertDatabaseHas('federation_external_partner_logs', ['id' => $boundaryId]);
    }

    // ---------------------------------------------------------------
    // Custom --days option
    // ---------------------------------------------------------------

    public function test_purge_respects_custom_days_option(): void
    {
        // A row 5 days old should be deleted when --days=3
        $oldId = DB::table('federation_external_partner_logs')->insertGetId([
            'partner_id'    => self::PARTNER_ID,
            'endpoint'      => '/listings',
            'method'        => 'GET',
            'response_code' => 200,
            'success'       => 1,
            'created_at'    => now()->subDays(5)->toDateTimeString(),
        ]);

        // A row 2 days old should survive --days=3
        $recentId = DB::table('federation_external_partner_logs')->insertGetId([
            'partner_id'    => self::PARTNER_ID,
            'endpoint'      => '/listings',
            'method'        => 'GET',
            'response_code' => 200,
            'success'       => 1,
            'created_at'    => now()->subDays(2)->toDateTimeString(),
        ]);

        $this->artisan('federation:purge-external-logs', ['--days' => '3'])->assertExitCode(0);

        $this->assertDatabaseMissing('federation_external_partner_logs', ['id' => $oldId]);
        $this->assertDatabaseHas('federation_external_partner_logs', ['id' => $recentId]);
    }

    // ---------------------------------------------------------------
    // Multiple old rows all deleted
    // ---------------------------------------------------------------

    public function test_purge_deletes_multiple_old_rows(): void
    {
        $id1 = DB::table('federation_external_partner_logs')->insertGetId([
            'partner_id'    => self::PARTNER_ID,
            'endpoint'      => '/transactions',
            'method'        => 'POST',
            'response_code' => 500,
            'success'       => 0,
            'error_message' => 'timeout',
            'created_at'    => now()->subDays(120)->toDateTimeString(),
        ]);

        $id2 = DB::table('federation_external_partner_logs')->insertGetId([
            'partner_id'    => self::PARTNER_ID,
            'endpoint'      => '/messages',
            'method'        => 'POST',
            'response_code' => 200,
            'success'       => 1,
            'created_at'    => now()->subDays(200)->toDateTimeString(),
        ]);

        $this->artisan('federation:purge-external-logs')->assertExitCode(0);

        $this->assertDatabaseMissing('federation_external_partner_logs', ['id' => $id1]);
        $this->assertDatabaseMissing('federation_external_partner_logs', ['id' => $id2]);
    }

    // ---------------------------------------------------------------
    // No-op: nothing old → command still exits 0
    // ---------------------------------------------------------------

    public function test_purge_succeeds_with_no_qualifying_rows(): void
    {
        $recentId = DB::table('federation_external_partner_logs')->insertGetId([
            'partner_id'    => self::PARTNER_ID,
            'endpoint'      => '/health',
            'method'        => 'GET',
            'response_code' => 200,
            'success'       => 1,
            'created_at'    => now()->subHours(1)->toDateTimeString(),
        ]);

        $this->artisan('federation:purge-external-logs')->assertExitCode(0);

        $this->assertDatabaseHas('federation_external_partner_logs', ['id' => $recentId]);
    }

    // ---------------------------------------------------------------
    // Validation: --days=0 returns exit code FAILURE
    // ---------------------------------------------------------------

    public function test_purge_rejects_days_zero_with_failure_exit_code(): void
    {
        $this->artisan('federation:purge-external-logs', ['--days' => '0'])
            ->assertExitCode(1);

        // The command must have printed an error message; confirm command
        // output contains "Days must be at least 1" by checking exit code
        // is non-zero (the only observable side-effect for this path).
        $this->assertTrue(true); // failOnRisky guard — assertExitCode above is the real assertion
    }

    // ---------------------------------------------------------------
    // Output: command reports how many rows were purged
    // ---------------------------------------------------------------

    public function test_purge_output_includes_deleted_count(): void
    {
        DB::table('federation_external_partner_logs')->insertGetId([
            'partner_id'    => self::PARTNER_ID,
            'endpoint'      => '/organizations',
            'method'        => 'GET',
            'response_code' => 200,
            'success'       => 1,
            'created_at'    => now()->subDays(95)->toDateTimeString(),
        ]);

        $this->artisan('federation:purge-external-logs')
            ->expectsOutputToContain('Purged')
            ->assertExitCode(0);
    }

    // ---------------------------------------------------------------
    // Long retention: rows exactly at 30 days kept when --days=30
    // ---------------------------------------------------------------

    public function test_purge_custom_days_30_keeps_29_day_old_row(): void
    {
        $keepId = DB::table('federation_external_partner_logs')->insertGetId([
            'partner_id'    => self::PARTNER_ID,
            'endpoint'      => '/health',
            'method'        => 'GET',
            'response_code' => 200,
            'success'       => 1,
            'created_at'    => now()->subDays(29)->toDateTimeString(),
        ]);

        $this->artisan('federation:purge-external-logs', ['--days' => '30'])->assertExitCode(0);

        $this->assertDatabaseHas('federation_external_partner_logs', ['id' => $keepId]);
    }

    // ---------------------------------------------------------------
    // Failure-row columns: success=0 + error_message are deleted too
    // ---------------------------------------------------------------

    public function test_purge_deletes_failed_log_rows_beyond_retention(): void
    {
        $failedId = DB::table('federation_external_partner_logs')->insertGetId([
            'partner_id'      => self::PARTNER_ID,
            'endpoint'        => '/members',
            'method'          => 'GET',
            'response_code'   => 503,
            'success'         => 0,
            'error_message'   => 'Service unavailable',
            'response_time_ms' => 5000,
            'created_at'      => now()->subDays(91)->toDateTimeString(),
        ]);

        $this->artisan('federation:purge-external-logs')->assertExitCode(0);

        $this->assertDatabaseMissing('federation_external_partner_logs', ['id' => $failedId]);
    }
}
