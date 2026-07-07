<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Jobs;

use App\Core\TenantContext;
use App\Services\FederationAuditService;
use App\Services\FederationExternalApiClient;
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
 * than the threshold, and attempts to resolve external rows by querying the
 * partner's transaction-status endpoint. Rows with a terminal remote state are
 * auto-finalised or refunded; unresolved rows are surfaced via critical-level
 * logs + audit events so ops can manually resolve.
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

        $stale = DB::table('transactions as tx')
            ->leftJoin('federation_external_partners as ep', function ($join): void {
                $join->on('ep.id', '=', 'tx.receiver_tenant_id')
                    ->on('ep.tenant_id', '=', 'tx.tenant_id');
            })
            ->where('tx.status', 'pending')
            ->where('tx.is_federated', 1)
            ->where('tx.created_at', '<', $cutoff)
            ->orderBy('tx.created_at')
            ->limit(self::MAX_PER_RUN)
            ->select([
                'tx.*',
                'ep.id as external_partner_id',
                'ep.name as external_partner_name',
                'ep.protocol_type as external_partner_protocol',
            ])
            ->get();

        if ($stale->isEmpty()) {
            return;
        }

        $unresolved = collect();
        $resolvedCount = 0;

        foreach ($stale as $tx) {
            if (!empty($tx->external_partner_id)) {
                $resolution = $this->resolveExternalTransaction($tx);
                if ($resolution !== 'unresolved') {
                    $resolvedCount++;
                    continue;
                }
            }

            $unresolved->push($tx);
        }

        if ($unresolved->isEmpty()) {
            Log::info('ReconcileFederationPendingTxJob: resolved stale federated transactions automatically', [
                'count' => $resolvedCount,
                'cutoff' => $cutoff->toIso8601String(),
            ]);
            return;
        }

        $sampleIds = $unresolved->take(10)->pluck('id')->all();

        Log::critical('ReconcileFederationPendingTxJob: stale federated transactions detected', [
            'count' => $unresolved->count(),
            'resolved_count' => $resolvedCount,
            'cutoff' => $cutoff->toIso8601String(),
            'sample_ids' => $sampleIds,
            'external_partner_ids' => $unresolved
                ->pluck('external_partner_id')
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ]);

        // Page a human. A Log::critical only reaches Sentry if LOG_STACK includes
        // the `sentry` channel, which prod cannot be assumed to set; report() goes
        // through the Sentry integration directly. Stuck federated money is a
        // money-state-drift signal that MUST be visible, not just logged.
        report(new \RuntimeException(sprintf(
            'Federation reconcile: %d federated transaction(s) stuck pending > %d min (sample ids: %s)',
            $unresolved->count(),
            self::STALE_AFTER_MINUTES,
            implode(',', array_map('strval', $sampleIds))
        )));

        foreach ($unresolved as $tx) {
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
                        'external_partner_id' => isset($tx->external_partner_id) ? (int) $tx->external_partner_id : null,
                        'external_partner_name' => $tx->external_partner_name ?? null,
                        'external_partner_protocol' => $tx->external_partner_protocol ?? null,
                        'partner_idempotency_key' => $tx->federation_partner_idempotency_key ?? null,
                        'external_transaction_id' => $tx->external_transaction_id ?? null,
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

    private function resolveExternalTransaction(object $tx): string
    {
        $tenantId = (int) $tx->tenant_id;
        $partnerId = (int) $tx->external_partner_id;

        try {
            $remote = TenantContext::runForTenant($tenantId, fn () => FederationExternalApiClient::fetchTransactionStatus(
                $partnerId,
                isset($tx->external_transaction_id) ? (string) $tx->external_transaction_id : null,
                isset($tx->federation_partner_idempotency_key) ? (string) $tx->federation_partner_idempotency_key : null
            ));
        } catch (\Throwable $e) {
            Log::warning('ReconcileFederationPendingTxJob: remote status lookup failed', [
                'transaction_id' => $tx->id,
                'tenant_id' => $tenantId,
                'external_partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);

            return 'unresolved';
        }

        if (!($remote['success'] ?? false) || empty($remote['data']) || !is_array($remote['data'])) {
            return 'unresolved';
        }

        $remoteData = $remote['data'];
        $remoteStatus = $this->normaliseRemoteStatus((string) ($remoteData['status'] ?? ''));
        $remoteTxId = (string) (
            $remoteData['external_transaction_id']
            ?? $remoteData['id']
            ?? $remoteData['uuid']
            ?? $tx->external_transaction_id
            ?? ''
        );

        if ($remoteStatus === 'completed') {
            return $this->completeLocalTransaction($tx, $remoteTxId, $remoteData);
        }

        if ($remoteStatus === 'cancelled') {
            return $this->refundCancelledRemoteTransaction($tx, $remoteTxId, $remoteData);
        }

        return 'unresolved';
    }

    private function completeLocalTransaction(object $tx, string $remoteTxId, array $remoteData): string
    {
        $updated = DB::table('transactions')
            ->where('id', $tx->id)
            ->where('tenant_id', $tx->tenant_id)
            ->where('status', 'pending')
            ->update(array_filter([
                'status' => 'completed',
                'external_transaction_id' => $remoteTxId !== '' ? $remoteTxId : null,
                'updated_at' => now(),
            ], fn ($value) => $value !== null));

        if ($updated === 0) {
            return 'unresolved';
        }

        FederationAuditService::log(
            'external_transaction_reconciled_completed',
            (int) $tx->tenant_id,
            isset($tx->receiver_tenant_id) ? (int) $tx->receiver_tenant_id : null,
            isset($tx->sender_id) ? (int) $tx->sender_id : null,
            [
                'transaction_id' => (int) $tx->id,
                'external_partner_id' => isset($tx->external_partner_id) ? (int) $tx->external_partner_id : null,
                'external_transaction_id' => $remoteTxId !== '' ? $remoteTxId : null,
                'remote_status' => $remoteData['status'] ?? null,
            ]
        );

        return 'completed';
    }

    private function refundCancelledRemoteTransaction(object $tx, string $remoteTxId, array $remoteData): string
    {
        try {
            DB::transaction(function () use ($tx, $remoteTxId): void {
                $updated = DB::table('transactions')
                    ->where('id', $tx->id)
                    ->where('tenant_id', $tx->tenant_id)
                    ->where('status', 'pending')
                    ->update(array_filter([
                        'status' => 'cancelled',
                        'external_transaction_id' => $remoteTxId !== '' ? $remoteTxId : null,
                        'updated_at' => now(),
                    ], fn ($value) => $value !== null));

                if ($updated === 0) {
                    throw new \RuntimeException('pending_transaction_not_updated');
                }

                if (!empty($tx->sender_id)) {
                    DB::table('users')
                        ->where('id', (int) $tx->sender_id)
                        ->where('tenant_id', (int) $tx->tenant_id)
                        ->update([
                            'balance' => DB::raw('balance + ' . (float) $tx->amount),
                            'updated_at' => now(),
                        ]);
                }
            });
        } catch (\Throwable $e) {
            Log::critical('ReconcileFederationPendingTxJob: remote cancellation refund failed', [
                'transaction_id' => $tx->id,
                'tenant_id' => $tx->tenant_id,
                'external_partner_id' => $tx->external_partner_id ?? null,
                'error' => $e->getMessage(),
            ]);

            return 'unresolved';
        }

        FederationAuditService::log(
            'external_transaction_reconciled_cancelled',
            (int) $tx->tenant_id,
            isset($tx->receiver_tenant_id) ? (int) $tx->receiver_tenant_id : null,
            isset($tx->sender_id) ? (int) $tx->sender_id : null,
            [
                'transaction_id' => (int) $tx->id,
                'external_partner_id' => isset($tx->external_partner_id) ? (int) $tx->external_partner_id : null,
                'external_transaction_id' => $remoteTxId !== '' ? $remoteTxId : null,
                'remote_status' => $remoteData['status'] ?? null,
                'refunded_amount' => $tx->amount,
            ]
        );

        return 'cancelled';
    }

    private function normaliseRemoteStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'completed', 'complete', 'committed', 'settled', 'accepted', 'delivered', 'c' => 'completed',
            'cancelled', 'canceled', 'failed', 'rejected', 'declined', 'void', 'voided', 'e', 'x' => 'cancelled',
            default => 'pending',
        };
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
