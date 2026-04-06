<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MarketplaceCommunityDeliveryService — Community-powered delivery for marketplace.
 *
 * NEXUS differentiator: community members can offer to deliver marketplace items
 * for time credits, leveraging the timebanking ecosystem. This creates a
 * peer-to-peer delivery network where deliverers earn time credits.
 *
 * Flow:
 * 1. Listing has delivery_method = 'community_delivery'
 * 2. Community members browse deliverable orders and offer to deliver
 * 3. Seller/buyer accepts a delivery offer
 * 4. Deliverer completes delivery, gets confirmed, earns time credits
 *
 * Uses the `marketplace_delivery_offers` table (created by migration).
 */
class MarketplaceCommunityDeliveryService
{
    /**
     * Offer to deliver an order for time credits.
     *
     * @param int $orderId The marketplace order ID
     * @param int $delivererId The user offering to deliver
     * @param array{time_credits: float, estimated_minutes?: int, notes?: string} $data
     * @return array The created delivery offer
     *
     * @throws \InvalidArgumentException On validation failure
     * @throws \RuntimeException On business logic violations
     */
    public static function offerDelivery(int $orderId, int $delivererId, array $data): array
    {
        $tenantId = TenantContext::getId();
        $timeCredits = (float) ($data['time_credits'] ?? 0);
        $estimatedMinutes = (int) ($data['estimated_minutes'] ?? 0);
        $notes = trim($data['notes'] ?? '');

        if ($timeCredits <= 0) {
            throw new \InvalidArgumentException('Time credit amount must be greater than 0');
        }

        if ($timeCredits > 100) {
            throw new \InvalidArgumentException('Time credit amount cannot exceed 100 hours');
        }

        // Verify the order exists and belongs to this tenant
        $order = DB::table('marketplace_orders')
            ->where('id', $orderId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$order) {
            throw new \RuntimeException('Order not found');
        }

        // Cannot deliver your own order
        if ($order->buyer_id === $delivererId || $order->seller_id === $delivererId) {
            throw new \RuntimeException('Cannot offer to deliver your own order');
        }

        // Check order status allows delivery offers
        if (!in_array($order->status, ['confirmed', 'paid', 'processing'], true)) {
            throw new \RuntimeException('This order is not available for delivery offers');
        }

        // Check if delivery method is community_delivery
        $listing = DB::table('marketplace_listings')
            ->where('id', $order->marketplace_listing_id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$listing || $listing->delivery_method !== 'community_delivery') {
            throw new \RuntimeException('This listing does not use community delivery');
        }

        // Check for duplicate offer from same deliverer
        $existingOffer = DB::table('marketplace_delivery_offers')
            ->where('order_id', $orderId)
            ->where('deliverer_id', $delivererId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if ($existingOffer) {
            throw new \RuntimeException('You already have an active delivery offer for this order');
        }

        $offerId = DB::table('marketplace_delivery_offers')->insertGetId([
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'deliverer_id' => $delivererId,
            'time_credits' => $timeCredits,
            'estimated_minutes' => $estimatedMinutes > 0 ? $estimatedMinutes : null,
            'notes' => $notes ?: null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return self::formatOffer(
            DB::table('marketplace_delivery_offers')->where('id', $offerId)->where('tenant_id', TenantContext::getId())->first()
        );
    }

    /**
     * Accept a delivery offer.
     *
     * Called by the seller or buyer to accept a specific delivery offer.
     * Declines all other pending offers for the same order.
     *
     * @param int $orderId
     * @param int $delivererId The deliverer whose offer to accept
     * @return void
     *
     * @throws \RuntimeException If offer not found or invalid state
     */
    public static function acceptDeliveryOffer(int $orderId, int $delivererId): void
    {
        $tenantId = TenantContext::getId();

        $offer = DB::table('marketplace_delivery_offers')
            ->where('order_id', $orderId)
            ->where('deliverer_id', $delivererId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->first();

        if (!$offer) {
            throw new \RuntimeException('Delivery offer not found or already processed');
        }

        DB::transaction(function () use ($orderId, $offer, $tenantId) {
            // Accept this offer
            DB::table('marketplace_delivery_offers')
                ->where('id', $offer->id)
                ->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'updated_at' => now(),
                ]);

            // Decline all other pending offers for this order
            DB::table('marketplace_delivery_offers')
                ->where('order_id', $orderId)
                ->where('tenant_id', $tenantId)
                ->where('id', '!=', $offer->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'declined',
                    'updated_at' => now(),
                ]);
        });

        Log::info('Community delivery offer accepted', [
            'order_id' => $orderId,
            'deliverer_id' => $delivererId,
            'time_credits' => $offer->time_credits,
        ]);
    }

    /**
     * Confirm delivery completion and award time credits to the deliverer.
     *
     * This is the final step: the buyer/seller confirms the item was delivered,
     * and the deliverer receives the agreed time credits from the buyer.
     *
     * @param int $orderId
     * @param int $delivererId
     * @return void
     *
     * @throws \RuntimeException If offer not found, wrong state, or insufficient balance
     */
    public static function confirmDelivery(int $orderId, int $delivererId): void
    {
        $tenantId = TenantContext::getId();

        $offer = DB::table('marketplace_delivery_offers')
            ->where('order_id', $orderId)
            ->where('deliverer_id', $delivererId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'accepted')
            ->first();

        if (!$offer) {
            throw new \RuntimeException('No accepted delivery offer found');
        }

        $order = DB::table('marketplace_orders')
            ->where('id', $orderId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$order) {
            throw new \RuntimeException('Order not found');
        }

        DB::transaction(function () use ($offer, $order, $tenantId) {
            // Mark offer as completed
            DB::table('marketplace_delivery_offers')
                ->where('id', $offer->id)
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            // Award time credits: buyer pays deliverer
            $buyerId = $order->buyer_id;
            $delivererId = $offer->deliverer_id;
            $amount = (float) $offer->time_credits;

            // Check buyer balance
            $buyer = DB::table('users')
                ->where('id', $buyerId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$buyer || (float) $buyer->balance < $amount) {
                // If buyer cannot pay, log warning but still complete delivery
                Log::warning('Community delivery: buyer has insufficient balance for time credit payment', [
                    'order_id' => $order->id,
                    'buyer_id' => $buyerId,
                    'deliverer_id' => $delivererId,
                    'amount' => $amount,
                    'balance' => $buyer->balance ?? 0,
                ]);
                return;
            }

            // Create transaction record
            DB::table('transactions')->insert([
                'tenant_id' => $tenantId,
                'sender_id' => $buyerId,
                'receiver_id' => $delivererId,
                'amount' => $amount,
                'description' => 'Community delivery payment for order #' . $order->id,
                'transaction_type' => 'community_delivery',
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update balances
            DB::table('users')->where('id', $buyerId)->where('tenant_id', $tenantId)
                ->decrement('balance', $amount);
            DB::table('users')->where('id', $delivererId)->where('tenant_id', $tenantId)
                ->increment('balance', $amount);
        });

        Log::info('Community delivery confirmed and time credits awarded', [
            'order_id' => $orderId,
            'deliverer_id' => $delivererId,
            'time_credits' => $offer->time_credits,
        ]);
    }

    /**
     * Get all delivery offers for an order.
     *
     * @param int $orderId
     * @return array List of delivery offers with deliverer info
     */
    public static function getDeliveryOffers(int $orderId): array
    {
        $tenantId = TenantContext::getId();

        $offers = DB::table('marketplace_delivery_offers as mdo')
            ->leftJoin('users as u', 'u.id', '=', 'mdo.deliverer_id')
            ->where('mdo.order_id', $orderId)
            ->where('mdo.tenant_id', $tenantId)
            ->select(
                'mdo.*',
                'u.first_name',
                'u.last_name',
                'u.avatar_url',
                'u.is_verified'
            )
            ->orderByDesc('mdo.created_at')
            ->get();

        return $offers->map(fn ($o) => self::formatOfferWithUser($o))->all();
    }

    // -----------------------------------------------------------------
    //  Formatting helpers
    // -----------------------------------------------------------------

    private static function formatOffer(object $offer): array
    {
        return [
            'id' => $offer->id,
            'order_id' => $offer->order_id,
            'deliverer_id' => $offer->deliverer_id,
            'time_credits' => (float) $offer->time_credits,
            'estimated_minutes' => $offer->estimated_minutes ? (int) $offer->estimated_minutes : null,
            'notes' => $offer->notes,
            'status' => $offer->status,
            'accepted_at' => $offer->accepted_at ?? null,
            'completed_at' => $offer->completed_at ?? null,
            'created_at' => $offer->created_at,
        ];
    }

    private static function formatOfferWithUser(object $offer): array
    {
        return [
            'id' => $offer->id,
            'order_id' => $offer->order_id,
            'deliverer_id' => $offer->deliverer_id,
            'time_credits' => (float) $offer->time_credits,
            'estimated_minutes' => $offer->estimated_minutes ? (int) $offer->estimated_minutes : null,
            'notes' => $offer->notes,
            'status' => $offer->status,
            'accepted_at' => $offer->accepted_at ?? null,
            'completed_at' => $offer->completed_at ?? null,
            'created_at' => $offer->created_at,
            'deliverer' => [
                'id' => $offer->deliverer_id,
                'name' => trim(($offer->first_name ?? '') . ' ' . ($offer->last_name ?? '')),
                'avatar_url' => $offer->avatar_url ?? null,
                'is_verified' => (bool) ($offer->is_verified ?? false),
            ],
        ];
    }
}
