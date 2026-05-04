<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\UserFederatedOptOut;
use App\Models\FederatedIdentity;
use App\Services\FederationExternalApiClient;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * PushFederationDataRetraction — GDPR Article 17 enforcement for federated data.
 *
 * When a user deletes their account or opts out of federation, this listener
 * notifies every active federated partner that holds a linked identity for
 * this user, instructing them to retract (delete) the user's mirrored profile.
 *
 * Runs asynchronously on the queue to avoid blocking the HTTP response.
 */
class PushFederationDataRetraction implements ShouldQueue
{
    /** Route to the standard federation queue (bulk, non-time-critical). */
    public string $queue = 'federation';

    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    public function handle(UserFederatedOptOut $event): void
    {
        $userId   = $event->userId;
        $tenantId = $event->tenantId;

        try {
            TenantContext::setById($tenantId);

            if (!TenantContext::hasFeature('federation')) {
                return;
            }

            if (!$this->federationFeatureService->isTenantFederationEnabled($tenantId)) {
                return;
            }

            // Find all federated identities for this user (cross-partner links)
            $identities = FederatedIdentity::query()
                ->where('tenant_id', $tenantId)
                ->where('local_user_id', $userId)
                ->get();

            if ($identities->isEmpty()) {
                return;
            }

            // Notify every linked partner, even if member-sync is currently disabled.
            foreach ($identities as $identity) {
                $partnerId = (int) $identity->partner_id;
                if ($partnerId <= 0) {
                    continue;
                }

                try {
                    FederationExternalApiClient::retractMemberProfile($partnerId, $userId, [
                        'external_user_id' => $identity->external_user_id,
                        'reason'           => $event->reason,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('PushFederationDataRetraction: retraction failed for partner', [
                        'partner_id' => $partnerId,
                        'tenant_id'  => $tenantId,
                        'user_id'    => $userId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('PushFederationDataRetraction listener failed', [
                'tenant_id' => $tenantId ?? null,
                'user_id'   => $userId ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
