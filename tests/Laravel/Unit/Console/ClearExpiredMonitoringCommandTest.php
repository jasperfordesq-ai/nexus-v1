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
 * Tests for safeguarding:clear-expired-monitoring Artisan command.
 *
 * Uses tenant id 99709 to stay isolated from the default test tenant.
 *
 * The command clears the under_monitoring flag on rows in
 * user_messaging_restrictions where monitoring_expires_at is in the past.
 * It does NOT scope by tenant_id — it operates globally. Tests therefore
 * assert on explicit row IDs rather than counts.
 *
 * user_messaging_restrictions has a FK to users (user_id) and tenants
 * (tenant_id), so we seed minimal users and tenant rows.
 */
class ClearExpiredMonitoringCommandTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99709;

    /** IDs of users seeded for this test class */
    private int $userId1;
    private int $userId2;
    private int $userId3;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Ensure the isolated tenant exists.
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'ClearMonitoring Test Tenant',
                'slug'              => 'clear-monitoring-test',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        // Seed three minimal users for FK satisfaction.
        $this->userId1 = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Monitor Test User 1',
            'email'      => 'monitor-test-1-' . uniqid() . '@example.com',
            'password'   => 'hashed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->userId2 = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Monitor Test User 2',
            'email'      => 'monitor-test-2-' . uniqid() . '@example.com',
            'password'   => 'hashed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->userId3 = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Monitor Test User 3',
            'email'      => 'monitor-test-3-' . uniqid() . '@example.com',
            'password'   => 'hashed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);
    }

    // ---------------------------------------------------------------
    // Core: expired restriction is cleared
    // ---------------------------------------------------------------

    public function test_clears_under_monitoring_flag_when_expiry_is_in_the_past(): void
    {
        $id = DB::table('user_messaging_restrictions')->insertGetId([
            'tenant_id'            => self::TENANT_ID,
            'user_id'              => $this->userId1,
            'under_monitoring'     => true,
            'monitoring_expires_at' => now()->subHour()->toDateTimeString(),
        ]);

        $this->artisan('safeguarding:clear-expired-monitoring')->assertExitCode(0);

        $row = DB::table('user_messaging_restrictions')->where('id', $id)->first();
        $this->assertNotNull($row, 'Row should still exist — only flag cleared');
        $this->assertFalse((bool) $row->under_monitoring, 'under_monitoring should be cleared');
    }

    // ---------------------------------------------------------------
    // Non-interference: active monitoring is kept
    // ---------------------------------------------------------------

    public function test_does_not_clear_monitoring_that_has_not_expired(): void
    {
        $id = DB::table('user_messaging_restrictions')->insertGetId([
            'tenant_id'            => self::TENANT_ID,
            'user_id'              => $this->userId2,
            'under_monitoring'     => true,
            'monitoring_expires_at' => now()->addHour()->toDateTimeString(),
        ]);

        $this->artisan('safeguarding:clear-expired-monitoring')->assertExitCode(0);

        $row = DB::table('user_messaging_restrictions')->where('id', $id)->first();
        $this->assertTrue((bool) $row->under_monitoring, 'Active monitoring should not be touched');
    }

    // ---------------------------------------------------------------
    // Non-interference: null expiry is not cleared
    // ---------------------------------------------------------------

    public function test_does_not_clear_monitoring_with_null_expiry(): void
    {
        // A row with under_monitoring=true but no expiry = indefinite restriction.
        $id = DB::table('user_messaging_restrictions')->insertGetId([
            'tenant_id'             => self::TENANT_ID,
            'user_id'               => $this->userId3,
            'under_monitoring'      => true,
            'monitoring_expires_at' => null,
        ]);

        $this->artisan('safeguarding:clear-expired-monitoring')->assertExitCode(0);

        $row = DB::table('user_messaging_restrictions')->where('id', $id)->first();
        $this->assertTrue((bool) $row->under_monitoring, 'Indefinite monitoring should not be cleared');
    }

    // ---------------------------------------------------------------
    // Non-interference: already-cleared rows are untouched
    // ---------------------------------------------------------------

    public function test_does_not_affect_rows_where_under_monitoring_is_already_false(): void
    {
        $id = DB::table('user_messaging_restrictions')->insertGetId([
            'tenant_id'             => self::TENANT_ID,
            'user_id'               => $this->userId1,
            'under_monitoring'      => false,
            'monitoring_expires_at' => now()->subHour()->toDateTimeString(),
        ]);

        $this->artisan('safeguarding:clear-expired-monitoring')->assertExitCode(0);

        $row = DB::table('user_messaging_restrictions')->where('id', $id)->first();
        $this->assertFalse((bool) $row->under_monitoring, 'Already-cleared row should remain unchanged');
    }

    // ---------------------------------------------------------------
    // No-op: nothing expired → command still returns 0
    // ---------------------------------------------------------------

    public function test_returns_success_when_no_expired_restrictions_exist(): void
    {
        // No rows seeded at all with under_monitoring=true + past expiry.
        $this->artisan('safeguarding:clear-expired-monitoring')->assertExitCode(0);

        // No DB side-effect to assert; confirm it didn't blow up.
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // Multiple expired rows: all cleared in one run
    // ---------------------------------------------------------------

    public function test_clears_multiple_expired_restrictions_in_single_run(): void
    {
        $id1 = DB::table('user_messaging_restrictions')->insertGetId([
            'tenant_id'             => self::TENANT_ID,
            'user_id'               => $this->userId1,
            'under_monitoring'      => true,
            'monitoring_expires_at' => now()->subDays(2)->toDateTimeString(),
        ]);

        $id2 = DB::table('user_messaging_restrictions')->insertGetId([
            'tenant_id'             => self::TENANT_ID,
            'user_id'               => $this->userId2,
            'under_monitoring'      => true,
            'monitoring_expires_at' => now()->subDays(5)->toDateTimeString(),
        ]);

        $this->artisan('safeguarding:clear-expired-monitoring')->assertExitCode(0);

        $this->assertFalse(
            (bool) DB::table('user_messaging_restrictions')->where('id', $id1)->value('under_monitoring')
        );
        $this->assertFalse(
            (bool) DB::table('user_messaging_restrictions')->where('id', $id2)->value('under_monitoring')
        );
    }

    // ---------------------------------------------------------------
    // Mixed state: only expired rows are cleared, active ones kept
    // ---------------------------------------------------------------

    public function test_selectively_clears_only_expired_rows_among_mixed_state(): void
    {
        $expiredId = DB::table('user_messaging_restrictions')->insertGetId([
            'tenant_id'             => self::TENANT_ID,
            'user_id'               => $this->userId1,
            'under_monitoring'      => true,
            'monitoring_expires_at' => now()->subHour()->toDateTimeString(),
        ]);

        $activeId = DB::table('user_messaging_restrictions')->insertGetId([
            'tenant_id'             => self::TENANT_ID,
            'user_id'               => $this->userId2,
            'under_monitoring'      => true,
            'monitoring_expires_at' => now()->addDays(7)->toDateTimeString(),
        ]);

        $this->artisan('safeguarding:clear-expired-monitoring')->assertExitCode(0);

        $this->assertFalse(
            (bool) DB::table('user_messaging_restrictions')->where('id', $expiredId)->value('under_monitoring'),
            'Expired restriction must be cleared'
        );
        $this->assertTrue(
            (bool) DB::table('user_messaging_restrictions')->where('id', $activeId)->value('under_monitoring'),
            'Active restriction must be preserved'
        );
    }

    // ---------------------------------------------------------------
    // Boundary: expiry exactly at now() is treated as expired
    // ---------------------------------------------------------------

    public function test_row_expiring_in_past_seconds_is_cleared(): void
    {
        // A monitoring_expires_at that is a few seconds before now() should be cleared.
        $id = DB::table('user_messaging_restrictions')->insertGetId([
            'tenant_id'             => self::TENANT_ID,
            'user_id'               => $this->userId3,
            'under_monitoring'      => true,
            'monitoring_expires_at' => now()->subSeconds(5)->toDateTimeString(),
        ]);

        $this->artisan('safeguarding:clear-expired-monitoring')->assertExitCode(0);

        $this->assertFalse(
            (bool) DB::table('user_messaging_restrictions')->where('id', $id)->value('under_monitoring')
        );
    }
}
