<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\TransactionCompleted;
use App\Services\FederationExternalApiClient;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * PushTransactionToFederatedPartner — pushes a completed local transaction
 * to an external federation partner when either participant is federated.
 *
 * Queued so the local wallet flow is never blocked by outbound HTTP.
 * Client-side circuit breaker + retry handles transient failures.
 */
class PushTransactionToFederatedPartner implements ShouldQueue
{
    /** Process on the high-priority federation queue to minimise transaction latency. */
    public string $queue = 'federation-high';

    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    public function handle(TransactionCompleted $event): void
    {
        try {
            TenantContext::setById($event->tenantId);

            if (!TenantContext::hasFeature('federation')) {
                return;
            }

            if (!$this->federationFeatureService->isTenantFederationEnabled($event->tenantId)) {
                return;
            }

            $transaction = $event->transaction;

            // Only forward federated transactions
            if (empty($transaction->is_federated)) {
                return;
            }

            $partnerId = (int) ($transaction->external_partner_id ?? 0);

            if ($partnerId <= 0) {
                // Nothing to push — transaction is flagged federated but has
                // no external partner linkage (purely cross-tenant internal).
                return;
            }

            $payload = [
                'id'              => $transaction->id,
                'amount'          => $transaction->amount,
                'description'     => $transaction->description,
                'sender_id'       => $event->sender->id,
                'receiver_id'     => $event->receiver->id,
                'recipient_id'    => $event->receiver->id,
                'sender_user_id'  => $event->sender->id,
                'receiver_user_id' => $event->receiver->id,
                'sender_tenant'   => $event->tenantId,
                'sender_tenant_id' => $event->tenantId,
                'receiver_tenant_id' => $transaction->receiver_tenant_id ?? $event->receiver->tenant_id ?? null,
                'created_at'      => $transaction->created_at?->toISOString(),
            ];

            $result = FederationExternalApiClient::createTransaction($partnerId, $payload);

            if (empty($result['success'])) {
                Log::warning('PushTransactionToFederatedPartner: partner rejected transaction', [
                    'partner_id'     => $partnerId,
                    'tenant_id'      => $event->tenantId,
                    'transaction_id' => $transaction->id,
                    'error'          => $result['error'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('PushTransactionToFederatedPartner listener failed', [
                'tenant_id'      => $event->tenantId ?? null,
                'transaction_id' => $event->transaction->id ?? null,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
