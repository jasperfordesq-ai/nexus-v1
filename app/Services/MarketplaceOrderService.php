<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Models\MarketplaceOrder;
use Illuminate\Support\Facades\DB;

/**
 * MarketplaceOrderService — Order lifecycle management for the marketplace module.
 *
 * Handles: create from offer / direct purchase → ship → deliver → complete / cancel.
 */
class MarketplaceOrderService
{
    // -----------------------------------------------------------------
    //  Create
    // -----------------------------------------------------------------

    /**
     * Create an order from an accepted offer.
     */
    public static function createFromOffer(MarketplaceOffer $offer): MarketplaceOrder
    {
        if ($offer->status !== 'accepted') {
            throw new \InvalidArgumentException('Offer must be accepted before creating an order.');
        }

        $listing = MarketplaceListing::findOrFail($offer->marketplace_listing_id);
        $tenantId = TenantContext::getId();

        return DB::transaction(function () use ($offer, $listing, $tenantId) {
            $order = new MarketplaceOrder();
            $order->tenant_id = $tenantId;
            $order->order_number = self::generateOrderNumber($tenantId);
            $order->buyer_id = $offer->buyer_id;
            $order->seller_id = $offer->seller_id;
            $order->marketplace_listing_id = $listing->id;
            $order->marketplace_offer_id = $offer->id;
            $order->quantity = 1;
            $order->unit_price = $offer->amount;
            $order->total_price = $offer->amount;
            $order->currency = $offer->currency;
            $order->status = 'pending_payment';
            $order->save();

            // Mark listing as sold
            MarketplaceListing::where('id', $listing->id)
                ->update(['status' => 'sold']);

            return $order;
        });
    }

    /**
     * Create an order via direct purchase (buy-now flow, no offer).
     */
    public static function createDirectPurchase(int $buyerId, int $listingId, array $data): MarketplaceOrder
    {
        $listing = MarketplaceListing::findOrFail($listingId);

        if ($listing->user_id === $buyerId) {
            throw new \InvalidArgumentException('Cannot purchase your own listing.');
        }

        if (!in_array($listing->status, ['active'], true)) {
            throw new \InvalidArgumentException('This listing is not available for purchase.');
        }

        $tenantId = TenantContext::getId();
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $unitPrice = (float) $listing->price;
        $shippingCost = isset($data['shipping_cost']) ? (float) $data['shipping_cost'] : null;
        $totalPrice = ($unitPrice * $quantity) + ($shippingCost ?? 0);

        return DB::transaction(function () use (
            $buyerId, $listing, $tenantId, $quantity,
            $unitPrice, $totalPrice, $shippingCost, $data
        ) {
            $order = new MarketplaceOrder();
            $order->tenant_id = $tenantId;
            $order->order_number = self::generateOrderNumber($tenantId);
            $order->buyer_id = $buyerId;
            $order->seller_id = $listing->user_id;
            $order->marketplace_listing_id = $listing->id;
            $order->marketplace_offer_id = null;
            $order->quantity = $quantity;
            $order->unit_price = $unitPrice;
            $order->total_price = $totalPrice;
            $order->currency = $listing->price_currency ?? 'EUR';
            $order->time_credits_used = $data['time_credits_used'] ?? null;
            $order->shipping_method = $data['shipping_method'] ?? null;
            $order->shipping_cost = $shippingCost;
            $order->delivery_address = $data['delivery_address'] ?? null;
            $order->delivery_notes = $data['delivery_notes'] ?? null;
            $order->status = 'pending_payment';
            $order->save();

            // Mark listing as sold (or reduce quantity in future)
            MarketplaceListing::where('id', $listing->id)
                ->update(['status' => 'sold']);

            return $order;
        });
    }

    // -----------------------------------------------------------------
    //  Lifecycle transitions
    // -----------------------------------------------------------------

    /**
     * Seller marks order as shipped with optional tracking info.
     */
    public static function markShipped(MarketplaceOrder $order, array $data): MarketplaceOrder
    {
        if ($order->status !== 'paid') {
            throw new \InvalidArgumentException('Order must be paid before marking as shipped.');
        }

        $order->status = 'shipped';
        $order->seller_confirmed_at = now();
        $order->shipping_method = $data['shipping_method'] ?? $order->shipping_method;
        $order->tracking_number = $data['tracking_number'] ?? null;
        $order->tracking_url = $data['tracking_url'] ?? null;
        $order->save();

        return $order;
    }

    /**
     * Buyer confirms receipt of the order. Sets auto-complete countdown (14 days).
     */
    public static function confirmDelivery(MarketplaceOrder $order): MarketplaceOrder
    {
        if (!in_array($order->status, ['shipped', 'paid'], true)) {
            throw new \InvalidArgumentException('Order must be shipped or paid to confirm delivery.');
        }

        $order->status = 'delivered';
        $order->buyer_confirmed_at = now();
        $order->auto_complete_at = now()->addDays(14);
        $order->save();

        return $order;
    }

    /**
     * Cancel an order (only before shipped).
     */
    public static function cancel(MarketplaceOrder $order, string $reason): MarketplaceOrder
    {
        if (in_array($order->status, ['shipped', 'delivered', 'completed', 'refunded'], true)) {
            throw new \InvalidArgumentException('Cannot cancel an order that has already been shipped or completed.');
        }

        return DB::transaction(function () use ($order, $reason) {
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->cancellation_reason = $reason;
            $order->save();

            // Re-activate the listing
            MarketplaceListing::where('id', $order->marketplace_listing_id)
                ->update(['status' => 'active']);

            return $order;
        });
    }

    /**
     * Finalize an order — mark as completed and release escrow.
     */
    public static function complete(MarketplaceOrder $order): MarketplaceOrder
    {
        if (!in_array($order->status, ['delivered', 'paid', 'shipped'], true)) {
            throw new \InvalidArgumentException('Order must be delivered before it can be completed.');
        }

        $order->status = 'completed';
        $order->escrow_released_at = now();
        $order->save();

        // Update seller stats
        $sellerProfile = \App\Models\MarketplaceSellerProfile::where('user_id', $order->seller_id)->first();
        if ($sellerProfile) {
            $sellerProfile->increment('total_sales');
            $sellerProfile->increment('total_revenue', (float) $order->total_price);
        }

        return $order;
    }

    // -----------------------------------------------------------------
    //  Read
    // -----------------------------------------------------------------

    /**
     * Get paginated orders for a buyer.
     */
    public static function getBuyerOrders(int $userId, ?string $status, int $limit = 20, ?string $cursor = null): array
    {
        $query = MarketplaceOrder::with([
            'listing:id,title,price,price_currency,status',
            'listing.images' => fn ($q) => $q->where('is_primary', true)->limit(1),
            'seller:id,first_name,last_name,avatar_url',
        ])
            ->forBuyer($userId)
            ->orderBy('id', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($cursor) {
            $query->where('id', '<', (int) base64_decode($cursor, true));
        }

        $orders = $query->limit($limit + 1)->get();
        $hasMore = $orders->count() > $limit;
        if ($hasMore) {
            $orders->pop();
        }

        return [
            'items' => $orders->map(fn ($o) => self::formatOrder($o))->all(),
            'cursor' => $hasMore && $orders->isNotEmpty() ? base64_encode((string) $orders->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get paginated orders for a seller.
     */
    public static function getSellerOrders(int $userId, ?string $status, int $limit = 20, ?string $cursor = null): array
    {
        $query = MarketplaceOrder::with([
            'listing:id,title,price,price_currency,status',
            'listing.images' => fn ($q) => $q->where('is_primary', true)->limit(1),
            'buyer:id,first_name,last_name,avatar_url',
        ])
            ->forSeller($userId)
            ->orderBy('id', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($cursor) {
            $query->where('id', '<', (int) base64_decode($cursor, true));
        }

        $orders = $query->limit($limit + 1)->get();
        $hasMore = $orders->count() > $limit;
        if ($hasMore) {
            $orders->pop();
        }

        return [
            'items' => $orders->map(fn ($o) => self::formatOrder($o))->all(),
            'cursor' => $hasMore && $orders->isNotEmpty() ? base64_encode((string) $orders->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Find an order by ID.
     */
    public static function getById(int $id): ?MarketplaceOrder
    {
        return MarketplaceOrder::with([
            'listing:id,title,price,price_currency,status',
            'listing.images' => fn ($q) => $q->where('is_primary', true)->limit(1),
            'buyer:id,first_name,last_name,avatar_url',
            'seller:id,first_name,last_name,avatar_url',
            'ratings',
            'dispute',
        ])->find($id);
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    /**
     * Generate a unique order number: MKT-000001, MKT-000002, etc.
     */
    public static function generateOrderNumber(int $tenantId): string
    {
        $lastOrder = MarketplaceOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOrder && preg_match('/MKT-(\d+)/', $lastOrder->order_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return 'MKT-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Format an order for API responses.
     */
    public static function formatOrder(MarketplaceOrder $order): array
    {
        $listing = $order->relationLoaded('listing') ? $order->listing : null;
        $primaryImage = $listing && $listing->relationLoaded('images')
            ? $listing->images->first()
            : null;

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'quantity' => $order->quantity,
            'unit_price' => $order->unit_price,
            'total_price' => $order->total_price,
            'currency' => $order->currency,
            'time_credits_used' => $order->time_credits_used,
            'shipping_method' => $order->shipping_method,
            'shipping_cost' => $order->shipping_cost,
            'tracking_number' => $order->tracking_number,
            'tracking_url' => $order->tracking_url,
            'delivery_address' => $order->delivery_address,
            'delivery_notes' => $order->delivery_notes,
            'buyer_confirmed_at' => $order->buyer_confirmed_at?->toISOString(),
            'seller_confirmed_at' => $order->seller_confirmed_at?->toISOString(),
            'auto_complete_at' => $order->auto_complete_at?->toISOString(),
            'cancelled_at' => $order->cancelled_at?->toISOString(),
            'cancellation_reason' => $order->cancellation_reason,
            'escrow_released_at' => $order->escrow_released_at?->toISOString(),
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
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
            'buyer' => $order->relationLoaded('buyer') && $order->buyer ? [
                'id' => $order->buyer->id,
                'name' => trim($order->buyer->first_name . ' ' . $order->buyer->last_name),
                'avatar_url' => $order->buyer->avatar_url,
            ] : null,
            'seller' => $order->relationLoaded('seller') && $order->seller ? [
                'id' => $order->seller->id,
                'name' => trim($order->seller->first_name . ' ' . $order->seller->last_name),
                'avatar_url' => $order->seller->avatar_url,
            ] : null,
            'ratings' => $order->relationLoaded('ratings')
                ? $order->ratings->map(fn ($r) => [
                    'id' => $r->id,
                    'rater_role' => $r->rater_role,
                    'rating' => $r->rating,
                    'comment' => $r->is_anonymous ? null : $r->comment,
                    'is_anonymous' => $r->is_anonymous,
                    'created_at' => $r->created_at?->toISOString(),
                ])->all()
                : [],
            'dispute' => $order->relationLoaded('dispute') && $order->dispute ? [
                'id' => $order->dispute->id,
                'reason' => $order->dispute->reason,
                'status' => $order->dispute->status,
                'created_at' => $order->dispute->created_at?->toISOString(),
            ] : null,
        ];
    }
}
