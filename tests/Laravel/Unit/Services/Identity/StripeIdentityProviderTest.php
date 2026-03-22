<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Services\Identity\StripeIdentityProvider;
use App\Services\Identity\IdentityVerificationProviderInterface;
use Illuminate\Support\Facades\DB;

class StripeIdentityProviderTest extends TestCase
{
    private StripeIdentityProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new StripeIdentityProvider();
    }

    public function test_get_slug_returns_stripe_identity(): void
    {
        $this->assertSame('stripe_identity', $this->provider->getSlug());
    }

    public function test_get_name_returns_stripe_identity_label(): void
    {
        $this->assertSame('Stripe Identity', $this->provider->getName());
    }

    public function test_implements_identity_verification_provider_interface(): void
    {
        $this->assertInstanceOf(IdentityVerificationProviderInterface::class, $this->provider);
    }

    public function test_get_supported_levels_returns_expected_values(): void
    {
        $levels = $this->provider->getSupportedLevels();

        $this->assertContains('document_only', $levels);
        $this->assertContains('document_selfie', $levels);
    }

    public function test_is_available_returns_true_when_global_api_key_set(): void
    {
        putenv('STRIPE_IDENTITY_SECRET_KEY=sk_test_abc123');

        $result = $this->provider->isAvailable(2);

        $this->assertTrue($result);

        putenv('STRIPE_IDENTITY_SECRET_KEY=');
    }

    public function test_is_available_returns_false_when_no_key_and_no_tenant_credentials(): void
    {
        putenv('STRIPE_IDENTITY_SECRET_KEY=');

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $result = $this->provider->isAvailable(2);

        $this->assertFalse($result);
    }

    public function test_is_available_returns_true_when_tenant_has_credentials(): void
    {
        putenv('STRIPE_IDENTITY_SECRET_KEY=');

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1,
            'tenant_id' => 2,
            'provider_slug' => 'stripe_identity',
            'credentials' => json_encode(['api_key' => 'sk_live_xyz', 'webhook_secret' => 'whsec_abc']),
        ]);

        $result = $this->provider->isAvailable(2);

        $this->assertTrue($result);
    }

    public function test_verify_webhook_signature_returns_false_without_signature_header(): void
    {
        $result = $this->provider->verifyWebhookSignature('body', []);

        $this->assertFalse($result);
    }

    public function test_verify_webhook_signature_returns_false_without_webhook_secret(): void
    {
        putenv('STRIPE_IDENTITY_WEBHOOK_SECRET=');

        $result = $this->provider->verifyWebhookSignature('body', [
            'Stripe-Signature' => 't=1234567890,v1=abc',
        ]);

        $this->assertFalse($result);
    }

    public function test_verify_webhook_signature_returns_false_for_stale_timestamp(): void
    {
        putenv('STRIPE_IDENTITY_WEBHOOK_SECRET=whsec_test');

        // Use a very old timestamp to trigger the 5-minute tolerance check
        $oldTimestamp = time() - 400;
        $body = 'test-body';
        $secret = 'whsec_test';
        $signedPayload = $oldTimestamp . '.' . $body;
        $sig = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t={$oldTimestamp},v1={$sig}";

        $result = $this->provider->verifyWebhookSignature($body, ['Stripe-Signature' => $header]);

        $this->assertFalse($result);

        putenv('STRIPE_IDENTITY_WEBHOOK_SECRET=');
    }

    public function test_verify_webhook_signature_matches_correct_hmac(): void
    {
        $secret = 'whsec_test_secret';
        putenv('STRIPE_IDENTITY_WEBHOOK_SECRET=' . $secret);

        $body = '{"type":"identity.verification_session.verified"}';
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $body;
        $sig = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t={$timestamp},v1={$sig}";

        $result = $this->provider->verifyWebhookSignature($body, ['Stripe-Signature' => $header]);

        $this->assertTrue($result);

        putenv('STRIPE_IDENTITY_WEBHOOK_SECRET=');
    }

    public function test_handle_webhook_maps_verified_event_to_passed(): void
    {
        $payload = [
            'type' => 'identity.verification_session.verified',
            'data' => [
                'object' => ['id' => 'vs_abc123'],
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('vs_abc123', $result['provider_session_id']);
        $this->assertSame('passed', $result['status']);
        $this->assertSame('approved', $result['decision']);
    }

    public function test_handle_webhook_maps_requires_input_to_failed(): void
    {
        $payload = [
            'type' => 'identity.verification_session.requires_input',
            'data' => [
                'object' => [
                    'id' => 'vs_def456',
                    'last_error' => ['reason' => 'document_expired'],
                ],
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('vs_def456', $result['provider_session_id']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
        $this->assertSame('document_expired', $result['failure_reason']);
    }

    public function test_handle_webhook_maps_canceled_event_to_cancelled(): void
    {
        $payload = [
            'type' => 'identity.verification_session.canceled',
            'data' => [
                'object' => ['id' => 'vs_ghi789'],
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('cancelled', $result['status']);
    }

    public function test_handle_webhook_maps_processing_event(): void
    {
        $payload = [
            'type' => 'identity.verification_session.processing',
            'data' => [
                'object' => ['id' => 'vs_jkl000'],
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('processing', $result['status']);
        $this->assertNull($result['decision']);
    }
}
