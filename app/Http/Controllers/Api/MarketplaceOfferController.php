<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Services\MarketplaceOfferService;
use Illuminate\Http\JsonResponse;

/**
 * MarketplaceOfferController — Offer/negotiation lifecycle for the marketplace module.
 *
 * Endpoints (v2):
 *   POST   /v2/marketplace/listings/{id}/offers       store()       — make offer (auth)
 *   GET    /v2/marketplace/listings/{id}/offers       listForListing() — offers on a listing (auth, seller)
 *   PUT    /v2/marketplace/offers/{id}/accept          accept()      — accept offer (auth, seller)
 *   PUT    /v2/marketplace/offers/{id}/decline         decline()     — decline offer (auth, seller)
 *   PUT    /v2/marketplace/offers/{id}/counter         counter()     — counter-offer (auth, seller)
 *   PUT    /v2/marketplace/offers/{id}/accept-counter  acceptCounter() — accept counter (auth, buyer)
 *   DELETE /v2/marketplace/offers/{id}                 withdraw()    — withdraw offer (auth, buyer)
 *   GET    /v2/marketplace/my-offers/sent              sentOffers()  — buyer's sent offers (auth)
 *   GET    /v2/marketplace/my-offers/received          receivedOffers() — seller's received offers (auth)
 */
class MarketplaceOfferController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * Ensure the marketplace feature is enabled for the current tenant.
     */
    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('marketplace')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'The marketplace feature is not enabled for this community.', null, 403)
            );
        }
    }

    // -----------------------------------------------------------------
    //  POST /v2/marketplace/listings/{id}/offers
    // -----------------------------------------------------------------

    /**
     * Create a new offer on a marketplace listing.
     */
    public function store(int $listingId): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_offer_create', 30, 60);

        $userId = $this->requireAuth();

        $validated = request()->validate([
            'amount' => 'required|numeric|min:0.01',
            'message' => 'nullable|string|max:500',
        ]);

        try {
            $offer = MarketplaceOfferService::create($userId, $listingId, $validated);

            return $this->respondWithData($offer->toArray(), null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    // -----------------------------------------------------------------
    //  GET /v2/marketplace/listings/{id}/offers
    // -----------------------------------------------------------------

    /**
     * List offers on a listing (seller only).
     */
    public function listForListing(int $listingId): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_offer_list', 60, 60);

        $userId = $this->requireAuth();

        // Verify the authenticated user owns the listing
        $listing = MarketplaceListing::findOrFail($listingId);
        if ($listing->user_id !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'You do not own this listing.', null, 403);
        }

        $offers = MarketplaceOfferService::getOffersForListing($listingId, $userId);

        return $this->respondWithData($offers);
    }

    // -----------------------------------------------------------------
    //  PUT /v2/marketplace/offers/{id}/accept
    // -----------------------------------------------------------------

    /**
     * Accept an offer (seller action).
     */
    public function accept(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_offer_action', 30, 60);

        $userId = $this->requireAuth();
        $offer = MarketplaceOffer::findOrFail($id);

        if ($offer->seller_id !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'Only the seller can accept this offer.', null, 403);
        }

        try {
            $offer = MarketplaceOfferService::accept($offer, $userId);

            return $this->respondWithData($offer->toArray());
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    // -----------------------------------------------------------------
    //  PUT /v2/marketplace/offers/{id}/decline
    // -----------------------------------------------------------------

    /**
     * Decline an offer (seller action).
     */
    public function decline(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_offer_action', 30, 60);

        $userId = $this->requireAuth();
        $offer = MarketplaceOffer::findOrFail($id);

        if ($offer->seller_id !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'Only the seller can decline this offer.', null, 403);
        }

        try {
            $offer = MarketplaceOfferService::decline($offer, $userId);

            return $this->respondWithData($offer->toArray());
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    // -----------------------------------------------------------------
    //  PUT /v2/marketplace/offers/{id}/counter
    // -----------------------------------------------------------------

    /**
     * Counter an offer with a new amount (seller action).
     */
    public function counter(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_offer_action', 30, 60);

        $userId = $this->requireAuth();
        $offer = MarketplaceOffer::findOrFail($id);

        if ($offer->seller_id !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'Only the seller can counter this offer.', null, 403);
        }

        $validated = request()->validate([
            'amount' => 'required|numeric|min:0.01',
            'message' => 'nullable|string|max:500',
        ]);

        try {
            $offer = MarketplaceOfferService::counter($offer, $userId, $validated);

            return $this->respondWithData($offer->toArray());
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    // -----------------------------------------------------------------
    //  PUT /v2/marketplace/offers/{id}/accept-counter
    // -----------------------------------------------------------------

    /**
     * Accept a counter-offer (buyer action).
     */
    public function acceptCounter(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_offer_action', 30, 60);

        $userId = $this->requireAuth();
        $offer = MarketplaceOffer::findOrFail($id);

        if ($offer->buyer_id !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'Only the buyer can accept a counter-offer.', null, 403);
        }

        try {
            $offer = MarketplaceOfferService::acceptCounter($offer, $userId);

            return $this->respondWithData($offer->toArray());
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    // -----------------------------------------------------------------
    //  DELETE /v2/marketplace/offers/{id}
    // -----------------------------------------------------------------

    /**
     * Withdraw an offer (buyer action).
     */
    public function withdraw(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_offer_action', 30, 60);

        $userId = $this->requireAuth();
        $offer = MarketplaceOffer::findOrFail($id);

        if ($offer->buyer_id !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'Only the buyer can withdraw their offer.', null, 403);
        }

        try {
            MarketplaceOfferService::withdraw($offer, $userId);

            return $this->respondWithData(['message' => 'Offer withdrawn successfully.']);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    // -----------------------------------------------------------------
    //  GET /v2/marketplace/my-offers/sent
    // -----------------------------------------------------------------

    /**
     * List the authenticated buyer's sent offers.
     */
    public function sentOffers(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_offer_list', 60, 60);

        $userId = $this->requireAuth();

        $limit = $this->queryInt('per_page', 20, 1, 100);
        $cursor = $this->query('cursor');

        $result = MarketplaceOfferService::getSentOffers($userId, $limit, $cursor);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $limit,
            $result['has_more'] ?? false
        );
    }

    // -----------------------------------------------------------------
    //  GET /v2/marketplace/my-offers/received
    // -----------------------------------------------------------------

    /**
     * List the authenticated seller's received offers.
     */
    public function receivedOffers(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_offer_list', 60, 60);

        $userId = $this->requireAuth();

        $limit = $this->queryInt('per_page', 20, 1, 100);
        $cursor = $this->query('cursor');

        $result = MarketplaceOfferService::getReceivedOffers($userId, $limit, $cursor);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $limit,
            $result['has_more'] ?? false
        );
    }
}
