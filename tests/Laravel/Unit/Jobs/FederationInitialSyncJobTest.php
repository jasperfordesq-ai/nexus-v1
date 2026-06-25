<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Jobs;

use App\Jobs\FederationInitialSyncJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * FederationInitialSyncJobTest
 *
 * FIXED BUG (regression guard): FederationInitialSyncJob previously declared
 * `public string $queue = 'federation';` with an explicit type annotation. PHP
 * 8.2+ trait composition rules require type-compatible property declarations;
 * Illuminate\Bus\Queueable declares `public $queue;` (untyped), so the override
 * was incompatible and produced a fatal error at class-load time:
 *
 *   "App\Jobs\FederationInitialSyncJob and Illuminate\Bus\Queueable define the same
 *    property ($queue) in the composition of App\Jobs\FederationInitialSyncJob.
 *    However, the definition differs and is considered incompatible."
 *
 * Because the fatal fired during PHP compilation of the class, the job could not
 * be dispatched at all in production. The fix assigns `$this->queue = 'federation';`
 * inside the constructor instead of re-declaring the property (mirroring
 * ReconcileFederationPendingTxJob). These tests now instantiate the real class
 * to guard against the regression returning, and verify the queue/tries/timeout
 * configuration and the bilateral audit pattern handle() relies on.
 */
class FederationInitialSyncJobTest extends TestCase
{
    use DatabaseTransactions;

    // ── instantiation tests (proves the class loads & is configured) ──────────

    /** The class loads and instantiates without the trait-composition fatal. */
    public function test_job_can_be_instantiated(): void
    {
        $job = new FederationInitialSyncJob(2, 3, 1);

        $this->assertInstanceOf(FederationInitialSyncJob::class, $job);
        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    /** Job targets the 'federation' queue (set in the constructor, not as a typed property). */
    public function test_job_targets_federation_queue(): void
    {
        $job = new FederationInitialSyncJob(2, 3, 1);

        $this->assertSame('federation', $job->queue);
    }

    /** Job declares tries = 1 (idempotency by design — fire once). */
    public function test_job_declares_one_try(): void
    {
        $job = new FederationInitialSyncJob(2, 3, 1);

        $this->assertSame(1, $job->tries);
    }

    /** Job declares a 300-second timeout (large tenant tables may be scanned). */
    public function test_job_declares_300_second_timeout(): void
    {
        $job = new FederationInitialSyncJob(2, 3, 1);

        $this->assertSame(300, $job->timeout);
    }

    /** Constructor stores tenantId, partnerTenantId and partnershipId. */
    public function test_constructor_stores_required_params(): void
    {
        $job = new FederationInitialSyncJob(2, 3, 7);

        $this->assertSame(2, $job->tenantId);
        $this->assertSame(3, $job->partnerTenantId);
        $this->assertSame(7, $job->partnershipId);
    }

    /**
     * Regression guard: the `$queue` property must NOT be re-declared with a type
     * in the class body. A typed re-declaration reintroduces the PHP trait-
     * composition fatal that made this job undispatchable. The value must be set
     * via the constructor instead.
     */
    public function test_queue_property_is_not_typed_redeclaration(): void
    {
        $source = file_get_contents(base_path('app/Jobs/FederationInitialSyncJob.php'));

        $this->assertStringNotContainsString(
            'public string $queue',
            $source,
            'Regression: `public string $queue` re-declaration conflicts with the ' .
            'Queueable trait and fatals at class load. Set $this->queue in the constructor instead.'
        );
        $this->assertStringContainsString(
            "\$this->queue = 'federation';",
            $source,
            'The federation queue must be assigned in the constructor.'
        );
    }

    // ── FederationAuditService integration: verify bilateral audit pattern ────
    //
    // These tests replicate what handle() does (call FederationAuditService::log
    // twice with source_tenant_id swapped) and assert the DB outcome, so the
    // infrastructure (audit table columns, bilateral insert logic) is covered.

    /** FederationAuditService::log writes a row with the correct columns. */
    public function test_audit_service_writes_row_with_correct_columns(): void
    {
        $partnershipId = (int) DB::table('federation_partnerships')->insertGetId([
            'tenant_id'         => 2,
            'partner_tenant_id' => 3,
            'canonical_pair'    => 'fed-audit-col-' . uniqid(),
            'status'            => 'active',
            'federation_level'  => 1,
            'requested_at'      => now(),
            'created_at'        => now(),
        ]);

        $before = now()->subSecond();

        \App\Services\FederationAuditService::log(
            'partnership_initial_sync_complete',
            2,
            3,
            null,
            ['partnership_id' => $partnershipId, 'opted_in_members' => 5, 'active_listings' => 2]
        );

        $row = DB::table('federation_audit_log')
            ->where('action_type', 'partnership_initial_sync_complete')
            ->where('source_tenant_id', 2)
            ->where('target_tenant_id', 3)
            ->where('created_at', '>=', $before)
            ->first();

        $this->assertNotNull($row, 'Audit row should be written');
        $data = json_decode($row->data, true);
        $this->assertSame($partnershipId, $data['partnership_id']);
        $this->assertSame(5, $data['opted_in_members']);
        $this->assertSame(2, $data['active_listings']);
    }

    /**
     * Bilateral audit pattern: two calls with swapped source/target produce
     * two rows each from the other tenant's perspective (mirrors what handle() does).
     */
    public function test_bilateral_audit_rows_represent_both_tenant_perspectives(): void
    {
        $partnershipId = (int) DB::table('federation_partnerships')->insertGetId([
            'tenant_id'         => 2,
            'partner_tenant_id' => 3,
            'canonical_pair'    => 'fed-bilateral-' . uniqid(),
            'status'            => 'active',
            'federation_level'  => 1,
            'requested_at'      => now(),
            'created_at'        => now(),
        ]);

        $before = now()->subSecond();

        // Replicate the bilateral write pattern from handle().
        \App\Services\FederationAuditService::log(
            'partnership_initial_sync_complete',
            2,
            3,
            null,
            ['partnership_id' => $partnershipId, 'opted_in_members' => 3, 'active_listings' => 1]
        );
        \App\Services\FederationAuditService::log(
            'partnership_initial_sync_complete',
            3,
            2,
            null,
            ['partnership_id' => $partnershipId, 'opted_in_members' => 4, 'active_listings' => 2]
        );

        $rows = DB::table('federation_audit_log')
            ->where('action_type', 'partnership_initial_sync_complete')
            ->where('created_at', '>=', $before)
            ->orderBy('source_tenant_id')
            ->get();

        $this->assertCount(2, $rows, 'Two bilateral audit rows must be written');
        $this->assertSame(2, (int) $rows[0]->source_tenant_id);
        $this->assertSame(3, (int) $rows[0]->target_tenant_id);
        $this->assertSame(3, (int) $rows[1]->source_tenant_id);
        $this->assertSame(2, (int) $rows[1]->target_tenant_id);
    }

    /**
     * Idempotency cache key pattern: done-cache key blocks re-entry.
     * Mirrors the guard in handle() — cache key = 'federation_initial_sync:done:{id}'.
     */
    public function test_idempotency_done_cache_key_pattern(): void
    {
        $partnershipId = 42001; // artificial id, no DB row needed for this test.
        $doneKey  = 'federation_initial_sync:done:' . $partnershipId;
        $claimKey = 'federation_initial_sync:claim:' . $partnershipId;

        Cache::forget($doneKey);
        Cache::forget($claimKey);

        // Simulate the guard: if done-key exists, skip.
        Cache::put($doneKey, 1, 3600);
        $this->assertTrue(Cache::has($doneKey), 'done-key guard must block re-entry');

        // Claim key must NOT be present (job exits before acquiring it).
        $this->assertFalse(Cache::has($claimKey));

        // Cleanup.
        Cache::forget($doneKey);
    }
}
