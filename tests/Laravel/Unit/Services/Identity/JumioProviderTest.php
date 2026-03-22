<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Services\Identity\JumioProvider;
use App\Services\Identity\IdentityVerificationProviderInterface;
use Illuminate\Support\Facades\DB;

class JumioProviderTest extends TestCase
{
    private JumioProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new JumioProvider();
    }

    public function test_get_slug_returns_jumio(): void
    {
        $this->assertSame('jumio', $this->provider->getSlug());
    }

    public function test_get_name_returns_jumio_label(): void
    {
        $this->assertSame('Jumio', $this->provider->getName());
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
        $this->assertNotContains('manual_review', $levels);
    }

    public function test_is_available_returns_true_when_global_keys_set(): void
    {
        putenv('JUMIO_API_TOKEN=test-token');
        putenv('JUMIO_API_SECRET=test-secret');

        $result = $this->provider->isAvailable(2);

        $this->assertTrue($result);

        putenv('JUMIO_API_TOKEN=');
        putenv('JUMIO_API_SECRET=');
    }

    public function test_is_available_returns_false_when_no_keys_and_no_tenant_credentials(): void
    {
        putenv('JUMIO_API_TOKEN=');
        putenv('JUMIO_API_SECRET=');

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $result = $this->provider->isAvailable(2);

        $this->assertFalse($result);
    }

    public function test_is_available_returns_true_when_tenant_has_credentials(): void
    {
        putenv('JUMIO_API_TOKEN=');
        putenv('JUMIO_API_SECRET=');

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1,
            'tenant_id' => 2,
            'provider_slug' => 'jumio',
            'credentials' => json_encode(['api_key' => 'token', 'webhook_secret' => 'sec']),
        ]);

        $result = $this->provider->isAvailable(2);

        $this->assertTrue($result);
    }

    public function test_cancel_session_always_returns_true(): void
    {
        // Jumio doesn't support explicit cancellation — always returns true
        $result = $this->provider->cancelSession('any-account-id');

        $this->assertTrue($result);
    }

    public function test_verify_webhook_signature_returns_false_without_secret(): void
    {
        putenv('JUMIO_API_SECRET=');

        $result = $this->provider->verifyWebhookSignature('body', []);

        $this->assertFalse($result);
    }

    public function test_verify_webhook_signature_returns_false_without_signature_header(): void
    {
        putenv('JUMIO_API_SECRET=test-secret');

        $result = $this->provider->verifyWebhookSignature('body', []);

        $this->assertFalse($result);

        putenv('JUMIO_API_SECRET=');
    }

    public function test_verify_webhook_signature_matches_correct_hmac(): void
    {
        $secret = 'jumio-secret-456';
        putenv('JUMIO_API_SECRET=' . $secret);

        $body = '{"accountId":"abc","decision":{"type":"APPROVED"}}';
        $sig = hash_hmac('sha256', $body, $secret);

        $result = $this->provider->verifyWebhookSignature($body, ['Jumio-Signature' => $sig]);

        $this->assertTrue($result);

        putenv('JUMIO_API_SECRET=');
    }

    public function test_handle_webhook_maps_approved_to_passed(): void
    {
        $payload = [
            'accountId' => 'acct-123',
            'decision' => ['type' => 'APPROVED'],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('acct-123', $result['provider_session_id']);
        $this->assertSame('passed', $result['status']);
        $this->assertSame('approved', $result['decision']);
    }

    public function test_handle_webhook_maps_rejected_to_failed(): void
    {
        $payload = [
            'accountId' => 'acct-456',
            'decision' => [
                'type' => 'REJECTED',
                'details' => ['label' => 'ID_INVALID'],
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
        $this->assertSame('ID_INVALID', $result['failure_reason']);
    }

    public function test_handle_webhook_maps_not_executed_to_failed(): void
    {
        $payload = [
            'accountId' => 'acct-789',
            'decision' => ['type' => 'NOT_EXECUTED'],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
    }
}
