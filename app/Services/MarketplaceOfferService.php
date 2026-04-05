<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use Illuminate\Support\Facades\DB;

/**
 * MarketplaceOfferService — Offer/negotiation lifecycle for the marketplace module.
 *
 * Handles: create offer → accept / decline / counter → expiry.
 */
class MarketplaceOfferService
{
    /**
     * Create a new offer on a marketplace listing.
     */
    public static function create(int $buyerId, int $listingId, array $data): MarketplaceOffer
    {
        $listing = MarketplaceListing::findOrFail($listingId);

        // Prevent self-offers
        if ($listing->user_id === $buyerId) {
            throw new \InvalidArgumentException('Cannot make an offer on your own listing.');
        }

        // Check for existing active offer
        $existingOffer = MarketplaceOffer::where('marketplace_listing_id', $listingId)
            ->where('buyer_id', $buyerId)
            ->whereIn('status', ['pending', 'countered'])
            ->first();

        if ($existingOffer) {
            throw new \InvalidArgumentException('You already have an active offer on this listing.');
        }

        $offer = new MarketplaceOffer();
        $offer->tenant_id = TenantContext::getId();
        $offer->marketplace_listing_id = $listingId;
        $offer->buyer_id = $buyerId;
        $offer->seller_id = $listing->user_id;
        $offer->amount = $data['amount'];
        $offer->currency = $data['currency'] ?? $listing->price_currency ?? 'EUR';
        $offer->message = $data['message'] ?? null;
        $offer->status = 'pending';
        $offer->expires_at = now()->addDays(2); // 48h expiry
        $offer->save();

        // Increment contacts count on listing
        MarketplaceListing::where('id', $listingId)->increment('contacts_count');

        return $offer;
    }

    /**
     * Accept an offer.
     */
    public static function accept(MarketplaceOffer $offer, int $sellerId): MarketplaceOffer
    {
        self::assertSellerOwns($offer, $sellerId);
        self::assertOfferActionable($offer);

        $offer->status = 'accepted';
        $offer->accepted_at = now();
        $offer->save();

        // Mark listing as reserved
        MarketplaceListing::where('id', $offer->marketplace_listing_id)
            ->update(['status' => 'reserved']);

        // Decline all other pending offers on this listing
        MarketplaceOffer::where('marketplace_listing_id', $offer->marketplace_listing_id)
            ->where('id', '!=', $offer->id)
            ->whereIn('status', ['pending', 'countered'])
            ->update(['status' => 'declined']);

        return $offer;
    }

    /**
     * Decline an offer.
     */
    public static function decline(MarketplaceOffer $offer, int $sellerId): MarketplaceOffer
    {
        self::assertSellerOwns($offer, $sellerId);
        self::assertOfferActionable($offer);

        $offer->status = 'declined';
        $offer->save();

        return $offer;
    }

    /**
     * Counter an offer with a new amount.
     */
    public static function counter(MarketplaceOffer $offer, int $sellerId, array $data): MarketplaceOffer
    {
        self::assertSellerOwns($offer, $sellerId);
        self::assertOfferActionable($offer);

        $offer->status = 'countered';
        $offer->counter_amount = $data['amount'];
        $offer->counter_message = $data['message'] ?? null;
        $offer->expires_at = now()->addDays(2); // Reset expiry
        $offer->save();

        return $offer;
    }

    /**
     * Withdraw an offer (buyer action).
     */
    public static function withdraw(MarketplaceOffer $offer, int $buyerId): MarketplaceOffer
    {
        if ($offer->buyer_id !== $buyerId) {
            throw new \InvalidArgumentException('Only the buyer can withdraw their offer.');
        }
        self::assertOfferActionable($offer);

        $offer->status = 'withdrawn';
        $offer->save();

        return $offer;
    }

    /**
     * Accept a counter-offer (buyer action).
     */
    public static function acceptCounter(MarketplaceOffer $offer, int $buyerId): MarketplaceOffer
    {
        if ($offer->buyer_id !== $buyerId) {
            throw new \InvalidArgumentException('Only the buyer can accept a counter-offer.');
        }
        if ($offer->status !== 'countered') {
            throw new \InvalidArgumentException('This offer has not been countered.');
        }

        $offer->status = 'accepted';
        $offer->amount = $offer->counter_amount; // Final agreed price
        $offer->accepted_at = now();
        $offer->save();

        // Mark listing as reserved
        MarketplaceListing::where('id', $offer->marketplace_listing_id)
            ->update(['status' => 'reserved']);

        // Decline other offers
        MarketplaceOffer::where('marketplace_listing_id', $offer->marketplace_listing_id)
            ->where('id', '!=', $offer->id)
            ->whereIn('status', ['pending', 'countered'])
            ->update(['status' => 'declined']);

        return $offer;
    }

    // -----------------------------------------------------------------
    //  Read
    // -----------------------------------------------------------------

    /**
     * Get offers for a listing (seller view).
     */
    public static function getOffersForListing(int $listingId, int $sellerId): array
    {
        $offers = MarketplaceOffer::with('buyer:id,first_name,last_name,avatar_url')
            ->where('marketplace_listing_id', $listingId)
            ->where('seller_id', $sellerId)
            ->orderBy('id', 'desc')
            ->get();

        return $offers->map(fn ($o) => self::formatOffer($o))->all();
    }

    /**
     * Get buyer's sent offers.
     */
    public static function getSentOffers(int $buyerId, int $limit = 20, ?string $cursor = null): array
    {
        $query = MarketplaceOffer::with([
            'listing:id,title,price,price_currency,status',
            'listing.images' => fn ($q) => $q->where('is_primary', true)->limit(1),
            'seller:id,first_name,last_name,avatar_url',
        ])
            ->where('buyer_id', $buyerId)
            ->orderBy('id', 'desc');

        if ($cursor) {
            $query->where('id', '<', (int) base64_decode($cursor, true));
        }

        $offers = $query->limit($limit + 1)->get();
        $hasMore = $offers->count() > $limit;
        if ($hasMore) {
            $offers->pop();
        }

        return [
            'items' => $offers->map(fn ($o) => self::formatOffer($o))->all(),
            'cursor' => $hasMore && $offers->isNotEmpty() ? base64_encode((string) $offers->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get seller's received offers.
     */
    public static function getReceivedOffers(int $sellerId, int $limit = 20, ?string $cursor = null): array
    {
        $query = MarketplaceOffer::with([
            'listing:id,title,price,price_currency,status',
            'listing.images' => fn ($q) => $q->where('is_primary', true)->limit(1),
            'buyer:id,first_name,last_name,avatar_url',
        ])
            ->where('seller_id', $sellerId)
            ->orderBy('id', 'desc');

        if ($cursor) {
            $query->where('id', '<', (int) base64_decode($cursor, true));
        }

        $offers = $query->limit($limit + 1)->get();
        $hasMore = $offers->count() > $limit;
        if ($hasMore) {
            $offers->pop();
        }

        return [
            'items' => $offers->map(fn ($o) => self::formatOffer($o))->all(),
            'cursor' => $hasMore && $offers->isNotEmpty() ? base64_encode((string) $offers->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    // -----------------------------------------------------------------
    //  Expire stale offers (called from scheduler)
    // -----------------------------------------------------------------

    /**
     * Expire all offers past their expiry date.
     */
    public static function expireStaleOffers(): int
    {
        return MarketplaceOffer::whereIn('status', ['pending', 'countered'])
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    private static function assertSellerOwns(MarketplaceOffer $offer, int $sellerId): void
    {
        if ($offer->seller_id !== $sellerId) {
            throw new \InvalidArgumentException('You are not the seller for this offer.');
        }
    }

    private static function assertOfferActionable(MarketplaceOffer $offer): void
    {
        if (!in_array($offer->status, ['pending', 'countered'], true)) {
            throw new \InvalidArgumentException("Cannot act on an offer with status '{$offer->status}'.");
        }

        if ($offer->expires_at && $offer->expires_at < now()) {
            $offer->status = 'expired';
            $offer->save();
            throw new \InvalidArgumentException('This offer has expired.');
        }
    }

    private static function formatOffer(MarketplaceOffer $offer): array
    {
        $listing = $offer->relationLoaded('listing') ? $offer->listing : null;
        $primaryImage = $listing && $listing->relationLoaded('images')
            ? $listing->images->first()
            : null;

        return [
            'id' => $offer->id,
            'amount' => $offer->amount,
            'currency' => $offer->currency,
            'message' => $offer->message,
            'status' => $offer->status,
            'counter_amount' => $offer->counter_amount,
            'counter_message' => $offer->counter_message,
            'expires_at' => $offer->expires_at?->toISOString(),
            'accepted_at' => $offer->accepted_at?->toISOString(),
            'created_at' => $offer->created_at?->toISOString(),
            'listing' => $listing ? [
                'id' => $listing->id,
                'title' => $listing->title,
                'price' => $listing->price,
                'price_currency' => $listing->price_currency,
                'status' => $listing->status,
                'image' => $primaryImage ? [
                    'url' => $primaryImage->image_url,
                    'thumbnail_url' => $primaryImage->thumbnail_url,
                ] : null,
            ] : null,
            'buyer' => $offer->relationLoaded('buyer') && $offer->buyer ? [
                'id' => $offer->buyer->id,
                'name' => trim($offer->buyer->first_name . ' ' . $offer->buyer->last_name),
                'avatar_url' => $offer->buyer->avatar_url,
            ] : null,
            'seller' => $offer->relationLoaded('seller') && $offer->seller ? [
                'id' => $offer->seller->id,
                'name' => trim($offer->seller->first_name . ' ' . $offer->seller->last_name),
                'avatar_url' => $offer->seller->avatar_url,
            ] : null,
        ];
    }
}
