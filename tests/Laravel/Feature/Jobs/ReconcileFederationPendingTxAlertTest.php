<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Jobs;

use App\Core\TenantContext;
use App\Jobs\ReconcileFederationPendingTxJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Tests\Laravel\TestCase;

/**
 * B3 regression lock — stuck federated money must be VISIBLE (reach Sentry),
 * not just written to a log channel that prod may not route to Sentry.
 *
 * The reconcile job now calls report() (not only Log::critical) when it finds
 * federated transactions stuck in 'pending' past the staleness threshold. These
 * tests spy the exception handler to prove the alert fires for stale rows and
 * stays silent when nothing is stale.
 */
class ReconcileFederationPendingTxAlertTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        // Clear the slate (rolled back by DatabaseTransactions) so pre-existing
        // rows in the shared nexus_test DB can't skew the assertions — the job
        // scans transactions globally (no tenant scope), by design.
        DB::table('transactions')->where('is_federated', 1)->where('status', 'pending')->delete();
    }

    private function seedFederatedPending(\DateTimeInterface|string $createdAt): int
    {
        return (int) DB::table('transactions')->insertGetId([
            'tenant_id'        => $this->testTenantId,
            'amount'           => 5,
            'status'           => 'pending',
            'is_federated'     => 1,
            'transaction_type' => 'transfer',
            'created_at'       => $createdAt,
            'updated_at'       => $createdAt,
        ]);
    }

    public function test_stale_federated_pending_tx_is_reported_to_sentry(): void
    {
        Exceptions::fake();

        $this->seedFederatedPending(now()->subMinutes(30)); // older than the 10-min threshold

        (new ReconcileFederationPendingTxJob())->handle();

        Exceptions::assertReported(
            fn (\RuntimeException $e) => str_contains($e->getMessage(), 'stuck pending')
        );
    }

    public function test_recent_federated_pending_tx_does_not_alert(): void
    {
        Exceptions::fake();

        $this->seedFederatedPending(now()->subMinutes(2)); // within the threshold — not stale

        (new ReconcileFederationPendingTxJob())->handle();

        Exceptions::assertNothingReported();
    }
}
