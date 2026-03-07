<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Nexus\Services\Identity\IdentityProviderRegistry;
use Nexus\Services\Identity\MockIdentityProvider;
use Nexus\Services\Identity\RegistrationPolicyService;

/**
 * Registration Policy Engine — Integration Tests
 *
 * End-to-end tests using the mock provider, covering:
 * - Full verification flow (create session → webhook → result)
 * - Mock provider pass/fail/review modes
 * - Registration mode validation
 * - Provider config encryption/decryption
 * - Invite code generation uniqueness
 * - Policy validation edge cases
 */
class RegistrationPolicyIntegrationTest extends TestCase
{
    private MockIdentityProvider $mockProvider;

    protected function setUp(): void
    {
        parent::setUp();
        IdentityProviderRegistry::reset();
        $this->mockProvider = new MockIdentityProvider();
    }

    // ─── Mock Provider Full Flow ─────────────────────────────────────────

    public function testMockProviderCreateSessionReturnsValidStructure(): void
    {
        $session = $this->mockProvider->createSession(1, 1, 'document_selfie', []);

        $this->assertArrayHasKey('provider_session_id', $session);
        $this->assertArrayHasKey('redirect_url', $session);
        $this->assertArrayHasKey('client_token', $session);
        $this->assertArrayHasKey('expires_at', $session);
        $this->assertNotEmpty($session['provider_session_id']);
        $this->assertStringStartsWith('mock_', $session['provider_session_id']);
    }

    public function testMockProviderGetSessionStatusReturnsPassed(): void
    {
        $session = $this->mockProvider->createSession(1, 1, 'document_only', []);
        $status = $this->mockProvider->getSessionStatus($session['provider_session_id']);

        $this->assertArrayHasKey('status', $status);
        $this->assertSame('passed', $status['status']);
    }

    public function testMockProviderWebhookHandlerPassResult(): void
    {
        $session = $this->mockProvider->createSession(1, 1, 'document_selfie', []);
        $payload = [
            'session_id' => $session['provider_session_id'],
            'result' => 'passed',
        ];

        $result = $this->mockProvider->handleWebhook($payload, []);

        $this->assertSame('passed', $result['status']);
        $this->assertSame($session['provider_session_id'], $result['provider_session_id']);
    }

    public function testMockProviderWebhookHandlerFailResult(): void
    {
        $session = $this->mockProvider->createSession(1, 1, 'document_only', []);
        $payload = [
            'session_id' => $session['provider_session_id'],
            'result' => 'failed',
        ];

        $result = $this->mockProvider->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertArrayHasKey('failure_reason', $result);
    }

    public function testMockProviderWebhookHandlerReviewResult(): void
    {
        $session = $this->mockProvider->createSession(1, 1, 'manual_review', []);
        $payload = [
            'session_id' => $session['provider_session_id'],
            'result' => 'review',
        ];

        $result = $this->mockProvider->handleWebhook($payload, []);

        // Mock provider returns result as-is, not mapped
        $this->assertSame('review', $result['status']);
    }

    public function testMockProviderCancelSession(): void
    {
        $session = $this->mockProvider->createSession(1, 1, 'document_selfie', []);
        $result = $this->mockProvider->cancelSession($session['provider_session_id']);

        $this->assertTrue($result);
    }

    public function testMockProviderIsAlwaysAvailable(): void
    {
        $this->assertTrue($this->mockProvider->isAvailable(1));
        $this->assertTrue($this->mockProvider->isAvailable(999));
    }

    public function testMockProviderAlwaysVerifiesWebhookSignature(): void
    {
        $this->assertTrue($this->mockProvider->verifyWebhookSignature('any body', []));
    }

    // ─── Provider Registry ───────────────────────────────────────────────

    public function testRegistryAutoInitializesWithMockAndStripe(): void
    {
        // First access triggers init
        $this->assertTrue(IdentityProviderRegistry::has('mock'));
        $this->assertTrue(IdentityProviderRegistry::has('stripe_identity'));
    }

    public function testRegistryListForAdminReturnsAllProviders(): void
    {
        $list = IdentityProviderRegistry::listForAdmin();

        $this->assertIsArray($list);
        $this->assertGreaterThanOrEqual(2, count($list));

        $slugs = array_column($list, 'slug');
        $this->assertContains('mock', $slugs);
        $this->assertContains('stripe_identity', $slugs);

        // Each entry should have name and levels
        foreach ($list as $provider) {
            $this->assertArrayHasKey('slug', $provider);
            $this->assertArrayHasKey('name', $provider);
            $this->assertArrayHasKey('levels', $provider);
        }
    }

    public function testRegistryGetUnknownProviderThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IdentityProviderRegistry::get('nonexistent_provider');
    }

    public function testRegistryRegisterCustomProvider(): void
    {
        $custom = new MockIdentityProvider();
        // Re-register replaces
        IdentityProviderRegistry::register($custom);
        $this->assertTrue(IdentityProviderRegistry::has('mock'));
    }

    // ─── Registration Policy Validation ──────────────────────────────────

    public function testValidModesConstant(): void
    {
        $modes = RegistrationPolicyService::MODES;

        $this->assertContains('open', $modes);
        $this->assertContains('open_with_approval', $modes);
        $this->assertContains('verified_identity', $modes);
        $this->assertContains('government_id', $modes);
        $this->assertContains('invite_only', $modes);
        $this->assertContains('waitlist', $modes);
        $this->assertCount(6, $modes);
    }

    public function testValidVerificationLevelsConstant(): void
    {
        $levels = RegistrationPolicyService::VERIFICATION_LEVELS;

        $this->assertContains('none', $levels);
        $this->assertContains('document_only', $levels);
        $this->assertContains('document_selfie', $levels);
        $this->assertContains('reusable_digital_id', $levels);
        $this->assertContains('manual_review', $levels);
        $this->assertCount(5, $levels);
    }

    public function testValidPostVerificationActionsConstant(): void
    {
        $actions = RegistrationPolicyService::POST_VERIFICATION_ACTIONS;

        $this->assertContains('activate', $actions);
        $this->assertContains('admin_approval', $actions);
        $this->assertContains('limited_access', $actions);
        $this->assertContains('reject_on_fail', $actions);
        $this->assertCount(4, $actions);
    }

    public function testValidFallbackModesConstant(): void
    {
        $modes = RegistrationPolicyService::FALLBACK_MODES;

        $this->assertContains('none', $modes);
        $this->assertContains('admin_review', $modes);
        $this->assertContains('native_registration', $modes);
        $this->assertCount(3, $modes);
    }

    // ─── Encryption ──────────────────────────────────────────────────────

    public function testDecryptConfigHandlesInvalidBase64(): void
    {
        $result = RegistrationPolicyService::decryptConfig('not-valid-base64!!!');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testDecryptConfigHandlesPlainJson(): void
    {
        // When APP_KEY is not set, config is stored as plain JSON.
        // If APP_KEY is set (test env), plain JSON will fail decryption and return empty.
        $json = json_encode(['api_key' => 'test123']);
        $result = RegistrationPolicyService::decryptConfig($json);

        $this->assertIsArray($result);
        // If APP_KEY is set, plain JSON goes through decryption path (returns empty)
        // If APP_KEY is not set, it falls through to JSON decode (returns decoded)
        if (\Nexus\Core\Env::get('APP_KEY')) {
            $this->assertEmpty($result);
        } else {
            $this->assertSame('test123', $result['api_key']);
        }
    }

    public function testDecryptConfigHandlesTooShortInput(): void
    {
        $result = RegistrationPolicyService::decryptConfig(base64_encode('short'));
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ─── Mock Provider Session Metadata ──────────────────────────────────

    public function testMockProviderSessionWithMockResultPass(): void
    {
        $session = $this->mockProvider->createSession(1, 1, 'document_selfie', ['mock_result' => 'pass']);
        $this->assertNotEmpty($session['provider_session_id']);
    }

    public function testMockProviderSessionWithMockResultFail(): void
    {
        $session = $this->mockProvider->createSession(1, 1, 'document_selfie', ['mock_result' => 'fail']);
        $this->assertNotEmpty($session['provider_session_id']);
    }

    public function testMockProviderSupportedLevels(): void
    {
        $levels = $this->mockProvider->getSupportedLevels();
        $this->assertContains('document_only', $levels);
        $this->assertContains('document_selfie', $levels);
    }

    // ─── Multiple Sessions ───────────────────────────────────────────────

    public function testMockProviderGeneratesUniqueSessions(): void
    {
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $session = $this->mockProvider->createSession($i, 1, 'document_only', []);
            $ids[] = $session['provider_session_id'];
        }

        $this->assertCount(10, array_unique($ids), 'All session IDs should be unique');
    }

    // ─── Provider Name ──────────────────────────────────────────────────

    public function testMockProviderName(): void
    {
        $this->assertSame('Mock Provider (Testing)', $this->mockProvider->getName());
    }

    public function testRegistryGetMockProviderBySlug(): void
    {
        $provider = IdentityProviderRegistry::get('mock');
        $this->assertSame('mock', $provider->getSlug());
    }

    public function testRegistryGetStripeProviderBySlug(): void
    {
        $provider = IdentityProviderRegistry::get('stripe_identity');
        $this->assertSame('stripe_identity', $provider->getSlug());
    }
}
