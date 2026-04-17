<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\ListingCreated;
use App\Events\ListingUpdated;
use App\Services\FederationExternalApiClient;
use App\Services\FederationExternalPartnerService;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * PushListingToFederatedPartners — broadcasts new local listings to active
 * external federation partners that have allow_listing_search=1.
 *
 * Runs asynchronously via the queue so local listing creation is never blocked
 * by outbound HTTP calls. The underlying FederationExternalApiClient handles
 * circuit breaking and retries, so failures here are logged but never rethrown.
 */
class PushListingToFederatedPartners implements ShouldQueue
{
    /** Route to the standard federation queue (bulk, non-time-critical). */
    public string $queue = 'federation';

    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    public function handle(ListingCreated|ListingUpdated $event): void
    {
        try {
            // Ensure tenant context is set for queued execution
            TenantContext::setById($event->tenantId);

            // 1. Tenant-level feature gate — CLAUDE.md mandates TenantContext::hasFeature
            if (!TenantContext::hasFeature('federation')) {
                return;
            }

            // 2. System-level + whitelist gate via FederationFeatureService
            if (!$this->federationFeatureService->isTenantFederationEnabled($event->tenantId)) {
                return;
            }

            // 3. Only share listings whose federated_visibility allows it
            $visibility = $event->listing->federated_visibility ?? 'local';
            if (!in_array($visibility, ['listed', 'bookable'], true)) {
                return;
            }

            $partners = FederationExternalPartnerService::getActivePartnersForListings($event->tenantId);
            if (empty($partners)) {
                return;
            }

            $payload = $this->buildPayload($event);

            foreach ($partners as $partner) {
                $partnerId = (int) ($partner['id'] ?? 0);
                if ($partnerId <= 0) {
                    continue;
                }

                try {
                    $result = FederationExternalApiClient::sendListing($partnerId, $payload);

                    if (empty($result['success'])) {
                        Log::warning('PushListingToFederatedPartners: partner rejected listing', [
                            'partner_id' => $partnerId,
                            'tenant_id'  => $event->tenantId,
                            'listing_id' => $event->listing->id,
                            'error'      => $result['error'] ?? null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Circuit breaker in client already handles retries.
                    // Catch here so a single bad partner doesn't skip the rest.
                    Log::warning('PushListingToFederatedPartners: partner push failed', [
                        'partner_id' => $partnerId,
                        'tenant_id'  => $event->tenantId,
                        'listing_id' => $event->listing->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('PushListingToFederatedPartners listener failed', [
                'tenant_id'  => $event->tenantId ?? null,
                'listing_id' => $event->listing->id ?? null,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build a listing payload for outbound federation push.
     * Adapter transforms this into the wire format per partner protocol.
     */
    private function buildPayload(ListingCreated|ListingUpdated $event): array
    {
        $listing = $event->listing;
        $action  = $event instanceof ListingCreated ? 'created' : 'updated';

        return [
            'action'         => $action,
            'id'             => $listing->id,
            'title'          => $listing->title,
            'description'    => $listing->description,
            'type'           => $listing->type ?? null,
            'category_id'    => $listing->category_id ?? null,
            'user_id'        => $event->user->id,
            'tenant_id'      => $event->tenantId,
            'created_at'     => $listing->created_at?->toISOString(),
            'updated_at'     => $listing->updated_at?->toISOString(),
            'visibility'     => $listing->federated_visibility ?? 'listed',
        ];
    }
}
