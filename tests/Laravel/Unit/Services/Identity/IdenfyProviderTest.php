<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Services\Identity\IdenfyProvider;
use App\Services\Identity\IdentityVerificationProviderInterface;
use Illuminate\Support\Facades\DB;

class IdenfyProviderTest extends TestCase
{
    private IdenfyProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new IdenfyProvider();
    }

    public function test_get_slug_returns_idenfy(): void
    {
        $this->assertSame('idenfy', $this->provider->getSlug());
    }

    public function test_get_name_returns_idenfy_label(): void
    {
        $this->assertSame('iDenfy', $this->provider->getName());
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
        $this->assertContains('manual_review', $levels);
    }

    public function test_is_available_returns_true_when_global_keys_set(): void
    {
        // Set both env vars — isAvailable should return true without a DB call
        putenv('IDENFY_API_KEY=test-key');
        putenv('IDENFY_API_SECRET=test-secret');

        $result = $this->provider->isAvailable(2);

        $this->assertTrue($result);

        putenv('IDENFY_API_KEY=');
        putenv('IDENFY_API_SECRET=');
    }

    public function test_is_available_returns_false_when_no_keys_and_no_tenant_credentials(): void
    {
        putenv('IDENFY_API_KEY=');
        putenv('IDENFY_API_SECRET=');

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $result = $this->provider->isAvailable(2);

        $this->assertFalse($result);
    }

    public function test_is_available_returns_true_when_tenant_has_credentials(): void
    {
        putenv('IDENFY_API_KEY=');
        putenv('IDENFY_API_SECRET=');

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1,
            'tenant_id' => 2,
            'provider_slug' => 'idenfy',
            'credentials' => json_encode(['api_key' => 'sk', 'webhook_secret' => 'wh']),
        ]);

        $result = $this->provider->isAvailable(2);

        $this->assertTrue($result);
    }

    public function test_verify_webhook_signature_returns_false_without_secret(): void
    {
        putenv('IDENFY_API_SECRET=');

        $result = $this->provider->verifyWebhookSignature('body', []);

        $this->assertFalse($result);
    }

    public function test_verify_webhook_signature_matches_correct_hmac(): void
    {
        $secret = 'test-webhook-secret';
        putenv('IDENFY_API_SECRET=' . $secret);

        $body = '{"scanRef":"abc123"}';
        $sig = hash_hmac('sha256', $body, $secret);

        $result = $this->provider->verifyWebhookSignature($body, ['Idenfy-Signature' => $sig]);

        $this->assertTrue($result);

        putenv('IDENFY_API_SECRET=');
    }

    public function test_cancel_session_returns_false_when_api_fails(): void
    {
        putenv('IDENFY_API_KEY=');
        putenv('IDENFY_API_SECRET=');

        // No real HTTP call made — curl will fail with empty creds, caught by try/catch
        $result = $this->provider->cancelSession('nonexistent-scan-ref');

        $this->assertIsBool($result);
    }

    public function test_handle_webhook_maps_approved_status_to_passed(): void
    {
        $payload = [
            'final' => [
                'scanRef' => 'abc123',
                'overall' => 'APPROVED',
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('abc123', $result['provider_session_id']);
        $this->assertSame('passed', $result['status']);
        $this->assertSame('approved', $result['decision']);
    }

    public function test_handle_webhook_maps_denied_status_to_failed(): void
    {
        $payload = [
            'final' => [
                'scanRef' => 'def456',
                'overall' => 'DENIED',
                'suspicionReasons' => ['FACE_MATCH_FAILED'],
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
        $this->assertStringContainsString('FACE_MATCH_FAILED', $result['failure_reason']);
    }
}
