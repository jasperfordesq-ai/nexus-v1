<?php
// Copyright Â© 2024â€“2026 Jasper Ford
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
 * PushVolunteerOpportunityToFederatedPartners â€” broadcasts local volunteer
 * opportunities to external federation partners that have
 * `allow_volunteering = 1`.  Handles both create and update; `action` in the
 * payload disambiguates so remote partners can upsert.
 */
class PushVolunteerOpportunityToFederatedPartners implements ShouldQueue
{
    /** Route to the standard federation queue (bulk, non-time-critical). */
    public string $queue = 'federation';

    /**
     * Run exactly once and cap runtime below the queue's retry_after (~90s) so a
     * long per-partner push can't still be in flight when the job becomes visible
     * again — which would re-broadcast every opportunity to every partner.
     */
    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    public function handle(VolunteerOpportunityCreated|VolunteerOpportunityUpdated $event): void
    {
        $action      = $event instanceof VolunteerOpportunityCreated ? 'created' : 'updated';
        $opportunity = $event->opportunity;
        $tenantId    = $event->tenantId;

        $previousTenantId = TenantContext::currentId();

        try {
            if (!TenantContext::setById($tenantId)) {
                Log::warning('PushVolunteerOpportunityToFederatedPartners: tenant not found, skipping', [
                    'tenant_id'      => $tenantId,
                    'opportunity_id' => $opportunity->id ?? null,
                ]);
                return;
            }

            if (!TenantContext::hasFeature('federation')) {
                return;
            }
            if (!$this->federationFeatureService->isTenantFederationEnabled($tenantId)) {
                return;
            }

            // (a) Only LOCAL rows may be exported. `is_federated` / `external_id`
            // mark rows IMPORTED from a partner (set by
            // IngestFederatedVolunteerOpportunity) — re-exporting them would
            // echo partner content back into the federation network.
            if (!empty($opportunity->is_federated) || !empty($opportunity->external_id)) {
                return;
            }

            // (b) Per-opportunity opt-in: only push when the owner explicitly
            // chose to share (mirrors listings.federated_visibility).
            if (($opportunity->federated_visibility ?? 'none') !== 'listed') {
                return;
            }

            // (c) A previously-shared opportunity that is now inactive or closed
            // must be RETRACTED from partners (action 'deleted'), not silently left
            // stale forever. deleteOpportunity() dispatches an update event with
            // is_active=0 for exactly this purpose. A newly-CREATED inactive/closed
            // opportunity was never shared, so there is nothing to retract — skip.
            $isActive = !empty($opportunity->is_active);
            $isOpen   = in_array((string) ($opportunity->status ?? 'open'), ['open', 'active'], true);
            if (!$isActive || !$isOpen) {
                if ($action === 'created') {
                    return;
                }
                $action = 'deleted';
            }

            $partners = FederationExternalPartnerService::getActivePartnersWithFlag($tenantId, 'allow_volunteering');
            if (empty($partners)) {
                return;
            }

            $payload = [
                'action'          => $action,
                'id'              => $opportunity->id,
                // The sender's opportunity id is the partner's external reference —
                // send it explicitly so create/update/delete all key on the same
                // value on the receiving side.
                'external_id'     => (string) ($opportunity->id ?? ''),
                'title'           => $opportunity->title ?? null,
                'description'     => $opportunity->description ?? null,
                'location'        => $opportunity->location ?? null,
                'skills_needed'   => $opportunity->skills_needed ?? null,
                'start_date'      => $opportunity->start_date ?? null,
                'end_date'        => $opportunity->end_date ?? null,
                'organization_id' => $opportunity->organization_id ?? null,
                // Deliberately NOT sent: the sender's local created_by (user id)
                // and tenant_id. No outbound adapter (Nexus/TimeOverflow/Komunitin/
                // CreditCommons) maps them and no inbound consumer
                // (FederationExternalWebhookController::handleInboundVolunteering →
                // IngestFederatedVolunteerOpportunity) reads them — the receiver
                // keys on (external_partner_id, external_id) and assigns its own
                // tenant. Sending them only leaked internal ids to partners.
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
        } finally {
            TenantContext::restoreAfterScopedListener($previousTenantId);
        }
    }
}
