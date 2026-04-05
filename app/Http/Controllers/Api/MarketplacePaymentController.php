<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Models\MarketplaceOrder;
use App\Models\MarketplacePayment;
use App\Services\MarketplacePaymentService;
use Illuminate\Http\JsonResponse;

/**
 * MarketplacePaymentController — Payment and Stripe Connect endpoints.
 *
 * Handles: PaymentIntent creation, payment confirmation, payment status,
 * seller onboarding, seller payouts, and seller balance.
 */
class MarketplacePaymentController extends BaseApiController
{
    protected bool $isV2Api = true;

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('marketplace')) {
            abort(403, 'Marketplace feature is not enabled for this tenant.');
        }
    }

    // -----------------------------------------------------------------
    //  Payment flow
    // -----------------------------------------------------------------

    /**
     * POST /v2/marketplace/payments/create-intent — Create a Stripe PaymentIntent for an order.
     */
    public function createIntent(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_payment_create', 10, 60);

        $data = request()->validate([
            'order_id' => 'required|integer|exists:marketplace_orders,id',
        ]);

        $order = MarketplaceOrder::findOrFail($data['order_id']);

        // Only the buyer can create a payment intent
        if ($order->buyer_id !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'Only the buyer can initiate payment.', null, 403);
        }

        try {
            $result = MarketplacePaymentService::createPaymentIntent($order);

            return $this->respondWithData([
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('PAYMENT_ERROR', $e->getMessage(), null, 400);
        }
    }

    /**
     * POST /v2/marketplace/payments/confirm — Confirm payment after frontend Stripe.js completes.
     */
    public function confirm(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_payment_confirm', 10, 60);

        $data = request()->validate([
            'payment_intent_id' => 'required|string|max:255',
        ]);

        try {
            $payment = MarketplacePaymentService::confirmPayment($data['payment_intent_id']);

            // Verify the buyer is the one confirming
            $order = $payment->order;
            if ($order && $order->buyer_id !== $userId) {
                return $this->respondWithError('FORBIDDEN', 'Only the buyer can confirm payment.', null, 403);
            }

            return $this->respondWithData([
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'order_id' => $payment->order_id,
            ]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('PAYMENT_ERROR', $e->getMessage(), null, 400);
        }
    }

    /**
     * GET /v2/marketplace/payments/{id}/status — Get payment status.
     */
    public function status(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_payment_read', 30, 60);

        $payment = MarketplacePayment::with('order:id,buyer_id,seller_id,order_number,status')
            ->find($id);

        if (!$payment) {
            return $this->respondWithError('NOT_FOUND', 'Payment not found.', null, 404);
        }

        // Only buyer or seller can view the payment
        $order = $payment->order;
        if ($order && $order->buyer_id !== $userId && $order->seller_id !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'You do not have access to this payment.', null, 403);
        }

        return $this->respondWithData([
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'platform_fee' => $payment->platform_fee,
            'seller_payout' => $payment->seller_payout,
            'payment_method' => $payment->payment_method,
            'status' => $payment->status,
            'payout_status' => $payment->payout_status,
            'refund_amount' => $payment->refund_amount,
            'refunded_at' => $payment->refunded_at?->toISOString(),
            'paid_out_at' => $payment->paid_out_at?->toISOString(),
            'created_at' => $payment->created_at?->toISOString(),
        ]);
    }

    // -----------------------------------------------------------------
    //  Seller endpoints
    // -----------------------------------------------------------------

    /**
     * GET /v2/marketplace/seller/payouts — Seller payout history.
     */
    public function payouts(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_seller_read', 30, 60);

        $limit = $this->queryInt('limit', 20, 1, 100);
        $page = $this->queryInt('page', 1, 1, 1000);
        $offset = ($page - 1) * $limit;

        $result = MarketplacePaymentService::getSellerPayouts($userId, $limit, $offset);

        return $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $page,
            $limit
        );
    }

    /**
     * GET /v2/marketplace/seller/balance — Seller pending and available balance.
     */
    public function balance(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_seller_read', 30, 60);

        $result = MarketplacePaymentService::getSellerBalance($userId);

        return $this->respondWithData($result);
    }

    /**
     * POST /v2/marketplace/seller/onboard — Start or resume Stripe Connect onboarding.
     */
    public function onboard(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_seller_onboard', 5, 60);

        try {
            $result = MarketplacePaymentService::createConnectAccount($userId);

            return $this->respondWithData([
                'account_id' => $result['account_id'],
                'onboarding_url' => $result['onboarding_url'],
            ]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('ONBOARDING_ERROR', $e->getMessage(), null, 400);
        }
    }
}
