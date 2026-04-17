<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\GroupMemberJoined;
use App\Models\Group;
use App\Services\FederationExternalApiClient;
use App\Services\FederationExternalPartnerService;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * PushGroupMembershipToFederatedPartners — broadcasts group join events to
 * external federation partners that have `allow_groups = 1`.
 */
class PushGroupMembershipToFederatedPartners implements ShouldQueue
{
    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    public function handle(GroupMemberJoined $event): void
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

            // Only push membership events for groups that are federated.
            // Load the group to check its federated_visibility — matches the
            // gate used in PushGroupToFederatedPartners.
            $group = Group::find($event->groupId);
            if (!$group) {
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
                'action'    => 'member_joined',
                'group_id'  => $event->groupId,
                'user_id'   => $event->userId,
                'tenant_id' => $tenantId,
                'joined_at' => now()->toISOString(),
            ];

            foreach ($partners as $partner) {
                $partnerId = (int) ($partner['id'] ?? 0);
                if ($partnerId <= 0) {
                    continue;
                }
                try {
                    $result = FederationExternalApiClient::sendGroup($partnerId, $payload);

                    if (empty($result['success'])) {
                        Log::warning('PushGroupMembershipToFederatedPartners: partner rejected membership', [
                            'partner_id' => $partnerId,
                            'tenant_id'  => $tenantId,
                            'group_id'   => $event->groupId,
                            'user_id'    => $event->userId,
                            'error'      => $result['error'] ?? null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('PushGroupMembershipToFederatedPartners: partner push failed', [
                        'partner_id' => $partnerId,
                        'tenant_id'  => $tenantId,
                        'group_id'   => $event->groupId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('PushGroupMembershipToFederatedPartners listener failed', [
                'tenant_id' => $tenantId ?? null,
                'group_id'  => $event->groupId ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
