<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Models\MarketplaceDispute;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceSellerProfile;
use App\Models\MarketplaceSellerRating;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MarketplaceRatingService — Mutual ratings and dispute management.
 *
 * Handles: rate order → refresh seller stats, open/get disputes.
 */
class MarketplaceRatingService
{
    // -----------------------------------------------------------------
    //  Ratings
    // -----------------------------------------------------------------

    /**
     * Rate a completed order.
     *
     * Validates: order exists, user participated, order is completed,
     * and user hasn't already rated for this role.
     */
    public static function rateOrder(int $orderId, int $raterId, string $role, array $data): MarketplaceSellerRating
    {
        if (!in_array($role, ['buyer', 'seller'], true)) {
            throw new \InvalidArgumentException('Role must be buyer or seller.');
        }

        $order = MarketplaceOrder::findOrFail($orderId);

        if ($order->status !== 'completed') {
            throw new \InvalidArgumentException('Can only rate completed orders.');
        }

        // Verify the rater participated in this order with the claimed role
        if ($role === 'buyer' && $order->buyer_id !== $raterId) {
            throw new \InvalidArgumentException('You are not the buyer of this order.');
        }
        if ($role === 'seller' && $order->seller_id !== $raterId) {
            throw new \InvalidArgumentException('You are not the seller of this order.');
        }

        // Determine the ratee
        $rateeId = ($role === 'buyer') ? $order->seller_id : $order->buyer_id;

        // Check for existing rating
        $existingRating = MarketplaceSellerRating::where('order_id', $orderId)
            ->where('rater_role', $role)
            ->first();

        if ($existingRating) {
            throw new \InvalidArgumentException('You have already rated this order.');
        }

        $rating = (int) ($data['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Rating must be between 1 and 5.');
        }

        $sellerRating = new MarketplaceSellerRating();
        $sellerRating->tenant_id = TenantContext::getId();
        $sellerRating->order_id = $orderId;
        $sellerRating->rater_id = $raterId;
        $sellerRating->ratee_id = $rateeId;
        $sellerRating->rater_role = $role;
        $sellerRating->rating = $rating;
        $sellerRating->comment = $data['comment'] ?? null;
        $sellerRating->is_anonymous = (bool) ($data['is_anonymous'] ?? false);
        $sellerRating->save();

        // Refresh cached seller stats when a buyer rates a seller
        if ($role === 'buyer') {
            self::refreshSellerStats($rateeId);
        }

        // Notify ratee they received a rating (skip if anonymous)
        if (!$sellerRating->is_anonymous) {
            try {
                $link = '/marketplace/orders/' . $orderId;
                self::sendRatingEmail(
                    $rateeId,
                    __('emails_misc.marketplace_rating.received_subject'),
                    __('emails_misc.marketplace_rating.received_title'),
                    __('emails_misc.marketplace_rating.received_body', [
                        'rating'       => $rating,
                        'order_number' => $order->order_number,
                    ]),
                    $link
                );
            } catch (\Throwable $e) {
                Log::warning('[MarketplaceRatingService] rateOrder email failed: ' . $e->getMessage());
            }
        }

        return $sellerRating;
    }

    /**
     * Get all ratings for a specific order.
     */
    public static function getOrderRatings(int $orderId): array
    {
        $ratings = MarketplaceSellerRating::with('rater:id,first_name,last_name,avatar_url')
            ->where('order_id', $orderId)
            ->get();

        return $ratings->map(fn ($r) => self::formatRating($r))->all();
    }

    /**
     * Get paginated ratings received by a seller (from buyers).
     */
    public static function getSellerRatings(int $userId, int $limit = 20, ?string $cursor = null): array
    {
        $query = MarketplaceSellerRating::with([
            'rater:id,first_name,last_name,avatar_url',
            'order:id,order_number,marketplace_listing_id',
            'order.listing:id,title',
        ])
            ->where('ratee_id', $userId)
            ->where('rater_role', 'buyer')
            ->orderBy('id', 'desc');

        if ($cursor) {
            $query->where('id', '<', (int) base64_decode($cursor, true));
        }

        $ratings = $query->limit($limit + 1)->get();
        $hasMore = $ratings->count() > $limit;
        if ($hasMore) {
            $ratings->pop();
        }

        return [
            'items' => $ratings->map(fn ($r) => self::formatRating($r))->all(),
            'cursor' => $hasMore && $ratings->isNotEmpty() ? base64_encode((string) $ratings->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Recalculate avg_rating and total_ratings on the seller's profile.
     */
    public static function refreshSellerStats(int $userId): void
    {
        $stats = MarketplaceSellerRating::where('ratee_id', $userId)
            ->where('rater_role', 'buyer')
            ->selectRaw('COUNT(*) as total_ratings, AVG(rating) as avg_rating')
            ->first();

        $profile = MarketplaceSellerProfile::where('user_id', $userId)->first();
        if ($profile && $stats) {
            $profile->update([
                'total_ratings' => (int) $stats->total_ratings,
                'avg_rating' => round((float) $stats->avg_rating, 2),
            ]);
        }
    }

    // -----------------------------------------------------------------
    //  Disputes
    // -----------------------------------------------------------------

    /**
     * Open a dispute on an order.
     */
    public static function openDispute(int $orderId, int $userId, array $data): MarketplaceDispute
    {
        $order = MarketplaceOrder::findOrFail($orderId);

        // Only buyer or seller can open a dispute
        if ($order->buyer_id !== $userId && $order->seller_id !== $userId) {
            throw new \InvalidArgumentException('You are not a participant in this order.');
        }

        // Cannot dispute cancelled/refunded orders
        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            throw new \InvalidArgumentException('Cannot dispute a cancelled or refunded order.');
        }

        // Check for existing open dispute
        $existingDispute = MarketplaceDispute::where('order_id', $orderId)
            ->whereNotIn('status', ['closed'])
            ->first();

        if ($existingDispute) {
            throw new \InvalidArgumentException('A dispute already exists for this order.');
        }

        $validReasons = ['not_received', 'not_as_described', 'damaged', 'wrong_item', 'other'];
        $reason = $data['reason'] ?? '';
        if (!in_array($reason, $validReasons, true)) {
            throw new \InvalidArgumentException('Invalid dispute reason.');
        }

        $dispute = DB::transaction(function () use ($order, $orderId, $userId, $data, $reason) {
            $dispute = new MarketplaceDispute();
            $dispute->tenant_id = TenantContext::getId();
            $dispute->order_id = $orderId;
            $dispute->opened_by = $userId;
            $dispute->reason = $reason;
            $dispute->description = $data['description'];
            $dispute->evidence_urls = $data['evidence_urls'] ?? null;
            $dispute->status = 'open';
            $dispute->save();

            // Move order to disputed status
            $order->status = 'disputed';
            $order->save();

            return $dispute;
        });

        // Notify the other party that a dispute was opened
        try {
            $otherPartyId = ($userId === (int) $order->buyer_id) ? (int) $order->seller_id : (int) $order->buyer_id;
            $link = '/marketplace/orders/' . $orderId;
            self::sendRatingEmail(
                $otherPartyId,
                __('emails_misc.marketplace_dispute.opened_subject', ['order_number' => $order->order_number]),
                __('emails_misc.marketplace_dispute.opened_title'),
                __('emails_misc.marketplace_dispute.opened_body', ['order_number' => $order->order_number]),
                $link
            );
        } catch (\Throwable $e) {
            Log::warning('[MarketplaceRatingService] openDispute email failed: ' . $e->getMessage());
        }

        return $dispute;
    }

    /**
     * Get the dispute for a specific order.
     */
    public static function getDispute(int $orderId): ?MarketplaceDispute
    {
        return MarketplaceDispute::with('openedBy:id,first_name,last_name,avatar_url')
            ->where('order_id', $orderId)
            ->first();
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    private static function sendRatingEmail(int $userId, string $subject, string $title, string $body, string $link): void
    {
        $tenantId = TenantContext::getId();
        $user = DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name'])->first();

        if (!$user || empty($user->email)) {
            return;
        }

        $firstName = $user->first_name ?? $user->name ?? 'there';
        $fullUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $link;

        $html = EmailTemplateBuilder::make()
            ->title($title)
            ->greeting($firstName)
            ->paragraph($body)
            ->button(__('emails_misc.marketplace_rating.received_cta'), $fullUrl)
            ->render();

        if (!Mailer::forCurrentTenant()->send($user->email, $subject, $html)) {
            Log::warning('[MarketplaceRatingService] email failed', ['user_id' => $userId]);
        }
    }

    private static function formatRating(MarketplaceSellerRating $rating): array
    {
        $rater = $rating->relationLoaded('rater') ? $rating->rater : null;
        $order = $rating->relationLoaded('order') ? $rating->order : null;
        $listing = $order && $order->relationLoaded('listing') ? $order->listing : null;

        return [
            'id' => $rating->id,
            'order_id' => $rating->order_id,
            'rater_role' => $rating->rater_role,
            'rating' => $rating->rating,
            'comment' => $rating->is_anonymous ? null : $rating->comment,
            'is_anonymous' => $rating->is_anonymous,
            'created_at' => $rating->created_at?->toISOString(),
            'rater' => $rating->is_anonymous ? null : ($rater ? [
                'id' => $rater->id,
                'name' => trim($rater->first_name . ' ' . $rater->last_name),
                'avatar_url' => $rater->avatar_url,
            ] : null),
            'order' => $order ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'listing' => $listing ? [
                    'id' => $listing->id,
                    'title' => $listing->title,
                ] : null,
            ] : null,
        ];
    }
}
