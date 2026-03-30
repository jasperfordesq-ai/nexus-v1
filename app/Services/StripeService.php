<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * StripeService — Stripe API client and webhook verification.
 *
 * Static methods following project convention.
 */
class StripeService
{
    /**
     * Get a configured Stripe client instance.
     */
    public static function client(): StripeClient
    {
        $secret = config('services.stripe.secret') ?: env('STRIPE_SECRET_KEY');

        if (empty($secret)) {
            throw new \RuntimeException('Stripe secret key is not configured. Set STRIPE_SECRET_KEY in .env.');
        }

        return new StripeClient($secret);
    }

    /**
     * Construct and verify a Stripe webhook event from the raw payload.
     *
     * @param string $payload  Raw request body
     * @param string $sigHeader  Stripe-Signature header value
     * @return Event  Verified Stripe event
     *
     * @throws SignatureVerificationException  If signature verification fails
     * @throws \RuntimeException  If webhook secret is not configured
     */
    public static function constructWebhookEvent(string $payload, string $sigHeader): Event
    {
        $webhookSecret = config('services.stripe.webhook_secret') ?: env('STRIPE_WEBHOOK_SECRET');

        if (empty($webhookSecret)) {
            throw new \RuntimeException('Stripe webhook secret is not configured. Set STRIPE_WEBHOOK_SECRET in .env.');
        }

        try {
            return Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            throw $e;
        }
    }
}
