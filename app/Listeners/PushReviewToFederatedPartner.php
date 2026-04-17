<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\ReviewCreated;
use App\Models\FederatedIdentity;
use App\Models\Review;
use App\Services\FederationExternalApiClient;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * PushReviewToFederatedPartner
 *
 * When a review is created locally and the receiver is a user who has a
 * `federated_identities` row (i.e. they came from an external federation
 * partner), push the review to that partner's API so reputation follows them.
 *
 * Local-only reviews (receiver has no federated identity) are a no-op — the
 * existing local review row is all that's needed.
 *
 * Feature-gated the same way as the other federation push listeners:
 *   1. Tenant-level `federation` feature flag
 *   2. System-level + whitelist gate via FederationFeatureService
 */
class PushReviewToFederatedPartner implements ShouldQueue
{
    /** Route to the standard federation queue (bulk, non-time-critical). */
    public string $queue = 'federation';

    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    public function handle(ReviewCreated $event): void
    {
        try {
            // Restore tenant context for the queued worker (no tenant is set
            // on queue boot — all DB reads below must be scoped correctly).
            if ($event->tenantId > 0) {
                TenantContext::setById($event->tenantId);
            }

            // 1. Tenant-level feature gate
            if (! TenantContext::hasFeature('federation')) {
                return;
            }

            // 2. System-level + whitelist gate via FederationFeatureService
            if ($event->tenantId > 0
                && ! $this->federationFeatureService->isTenantFederationEnabled($event->tenantId)
            ) {
                return;
            }

            $review = $event->review;

            if (! $review instanceof Review) {
                return;
            }

            $receiverId = (int) ($review->receiver_id ?? 0);
            if ($receiverId <= 0) {
                return;
            }

            // Look up ANY federated identity rows for the receiver. A user
            // may be federated to multiple partners — push to each so the
            // review lands wherever they have a reputation presence.
            $identities = FederatedIdentity::query()
                ->where('local_user_id', $receiverId)
                ->get();

            if ($identities->isEmpty()) {
                return; // Pure-local review, nothing to push.
            }

            foreach ($identities as $identity) {
                $this->pushToPartner($review, $identity);
            }
        } catch (\Throwable $e) {
            Log::error('PushReviewToFederatedPartner failed', [
                'review_id' => $event->review->id ?? null,
                'tenant_id' => $event->tenantId ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a single review to a single partner via the adapter-aware
     * FederationExternalApiClient::sendReview() helper, which resolves the
     * protocol adapter, runs transformOutboundReview(), and POSTs to the
     * protocol-specific endpoint from mapEndpoint('reviews').
     */
    private function pushToPartner(Review $review, FederatedIdentity $identity): void
    {
        $partnerId = (int) $identity->partner_id;

        $payload = [
            'rating'                    => (int) $review->rating,
            'comment'                   => $review->comment,
            'transaction_id'            => $review->transaction_id,
            'federation_transaction_id' => $review->federation_transaction_id ?? null,
            'reviewer_id'               => $review->reviewer_id,
            'reviewer_tenant'           => $review->tenant_id,
            'receiver_external_id'      => $identity->external_user_id,
            'external_id'               => (string) $review->id,
            'created_at'                => $review->created_at?->toIso8601String(),
        ];

        try {
            FederationExternalApiClient::sendReview($partnerId, $payload);
        } catch (\Throwable $e) {
            Log::warning('Federated review push to partner failed', [
                'partner_id' => $partnerId,
                'review_id'  => $review->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
