<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Services\Identity\VeriffProvider;
use App\Services\Identity\IdentityVerificationProviderInterface;
use Illuminate\Support\Facades\DB;

class VeriffProviderTest extends TestCase
{
    private VeriffProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new VeriffProvider();
    }

    public function test_get_slug_returns_veriff(): void
    {
        $this->assertSame('veriff', $this->provider->getSlug());
    }

    public function test_get_name_returns_veriff_label(): void
    {
        $this->assertSame('Veriff', $this->provider->getName());
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

    public function test_is_available_returns_true_when_both_global_keys_set(): void
    {
        putenv('VERIFF_API_KEY=test-pub-key');
        putenv('VERIFF_API_SECRET=test-secret');

        $result = $this->provider->isAvailable(2);

        $this->assertTrue($result);

        putenv('VERIFF_API_KEY=');
        putenv('VERIFF_API_SECRET=');
    }

    public function test_is_available_returns_false_when_only_one_global_key_set(): void
    {
        putenv('VERIFF_API_KEY=test-pub-key');
        putenv('VERIFF_API_SECRET='); // Missing secret

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $result = $this->provider->isAvailable(2);

        // isAvailable requires BOTH keys to be non-empty for global shortcut
        $this->assertFalse($result);

        putenv('VERIFF_API_KEY=');
    }

    public function test_is_available_returns_false_when_no_keys_and_no_tenant_credentials(): void
    {
        putenv('VERIFF_API_KEY=');
        putenv('VERIFF_API_SECRET=');

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $result = $this->provider->isAvailable(2);

        $this->assertFalse($result);
    }

    public function test_is_available_returns_true_when_tenant_has_credentials(): void
    {
        putenv('VERIFF_API_KEY=');
        putenv('VERIFF_API_SECRET=');

        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1,
            'tenant_id' => 2,
            'provider_slug' => 'veriff',
            'credentials' => json_encode(['api_key' => 'pub-key', 'webhook_secret' => 'sec']),
        ]);

        $result = $this->provider->isAvailable(2);

        $this->assertTrue($result);
    }

    public function test_verify_webhook_signature_returns_false_without_secret(): void
    {
        putenv('VERIFF_API_SECRET=');

        $result = $this->provider->verifyWebhookSignature('body', []);

        $this->assertFalse($result);
    }

    public function test_verify_webhook_signature_returns_false_without_header(): void
    {
        putenv('VERIFF_API_SECRET=some-secret');

        $result = $this->provider->verifyWebhookSignature('body', []);

        $this->assertFalse($result);

        putenv('VERIFF_API_SECRET=');
    }

    public function test_verify_webhook_signature_matches_correct_hmac(): void
    {
        $secret = 'veriff-hmac-secret';
        putenv('VERIFF_API_SECRET=' . $secret);

        $body = '{"verification":{"id":"ses-123","status":"approved"}}';
        $sig = hash_hmac('sha256', $body, $secret);

        $result = $this->provider->verifyWebhookSignature($body, ['X-HMAC-Signature' => $sig]);

        $this->assertTrue($result);

        putenv('VERIFF_API_SECRET=');
    }

    public function test_handle_webhook_maps_approved_to_passed(): void
    {
        $payload = [
            'verification' => [
                'id' => 'ses-abc',
                'status' => 'approved',
                'vendorData' => json_encode(['nexus_user_id' => 5]),
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('ses-abc', $result['provider_session_id']);
        $this->assertSame('passed', $result['status']);
        $this->assertSame('approved', $result['decision']);
    }

    public function test_handle_webhook_maps_declined_to_failed(): void
    {
        $payload = [
            'verification' => [
                'id' => 'ses-def',
                'status' => 'declined',
                'reason' => 'Suspected fraud',
                'vendorData' => '{}',
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
        $this->assertSame('Suspected fraud', $result['failure_reason']);
    }

    public function test_handle_webhook_maps_resubmission_requested_to_failed(): void
    {
        $payload = [
            'verification' => [
                'id' => 'ses-ghi',
                'status' => 'resubmission_requested',
                'vendorData' => '{}',
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
    }

    public function test_handle_webhook_maps_expired_to_expired(): void
    {
        $payload = [
            'verification' => [
                'id' => 'ses-jkl',
                'status' => 'expired',
                'vendorData' => '{}',
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('expired', $result['status']);
        $this->assertNull($result['decision']);
    }

    public function test_handle_webhook_unknown_status_defaults_to_processing(): void
    {
        $payload = [
            'verification' => [
                'id' => 'ses-mno',
                'status' => 'submitted',
                'vendorData' => '{}',
            ],
        ];

        $result = $this->provider->handleWebhook($payload, []);

        $this->assertSame('processing', $result['status']);
    }
}
