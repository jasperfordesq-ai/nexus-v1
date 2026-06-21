<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Jobs;

use App\Services\FederationAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ReconcileFederationPendingTxJob — safety-net for the FederationV2 saga.
 *
 * The saga in FederationV2Controller::sendExternalTransaction commits a local
 * debit + 'pending' transaction row BEFORE calling the external partner. On
 * partner success it flips the row to 'completed'; on partner rejection it
 * issues a compensating refund. But network errors, process kills, or local
 * DB failures can leave the row stuck in 'pending' forever.
 *
 * This job runs on a schedule, finds federated 'pending' transactions older
 * than the threshold, and surfaces them via critical-level logs + audit
 * events so ops can manually resolve. A future enhancement should call the
 * partner's transaction-status endpoint and auto-finalise.
 *
 * Scheduled in bootstrap/app.php withSchedule().
 */
class ReconcileFederationPendingTxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Pending tx older than this is flagged for reconciliation. */
    private const STALE_AFTER_MINUTES = 10;

    /** Cap how many stale rows we surface per run, to avoid log floods. */
    private const MAX_PER_RUN = 100;

    // PHP 8.4 strict trait composition rejects ANY property re-declaration
    // when the trait's signature differs (Queueable declares `public $queue;`
    // with implicit null default; we need to assign 'federation'). Setting
    // $this->queue inside the constructor sidesteps the trait conflict
    // entirely while preserving the queue routing.

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 120;

    public function __construct()
    {
        $this->queue = 'federation';
    }

    public function handle(): void
    {
        $cutoff = now()->subMinutes(self::STALE_AFTER_MINUTES);

        $stale = DB::table('transactions')
            ->where('status', 'pending')
            ->where('is_federated', 1)
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->limit(self::MAX_PER_RUN)
            ->get();

        if ($stale->isEmpty()) {
            return;
        }

        $sampleIds = $stale->take(10)->pluck('id')->all();

        Log::critical('ReconcileFederationPendingTxJob: stale federated transactions detected', [
            'count' => $stale->count(),
            'cutoff' => $cutoff->toIso8601String(),
            'sample_ids' => $sampleIds,
        ]);

        // Page a human. A Log::critical only reaches Sentry if LOG_STACK includes
        // the `sentry` channel, which prod cannot be assumed to set; report() goes
        // through the Sentry integration directly. Stuck federated money is a
        // money-state-drift signal that MUST be visible, not just logged.
        report(new \RuntimeException(sprintf(
            'Federation reconcile: %d federated transaction(s) stuck pending > %d min (sample ids: %s)',
            $stale->count(),
            self::STALE_AFTER_MINUTES,
            implode(',', array_map('strval', $sampleIds))
        )));

        foreach ($stale as $tx) {
            try {
                FederationAuditService::log(
                    'external_transaction_stuck_pending',
                    (int) $tx->tenant_id,
                    isset($tx->receiver_tenant_id) ? (int) $tx->receiver_tenant_id : null,
                    isset($tx->sender_id) ? (int) $tx->sender_id : null,
                    [
                        'transaction_id' => (int) $tx->id,
                        'amount' => $tx->amount,
                        'created_at' => $tx->created_at,
                        'minutes_stale' => self::STALE_AFTER_MINUTES,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('ReconcileFederationPendingTxJob: audit log write failed', [
                    'transaction_id' => $tx->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ReconcileFederationPendingTxJob failed permanently', [
            'error' => $e->getMessage(),
        ]);

        // The reconcile job is itself a safety-net; if IT dies permanently,
        // stuck federated money goes unwatched. Surface it to Sentry.
        report($e);
    }
}
