<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Services\Identity\OnfidoProvider;
use App\Services\Identity\IdentityVerificationProviderInterface;
use Illuminate\Support\Facades\DB;

class OnfidoProviderTest extends TestCase
{
    private OnfidoProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new OnfidoProvider();
    }

    public function test_get_slug_returns_onfido(): void
    {
        $this->assertSame('onfido', $this->provider->getSlug());
    }

    public function test_get_name_returns_onfido_label(): void
    {
        $this->assertSame('Onfido', $this->provider->getName());
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

    public function test_is_available_returns_true_when_global_token_set(): void
    {
        putenv('ONFIDO_API_TOKEN=live_test-token-123');

        $result = $this->provider->isAvailable(2);

        $this->assertTrue($result);

        putenv('ONFIDO_API_TOKEN=');
    }

    public function test_is_available_returns_false_when_no_token_and_no_tenant_credentials(): void
    {
        putenv('ONFIDO_API_TOKEN=');

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $result = $this->provider->isAvailable(2);

        $this->assertFalse($result);
    }

    public function test_is_available_returns_true_when_tenant_has_credentials(): void
    {
        putenv('ONFIDO_API_TOKEN=');

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1,
            'tenant_id' => 2,
            'provider_slug' => 'onfido',
            'credentials' => json_encode(['api_key' => 'live_abc']),
        ]);

        $result = $this->provider->isAvailable(2);

        $this->assertTrue($result);
    }

    public function test_cancel_session_returns_true(): void
    {
        // Onfido doesn't support applicant cancellation — always returns true
        $result = $this->provider->cancelSession('applicant-uuid');

        $this->assertTrue($result);
    }

    public function test_verify_webhook_signature_returns_false_without_secret(): void
    {
        putenv('ONFIDO_WEBHOOK_SECRET=');

        $result = $this->provider->verifyWebhookSignature('body', []);

        $this->assertFalse($result);
    }

    public function test_verify_webhook_signature_returns_false_without_header(): void
    {
        putenv('ONFIDO_WEBHOOK_SECRET=some-secret');

        $result = $this->provider->verifyWebhookSignature('body', []);

        $this->assertFalse($result);

        putenv('ONFIDO_WEBHOOK_SECRET=');
    }

    public function test_verify_webhook_signature_matches_correct_hmac(): void
    {
        $secret = 'onfido-webhook-secret';
        putenv('ONFIDO_WEBHOOK_SECRET=' . $secret);

        $body = '{"payload":{"resource_type":"check","action":"completed"}}';
        $sig = hash_hmac('sha256', $body, $secret);

        $result = $this->provider->verifyWebhookSignature($body, ['X-SHA2-Signature' => $sig]);

        $this->assertTrue($result);

        putenv('ONFIDO_WEBHOOK_SECRET=');
    }

    public function test_handle_webhook_maps_check_completed_clear_to_passed(): void
    {
        $payload = [
            'payload' => [
                'resource_type' => 'check',
                'action' => 'completed',
                'object' => [
                    'applicant_id' => 'applicant-abc',
                    'result' => 'clear',
                ],
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('applicant-abc', $result['provider_session_id']);
        $this->assertSame('passed', $result['status']);
        $this->assertSame('approved', $result['decision']);
    }

    public function test_handle_webhook_maps_check_completed_consider_to_failed(): void
    {
        $payload = [
            'payload' => [
                'resource_type' => 'check',
                'action' => 'completed',
                'object' => [
                    'applicant_id' => 'applicant-def',
                    'result' => 'consider',
                ],
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
    }

    public function test_handle_webhook_non_check_resource_returns_processing(): void
    {
        $payload = [
            'payload' => [
                'resource_type' => 'workflow_run',
                'action' => 'started',
                'object' => ['id' => 'wf-123'],
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('processing', $result['status']);
        $this->assertNull($result['decision']);
    }
}
