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
 * Tests for federation:prune-aggregate-logs Artisan command.
 *
 * Uses tenant id 99726 (unique, isolated from other test suites).
 *
 * federation_aggregate_query_log retains 12 months (365 days) per
 * FederationAggregateService::LOG_RETENTION_DAYS.  The command takes no
 * options — it delegates to FederationAggregateService::pruneOldLogs()
 * which issues a single DB delete via Laravel's query builder.
 *
 * Real columns (from database/schema/mysql-schema.sql):
 *   id, tenant_id, requester_origin, period_from, period_to,
 *   fields_returned (JSON), response_signature, created_at
 */
class PruneFederationAggregateLogsTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99726;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'PruneAggLogs Test Tenant',
                'slug'              => 'prune-agg-logs-test',
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
    // Helpers
    // ---------------------------------------------------------------

    private function insertLog(int $daysAgo, string $origin = 'https://peer.example.com'): int
    {
        return DB::table('federation_aggregate_query_log')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'requester_origin'   => $origin,
            'period_from'        => now()->subYear()->toDateString(),
            'period_to'          => now()->toDateString(),
            'fields_returned'    => json_encode(['hours', 'members']),
            'response_signature' => str_repeat('a', 64),
            'created_at'         => now()->subDays($daysAgo)->toDateTimeString(),
        ]);
    }

    // ---------------------------------------------------------------
    // Basic deletion: row older than 365 days is pruned
    // ---------------------------------------------------------------

    public function test_prune_deletes_log_rows_older_than_365_days(): void
    {
        $oldId    = $this->insertLog(400);
        $recentId = $this->insertLog(30);

        $this->artisan('federation:prune-aggregate-logs')->assertExitCode(0);

        $this->assertDatabaseMissing('federation_aggregate_query_log', ['id' => $oldId]);
        $this->assertDatabaseHas('federation_aggregate_query_log', ['id' => $recentId]);
    }

    // ---------------------------------------------------------------
    // Boundary: row at exactly 364 days is kept (not yet past cutoff)
    // ---------------------------------------------------------------

    public function test_prune_keeps_row_within_365_day_boundary(): void
    {
        $boundaryId = $this->insertLog(364);

        $this->artisan('federation:prune-aggregate-logs')->assertExitCode(0);

        $this->assertDatabaseHas('federation_aggregate_query_log', ['id' => $boundaryId]);
    }

    // ---------------------------------------------------------------
    // Multiple old rows all deleted
    // ---------------------------------------------------------------

    public function test_prune_deletes_multiple_old_rows(): void
    {
        $id1 = $this->insertLog(366);
        $id2 = $this->insertLog(500);
        $id3 = $this->insertLog(730);

        $this->artisan('federation:prune-aggregate-logs')->assertExitCode(0);

        $this->assertDatabaseMissing('federation_aggregate_query_log', ['id' => $id1]);
        $this->assertDatabaseMissing('federation_aggregate_query_log', ['id' => $id2]);
        $this->assertDatabaseMissing('federation_aggregate_query_log', ['id' => $id3]);
    }

    // ---------------------------------------------------------------
    // No-op: no qualifying rows → command still exits 0
    // ---------------------------------------------------------------

    public function test_prune_succeeds_with_no_qualifying_rows(): void
    {
        $recentId = $this->insertLog(10);

        $this->artisan('federation:prune-aggregate-logs')->assertExitCode(0);

        $this->assertDatabaseHas('federation_aggregate_query_log', ['id' => $recentId]);
    }

    // ---------------------------------------------------------------
    // No-op: completely empty table → exits 0
    // ---------------------------------------------------------------

    public function test_prune_succeeds_on_empty_table(): void
    {
        // Do not seed any rows for this tenant. The command must handle
        // a zero-row result without error.
        $this->artisan('federation:prune-aggregate-logs')->assertExitCode(0);

        $count = DB::table('federation_aggregate_query_log')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame(0, $count);
    }

    // ---------------------------------------------------------------
    // Isolation: rows from a different tenant are NOT pruned
    // ---------------------------------------------------------------

    public function test_prune_does_not_delete_recent_rows_from_other_tenants(): void
    {
        // Seed a row for a different tenant that is old enough to be pruned
        // globally (if the command were tenant-agnostic). The command calls
        // FederationAggregateService::pruneOldLogs() which uses a raw cutoff
        // without tenant scoping — it prunes ALL tenants' old data. Verify
        // that only old rows are deleted regardless of tenant.
        $otherTenantId = 999; // The secondary tenant pre-seeded by TestCase::setUpTenantContext()

        $otherRecentId = DB::table('federation_aggregate_query_log')->insertGetId([
            'tenant_id'          => $otherTenantId,
            'requester_origin'   => 'https://other.example.com',
            'period_from'        => now()->subYear()->toDateString(),
            'period_to'          => now()->toDateString(),
            'fields_returned'    => json_encode(['hours']),
            'response_signature' => str_repeat('b', 64),
            'created_at'         => now()->subDays(20)->toDateTimeString(), // recent → must survive
        ]);

        $this->artisan('federation:prune-aggregate-logs')->assertExitCode(0);

        $this->assertDatabaseHas('federation_aggregate_query_log', ['id' => $otherRecentId]);
    }

    // ---------------------------------------------------------------
    // Output: command reports pruned count
    // ---------------------------------------------------------------

    public function test_prune_output_includes_pruned_count(): void
    {
        $this->insertLog(400);

        $this->artisan('federation:prune-aggregate-logs')
            ->expectsOutputToContain('Pruned')
            ->assertExitCode(0);
    }

    // ---------------------------------------------------------------
    // Old + recent mix: only the old row is deleted
    // ---------------------------------------------------------------

    public function test_prune_mixed_old_and_recent_rows_for_same_tenant(): void
    {
        $oldId    = $this->insertLog(400, 'https://peer-a.example.com');
        $keepId1  = $this->insertLog(200, 'https://peer-b.example.com');
        $keepId2  = $this->insertLog(10,  'https://peer-c.example.com');

        $this->artisan('federation:prune-aggregate-logs')->assertExitCode(0);

        $this->assertDatabaseMissing('federation_aggregate_query_log', ['id' => $oldId]);
        $this->assertDatabaseHas('federation_aggregate_query_log', ['id' => $keepId1]);
        $this->assertDatabaseHas('federation_aggregate_query_log', ['id' => $keepId2]);
    }

    // ---------------------------------------------------------------
    // Signature field: full 64-char hex signature stored correctly
    // ---------------------------------------------------------------

    public function test_prune_preserves_signature_on_kept_rows(): void
    {
        $sig = str_repeat('f', 64);

        $keepId = DB::table('federation_aggregate_query_log')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'requester_origin'   => 'https://verifier.example.com',
            'period_from'        => '2025-01-01',
            'period_to'          => '2025-12-31',
            'fields_returned'    => json_encode(['hours', 'members', 'partner_orgs']),
            'response_signature' => $sig,
            'created_at'         => now()->subDays(100)->toDateTimeString(), // within 365
        ]);

        $this->artisan('federation:prune-aggregate-logs')->assertExitCode(0);

        $this->assertDatabaseHas('federation_aggregate_query_log', [
            'id'                 => $keepId,
            'response_signature' => $sig,
        ]);
    }
}
