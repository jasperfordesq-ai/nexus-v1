<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for retention:enforce (EnforceRetentionPolicies).
 *
 * Uses a unique tenant id 99704 to avoid cross-test contamination.
 * Uses DatabaseTransactions to roll back all inserts after each test.
 *
 * The command delegates real deletion to RetentionPolicyService::enforceForTenant()
 * which scopes every DELETE to (tenant_id, data_type). We seed the policy +
 * data rows and assert the right rows are purged vs. kept.
 *
 * Exit codes:
 *   0 (SUCCESS) = all enabled policies completed without failure
 *   1 (FAILURE) = at least one policy pass failed (exception or failed status)
 */
class EnforceRetentionPoliciesTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99704;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'             => 'Retention Test Tenant 99704',
                'slug'             => 'retention-test-99704',
                'domain'           => null,
                'is_active'        => true,
                'depth'            => 0,
                'allows_subtenants'=> false,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Upsert a tenant_retention_policies row for this tenant. */
    private function seedPolicy(string $dataType, int $retentionDays, bool $enabled = true): void
    {
        DB::table('tenant_retention_policies')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'data_type' => $dataType],
            [
                'retention_days' => $retentionDays,
                'action'         => 'delete',
                'is_enabled'     => $enabled ? 1 : 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]
        );
    }

    /** Seed an activity_log row for this tenant with an explicit created_at. */
    private function seedActivityLog(string $createdAt): int
    {
        return DB::table('activity_log')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'user_id'    => null,
            'action'     => 'test_action',
            'created_at' => $createdAt,
        ]);
    }

    /** Seed an email_log row for this tenant with an explicit created_at. */
    private function seedEmailLog(string $createdAt): int
    {
        return DB::table('email_log')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'recipient_email' => 'test-' . uniqid() . '@example-test.invalid',
            'status'          => 'sent',
            'created_at'      => $createdAt,
            'updated_at'      => $createdAt,
        ]);
    }

    // -------------------------------------------------------------------------
    // No policies: command succeeds, nothing purged
    // -------------------------------------------------------------------------

    public function test_succeeds_with_no_enabled_policies(): void
    {
        // Seed an old log row — without any enabled policy, it should stay.
        $id = $this->seedActivityLog(now()->subDays(400)->toDateTimeString());

        $this->artisan('retention:enforce', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $this->assertNotNull(
            DB::table('activity_log')->where('id', $id)->first(),
            'Row should survive when no retention policy is enabled'
        );
    }

    public function test_succeeds_when_policy_exists_but_is_disabled(): void
    {
        $this->seedPolicy('activity_log', retentionDays: 90, enabled: false);
        $id = $this->seedActivityLog(now()->subDays(200)->toDateTimeString());

        $this->artisan('retention:enforce', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $this->assertNotNull(
            DB::table('activity_log')->where('id', $id)->first(),
            'Disabled policy must not purge data'
        );
    }

    // -------------------------------------------------------------------------
    // Rows within retention window: must NOT be purged
    // -------------------------------------------------------------------------

    public function test_keeps_rows_within_retention_window(): void
    {
        // Policy: keep 90 days. Row is 30 days old → safe.
        $this->seedPolicy('activity_log', retentionDays: 90);
        $id = $this->seedActivityLog(now()->subDays(30)->toDateTimeString());

        $this->artisan('retention:enforce', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $this->assertNotNull(
            DB::table('activity_log')->where('id', $id)->first(),
            'Row within retention window must NOT be purged'
        );
    }

    public function test_keeps_email_log_within_window(): void
    {
        // Policy: 60-day retention. Row is 45 days old → should be kept.
        $this->seedPolicy('email_log', retentionDays: 60);
        $id = $this->seedEmailLog(now()->subDays(45)->toDateTimeString());

        $this->artisan('retention:enforce', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $this->assertNotNull(
            DB::table('email_log')->where('id', $id)->first(),
            'email_log row within retention window must be kept'
        );
    }

    // -------------------------------------------------------------------------
    // Rows past retention window: must be purged
    // -------------------------------------------------------------------------

    public function test_purges_activity_log_rows_past_retention_window(): void
    {
        // Policy: 90-day retention. Row is 200 days old → must be purged.
        $this->seedPolicy('activity_log', retentionDays: 90);
        $id = $this->seedActivityLog(now()->subDays(200)->toDateTimeString());

        $this->artisan('retention:enforce', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $this->assertNull(
            DB::table('activity_log')->where('id', $id)->first(),
            'Row past retention window must be purged'
        );
    }

    public function test_purges_email_log_rows_past_retention_window(): void
    {
        $this->seedPolicy('email_log', retentionDays: 60);
        $id = $this->seedEmailLog(now()->subDays(120)->toDateTimeString());

        $this->artisan('retention:enforce', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $this->assertNull(
            DB::table('email_log')->where('id', $id)->first(),
            'email_log row past retention window must be purged'
        );
    }

    // -------------------------------------------------------------------------
    // Tenant isolation: other tenants' rows must NOT be purged
    // -------------------------------------------------------------------------

    public function test_does_not_purge_other_tenants_rows(): void
    {
        $otherTenantId = 2; // hour-timebank tenant always exists in test env

        // Enable policy for OUR tenant.
        $this->seedPolicy('activity_log', retentionDays: 30);

        // Seed an old row for the OTHER tenant — must survive.
        $otherId = DB::table('activity_log')->insertGetId([
            'tenant_id'  => $otherTenantId,
            'user_id'    => null,
            'action'     => 'other_tenant_action',
            'created_at' => now()->subDays(200)->toDateTimeString(),
        ]);

        $this->artisan('retention:enforce', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $this->assertNotNull(
            DB::table('activity_log')->where('id', $otherId)->first(),
            'Retention enforcement must not purge rows from other tenants'
        );
    }

    // -------------------------------------------------------------------------
    // Mixed: old + new rows in same table — only old ones go
    // -------------------------------------------------------------------------

    public function test_purges_old_but_keeps_new_rows_in_same_table(): void
    {
        $this->seedPolicy('activity_log', retentionDays: 90);
        $oldId  = $this->seedActivityLog(now()->subDays(200)->toDateTimeString());
        $newId  = $this->seedActivityLog(now()->subDays(30)->toDateTimeString());

        $this->artisan('retention:enforce', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $this->assertNull(
            DB::table('activity_log')->where('id', $oldId)->first(),
            'Old row must be purged'
        );
        $this->assertNotNull(
            DB::table('activity_log')->where('id', $newId)->first(),
            'New row must survive'
        );
    }

    // -------------------------------------------------------------------------
    // run record: a tenant_retention_runs row is written after enforcement
    // -------------------------------------------------------------------------

    public function test_records_retention_run_after_enforcement(): void
    {
        $this->seedPolicy('activity_log', retentionDays: 90);
        $this->seedActivityLog(now()->subDays(200)->toDateTimeString());

        $before = now()->subSeconds(2)->toDateTimeString();

        $this->artisan('retention:enforce', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $run = DB::table('tenant_retention_runs')
            ->where('tenant_id', self::TENANT_ID)
            ->where('data_type', 'activity_log')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($run, 'A tenant_retention_runs record must be written after enforcement');
        $this->assertSame('completed', $run->status);
        $this->assertGreaterThanOrEqual(1, (int) $run->affected_rows);
    }

    // -------------------------------------------------------------------------
    // All-tenants mode: omit --tenant, confirm our tenant's data is still purged
    // -------------------------------------------------------------------------

    public function test_all_tenants_mode_still_purges_our_tenant_data(): void
    {
        $this->seedPolicy('activity_log', retentionDays: 90);
        $id = $this->seedActivityLog(now()->subDays(200)->toDateTimeString());

        // Run without --tenant (iterates all active tenants)
        $this->artisan('retention:enforce')
            ->assertExitCode(0);

        $this->assertNull(
            DB::table('activity_log')->where('id', $id)->first(),
            'All-tenants mode must also purge old rows for our tenant'
        );
    }

    // -------------------------------------------------------------------------
    // Retention minimum floor: service enforces MIN_RETENTION_DAYS = 30
    // Any policy stored with <30 days is clamped to 30 inside the service.
    // -------------------------------------------------------------------------

    public function test_minimum_retention_floor_is_respected(): void
    {
        // Store a suspiciously short policy (1 day), which the service will clamp to 30.
        DB::table('tenant_retention_policies')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'data_type' => 'activity_log'],
            [
                'retention_days' => 1, // below MIN_RETENTION_DAYS
                'action'         => 'delete',
                'is_enabled'     => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]
        );

        // 15-day-old row — safe because clamped floor is 30 days.
        $id = $this->seedActivityLog(now()->subDays(15)->toDateTimeString());

        $this->artisan('retention:enforce', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        // Row is 15 days old; effective retention is clamped to 30 days → row is kept.
        $this->assertNotNull(
            DB::table('activity_log')->where('id', $id)->first(),
            'Row less than MIN_RETENTION_DAYS (30) old must be kept even if policy says 1 day'
        );
    }
}
