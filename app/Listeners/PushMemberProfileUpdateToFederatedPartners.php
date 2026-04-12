<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\MemberProfileUpdated;
use App\Models\FederatedIdentity;
use App\Services\FederationExternalApiClient;
use App\Services\FederationExternalPartnerService;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * PushMemberProfileUpdateToFederatedPartners — propagates profile changes for
 * federated members (users who have a `federated_identities` row) to each
 * partner they are linked with.  Only partners with `allow_member_sync = 1`
 * receive the update.  The payload only includes fields actually changed to
 * minimise wire size and avoid leaking unchanged data.
 *
 * Fields that are never safe to sync cross-network (password hashes, tokens,
 * internal flags) are filtered at the source: whatever `changedFields`
 * contains is what gets pushed, so upstream event dispatchers must only
 * include user-facing profile fields.
 */
class PushMemberProfileUpdateToFederatedPartners implements ShouldQueue
{
    /** Profile fields we are willing to broadcast to federation partners. */
    private const SYNCABLE_FIELDS = [
        'first_name', 'last_name', 'name',
        'bio', 'location', 'avatar_url',
        'organization_name', 'profile_type',
        'latitude', 'longitude', 'timezone', 'locale',
    ];

    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    public function handle(MemberProfileUpdated $event): void
    {
        $user     = $event->user;
        $tenantId = $event->tenantId;

        try {
            TenantContext::setById($tenantId);

            if (!TenantContext::hasFeature('federation')) {
                return;
            }
            if (!$this->federationFeatureService->isTenantFederationEnabled($tenantId)) {
                return;
            }

            // Filter the changed field list down to the syncable allowlist.
            $syncable = array_values(array_intersect($event->changedFields, self::SYNCABLE_FIELDS));
            if (empty($syncable)) {
                return;
            }

            $identities = FederatedIdentity::query()
                ->where('local_user_id', (int) $user->id)
                ->get();
            if ($identities->isEmpty()) {
                return;
            }

            // Partners that have opted-in to member sync for this tenant
            $allowed = FederationExternalPartnerService::getActivePartnersWithFlag($tenantId, 'allow_member_sync');
            $allowedPartnerIds = array_flip(array_map(fn ($p) => (int) $p['id'], $allowed));

            $changes = [];
            foreach ($syncable as $field) {
                $changes[$field] = $user->{$field} ?? null;
            }

            foreach ($identities as $identity) {
                $partnerId = (int) $identity->partner_id;
                if ($partnerId <= 0 || !isset($allowedPartnerIds[$partnerId])) {
                    continue;
                }

                $payload = [
                    'action'           => 'profile_updated',
                    'local_user_id'    => $user->id,
                    'external_user_id' => $identity->external_user_id,
                    'tenant_id'        => $tenantId,
                    'changed_fields'   => $syncable,
                    'profile'          => $changes,
                    'updated_at'       => now()->toISOString(),
                ];

                try {
                    if (method_exists(FederationExternalApiClient::class, 'sendMember')) {
                        $result = FederationExternalApiClient::sendMember($partnerId, $payload);
                    } else {
                        $adapter = FederationExternalApiClient::resolveAdapter($partnerId);
                        $endpoint = $adapter->mapEndpoint('members');
                        $result = FederationExternalApiClient::post($partnerId, $endpoint, $payload);
                    }

                    if (empty($result['success'])) {
                        Log::warning('PushMemberProfileUpdateToFederatedPartners: partner rejected', [
                            'partner_id' => $partnerId,
                            'tenant_id'  => $tenantId,
                            'user_id'    => $user->id,
                            'error'      => $result['error'] ?? null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('PushMemberProfileUpdateToFederatedPartners: partner push failed', [
                        'partner_id' => $partnerId,
                        'tenant_id'  => $tenantId,
                        'user_id'    => $user->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('PushMemberProfileUpdateToFederatedPartners listener failed', [
                'tenant_id' => $tenantId ?? null,
                'user_id'   => $user->id ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
