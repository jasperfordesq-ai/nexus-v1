<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReviewCreated;
use App\Models\FederatedIdentity;
use App\Models\Review;
use App\Services\FederationExternalApiClient;
use App\Services\Protocols\KomunitinAdapter;
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
 */
class PushReviewToFederatedPartner implements ShouldQueue
{
    public function handle(ReviewCreated $event): void
    {
        try {
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
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a single review to a single partner, preferring the Komunitin
     * adapter when the partner speaks that protocol, otherwise falling back
     * to a generic POST.
     */
    private function pushToPartner(Review $review, FederatedIdentity $identity): void
    {
        $partnerId = (int) $identity->partner_id;

        $payload = [
            'rating'                   => (int) $review->rating,
            'comment'                  => $review->comment,
            'transaction_id'           => $review->transaction_id,
            'federation_transaction_id' => $review->federation_transaction_id ?? null,
            'reviewer_id'              => $review->reviewer_id,
            'reviewer_tenant'          => $review->tenant_id,
            'receiver_external_id'     => $identity->external_user_id,
            'created_at'               => $review->created_at?->toIso8601String(),
        ];

        try {
            $adapter = FederationExternalApiClient::resolveAdapter($partnerId);

            // Prefer Komunitin's JSON:API review envelope when available.
            if ($adapter instanceof KomunitinAdapter) {
                $body = $adapter->sendReview($payload, $partnerId);
                $endpoint = $adapter->mapEndpoint('reviews');
                FederationExternalApiClient::post($partnerId, $endpoint, $body);
                return;
            }

            // Fallback for other protocols: generic POST /reviews with our payload.
            FederationExternalApiClient::post($partnerId, '/reviews', $payload);
        } catch (\Throwable $e) {
            Log::warning('Federated review push to partner failed', [
                'partner_id' => $partnerId,
                'review_id'  => $review->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
