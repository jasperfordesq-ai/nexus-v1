<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\StripeService;
use Tests\Laravel\TestCase;
use Mockery;

/**
 * @covers \App\Services\StripeService
 *
 * Note: Stripe SDK (stripe/stripe-php) is only installed on the production
 * server via a manual composer install. Tests that require the SDK classes
 * are skipped when the package is not present.
 */
class StripeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_client_throwsWhenSecretKeyNotConfigured(): void
    {
        config(['services.stripe.secret' => null]);
        // Also clear env fallback
        putenv('STRIPE_SECRET_KEY');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe secret key is not configured');

        StripeService::client();
    }

    public function test_client_returnsStripeClientWhenKeyConfigured(): void
    {
        if (!class_exists(\Stripe\StripeClient::class)) {
            $this->markTestSkipped('Stripe SDK not installed — skipping client instantiation test');
        }

        config(['services.stripe.secret' => 'sk_test_fake_key_for_testing']);

        $client = StripeService::client();

        $this->assertInstanceOf(\Stripe\StripeClient::class, $client);
    }

    public function test_constructWebhookEvent_throwsWhenWebhookSecretNotConfigured(): void
    {
        config(['services.stripe.webhook_secret' => null]);
        putenv('STRIPE_WEBHOOK_SECRET');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe webhook secret is not configured');

        StripeService::constructWebhookEvent('{}', 'sig_header');
    }

    public function test_constructWebhookEvent_throwsOnInvalidSignature(): void
    {
        if (!class_exists(\Stripe\Webhook::class)) {
            $this->markTestSkipped('Stripe SDK not installed — skipping signature verification test');
        }

        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

        $this->expectException(\Stripe\Exception\SignatureVerificationException::class);

        StripeService::constructWebhookEvent('{"id": "evt_test"}', 'invalid_signature');
    }

    public function test_constructWebhookEvent_returnsEventOnValidSignature(): void
    {
        if (!class_exists(\Stripe\Webhook::class)) {
            $this->markTestSkipped('Stripe SDK not installed — skipping valid signature test');
        }

        $webhookSecret = 'whsec_test_secret';
        config(['services.stripe.webhook_secret' => $webhookSecret]);

        $payload = json_encode([
            'id' => 'evt_test_123',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => []],
        ]);

        // Generate a valid signature using Stripe's own HMAC approach
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $webhookSecret);
        $sigHeader = "t={$timestamp},v1={$signature}";

        $event = StripeService::constructWebhookEvent($payload, $sigHeader);

        $this->assertInstanceOf(\Stripe\Event::class, $event);
        $this->assertEquals('evt_test_123', $event->id);
    }
}
