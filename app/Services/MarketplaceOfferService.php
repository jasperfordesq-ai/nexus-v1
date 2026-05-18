<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $listing = MarketplaceListing::withoutGlobalScopes()->findOrFail($listingId);
        $tenantId = (int) $listing->tenant_id;

        return TenantContext::runForTenant($tenantId, function () use ($buyerId, $listingId, $data, $listing, $tenantId): MarketplaceOffer {
            // Prevent self-offers
            if ($listing->user_id === $buyerId) {
                throw new \InvalidArgumentException('Cannot make an offer on your own listing.');
            }

            // Check for existing active offer
            $existingOffer = MarketplaceOffer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('marketplace_listing_id', $listingId)
                ->where('buyer_id', $buyerId)
                ->whereIn('status', ['pending', 'countered'])
                ->first();

            if ($existingOffer) {
                throw new \InvalidArgumentException('You already have an active offer on this listing.');
            }

            $offer = new MarketplaceOffer();
            $offer->tenant_id = $tenantId;
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
            MarketplaceListing::withoutGlobalScopes()
                ->where('id', $listingId)
                ->where('tenant_id', $tenantId)
                ->increment('contacts_count');

            // Notify seller of the new offer
            try {
                $buyerName = self::userName($buyerId);
                $amount    = number_format((float) $offer->amount, 2) . ' ' . $offer->currency;
                self::sendOfferEmail(
                    (int) $listing->user_id,
                    'emails_misc.marketplace_offer.received_subject',
                    'emails_misc.marketplace_offer.received_title',
                    'emails_misc.marketplace_offer.received_body',
                    ['title' => $listing->title, 'buyer' => $buyerName, 'amount' => $amount],
                    ['title' => $listing->title],
                    '/marketplace/listings/' . $listingId,
                    'emails_misc.marketplace_offer.received_cta'
                );
            } catch (\Throwable $e) {
                Log::warning('[MarketplaceOfferService] create email failed: ' . $e->getMessage());
            }

            // In-app bell to seller
            Notification::create([
                'tenant_id'   => $tenantId,
                'user_id'     => $listing->user_id,
                'message'     => __('api_controllers_3.marketplace_offer.received', ['buyer' => self::userName($buyerId), 'amount' => number_format((float) $offer->amount, 2) . ' ' . $offer->currency, 'title' => $listing->title]),
                'link'        => '/marketplace/listings/' . $listingId,
                'type'        => 'marketplace_offer',
                'created_at'  => now(),
            ]);

            return $offer;
        });
    }

    /**
     * Accept an offer.
     */
    public static function accept(MarketplaceOffer $offer, int $sellerId): MarketplaceOffer
    {
        return self::withOfferTenant($offer, function () use ($offer, $sellerId): MarketplaceOffer {
            self::assertSellerOwns($offer, $sellerId);
            self::assertOfferActionable($offer);

            $tenantId = (int) $offer->tenant_id;
            $offer->status = 'accepted';
            $offer->accepted_at = now();
            $offer->save();

            // Mark listing as reserved
            MarketplaceListing::withoutGlobalScopes()
                ->where('id', $offer->marketplace_listing_id)
                ->where('tenant_id', $tenantId)
                ->update(['status' => 'reserved']);

            // Decline all other pending offers on this listing
            MarketplaceOffer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('marketplace_listing_id', $offer->marketplace_listing_id)
                ->where('id', '!=', $offer->id)
                ->whereIn('status', ['pending', 'countered'])
                ->update(['status' => 'declined']);

            // Notify buyer their offer was accepted
            try {
                $listing = self::listingForOffer($offer);
                $amount  = number_format((float) $offer->amount, 2) . ' ' . $offer->currency;
                self::sendOfferEmail(
                    (int) $offer->buyer_id,
                    'emails_misc.marketplace_offer.accepted_subject',
                    'emails_misc.marketplace_offer.accepted_title',
                    'emails_misc.marketplace_offer.accepted_body',
                    ['title' => $listing->title ?? '', 'amount' => $amount],
                    ['title' => $listing->title ?? ''],
                    '/marketplace/orders',
                    'emails_misc.marketplace_offer.accepted_cta'
                );
            } catch (\Throwable $e) {
                Log::warning('[MarketplaceOfferService] accept email failed: ' . $e->getMessage());
            }

            // In-app bell to buyer
            $listing ??= self::listingForOffer($offer);
            Notification::create([
                'tenant_id'   => $tenantId,
                'user_id'     => $offer->buyer_id,
                'message'     => __('api_controllers_3.marketplace_offer.accepted', ['title' => $listing->title ?? '']),
                'link'        => '/marketplace/orders',
                'type'        => 'marketplace_offer',
                'created_at'  => now(),
            ]);

            return $offer;
        });
    }

    /**
     * Decline an offer.
     */
    public static function decline(MarketplaceOffer $offer, int $sellerId): MarketplaceOffer
    {
        return self::withOfferTenant($offer, function () use ($offer, $sellerId): MarketplaceOffer {
            self::assertSellerOwns($offer, $sellerId);
            self::assertOfferActionable($offer);

            $tenantId = (int) $offer->tenant_id;
            $offer->status = 'declined';
            $offer->save();

            // Notify buyer their offer was declined
            try {
                $listing = self::listingForOffer($offer);
                self::sendOfferEmail(
                    (int) $offer->buyer_id,
                    'emails_misc.marketplace_offer.declined_subject',
                    'emails_misc.marketplace_offer.declined_title',
                    'emails_misc.marketplace_offer.declined_body',
                    ['title' => $listing->title ?? ''],
                    ['title' => $listing->title ?? ''],
                    '/marketplace/listings/' . $offer->marketplace_listing_id,
                    'emails_misc.marketplace_offer.declined_cta'
                );
            } catch (\Throwable $e) {
                Log::warning('[MarketplaceOfferService] decline email failed: ' . $e->getMessage());
            }

            // In-app bell to buyer
            $listing ??= self::listingForOffer($offer);
            Notification::create([
                'tenant_id'   => $tenantId,
                'user_id'     => $offer->buyer_id,
                'message'     => __('api_controllers_3.marketplace_offer.declined', ['title' => $listing->title ?? '']),
                'link'        => '/marketplace/listings/' . $offer->marketplace_listing_id,
                'type'        => 'marketplace_offer',
                'created_at'  => now(),
            ]);

            return $offer;
        });
    }

    /**
     * Counter an offer with a new amount.
     */
    public static function counter(MarketplaceOffer $offer, int $sellerId, array $data): MarketplaceOffer
    {
        return self::withOfferTenant($offer, function () use ($offer, $sellerId, $data): MarketplaceOffer {
            self::assertSellerOwns($offer, $sellerId);
            self::assertOfferActionable($offer);

            $tenantId = (int) $offer->tenant_id;
            $offer->status = 'countered';
            $offer->counter_amount = $data['amount'];
            $offer->counter_message = $data['message'] ?? null;
            $offer->expires_at = now()->addDays(2); // Reset expiry
            $offer->save();

            // Notify buyer about the counter-offer
            try {
                $listing = self::listingForOffer($offer);
                $amount  = number_format((float) $offer->counter_amount, 2) . ' ' . $offer->currency;
                self::sendOfferEmail(
                    (int) $offer->buyer_id,
                    'emails_misc.marketplace_offer.countered_subject',
                    'emails_misc.marketplace_offer.countered_title',
                    'emails_misc.marketplace_offer.countered_body',
                    ['title' => $listing->title ?? '', 'amount' => $amount],
                    ['title' => $listing->title ?? ''],
                    '/marketplace/offers',
                    'emails_misc.marketplace_offer.countered_cta'
                );
            } catch (\Throwable $e) {
                Log::warning('[MarketplaceOfferService] counter email failed: ' . $e->getMessage());
            }

            // In-app bell to buyer
            $listing ??= self::listingForOffer($offer);
            $counterAmount = number_format((float) $offer->counter_amount, 2) . ' ' . $offer->currency;
            Notification::create([
                'tenant_id'   => $tenantId,
                'user_id'     => $offer->buyer_id,
                'message'     => __('api_controllers_3.marketplace_offer.countered', ['title' => $listing->title ?? '', 'amount' => $counterAmount]),
                'link'        => '/marketplace/offers',
                'type'        => 'marketplace_offer',
                'created_at'  => now(),
            ]);

            return $offer;
        });
    }

    /**
     * Withdraw an offer (buyer action).
     */
    public static function withdraw(MarketplaceOffer $offer, int $buyerId): MarketplaceOffer
    {
        return self::withOfferTenant($offer, function () use ($offer, $buyerId): MarketplaceOffer {
            if ($offer->buyer_id !== $buyerId) {
                throw new \InvalidArgumentException('Only the buyer can withdraw their offer.');
            }
            self::assertOfferActionable($offer);

            $tenantId = (int) $offer->tenant_id;
            $offer->status = 'withdrawn';
            $offer->save();

            // In-app bell to seller
            $listing = self::listingForOffer($offer);
            Notification::create([
                'tenant_id'   => $tenantId,
                'user_id'     => $offer->seller_id,
                'message'     => __('api_controllers_3.marketplace_offer.withdrawn', ['buyer' => self::userName($buyerId), 'title' => $listing->title ?? '']),
                'link'        => '/marketplace/listings/' . $offer->marketplace_listing_id,
                'type'        => 'marketplace_offer',
                'created_at'  => now(),
            ]);

            return $offer;
        });
    }

    /**
     * Accept a counter-offer (buyer action).
     */
    public static function acceptCounter(MarketplaceOffer $offer, int $buyerId): MarketplaceOffer
    {
        return self::withOfferTenant($offer, function () use ($offer, $buyerId): MarketplaceOffer {
            if ($offer->buyer_id !== $buyerId) {
                throw new \InvalidArgumentException('Only the buyer can accept a counter-offer.');
            }
            if ($offer->status !== 'countered') {
                throw new \InvalidArgumentException('This offer has not been countered.');
            }

            $tenantId = (int) $offer->tenant_id;
            $offer->status = 'accepted';
            $offer->amount = $offer->counter_amount; // Final agreed price
            $offer->accepted_at = now();
            $offer->save();

            // Mark listing as reserved
            MarketplaceListing::withoutGlobalScopes()
                ->where('id', $offer->marketplace_listing_id)
                ->where('tenant_id', $tenantId)
                ->update(['status' => 'reserved']);

            // Decline other offers
            MarketplaceOffer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('marketplace_listing_id', $offer->marketplace_listing_id)
                ->where('id', '!=', $offer->id)
                ->whereIn('status', ['pending', 'countered'])
                ->update(['status' => 'declined']);

            // Notify seller their counter-offer was accepted
            try {
                $listing = self::listingForOffer($offer);
                $amount  = number_format((float) $offer->amount, 2) . ' ' . $offer->currency;
                self::sendOfferEmail(
                    (int) $offer->seller_id,
                    'emails_misc.marketplace_offer.counter_accepted_subject',
                    'emails_misc.marketplace_offer.counter_accepted_title',
                    'emails_misc.marketplace_offer.counter_accepted_body',
                    ['title' => $listing->title ?? '', 'amount' => $amount],
                    ['title' => $listing->title ?? ''],
                    '/marketplace/orders',
                    'emails_misc.marketplace_offer.counter_accepted_cta'
                );
            } catch (\Throwable $e) {
                Log::warning('[MarketplaceOfferService] acceptCounter email failed: ' . $e->getMessage());
            }

            // In-app bell to seller
            $listing ??= self::listingForOffer($offer);
            Notification::create([
                'tenant_id'   => $tenantId,
                'user_id'     => $offer->seller_id,
                'message'     => __('api_controllers_3.marketplace_offer.counter_accepted', ['title' => $listing->title ?? '']),
                'link'        => '/marketplace/orders',
                'type'        => 'marketplace_offer',
                'created_at'  => now(),
            ]);

            return $offer;
        });
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
        return MarketplaceOffer::withoutGlobalScopes()
            ->whereIn('status', ['pending', 'countered'])
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    // -----------------------------------------------------------------
    //  Email helpers
    // -----------------------------------------------------------------

    /**
     * Send a marketplace offer email to a user.
     *
     * @param int    $userId       Recipient user ID
     * @param string $subjectKey   Translation key for email subject
     * @param string $titleKey     Translation key for email title
     * @param string $bodyKey      Translation key for email body paragraph
     * @param array  $bodyParams   Body translation params
     * @param array  $subjectParams Subject translation params (subset of bodyParams)
     * @param string $link         Relative path for CTA button
     * @param string $ctaKey       Translation key for CTA button text
     */
    private static function sendOfferEmail(int $userId, string $subjectKey, string $titleKey, string $bodyKey, array $bodyParams, array $subjectParams, string $link, string $ctaKey): void
    {
        $tenantId = TenantContext::getId();
        $user     = DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name', 'preferred_language'])->first();

        if (!$user || empty($user->email)) {
            return;
        }

        $fullUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $link;

        LocaleContext::withLocale($user, function () use ($user, $subjectKey, $subjectParams, $titleKey, $bodyKey, $bodyParams, $ctaKey, $fullUrl, $userId, $tenantId): void {
            $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');

            $html = EmailTemplateBuilder::make()
                ->title(__($titleKey))
                ->greeting($firstName)
                ->paragraph(__($bodyKey, $bodyParams))
                ->button(__($ctaKey), $fullUrl)
                ->render();

            if (!\App\Services\EmailDispatchService::sendRaw($user->email, __($subjectKey, $subjectParams), $html, null, null, null, 'marketplace_offer', ['tenant_id' => $tenantId])) {
                Log::warning('[MarketplaceOfferService] email failed', ['user_id' => $userId, 'subject_key' => $subjectKey]);
            }
        });
    }

    private static function userName(int $userId): string
    {
        $tenantId = TenantContext::getId();
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->select(['first_name', 'last_name', 'name'])
            ->first();
        if (!$user) {
            return __('emails.common.fallback_member_name');
        }
        $full = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        return $full ?: ($user->name ?? __('emails.common.fallback_member_name'));
    }

    private static function withOfferTenant(MarketplaceOffer $offer, callable $callback): mixed
    {
        $tenantId = (int) ($offer->tenant_id ?: TenantContext::getId());

        return TenantContext::runForTenant($tenantId, $callback);
    }

    private static function listingForOffer(MarketplaceOffer $offer): ?MarketplaceListing
    {
        return MarketplaceListing::withoutGlobalScopes()
            ->where('id', $offer->marketplace_listing_id)
            ->where('tenant_id', (int) $offer->tenant_id)
            ->first();
    }

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
