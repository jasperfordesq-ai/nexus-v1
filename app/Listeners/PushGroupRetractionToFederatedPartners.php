<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\GroupDeleted;
use App\Events\GroupMemberLeft;
use App\Services\FederationExternalApiClient;
use App\Services\FederationExternalPartnerService;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * PushGroupRetractionToFederatedPartners — notifies federation partners when a
 * group is deleted or a member leaves, so shadow tables stay in sync.
 *
 * Handles two events:
 *   - GroupDeleted:    pushes action='deleted' so partners can remove the shadow row
 *   - GroupMemberLeft: pushes action='member_left' so partners can decrement counts
 */
class PushGroupRetractionToFederatedPartners implements ShouldQueue
{
    /** Route to the standard federation queue (bulk, non-time-critical). */
    public string $queue = 'federation';

    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    /**
     * @param GroupDeleted|GroupMemberLeft $event
     */
    public function handle(GroupDeleted|GroupMemberLeft $event): void
    {
        $tenantId = $event->tenantId;

        try {
            TenantContext::setById($tenantId);

            if (!TenantContext::hasFeature('federation')) {
                return;
            }
            if (!$this->federationFeatureService->isTenantFederationEnabled($tenantId)) {
                return;
            }

            $partners = FederationExternalPartnerService::getActivePartnersWithFlag($tenantId, 'allow_groups');
            if (empty($partners)) {
                return;
            }

            if ($event instanceof GroupDeleted) {
                $payload = [
                    'action'    => 'deleted',
                    'id'        => $event->groupId,
                    'tenant_id' => $tenantId,
                    'name'      => $event->groupName,
                ];
            } else {
                // GroupMemberLeft
                $payload = [
                    'action'    => 'member_left',
                    'id'        => $event->groupId,
                    'tenant_id' => $tenantId,
                    'user_id'   => $event->userId,
                ];
            }

            foreach ($partners as $partner) {
                $partnerId = (int) ($partner['id'] ?? 0);
                if ($partnerId <= 0) {
                    continue;
                }
                try {
                    $result = FederationExternalApiClient::sendGroup($partnerId, $payload);

                    if (empty($result['success'])) {
                        Log::warning('PushGroupRetractionToFederatedPartners: partner rejected retraction', [
                            'partner_id' => $partnerId,
                            'tenant_id'  => $tenantId,
                            'group_id'   => $event instanceof GroupDeleted ? $event->groupId : $event->groupId,
                            'action'     => $payload['action'],
                            'error'      => $result['error'] ?? null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('PushGroupRetractionToFederatedPartners: partner push failed', [
                        'partner_id' => $partnerId,
                        'tenant_id'  => $tenantId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('PushGroupRetractionToFederatedPartners listener failed', [
                'tenant_id' => $tenantId ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
