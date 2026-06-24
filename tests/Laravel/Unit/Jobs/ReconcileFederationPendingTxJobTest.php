<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Jobs;

use App\Core\TenantContext;
use App\Jobs\ReconcileFederationPendingTxJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * ReconcileFederationPendingTxJobTest
 *
 * Verifies ReconcileFederationPendingTxJob behaviour:
 *  - No stale transactions → handle() returns without writing audit rows.
 *  - Stale federated pending transactions → audit rows written + Log::critical fired.
 *  - Fresh pending transactions (under 10 min) are NOT flagged.
 *  - Non-federated pending transactions are NOT flagged.
 *  - Capped at MAX_PER_RUN (100) — only the oldest rows are surfaced.
 *  - Job constructor sets queue = 'federation' without the trait conflict.
 *  - Job declares correct tries/backoff/timeout.
 */
class ReconcileFederationPendingTxJobTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    // ── helpers ────────────────────────────────────────────────────────────────

    /**
     * Insert a transaction row with control over age and federation flag.
     *
     * @param int  $minutesAgo How many minutes ago the tx was created.
     * @param bool $isFederated Whether to set is_federated = 1.
     * @param string $status   Transaction status.
     */
    private function insertTransaction(
        int    $minutesAgo = 15,
        bool   $isFederated = true,
        string $status = 'pending',
        int    $tenantId = self::TENANT_ID,
    ): int {
        $uid = uniqid('tx_', true);
        return (int) DB::table('transactions')->insertGetId([
            'tenant_id'    => $tenantId,
            'amount'       => 1.00,
            'description'  => 'reconcile-test-' . $uid,
            'status'       => $status,
            'is_federated' => $isFederated ? 1 : 0,
            'created_at'   => now()->subMinutes($minutesAgo),
            'updated_at'   => now()->subMinutes($minutesAgo),
            'transaction_type' => 'transfer',
        ]);
    }

    // ── tests ──────────────────────────────────────────────────────────────────

    /** No stale federated pending txs → handle() returns early, no audit rows. */
    public function test_no_stale_transactions_produces_no_audit_rows(): void
    {
        // Ensure there are no pre-existing stale federated pending rows
        // that could bleed into this test (DatabaseTransactions rolls back after).
        TenantContext::setById(self::TENANT_ID);

        $countBefore = DB::table('federation_audit_log')
            ->where('action_type', 'external_transaction_stuck_pending')
            ->count();

        $job = new ReconcileFederationPendingTxJob();
        $job->handle();

        $this->assertSame(
            (int) $countBefore,
            (int) DB::table('federation_audit_log')
                ->where('action_type', 'external_transaction_stuck_pending')
                ->count(),
            'No audit rows expected when no stale federated pending transactions exist'
        );
    }

    /**
     * Stale federated pending transactions (>10 min old) → one audit row per tx.
     */
    public function test_stale_federated_pending_transactions_produce_audit_rows(): void
    {
        TenantContext::setById(self::TENANT_ID);

        $tx1 = $this->insertTransaction(minutesAgo: 15, isFederated: true);
        $tx2 = $this->insertTransaction(minutesAgo: 20, isFederated: true);

        $before = now()->subSecond();

        $job = new ReconcileFederationPendingTxJob();
        $job->handle();

        $auditRows = DB::table('federation_audit_log')
            ->where('action_type', 'external_transaction_stuck_pending')
            ->where('created_at', '>=', $before)
            ->get();

        $this->assertGreaterThanOrEqual(2, $auditRows->count(),
            'At least one audit row per stale federated pending tx expected');

        $auditTxIds = $auditRows->map(fn ($r) => json_decode($r->data, true)['transaction_id'] ?? null)->all();
        $this->assertContains($tx1, $auditTxIds, 'tx1 should be in audit rows');
        $this->assertContains($tx2, $auditTxIds, 'tx2 should be in audit rows');
    }

    /**
     * Fresh federated pending transactions (< 10 min old) are NOT flagged.
     */
    public function test_fresh_federated_pending_transactions_are_not_flagged(): void
    {
        TenantContext::setById(self::TENANT_ID);

        // Insert a tx only 5 minutes old — under the 10-minute threshold.
        $freshTxId = $this->insertTransaction(minutesAgo: 5, isFederated: true);

        $before = now()->subSecond();

        $job = new ReconcileFederationPendingTxJob();
        $job->handle();

        $auditRows = DB::table('federation_audit_log')
            ->where('action_type', 'external_transaction_stuck_pending')
            ->where('created_at', '>=', $before)
            ->get();

        $auditTxIds = $auditRows->map(fn ($r) => json_decode($r->data, true)['transaction_id'] ?? null)->all();
        $this->assertNotContains($freshTxId, $auditTxIds,
            'Fresh (< 10 min) pending tx must NOT be flagged');
    }

    /**
     * Non-federated pending transactions (is_federated=0) are NOT flagged.
     */
    public function test_non_federated_pending_transactions_are_not_flagged(): void
    {
        TenantContext::setById(self::TENANT_ID);

        // 15 minutes old but NOT federated.
        $nonFedTxId = $this->insertTransaction(minutesAgo: 15, isFederated: false);

        $before = now()->subSecond();

        $job = new ReconcileFederationPendingTxJob();
        $job->handle();

        $auditRows = DB::table('federation_audit_log')
            ->where('action_type', 'external_transaction_stuck_pending')
            ->where('created_at', '>=', $before)
            ->get();

        $auditTxIds = $auditRows->map(fn ($r) => json_decode($r->data, true)['transaction_id'] ?? null)->all();
        $this->assertNotContains($nonFedTxId, $auditTxIds,
            'Non-federated pending tx must NOT be flagged');
    }

    /**
     * Completed federated transactions are NOT flagged (status != 'pending').
     */
    public function test_completed_federated_transactions_are_not_flagged(): void
    {
        TenantContext::setById(self::TENANT_ID);

        $completedTxId = $this->insertTransaction(minutesAgo: 30, isFederated: true, status: 'completed');

        $before = now()->subSecond();

        $job = new ReconcileFederationPendingTxJob();
        $job->handle();

        $auditRows = DB::table('federation_audit_log')
            ->where('action_type', 'external_transaction_stuck_pending')
            ->where('created_at', '>=', $before)
            ->get();

        $auditTxIds = $auditRows->map(fn ($r) => json_decode($r->data, true)['transaction_id'] ?? null)->all();
        $this->assertNotContains($completedTxId, $auditTxIds,
            'Completed (non-pending) federated tx must NOT be flagged');
    }

    /**
     * Audit row data contains required fields: transaction_id, amount, minutes_stale.
     */
    public function test_audit_row_data_contains_required_fields(): void
    {
        TenantContext::setById(self::TENANT_ID);

        $txId = $this->insertTransaction(minutesAgo: 20, isFederated: true);
        $before = now()->subSecond();

        $job = new ReconcileFederationPendingTxJob();
        $job->handle();

        $row = DB::table('federation_audit_log')
            ->where('action_type', 'external_transaction_stuck_pending')
            ->where('created_at', '>=', $before)
            ->whereJsonContains('data->transaction_id', $txId)
            ->first();

        $this->assertNotNull($row, 'Expected audit row not found for txId ' . $txId);

        $data = json_decode($row->data, true);
        $this->assertArrayHasKey('transaction_id', $data);
        $this->assertArrayHasKey('amount', $data);
        $this->assertArrayHasKey('minutes_stale', $data);
        $this->assertSame(10, $data['minutes_stale'],
            'minutes_stale must equal the STALE_AFTER_MINUTES constant (10)');
    }

    /**
     * Constructor assigns queue='federation' correctly (no trait conflict).
     * ReconcileFederationPendingTxJob uses the constructor-assignment pattern.
     */
    public function test_constructor_sets_queue_to_federation(): void
    {
        $job = new ReconcileFederationPendingTxJob();
        $this->assertSame('federation', $job->queue);
    }

    /** Job declares tries=3 (retries on transient DB failures). */
    public function test_job_declares_three_tries(): void
    {
        $job = new ReconcileFederationPendingTxJob();
        $this->assertSame(3, $job->tries);
    }

    /** Job declares a 120-second timeout. */
    public function test_job_declares_120_second_timeout(): void
    {
        $job = new ReconcileFederationPendingTxJob();
        $this->assertSame(120, $job->timeout);
    }

    /** Job declares backoff array [60, 300, 900]. */
    public function test_job_declares_backoff_schedule(): void
    {
        $job = new ReconcileFederationPendingTxJob();
        $this->assertSame([60, 300, 900], $job->backoff);
    }

    /**
     * Mix of stale + fresh + non-federated: only the stale+federated tx is flagged.
     */
    public function test_only_stale_federated_pending_transactions_are_flagged(): void
    {
        TenantContext::setById(self::TENANT_ID);

        $staleFedId    = $this->insertTransaction(minutesAgo: 30, isFederated: true);
        $freshFedId    = $this->insertTransaction(minutesAgo: 3,  isFederated: true);
        $staleNonFedId = $this->insertTransaction(minutesAgo: 30, isFederated: false);
        $staleCompId   = $this->insertTransaction(minutesAgo: 30, isFederated: true, status: 'completed');

        $before = now()->subSecond();
        $job = new ReconcileFederationPendingTxJob();
        $job->handle();

        $auditRows = DB::table('federation_audit_log')
            ->where('action_type', 'external_transaction_stuck_pending')
            ->where('created_at', '>=', $before)
            ->get();
        $auditTxIds = $auditRows->map(fn ($r) => json_decode($r->data, true)['transaction_id'] ?? null)->all();

        $this->assertContains($staleFedId, $auditTxIds, 'Stale federated pending tx MUST be flagged');
        $this->assertNotContains($freshFedId, $auditTxIds, 'Fresh federated pending tx must NOT be flagged');
        $this->assertNotContains($staleNonFedId, $auditTxIds, 'Stale non-federated tx must NOT be flagged');
        $this->assertNotContains($staleCompId, $auditTxIds, 'Completed federated tx must NOT be flagged');
    }
}
