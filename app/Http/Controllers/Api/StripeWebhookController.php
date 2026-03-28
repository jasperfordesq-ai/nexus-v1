<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\StripeDonationService;
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

        // Verify webhook signature
        try {
            $event = StripeService::constructWebhookEvent($payload, $sigHeader);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return $this->respondWithError(
                'INVALID_SIGNATURE',
                'Webhook signature verification failed.',
                null,
                400
            );
        } catch (\Exception $e) {
            Log::error('Stripe webhook error during signature verification', [
                'error' => $e->getMessage(),
            ]);
            return $this->respondWithError(
                'WEBHOOK_ERROR',
                'Internal error processing webhook.',
                null,
                500
            );
        }

        // Idempotency check — skip already-processed events
        $eventId = $event->id;
        $existing = DB::table('stripe_webhook_events')
            ->where('event_id', $eventId)
            ->first();

        if ($existing && $existing->status === 'processed') {
            return $this->respondWithData(['received' => true]);
        }

        // Record the event as "processing" for idempotency (or update if retrying a failed event)
        if ($existing) {
            DB::table('stripe_webhook_events')
                ->where('event_id', $eventId)
                ->update(['status' => 'processing', 'processed_at' => now()]);
        } else {
            DB::table('stripe_webhook_events')->insert([
                'event_id' => $eventId,
                'event_type' => $event->type,
                'status' => 'processing',
                'processed_at' => now(),
            ]);
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
                'Error processing webhook event.',
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
    }

    private function handlePaymentFailed(object $paymentIntent): void
    {
        StripeDonationService::handlePaymentFailed($paymentIntent);
    }

    private function handleChargeRefunded(object $charge): void
    {
        StripeDonationService::handleChargeRefunded($charge);
    }
}
