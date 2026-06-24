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
 * Tests for nexus:prune-match-notifications Artisan command.
 *
 * Uses tenant id 99710 to stay isolated from the default test tenant.
 *
 * The command deletes rows from match_notification_sent where
 * sent_at < NOW() - INTERVAL ? DAY (default 30 days).
 *
 * match_notification_sent has no FK to users or tenants beyond the
 * tenant_id / listing_id / matched_user_id columns (no FK constraints
 * enforced in the schema definition), so we can insert rows freely
 * without seeding related entities.
 */
class PruneMatchNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99710;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Ensure the isolated tenant exists.
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'PruneMatchNotif Test Tenant',
                'slug'              => 'prune-match-notif-test',
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
    // Core: old rows are deleted, recent rows are kept
    // ---------------------------------------------------------------

    public function test_deletes_markers_older_than_30_days(): void
    {
        $oldId = DB::table('match_notification_sent')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'listing_id'      => 1,
            'matched_user_id' => 1,
            'match_score'     => 50,
            'sent_at'         => now()->subDays(40)->toDateTimeString(),
        ]);

        $recentId = DB::table('match_notification_sent')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'listing_id'      => 2,
            'matched_user_id' => 2,
            'match_score'     => 60,
            'sent_at'         => now()->subDays(5)->toDateTimeString(),
        ]);

        $this->artisan('nexus:prune-match-notifications')->assertExitCode(0);

        $this->assertDatabaseMissing('match_notification_sent', ['id' => $oldId]);
        $this->assertDatabaseHas('match_notification_sent', ['id' => $recentId]);
    }

    // ---------------------------------------------------------------
    // Boundary: row exactly at (days - 1) is kept; row past threshold is pruned
    // ---------------------------------------------------------------

    public function test_keeps_marker_within_retention_window(): void
    {
        // 29 days old with default 30-day window → must be kept.
        $keepId = DB::table('match_notification_sent')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'listing_id'      => 3,
            'matched_user_id' => 3,
            'match_score'     => 70,
            'sent_at'         => now()->subDays(29)->toDateTimeString(),
        ]);

        $this->artisan('nexus:prune-match-notifications')->assertExitCode(0);

        $this->assertDatabaseHas('match_notification_sent', ['id' => $keepId]);
    }

    public function test_deletes_marker_just_past_retention_threshold(): void
    {
        // 31 days old with default 30-day window → must be deleted.
        $pruneId = DB::table('match_notification_sent')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'listing_id'      => 4,
            'matched_user_id' => 4,
            'match_score'     => 80,
            'sent_at'         => now()->subDays(31)->toDateTimeString(),
        ]);

        $this->artisan('nexus:prune-match-notifications')->assertExitCode(0);

        $this->assertDatabaseMissing('match_notification_sent', ['id' => $pruneId]);
    }

    // ---------------------------------------------------------------
    // No-op: nothing old → command still returns 0
    // ---------------------------------------------------------------

    public function test_returns_success_when_no_old_markers_exist(): void
    {
        // Only a very recent row — nothing to prune.
        $id = DB::table('match_notification_sent')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'listing_id'      => 5,
            'matched_user_id' => 5,
            'match_score'     => 90,
            'sent_at'         => now()->subHours(2)->toDateTimeString(),
        ]);

        $this->artisan('nexus:prune-match-notifications')->assertExitCode(0);

        $this->assertDatabaseHas('match_notification_sent', ['id' => $id]);
    }

    // ---------------------------------------------------------------
    // Empty table: no rows at all → succeeds cleanly
    // ---------------------------------------------------------------

    public function test_succeeds_with_empty_table(): void
    {
        // The DatabaseTransactions trait rolls back previous inserts; if this
        // test runs first there are no rows at all.  Just assert exit code.
        $this->artisan('nexus:prune-match-notifications')->assertExitCode(0);

        // Ensure at least one assertion so failOnRisky doesn't fire.
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // Custom --days option
    // ---------------------------------------------------------------

    public function test_respects_custom_days_option(): void
    {
        // With --days=7 a row 10 days old should be pruned.
        $oldId = DB::table('match_notification_sent')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'listing_id'      => 6,
            'matched_user_id' => 6,
            'match_score'     => 30,
            'sent_at'         => now()->subDays(10)->toDateTimeString(),
        ]);

        // With --days=7 a row 5 days old should be kept.
        $recentId = DB::table('match_notification_sent')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'listing_id'      => 7,
            'matched_user_id' => 7,
            'match_score'     => 30,
            'sent_at'         => now()->subDays(5)->toDateTimeString(),
        ]);

        $this->artisan('nexus:prune-match-notifications', ['--days' => '7'])->assertExitCode(0);

        $this->assertDatabaseMissing('match_notification_sent', ['id' => $oldId]);
        $this->assertDatabaseHas('match_notification_sent', ['id' => $recentId]);
    }

    // ---------------------------------------------------------------
    // Multiple old rows: all pruned in a single run
    // ---------------------------------------------------------------

    public function test_deletes_multiple_old_markers_at_once(): void
    {
        $id1 = DB::table('match_notification_sent')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'listing_id'      => 8,
            'matched_user_id' => 8,
            'match_score'     => 55,
            'sent_at'         => now()->subDays(35)->toDateTimeString(),
        ]);

        $id2 = DB::table('match_notification_sent')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'listing_id'      => 9,
            'matched_user_id' => 9,
            'match_score'     => 65,
            'sent_at'         => now()->subDays(60)->toDateTimeString(),
        ]);

        $this->artisan('nexus:prune-match-notifications')->assertExitCode(0);

        $this->assertDatabaseMissing('match_notification_sent', ['id' => $id1]);
        $this->assertDatabaseMissing('match_notification_sent', ['id' => $id2]);
    }

    // ---------------------------------------------------------------
    // Tenant isolation: only this tenant's rows are asserted
    // ---------------------------------------------------------------

    public function test_old_markers_from_isolated_tenant_are_deleted(): void
    {
        // The command has no tenant scope — it deletes globally by age.
        // We confirm our seeded tenant's old row is properly removed.
        $id = DB::table('match_notification_sent')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'listing_id'      => 10,
            'matched_user_id' => 10,
            'match_score'     => 45,
            'sent_at'         => now()->subDays(90)->toDateTimeString(),
        ]);

        $this->artisan('nexus:prune-match-notifications')->assertExitCode(0);

        $this->assertDatabaseMissing('match_notification_sent', ['id' => $id]);
    }

    // ---------------------------------------------------------------
    // Invalid (edge-case) --days value: min(1,...) guard in handle()
    // ---------------------------------------------------------------

    public function test_days_option_zero_is_clamped_to_one(): void
    {
        // --days=0 is clamped to 1 by max(1, ...) in the command.
        // A row from 2 days ago should be pruned when effective window = 1 day.
        $id = DB::table('match_notification_sent')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'listing_id'      => 11,
            'matched_user_id' => 11,
            'match_score'     => 20,
            'sent_at'         => now()->subDays(2)->toDateTimeString(),
        ]);

        $this->artisan('nexus:prune-match-notifications', ['--days' => '0'])->assertExitCode(0);

        $this->assertDatabaseMissing('match_notification_sent', ['id' => $id]);
    }
}
