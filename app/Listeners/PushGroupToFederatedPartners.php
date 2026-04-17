<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\GroupCreated;
use App\Services\FederationExternalApiClient;
use App\Services\FederationExternalPartnerService;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * PushGroupToFederatedPartners — broadcasts newly-created groups to external
 * federation partners that have `allow_groups = 1`.
 */
class PushGroupToFederatedPartners implements ShouldQueue
{
    /** Route to the standard federation queue (bulk, non-time-critical). */
    public string $queue = 'federation';

    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    public function handle(GroupCreated $event): void
    {
        $group    = $event->group;
        $tenantId = $event->tenantId;

        try {
            TenantContext::setById($tenantId);

            if (!TenantContext::hasFeature('federation')) {
                return;
            }
            if (!$this->federationFeatureService->isTenantFederationEnabled($tenantId)) {
                return;
            }

            $visibility = $group->federated_visibility ?? 'none';
            if (!in_array($visibility, ['listed', 'public'], true)) {
                return;
            }

            $partners = FederationExternalPartnerService::getActivePartnersWithFlag($tenantId, 'allow_groups');
            if (empty($partners)) {
                return;
            }

            $payload = [
                'action'      => 'created',
                'id'          => $group->id,
                'name'        => $group->name ?? null,
                'description' => $group->description ?? null,
                'visibility'  => $group->visibility ?? null,
                'owner_id'    => $group->owner_id ?? null,
                'tenant_id'   => $tenantId,
                'created_at'  => $group->created_at?->toISOString(),
            ];

            foreach ($partners as $partner) {
                $partnerId = (int) ($partner['id'] ?? 0);
                if ($partnerId <= 0) {
                    continue;
                }
                try {
                    $result = FederationExternalApiClient::sendGroup($partnerId, $payload);

                    if (empty($result['success'])) {
                        Log::warning('PushGroupToFederatedPartners: partner rejected group', [
                            'partner_id' => $partnerId,
                            'tenant_id'  => $tenantId,
                            'group_id'   => $group->id,
                            'error'      => $result['error'] ?? null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('PushGroupToFederatedPartners: partner push failed', [
                        'partner_id' => $partnerId,
                        'tenant_id'  => $tenantId,
                        'group_id'   => $group->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('PushGroupToFederatedPartners listener failed', [
                'tenant_id' => $tenantId ?? null,
                'group_id'  => $group->id ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
