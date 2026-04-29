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
use App\Models\MarketplaceOrder;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $listing  = MarketplaceListing::findOrFail($offer->marketplace_listing_id);
        $tenantId = TenantContext::getId();

        $order = DB::transaction(function () use ($offer, $listing, $tenantId) {
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

            MarketplaceListing::where('id', $listing->id)->update(['status' => 'sold']);

            return $order;
        });

        // Order confirmation emails (outside transaction)
        try {
            self::sendOrderConfirmationEmails($order, $listing->title);
        } catch (\Throwable $e) {
            Log::warning('[MarketplaceOrderService] createFromOffer email failed: ' . $e->getMessage());
        }

        return $order;
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
        $subtotal = ($unitPrice * $quantity) + ($shippingCost ?? 0);

        // AG63 — apply merchant coupon if supplied (validation only; redemption inside txn).
        $couponCode = isset($data['coupon_code']) ? trim((string) $data['coupon_code']) : '';
        $couponDiscount = 0.0;
        $coupon = null;
        if ($couponCode !== '') {
            try {
                $coupon = \App\Services\MerchantCouponService::validateCoupon(
                    $couponCode,
                    $buyerId,
                    (int) round($subtotal * 100),
                    (int) $listing->id,
                    isset($listing->category_id) ? (int) $listing->category_id : null
                );
                $discountCents = \App\Services\MerchantCouponService::calculateDiscountCents(
                    $coupon,
                    (int) round($subtotal * 100)
                );
                $couponDiscount = $discountCents / 100.0;
            } catch (\InvalidArgumentException $e) {
                throw $e;
            }
        }
        $totalPrice = max(0.0, $subtotal - $couponDiscount);

        $order = DB::transaction(function () use (
            $buyerId, $listing, $tenantId, $quantity,
            $unitPrice, $totalPrice, $shippingCost, $data, $coupon
        ) {
            // Lock listing row to prevent race condition on simultaneous purchases
            $lockedListing = MarketplaceListing::where('id', $listing->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedListing || $lockedListing->status !== 'active') {
                throw new \InvalidArgumentException('This listing is no longer available for purchase.');
            }

            $order = new MarketplaceOrder();
            $order->tenant_id = $tenantId;
            $order->order_number = self::generateOrderNumber($tenantId);
            $order->buyer_id = $buyerId;
            $order->seller_id = $lockedListing->user_id;
            $order->marketplace_listing_id = $lockedListing->id;
            $order->marketplace_offer_id = null;
            $order->quantity = $quantity;
            $order->unit_price = $unitPrice;
            $order->total_price = $totalPrice;
            $order->currency = $lockedListing->price_currency ?? 'EUR';
            $order->time_credits_used = $data['time_credits_used'] ?? null;
            $order->shipping_method = $data['shipping_method'] ?? null;
            $order->shipping_cost = $shippingCost;
            $order->delivery_address = $data['delivery_address'] ?? null;
            $order->delivery_notes = $data['delivery_notes'] ?? null;
            $order->status = 'pending_payment';
            $order->save();

            // Mark listing as sold (or reduce quantity in future)
            $lockedListing->update(['status' => 'sold']);

            // AG63 — atomically redeem coupon (locks coupon row, increments usage_count).
            if ($coupon) {
                \App\Services\MerchantCouponService::redeemForOrder(
                    (int) $coupon->id,
                    (int) $order->id,
                    (int) $buyerId,
                    'online'
                );
            }

            return $order;
        });

        // Order confirmation emails (outside transaction)
        try {
            self::sendOrderConfirmationEmails($order, $listing->title);
        } catch (\Throwable $e) {
            Log::warning('[MarketplaceOrderService] createDirectPurchase email failed: ' . $e->getMessage());
        }

        return $order;
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

        // Notify buyer their order has shipped
        try {
            $listing = MarketplaceListing::find($order->marketplace_listing_id);
            $title   = $listing->title ?? '';
            $extraLines = [];
            if (!empty($order->tracking_number)) {
                $extraLines[] = [
                    'key'    => 'emails_misc.marketplace_order.shipped_tracking',
                    'params' => ['tracking' => htmlspecialchars($order->tracking_number, ENT_QUOTES, 'UTF-8')],
                ];
            }
            self::sendOrderEmail(
                (int) $order->buyer_id,
                'emails_misc.marketplace_order.shipped_subject',
                ['order_number' => $order->order_number],
                'emails_misc.marketplace_order.shipped_title',
                'emails_misc.marketplace_order.shipped_body',
                ['order_number' => $order->order_number, 'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8')],
                '/marketplace/orders/' . $order->id,
                $extraLines
            );
        } catch (\Throwable $e) {
            Log::warning('[MarketplaceOrderService] markShipped email failed: ' . $e->getMessage());
        }

        // In-app bell to buyer
        Notification::create([
            'user_id'    => $order->buyer_id,
            'message'    => __('api_controllers_3.marketplace_order.shipped', ['order_number' => $order->order_number]),
            'link'       => '/marketplace/orders/' . $order->id,
            'type'       => 'marketplace_order',
            'created_at' => now(),
        ]);

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

        // Notify seller delivery was confirmed
        try {
            self::sendOrderEmail(
                (int) $order->seller_id,
                'emails_misc.marketplace_order.delivered_subject',
                ['order_number' => $order->order_number],
                'emails_misc.marketplace_order.delivered_title',
                'emails_misc.marketplace_order.delivered_body',
                ['order_number' => $order->order_number],
                '/marketplace/orders/' . $order->id
            );
        } catch (\Throwable $e) {
            Log::warning('[MarketplaceOrderService] confirmDelivery email failed: ' . $e->getMessage());
        }

        // In-app bell to seller
        Notification::create([
            'user_id'    => $order->seller_id,
            'message'    => __('api_controllers_3.marketplace_order.delivered', ['order_number' => $order->order_number]),
            'link'       => '/marketplace/orders/' . $order->id,
            'type'       => 'marketplace_order',
            'created_at' => now(),
        ]);

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

        $order = DB::transaction(function () use ($order, $reason) {
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->cancellation_reason = $reason;
            $order->save();

            MarketplaceListing::where('id', $order->marketplace_listing_id)->update(['status' => 'active']);

            return $order;
        });

        // Notify both parties of the cancellation
        try {
            $listing = MarketplaceListing::find($order->marketplace_listing_id);
            $title   = htmlspecialchars($listing->title ?? '', ENT_QUOTES, 'UTF-8');
            $extraLines = [];
            if (!empty($reason)) {
                $extraLines[] = [
                    'key'    => 'emails_misc.marketplace_order.cancelled_reason',
                    'params' => ['reason' => htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')],
                ];
            }
            $subjectKey    = 'emails_misc.marketplace_order.cancelled_subject';
            $subjectParams = ['order_number' => $order->order_number];
            $titleKey      = 'emails_misc.marketplace_order.cancelled_title';
            $bodyKey       = 'emails_misc.marketplace_order.cancelled_body';
            $bodyParams    = ['order_number' => $order->order_number, 'title' => $title];
            $link          = '/marketplace/orders/' . $order->id;
            self::sendOrderEmail((int) $order->buyer_id,  $subjectKey, $subjectParams, $titleKey, $bodyKey, $bodyParams, $link, $extraLines);
            self::sendOrderEmail((int) $order->seller_id, $subjectKey, $subjectParams, $titleKey, $bodyKey, $bodyParams, $link, $extraLines);
        } catch (\Throwable $e) {
            Log::warning('[MarketplaceOrderService] cancel email failed: ' . $e->getMessage());
        }

        // In-app bells to both parties
        $cancelLink = '/marketplace/orders/' . $order->id;
        $cancelBell = __('api_controllers_3.marketplace_order.cancelled', ['order_number' => $order->order_number]);
        Notification::create(['user_id' => $order->buyer_id,  'message' => $cancelBell, 'link' => $cancelLink, 'type' => 'marketplace_order', 'created_at' => now()]);
        Notification::create(['user_id' => $order->seller_id, 'message' => $cancelBell, 'link' => $cancelLink, 'type' => 'marketplace_order', 'created_at' => now()]);

        return $order;
    }

    /**
     * Finalize an order — mark as completed and release escrow.
     */
    public static function complete(MarketplaceOrder $order): MarketplaceOrder
    {
        // Prevent double-completion (and double stat increment)
        if ($order->status === 'completed') {
            return $order;
        }

        if (!in_array($order->status, ['delivered', 'paid', 'shipped'], true)) {
            throw new \InvalidArgumentException('Order must be delivered before it can be completed.');
        }

        $order = DB::transaction(function () use ($order) {
            $order->status = 'completed';
            $order->escrow_released_at = now();
            $order->save();

            $sellerProfile = \App\Models\MarketplaceSellerProfile::where('user_id', $order->seller_id)
                ->lockForUpdate()
                ->first();
            if ($sellerProfile) {
                $sellerProfile->increment('total_sales');
                $sellerProfile->increment('total_revenue', (float) $order->total_price);
            }

            return $order;
        });

        // Notify both parties of completion
        try {
            $listing = MarketplaceListing::find($order->marketplace_listing_id);
            $title   = htmlspecialchars($listing->title ?? '', ENT_QUOTES, 'UTF-8');
            $subjectKey    = 'emails_misc.marketplace_order.completed_subject';
            $subjectParams = ['order_number' => $order->order_number];
            $titleKey      = 'emails_misc.marketplace_order.completed_title';
            $link          = '/marketplace/orders/' . $order->id;
            self::sendOrderEmail(
                (int) $order->buyer_id, $subjectKey, $subjectParams,
                $titleKey,
                'emails_misc.marketplace_order.completed_buyer_body',
                ['order_number' => $order->order_number, 'title' => $title],
                $link
            );
            self::sendOrderEmail(
                (int) $order->seller_id, $subjectKey, $subjectParams,
                $titleKey,
                'emails_misc.marketplace_order.completed_seller_body',
                ['order_number' => $order->order_number, 'title' => $title],
                $link
            );
        } catch (\Throwable $e) {
            Log::warning('[MarketplaceOrderService] complete email failed: ' . $e->getMessage());
        }

        // In-app bells to both parties
        $completeLink = '/marketplace/orders/' . $order->id;
        $completeBell = __('api_controllers_3.marketplace_order.completed', ['order_number' => $order->order_number]);
        Notification::create(['user_id' => $order->buyer_id,  'message' => $completeBell, 'link' => $completeLink, 'type' => 'marketplace_order', 'created_at' => now()]);
        Notification::create(['user_id' => $order->seller_id, 'message' => $completeBell, 'link' => $completeLink, 'type' => 'marketplace_order', 'created_at' => now()]);

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
            if (str_contains($status, ',')) {
                $query->whereIn('status', explode(',', $status));
            } else {
                $query->where('status', $status);
            }
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
            if (str_contains($status, ',')) {
                $query->whereIn('status', explode(',', $status));
            } else {
                $query->where('status', $status);
            }
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
     * Send an order email to a single user.
     *
     * Accepts translation KEYS (not resolved strings) so the email renders under
     * the recipient's preferred_language via LocaleContext.
     *
     * Optional $extraLines is a list of ['key' => ..., 'params' => [...]] entries
     * appended to the body paragraph (each prefixed with <br>). Each extra line
     * is resolved via __() under the recipient locale wrap.
     *
     * @param array<int, array{key: string, params?: array}> $extraLines
     */
    private static function sendOrderEmail(int $userId, string $subjectKey, array $subjectParams, string $titleKey, string $bodyKey, array $bodyParams, string $link, array $extraLines = []): void
    {
        $tenantId = TenantContext::getId();
        $user = DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name', 'preferred_language'])->first();

        if (!$user || empty($user->email)) {
            return;
        }

        $fullUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $link;

        LocaleContext::withLocale($user, function () use ($user, $subjectKey, $subjectParams, $titleKey, $bodyKey, $bodyParams, $fullUrl, $userId, $extraLines): void {
            $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');

            $body = __($bodyKey, $bodyParams);
            foreach ($extraLines as $line) {
                $body .= '<br>' . __($line['key'], $line['params'] ?? []);
            }

            $html = EmailTemplateBuilder::make()
                ->title(__($titleKey))
                ->greeting($firstName)
                ->paragraph($body)
                ->button(__('emails_misc.marketplace_order.order_cta'), $fullUrl)
                ->render();

            if (!Mailer::forCurrentTenant()->send($user->email, __($subjectKey, $subjectParams), $html)) {
                Log::warning('[MarketplaceOrderService] email failed', ['user_id' => $userId]);
            }
        });
    }

    /**
     * Send order confirmation emails to both buyer and seller.
     */
    private static function sendOrderConfirmationEmails(MarketplaceOrder $order, string $listingTitle): void
    {
        $title      = htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8');
        $link       = '/marketplace/orders/' . $order->id;

        self::sendOrderEmail(
            (int) $order->buyer_id,
            'emails_misc.marketplace_order.confirmed_buyer_subject',
            ['order_number' => $order->order_number],
            'emails_misc.marketplace_order.confirmed_buyer_title',
            'emails_misc.marketplace_order.confirmed_buyer_body',
            ['order_number' => $order->order_number, 'title' => $title],
            $link
        );

        self::sendOrderEmail(
            (int) $order->seller_id,
            'emails_misc.marketplace_order.confirmed_seller_subject',
            ['order_number' => $order->order_number],
            'emails_misc.marketplace_order.confirmed_seller_title',
            'emails_misc.marketplace_order.confirmed_seller_body',
            ['order_number' => $order->order_number, 'title' => $title],
            $link
        );

        // In-app bells
        Notification::create([
            'user_id'    => $order->buyer_id,
            'message'    => __('api_controllers_3.marketplace_order.confirmed_buyer', ['order_number' => $order->order_number, 'title' => $title]),
            'link'       => $link,
            'type'       => 'marketplace_order',
            'created_at' => now(),
        ]);
        Notification::create([
            'user_id'    => $order->seller_id,
            'message'    => __('api_controllers_3.marketplace_order.confirmed_seller', ['order_number' => $order->order_number, 'title' => $title]),
            'link'       => $link,
            'type'       => 'marketplace_order',
            'created_at' => now(),
        ]);
    }

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
