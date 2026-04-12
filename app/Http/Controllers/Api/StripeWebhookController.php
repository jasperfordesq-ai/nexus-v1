<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\StripeDonationService;
use App\Services\MarketplacePaymentService;
use App\Services\StripeService;
use App\Services\StripeSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

/**
 * StripeWebhookController — Handles incoming Stripe webhook events.
 *
 * This endpoint is public (no auth/CSRF) — signature verification is
 * performed via StripeService::constructWebhookEvent().
 */
class StripeWebhookController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * Handle an incoming Stripe webhook event.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        // Missing signature header or empty body is a client error, not a server fault.
        if ($sigHeader === '' || $payload === '') {
            return $this->respondWithError(
                'INVALID_SIGNATURE',
                __('api.webhook_signature_failed'),
                null,
                400
            );
        }

        // Verify webhook signature
        try {
            $event = StripeService::constructWebhookEvent($payload, $sigHeader);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return $this->respondWithError(
                'INVALID_SIGNATURE',
                __('api.webhook_signature_failed'),
                null,
                400
            );
        } catch (\Exception $e) {
            Log::error('Stripe webhook error during signature verification', [
                'error' => $e->getMessage(),
            ]);
            return $this->respondWithError(
                'WEBHOOK_ERROR',
                __('api.webhook_internal_error'),
                null,
                500
            );
        }

        // Atomic idempotency claim — INSERT IGNORE relies on the UNIQUE index
        // on stripe_webhook_events.event_id. Exactly one concurrent caller
        // wins the insert; losers get 0 affected rows and bail out.
        $eventId = $event->id;
        $nowTs = now();

        $inserted = DB::affectingStatement(
            "INSERT IGNORE INTO stripe_webhook_events (event_id, event_type, status, processed_at)
             VALUES (?, ?, 'processing', ?)",
            [$eventId, $event->type, $nowTs]
        );

        if ($inserted === 0) {
            // Another delivery already claimed this event. Ack 200 so Stripe
            // stops retrying — we either already processed it or another
            // worker is currently processing it.
            $existing = DB::table('stripe_webhook_events')
                ->where('event_id', $eventId)
                ->first();

            Log::info('Stripe webhook: duplicate delivery suppressed', [
                'event_id' => $eventId,
                'existing_status' => $existing->status ?? null,
            ]);
            return $this->respondWithData(['received' => true]);
        }

        // Dispatch to the appropriate handler
        try {
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                'invoice.paid' => $this->handleInvoicePaid($event->data->object),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
                'payment_intent.succeeded' => $this->handlePaymentSucceeded($event->data->object),
                'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
                'charge.refunded' => $this->handleChargeRefunded($event->data->object),
                'account.updated' => $this->handleAccountUpdated($event->data->object),
                default => Log::info("Stripe webhook: unhandled event type {$event->type}"),
            };
        } catch (\Exception $e) {
            // Mark as failed so Stripe retries can be re-processed
            DB::table('stripe_webhook_events')
                ->where('event_id', $eventId)
                ->update(['status' => 'failed']);

            Log::error("Stripe webhook handler error for {$event->type}", [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->respondWithError(
                'HANDLER_ERROR',
                __('api.webhook_handler_error'),
                null,
                500
            );
        }

        // Mark as successfully processed
        DB::table('stripe_webhook_events')
            ->where('event_id', $eventId)
            ->update(['status' => 'processed']);

        return $this->respondWithData(['received' => true]);
    }

    // ============================================
    // EVENT HANDLERS — Stubs for Phase 1+2 agents
    // ============================================

    private function handleCheckoutCompleted(object $session): void
    {
        StripeSubscriptionService::handleCheckoutCompleted($session);
    }

    private function handleSubscriptionUpdated(object $subscription): void
    {
        StripeSubscriptionService::handleSubscriptionUpdated($subscription);
    }

    private function handleSubscriptionDeleted(object $subscription): void
    {
        StripeSubscriptionService::handleSubscriptionDeleted($subscription);
    }

    private function handleInvoicePaid(object $invoice): void
    {
        StripeSubscriptionService::handleInvoicePaid($invoice);
    }

    private function handleInvoicePaymentFailed(object $invoice): void
    {
        StripeSubscriptionService::handleInvoicePaymentFailed($invoice);
    }

    private function handlePaymentSucceeded(object $paymentIntent): void
    {
        StripeDonationService::handlePaymentSucceeded($paymentIntent);

        // Also dispatch to marketplace handler (it checks nexus_type metadata internally)
        MarketplacePaymentService::handleWebhookEvent('payment_intent.succeeded', $paymentIntent);

        // Also dispatch to identity verification payment handler
        \App\Services\Identity\IdentityVerificationPaymentService::handlePaymentSucceeded($paymentIntent);
    }

    private function handlePaymentFailed(object $paymentIntent): void
    {
        StripeDonationService::handlePaymentFailed($paymentIntent);

        // Also dispatch to identity verification payment handler
        \App\Services\Identity\IdentityVerificationPaymentService::handlePaymentFailed($paymentIntent);
    }

    private function handleChargeRefunded(object $charge): void
    {
        StripeDonationService::handleChargeRefunded($charge);

        // Also dispatch to marketplace handler (it checks for marketplace payments internally)
        MarketplacePaymentService::handleWebhookEvent('charge.refunded', $charge);
    }

    private function handleAccountUpdated(object $account): void
    {
        // Connect account updates — marketplace seller onboarding
        MarketplacePaymentService::handleWebhookEvent('account.updated', $account);
    }
}
