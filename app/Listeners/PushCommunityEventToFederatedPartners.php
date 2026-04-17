<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\CommunityEventCreated;
use App\Events\CommunityEventUpdated;
use App\Services\FederationExternalApiClient;
use App\Services\FederationExternalPartnerService;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * PushCommunityEventToFederatedPartners — broadcasts local community events to
 * active external federation partners that have `allow_events = 1`.
 *
 * Handles both CommunityEventCreated and CommunityEventUpdated; `action` in
 * the payload disambiguates so remote partners can upsert correctly.
 */
class PushCommunityEventToFederatedPartners implements ShouldQueue
{
    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    public function handle(CommunityEventCreated|CommunityEventUpdated $event): void
    {
        $action = $event instanceof CommunityEventCreated ? 'created' : 'updated';
        $eventModel = $event->event;
        $tenantId   = $event->tenantId;

        try {
            TenantContext::setById($tenantId);

            if (!TenantContext::hasFeature('federation')) {
                return;
            }
            if (!$this->federationFeatureService->isTenantFederationEnabled($tenantId)) {
                return;
            }

            // Only share events whose federated_visibility allows it
            $visibility = $eventModel->federated_visibility ?? 'none';
            if (!in_array($visibility, ['listed', 'public'], true)) {
                return;
            }

            $partners = FederationExternalPartnerService::getActivePartnersWithFlag($tenantId, 'allow_events');
            if (empty($partners)) {
                return;
            }

            $payload = [
                'action'      => $action,
                'id'          => $eventModel->id,
                'title'       => $eventModel->title ?? null,
                'description' => $eventModel->description ?? null,
                'start_time'  => $eventModel->start_time?->toISOString() ?? $eventModel->start_time ?? null,
                'end_time'    => $eventModel->end_time?->toISOString() ?? $eventModel->end_time ?? null,
                'location'    => $eventModel->location ?? null,
                'latitude'    => $eventModel->latitude ?? null,
                'longitude'   => $eventModel->longitude ?? null,
                'is_online'   => (bool) ($eventModel->is_online ?? false),
                'user_id'     => $eventModel->user_id ?? null,
                'tenant_id'   => $tenantId,
                'visibility'  => $visibility,
                'created_at'  => $eventModel->created_at?->toISOString(),
            ];

            foreach ($partners as $partner) {
                $partnerId = (int) ($partner['id'] ?? 0);
                if ($partnerId <= 0) {
                    continue;
                }
                $this->pushToPartner($partnerId, $tenantId, (int) $eventModel->id, $payload);
            }
        } catch (\Throwable $e) {
            Log::error('PushCommunityEventToFederatedPartners listener failed', [
                'tenant_id' => $tenantId ?? null,
                'event_id'  => $eventModel->id ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function pushToPartner(int $partnerId, int $tenantId, int $eventId, array $payload): void
    {
        try {
            $result = FederationExternalApiClient::sendEvent($partnerId, $payload);

            if (empty($result['success'])) {
                Log::warning('PushCommunityEventToFederatedPartners: partner rejected event', [
                    'partner_id' => $partnerId,
                    'tenant_id'  => $tenantId,
                    'event_id'   => $eventId,
                    'error'      => $result['error'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('PushCommunityEventToFederatedPartners: partner push failed', [
                'partner_id' => $partnerId,
                'tenant_id'  => $tenantId,
                'event_id'   => $eventId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
