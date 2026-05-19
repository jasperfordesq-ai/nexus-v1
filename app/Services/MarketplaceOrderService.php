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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

/**
 * MarketplaceOrderService — Order lifecycle management for the marketplace module.
 *
 * Handles: create from offer / direct purchase → ship → deliver → complete / cancel.
 */
class MarketplaceOrderService
{
    private const DELIVERY_CHANNEL_EMAIL = 'email';
    private const DELIVERY_CHANNEL_BELL = 'bell';
    private const DELIVERY_STATUS_CLAIMED = 'claimed';
    private const DELIVERY_STATUS_DELIVERED = 'delivered';
    private const DELIVERY_STATUS_FAILED = 'failed';
    private const DELIVERY_STATUS_SKIPPED = 'skipped';

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

        $tenantId = (int) ($offer->tenant_id ?: TenantContext::getId());
        $listing  = MarketplaceListing::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($offer->marketplace_listing_id);

        return TenantContext::runForTenant($tenantId, function () use ($offer, $listing, $tenantId): MarketplaceOrder {
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

        $tenantId = (int) ($listing->tenant_id ?: TenantContext::getId());
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

        return TenantContext::runForTenant($tenantId, function () use (
            $buyerId, $listing, $tenantId, $quantity,
            $unitPrice, $totalPrice, $shippingCost, $data, $coupon
        ): MarketplaceOrder {
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

            // AG46 — atomic inventory decrement (under same DB transaction).
            // If inventory_count is NULL the listing is unlimited and we mark
            // it sold like before; otherwise let inventory drive status.
            if ($lockedListing->inventory_count === null) {
                $lockedListing->update(['status' => 'sold']);
            } else {
                \App\Services\MarketplaceInventoryService::decrementForOrder(
                    (int) $lockedListing->id,
                    (int) $quantity
                );
            }

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
        if (!in_array($order->status, ['paid', 'shipped'], true)) {
            throw new \InvalidArgumentException('Order must be paid before marking as shipped.');
        }

        if ($order->status === 'paid') {
            $order->status = 'shipped';
            $order->seller_confirmed_at = now();
            $order->shipping_method = $data['shipping_method'] ?? $order->shipping_method;
            $order->tracking_number = $data['tracking_number'] ?? null;
            $order->tracking_url = $data['tracking_url'] ?? null;
            $order->save();
        }

        self::withOrderTenant($order, function () use ($order): void {
            // Notify buyer their order has shipped
            self::deliverOrderEmail($order, 'shipped', (int) $order->buyer_id, function () use ($order): string {
                $listing = MarketplaceListing::find($order->marketplace_listing_id);
                $title   = $listing->title ?? '';
                $extraLines = [];
                if (!empty($order->tracking_number)) {
                    $extraLines[] = [
                        'key'    => 'emails_misc.marketplace_order.shipped_tracking',
                        'params' => ['tracking' => htmlspecialchars($order->tracking_number, ENT_QUOTES, 'UTF-8')],
                    ];
                }
                return self::sendOrderEmail(
                    (int) $order->buyer_id,
                    'emails_misc.marketplace_order.shipped_subject',
                    ['order_number' => $order->order_number],
                    'emails_misc.marketplace_order.shipped_title',
                    'emails_misc.marketplace_order.shipped_body',
                    ['order_number' => $order->order_number, 'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8')],
                    '/marketplace/orders/' . $order->id,
                    $extraLines
                );
            });

            // In-app bell to buyer
            self::deliverOrderBell($order, 'shipped', (int) $order->buyer_id, [
                'user_id'    => $order->buyer_id,
                'message'    => __('api_controllers_3.marketplace_order.shipped', ['order_number' => $order->order_number]),
                'link'       => '/marketplace/orders/' . $order->id,
                'type'       => 'marketplace_order',
                'created_at' => now(),
            ]);
        });

        return $order;
    }

    /**
     * Buyer confirms receipt of the order. Sets auto-complete countdown (14 days).
     */
    public static function confirmDelivery(MarketplaceOrder $order): MarketplaceOrder
    {
        if (!in_array($order->status, ['shipped', 'paid', 'delivered'], true)) {
            throw new \InvalidArgumentException('Order must be shipped or paid to confirm delivery.');
        }

        if ($order->status !== 'delivered') {
            $order->status = 'delivered';
            $order->buyer_confirmed_at = now();
            $order->auto_complete_at = now()->addDays(14);
            $order->save();
        }

        self::withOrderTenant($order, function () use ($order): void {
            // Notify seller delivery was confirmed
            self::deliverOrderEmail($order, 'delivered', (int) $order->seller_id, function () use ($order): string {
                return self::sendOrderEmail(
                    (int) $order->seller_id,
                    'emails_misc.marketplace_order.delivered_subject',
                    ['order_number' => $order->order_number],
                    'emails_misc.marketplace_order.delivered_title',
                    'emails_misc.marketplace_order.delivered_body',
                    ['order_number' => $order->order_number],
                    '/marketplace/orders/' . $order->id
                );
            });

            // In-app bell to seller
            self::deliverOrderBell($order, 'delivered', (int) $order->seller_id, [
                'user_id'    => $order->seller_id,
                'message'    => __('api_controllers_3.marketplace_order.delivered', ['order_number' => $order->order_number]),
                'link'       => '/marketplace/orders/' . $order->id,
                'type'       => 'marketplace_order',
                'created_at' => now(),
            ]);
        });

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

        $order = self::withOrderTenant($order, function () use ($order, $reason): MarketplaceOrder {
            return DB::transaction(function () use ($order, $reason) {
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->cancellation_reason = $reason;
            $order->save();

            // AG46 — restore inventory on cancellation.
            $listing = MarketplaceListing::where('id', $order->marketplace_listing_id)->lockForUpdate()->first();
            if ($listing) {
                if ($listing->inventory_count === null) {
                    $listing->status = 'active';
                    $listing->save();
                } else {
                    \App\Services\MarketplaceInventoryService::incrementForCancellation(
                        (int) $listing->id,
                        (int) ($order->quantity ?? 1)
                    );
                }
            }

            return $order;
            });
        });

        self::withOrderTenant($order, function () use ($order, $reason): void {
            // Notify both parties of the cancellation
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
            self::deliverOrderEmail($order, 'cancelled', (int) $order->buyer_id, fn (): string => self::sendOrderEmail((int) $order->buyer_id, $subjectKey, $subjectParams, $titleKey, $bodyKey, $bodyParams, $link, $extraLines));
            self::deliverOrderEmail($order, 'cancelled', (int) $order->seller_id, fn (): string => self::sendOrderEmail((int) $order->seller_id, $subjectKey, $subjectParams, $titleKey, $bodyKey, $bodyParams, $link, $extraLines));

            // In-app bells to both parties
            $cancelLink = '/marketplace/orders/' . $order->id;
            $cancelBell = __('api_controllers_3.marketplace_order.cancelled', ['order_number' => $order->order_number]);
            self::deliverOrderBell($order, 'cancelled', (int) $order->buyer_id, ['user_id' => $order->buyer_id,  'message' => $cancelBell, 'link' => $cancelLink, 'type' => 'marketplace_order', 'created_at' => now()]);
            self::deliverOrderBell($order, 'cancelled', (int) $order->seller_id, ['user_id' => $order->seller_id, 'message' => $cancelBell, 'link' => $cancelLink, 'type' => 'marketplace_order', 'created_at' => now()]);
        });

        return $order;
    }

    /**
     * Finalize an order — mark as completed and release escrow.
     */
    public static function complete(MarketplaceOrder $order): MarketplaceOrder
    {
        // Prevent double-completion (and double stat increment)
        if ($order->status === 'completed') {
            self::sendCompletedNotifications($order);
            return $order;
        }

        if (!in_array($order->status, ['delivered', 'paid', 'shipped'], true)) {
            throw new \InvalidArgumentException('Order must be delivered before it can be completed.');
        }

        $order = self::withOrderTenant($order, function () use ($order): MarketplaceOrder {
            return DB::transaction(function () use ($order) {
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
        });

        self::sendCompletedNotifications($order);

        return $order;
    }

    private static function sendCompletedNotifications(MarketplaceOrder $order): void
    {
        self::withOrderTenant($order, function () use ($order): void {
            // Notify both parties of completion
            $listing = MarketplaceListing::find($order->marketplace_listing_id);
            $title   = htmlspecialchars($listing->title ?? '', ENT_QUOTES, 'UTF-8');
            $subjectKey    = 'emails_misc.marketplace_order.completed_subject';
            $subjectParams = ['order_number' => $order->order_number];
            $titleKey      = 'emails_misc.marketplace_order.completed_title';
            $link          = '/marketplace/orders/' . $order->id;
            self::deliverOrderEmail($order, 'completed', (int) $order->buyer_id, function () use ($order, $subjectKey, $subjectParams, $titleKey, $title, $link): string {
                return self::sendOrderEmail(
                    (int) $order->buyer_id, $subjectKey, $subjectParams,
                    $titleKey,
                    'emails_misc.marketplace_order.completed_buyer_body',
                    ['order_number' => $order->order_number, 'title' => $title],
                    $link
                );
            });
            self::deliverOrderEmail($order, 'completed', (int) $order->seller_id, function () use ($order, $subjectKey, $subjectParams, $titleKey, $title, $link): string {
                return self::sendOrderEmail(
                    (int) $order->seller_id, $subjectKey, $subjectParams,
                    $titleKey,
                    'emails_misc.marketplace_order.completed_seller_body',
                    ['order_number' => $order->order_number, 'title' => $title],
                    $link
                );
            });

            // In-app bells to both parties
            $completeLink = '/marketplace/orders/' . $order->id;
            $completeBell = __('api_controllers_3.marketplace_order.completed', ['order_number' => $order->order_number]);
            self::deliverOrderBell($order, 'completed', (int) $order->buyer_id, ['user_id' => $order->buyer_id,  'message' => $completeBell, 'link' => $completeLink, 'type' => 'marketplace_order', 'created_at' => now()]);
            self::deliverOrderBell($order, 'completed', (int) $order->seller_id, ['user_id' => $order->seller_id, 'message' => $completeBell, 'link' => $completeLink, 'type' => 'marketplace_order', 'created_at' => now()]);
        });
    }

    public static function sendPaidNotifications(MarketplaceOrder $order, \App\Models\MarketplacePayment $payment): void
    {
        self::withOrderTenant($order, function () use ($order, $payment): void {
            $listing = MarketplaceListing::find($order->marketplace_listing_id);
            $title = htmlspecialchars($listing->title ?? '', ENT_QUOTES, 'UTF-8');
            $link = '/marketplace/orders/' . $order->id;
            $currency = strtoupper($payment->currency ?? $order->currency ?? TenantContext::getCurrency() ?? 'EUR');
            $amount = number_format((float) $payment->amount, 2) . ' ' . $currency;

            self::deliverOrderEmail($order, 'paid', (int) $order->buyer_id, function () use ($order, $title, $amount, $link): string {
                return self::sendOrderEmail(
                    (int) $order->buyer_id,
                    'emails_misc.marketplace_order.paid_buyer_subject',
                    ['order_number' => $order->order_number],
                    'emails_misc.marketplace_order.paid_buyer_title',
                    'emails_misc.marketplace_order.paid_buyer_body',
                    ['order_number' => $order->order_number, 'title' => $title, 'amount' => $amount],
                    $link
                );
            });
            self::deliverOrderEmail($order, 'paid', (int) $order->seller_id, function () use ($order, $title, $amount, $link): string {
                return self::sendOrderEmail(
                    (int) $order->seller_id,
                    'emails_misc.marketplace_order.paid_seller_subject',
                    ['order_number' => $order->order_number],
                    'emails_misc.marketplace_order.paid_seller_title',
                    'emails_misc.marketplace_order.paid_seller_body',
                    ['order_number' => $order->order_number, 'title' => $title, 'amount' => $amount],
                    $link
                );
            });

            self::deliverOrderBell($order, 'paid', (int) $order->buyer_id, [
                'user_id' => $order->buyer_id,
                'message' => __('api_controllers_3.marketplace_order.paid_buyer', ['order_number' => $order->order_number, 'amount' => $amount]),
                'link' => $link,
                'type' => 'marketplace_order',
                'created_at' => now(),
            ]);
            self::deliverOrderBell($order, 'paid', (int) $order->seller_id, [
                'user_id' => $order->seller_id,
                'message' => __('api_controllers_3.marketplace_order.paid_seller', ['order_number' => $order->order_number, 'amount' => $amount]),
                'link' => $link,
                'type' => 'marketplace_order',
                'created_at' => now(),
            ]);
        });
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
    private static function sendOrderEmail(int $userId, string $subjectKey, array $subjectParams, string $titleKey, string $bodyKey, array $bodyParams, string $link, array $extraLines = []): string
    {
        $tenantId = TenantContext::getId();
        $user = DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name', 'preferred_language', 'tenant_id'])->first();

        if (!$user || empty($user->email)) {
            return self::DELIVERY_STATUS_SKIPPED;
        }

        $fullUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $link;

        return LocaleContext::withLocale($user, function () use ($user, $subjectKey, $subjectParams, $titleKey, $bodyKey, $bodyParams, $fullUrl, $userId, $extraLines, $tenantId): string {
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

            if (!\App\Services\EmailDispatchService::sendRaw($user->email, __($subjectKey, $subjectParams), $html, null, null, null, 'marketplace_order', ['tenant_id' => $tenantId])) {
                Log::warning('[MarketplaceOrderService] email failed', ['user_id' => $userId]);
                return self::DELIVERY_STATUS_FAILED;
            }

            return self::DELIVERY_STATUS_DELIVERED;
        });
    }

    /**
     * Send order confirmation emails to both buyer and seller.
     */
    private static function sendOrderConfirmationEmails(MarketplaceOrder $order, string $listingTitle): void
    {
        $title      = htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8');
        $link       = '/marketplace/orders/' . $order->id;

        self::deliverOrderEmail($order, 'confirmed', (int) $order->buyer_id, fn (): string => self::sendOrderEmail(
            (int) $order->buyer_id,
            'emails_misc.marketplace_order.confirmed_buyer_subject',
            ['order_number' => $order->order_number],
            'emails_misc.marketplace_order.confirmed_buyer_title',
            'emails_misc.marketplace_order.confirmed_buyer_body',
            ['order_number' => $order->order_number, 'title' => $title],
            $link
        ));

        self::deliverOrderEmail($order, 'confirmed', (int) $order->seller_id, fn (): string => self::sendOrderEmail(
            (int) $order->seller_id,
            'emails_misc.marketplace_order.confirmed_seller_subject',
            ['order_number' => $order->order_number],
            'emails_misc.marketplace_order.confirmed_seller_title',
            'emails_misc.marketplace_order.confirmed_seller_body',
            ['order_number' => $order->order_number, 'title' => $title],
            $link
        ));

        // In-app bells
        self::deliverOrderBell($order, 'confirmed', (int) $order->buyer_id, [
            'user_id'    => $order->buyer_id,
            'message'    => __('api_controllers_3.marketplace_order.confirmed_buyer', ['order_number' => $order->order_number, 'title' => $title]),
            'link'       => $link,
            'type'       => 'marketplace_order',
            'created_at' => now(),
        ]);
        self::deliverOrderBell($order, 'confirmed', (int) $order->seller_id, [
            'user_id'    => $order->seller_id,
            'message'    => __('api_controllers_3.marketplace_order.confirmed_seller', ['order_number' => $order->order_number, 'title' => $title]),
            'link'       => $link,
            'type'       => 'marketplace_order',
            'created_at' => now(),
        ]);
    }

    /**
     * Run marketplace side effects under the order's tenant so Eloquent scopes,
     * notification tenant resolution, URLs, and mailer config match the order.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private static function withOrderTenant(MarketplaceOrder $order, callable $callback)
    {
        $tenantId = (int) ($order->tenant_id ?: TenantContext::getId());

        return TenantContext::runForTenant($tenantId, $callback);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private static function deliverOrderEmail(MarketplaceOrder $order, string $event, int $userId, callable $send): void
    {
        if (!self::claimOrderNotificationDelivery($order, $event, $userId, self::DELIVERY_CHANNEL_EMAIL)) {
            return;
        }

        try {
            $status = $send();
            if ($status === self::DELIVERY_STATUS_DELIVERED || $status === self::DELIVERY_STATUS_SKIPPED) {
                self::markOrderNotificationDelivered($order, $event, $userId, self::DELIVERY_CHANNEL_EMAIL, $status);
                return;
            }

            self::markOrderNotificationFailed($order, $event, $userId, self::DELIVERY_CHANNEL_EMAIL, __('emails_misc.marketplace_order.delivery_failed'));
        } catch (\Throwable $e) {
            self::markOrderNotificationFailed($order, $event, $userId, self::DELIVERY_CHANNEL_EMAIL, $e->getMessage());
            Log::warning('[MarketplaceOrderService] order email delivery failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private static function deliverOrderBell(MarketplaceOrder $order, string $event, int $userId, array $attributes): void
    {
        if (!self::claimOrderNotificationDelivery($order, $event, $userId, self::DELIVERY_CHANNEL_BELL)) {
            return;
        }

        try {
            $notificationId = self::createOrderBell($attributes);
            if ($notificationId === null) {
                self::markOrderNotificationDelivered($order, $event, $userId, self::DELIVERY_CHANNEL_BELL, self::DELIVERY_STATUS_SKIPPED);
                return;
            }

            self::markOrderNotificationDelivered($order, $event, $userId, self::DELIVERY_CHANNEL_BELL, self::DELIVERY_STATUS_DELIVERED, (string) $notificationId);
        } catch (\Throwable $e) {
            self::markOrderNotificationFailed($order, $event, $userId, self::DELIVERY_CHANNEL_BELL, $e->getMessage());
            Log::warning('[MarketplaceOrderService] order bell delivery failed: ' . $e->getMessage());
        }
    }

    private static function claimOrderNotificationDelivery(MarketplaceOrder $order, string $event, int $userId, string $channel): bool
    {
        if ((int) ($order->id ?? 0) <= 0 || $userId <= 0 || !Schema::hasTable('marketplace_order_notification_deliveries')) {
            return $userId > 0;
        }

        $tenantId = (int) ($order->tenant_id ?: TenantContext::getId());
        $now = now();
        $staleBefore = $now->copy()->subMinutes(10);

        return DB::transaction(function () use ($tenantId, $order, $event, $userId, $channel, $now, $staleBefore): bool {
            $record = DB::table('marketplace_order_notification_deliveries')
                ->where('tenant_id', $tenantId)
                ->where('order_id', (int) $order->id)
                ->where('event', $event)
                ->where('user_id', $userId)
                ->where('channel', $channel)
                ->lockForUpdate()
                ->first();

            if (!$record) {
                DB::table('marketplace_order_notification_deliveries')->insert([
                    'tenant_id' => $tenantId,
                    'order_id' => (int) $order->id,
                    'event' => $event,
                    'user_id' => $userId,
                    'channel' => $channel,
                    'status' => self::DELIVERY_STATUS_CLAIMED,
                    'attempts' => 1,
                    'claimed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return true;
            }

            if (in_array($record->status, [self::DELIVERY_STATUS_DELIVERED, self::DELIVERY_STATUS_SKIPPED], true)) {
                return false;
            }

            if ($record->status === self::DELIVERY_STATUS_CLAIMED && $record->claimed_at !== null && Carbon::parse($record->claimed_at)->greaterThan($staleBefore)) {
                return false;
            }

            DB::table('marketplace_order_notification_deliveries')
                ->where('id', $record->id)
                ->update([
                    'status' => self::DELIVERY_STATUS_CLAIMED,
                    'attempts' => DB::raw('attempts + 1'),
                    'claimed_at' => $now,
                    'failed_at' => null,
                    'last_error' => null,
                    'updated_at' => $now,
                ]);

            return true;
        });
    }

    private static function markOrderNotificationDelivered(MarketplaceOrder $order, string $event, int $userId, string $channel, string $status, ?string $evidenceId = null): void
    {
        if ((int) ($order->id ?? 0) <= 0 || $userId <= 0 || !Schema::hasTable('marketplace_order_notification_deliveries')) {
            return;
        }

        DB::table('marketplace_order_notification_deliveries')
            ->where('tenant_id', (int) ($order->tenant_id ?: TenantContext::getId()))
            ->where('order_id', (int) $order->id)
            ->where('event', $event)
            ->where('user_id', $userId)
            ->where('channel', $channel)
            ->update([
                'status' => $status,
                'delivered_at' => $status === self::DELIVERY_STATUS_DELIVERED ? now() : null,
                'failed_at' => null,
                'evidence_id' => $evidenceId,
                'last_error' => null,
                'updated_at' => now(),
            ]);
    }

    private static function markOrderNotificationFailed(MarketplaceOrder $order, string $event, int $userId, string $channel, string $error): void
    {
        if ((int) ($order->id ?? 0) <= 0 || $userId <= 0 || !Schema::hasTable('marketplace_order_notification_deliveries')) {
            return;
        }

        DB::table('marketplace_order_notification_deliveries')
            ->where('tenant_id', (int) ($order->tenant_id ?: TenantContext::getId()))
            ->where('order_id', (int) $order->id)
            ->where('event', $event)
            ->where('user_id', $userId)
            ->where('channel', $channel)
            ->update([
                'status' => self::DELIVERY_STATUS_FAILED,
                'failed_at' => now(),
                'last_error' => mb_substr($error, 0, 2000),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private static function createOrderBell(array $attributes): ?int
    {
        if ((int) ($attributes['user_id'] ?? 0) <= 0) {
            Log::warning('[MarketplaceOrderService] skipped marketplace bell without recipient', [
                'type' => $attributes['type'] ?? null,
                'link' => $attributes['link'] ?? null,
            ]);
            return null;
        }

        return (int) Notification::create($attributes)->id;
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
