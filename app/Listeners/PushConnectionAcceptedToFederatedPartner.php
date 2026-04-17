<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\ConnectionAccepted;
use App\Models\FederatedIdentity;
use App\Services\FederationExternalApiClient;
use App\Services\FederationExternalPartnerService;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * PushConnectionAcceptedToFederatedPartner
 *
 * When a local user accepts a connection request, and the OTHER party has a
 * `federated_identities` row (i.e. they live on a federation partner), push
 * the acceptance to that partner so the relationship mirrors across networks.
 *
 * Only partners with `allow_connections = 1` receive the notification.
 */
class PushConnectionAcceptedToFederatedPartner implements ShouldQueue
{
    /** Process on the high-priority federation queue to minimise connection notification latency. */
    public string $queue = 'federation-high';

    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    public function handle(ConnectionAccepted $event): void
    {
        $connection = $event->connectionModel;
        $tenantId   = $event->tenantId;

        try {
            TenantContext::setById($tenantId);

            if (!TenantContext::hasFeature('federation')) {
                return;
            }
            if (!$this->federationFeatureService->isTenantFederationEnabled($tenantId)) {
                return;
            }

            // Push to every federated identity owned by EITHER participant —
            // the requester or the receiver may be federated.  We fire a
            // separate POST per identity so each partner sees the acceptance.
            $participantIds = array_filter([
                (int) ($connection->requester_id ?? 0),
                (int) ($connection->receiver_id ?? 0),
            ]);
            if (empty($participantIds)) {
                return;
            }

            $identities = FederatedIdentity::query()
                ->whereIn('local_user_id', $participantIds)
                ->get();

            if ($identities->isEmpty()) {
                return; // purely-local connection — nothing to federate
            }

            // Build the set of partner IDs that have allow_connections = 1
            $allowed = FederationExternalPartnerService::getActivePartnersWithFlag($tenantId, 'allow_connections');
            $allowedPartnerIds = array_flip(array_map(fn ($p) => (int) $p['id'], $allowed));

            foreach ($identities as $identity) {
                $partnerId = (int) $identity->partner_id;
                if ($partnerId <= 0 || !isset($allowedPartnerIds[$partnerId])) {
                    continue;
                }

                $payload = [
                    'action'               => 'accepted',
                    'connection_id'        => $connection->id,
                    'requester_id'         => $connection->requester_id,
                    'receiver_id'          => $connection->receiver_id,
                    'external_user_id'     => $identity->external_user_id,
                    'local_user_id'        => $identity->local_user_id,
                    'tenant_id'            => $tenantId,
                    'accepted_at'          => $connection->updated_at?->toISOString()
                        ?? now()->toISOString(),
                ];

                try {
                    $result = FederationExternalApiClient::sendConnection($partnerId, $payload);

                    if (empty($result['success'])) {
                        Log::warning('PushConnectionAcceptedToFederatedPartner: partner rejected', [
                            'partner_id'    => $partnerId,
                            'tenant_id'     => $tenantId,
                            'connection_id' => $connection->id,
                            'error'         => $result['error'] ?? null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('PushConnectionAcceptedToFederatedPartner: partner push failed', [
                        'partner_id'    => $partnerId,
                        'tenant_id'     => $tenantId,
                        'connection_id' => $connection->id,
                        'error'         => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('PushConnectionAcceptedToFederatedPartner listener failed', [
                'tenant_id'     => $tenantId ?? null,
                'connection_id' => $connection->id ?? null,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
