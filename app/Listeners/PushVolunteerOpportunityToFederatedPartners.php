<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\VolunteerOpportunityCreated;
use App\Events\VolunteerOpportunityUpdated;
use App\Services\FederationExternalApiClient;
use App\Services\FederationExternalPartnerService;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * PushVolunteerOpportunityToFederatedPartners — broadcasts local volunteer
 * opportunities to external federation partners that have
 * `allow_volunteering = 1`.  Handles both create and update; `action` in the
 * payload disambiguates so remote partners can upsert.
 */
class PushVolunteerOpportunityToFederatedPartners implements ShouldQueue
{
    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    public function handle(VolunteerOpportunityCreated|VolunteerOpportunityUpdated $event): void
    {
        $action      = $event instanceof VolunteerOpportunityCreated ? 'created' : 'updated';
        $opportunity = $event->opportunity;
        $tenantId    = $event->tenantId;

        try {
            TenantContext::setById($tenantId);

            if (!TenantContext::hasFeature('federation')) {
                return;
            }
            if (!$this->federationFeatureService->isTenantFederationEnabled($tenantId)) {
                return;
            }

            // Only push opportunities that are explicitly marked for federation.
            // The vol_opportunities schema does not yet have a federated_visibility
            // column — check is_federated (if present) or fall back to is_active
            // as a minimum gate so inactive/draft opportunities are never pushed.
            // TODO: add `is_federated` tinyint column to vol_opportunities to allow
            //       per-opportunity opt-in (mirrors listings.federated_visibility).
            if (empty($opportunity->is_federated) && empty($opportunity->federated_visibility)) {
                // Fall back: only push active, open opportunities
                $isActive = !empty($opportunity->is_active);
                $isOpen   = ($opportunity->status ?? 'open') === 'open';
                if (!$isActive || !$isOpen) {
                    return;
                }
            }

            $partners = FederationExternalPartnerService::getActivePartnersWithFlag($tenantId, 'allow_volunteering');
            if (empty($partners)) {
                return;
            }

            $payload = [
                'action'          => $action,
                'id'              => $opportunity->id,
                'title'           => $opportunity->title ?? null,
                'description'     => $opportunity->description ?? null,
                'location'        => $opportunity->location ?? null,
                'skills_needed'   => $opportunity->skills_needed ?? null,
                'start_date'      => $opportunity->start_date ?? null,
                'end_date'        => $opportunity->end_date ?? null,
                'organization_id' => $opportunity->organization_id ?? null,
                'created_by'      => $opportunity->created_by ?? null,
                'tenant_id'       => $tenantId,
                'created_at'      => $opportunity->created_at?->toISOString(),
            ];

            foreach ($partners as $partner) {
                $partnerId = (int) ($partner['id'] ?? 0);
                if ($partnerId <= 0) {
                    continue;
                }
                try {
                    $result = FederationExternalApiClient::sendVolunteering($partnerId, $payload);

                    if (empty($result['success'])) {
                        Log::warning('PushVolunteerOpportunityToFederatedPartners: partner rejected', [
                            'partner_id'     => $partnerId,
                            'tenant_id'      => $tenantId,
                            'opportunity_id' => $opportunity->id,
                            'error'          => $result['error'] ?? null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('PushVolunteerOpportunityToFederatedPartners: partner push failed', [
                        'partner_id'     => $partnerId,
                        'tenant_id'      => $tenantId,
                        'opportunity_id' => $opportunity->id,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('PushVolunteerOpportunityToFederatedPartners listener failed', [
                'tenant_id'      => $tenantId ?? null,
                'opportunity_id' => $opportunity->id ?? null,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
