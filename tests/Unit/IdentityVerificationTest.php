<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\Identity\IdentityProviderRegistry;
use Nexus\Services\Identity\MockIdentityProvider;
use Nexus\Services\Identity\IdentityVerificationProviderInterface;
use App\Services\Identity\RegistrationPolicyService;

/**
 * Identity Verification Unit Tests
 *
 * Pure unit tests for the identity verification provider abstraction.
 * These do NOT require a database connection.
 *
 * Tests:
 * - Provider interface contract compliance (mock provider)
 * - Provider registry (register, get, has, list)
 * - Mock provider session creation, status, webhooks
 * - Registration policy mode/level/action validation
 */
class IdentityVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        IdentityProviderRegistry::reset();
    }

    // ─── Provider Interface Tests ────────────────────────────────────────

    public function testMockProviderImplementsInterface(): void
    {
        $provider = new MockIdentityProvider();
        $this->assertInstanceOf(IdentityVerificationProviderInterface::class, $provider);
    }

    public function testMockProviderSlug(): void
    {
        $provider = new MockIdentityProvider();
        $this->assertSame('mock', $provider->getSlug());
    }

    public function testMockProviderName(): void
    {
        $provider = new MockIdentityProvider();
        $this->assertSame('Mock Provider (Testing)', $provider->getName());
    }

    public function testMockProviderSupportedLevels(): void
    {
        $provider = new MockIdentityProvider();
        $levels = $provider->getSupportedLevels();

        $this->assertIsArray($levels);
        $this->assertContains('document_only', $levels);
        $this->assertContains('document_selfie', $levels);
        $this->assertContains('reusable_digital_id', $levels);
        $this->assertContains('manual_review', $levels);
    }

    public function testMockProviderCreateSession(): void
    {
        $provider = new MockIdentityProvider();
        $session = $provider->createSession(1, 1, 'document_selfie');

        $this->assertArrayHasKey('provider_session_id', $session);
        $this->assertArrayHasKey('client_token', $session);
        $this->assertArrayHasKey('expires_at', $session);
        $this->assertStringStartsWith('mock_', $session['provider_session_id']);
        $this->assertNull($session['redirect_url']);
    }

    public function testMockProviderCreateSessionWithCustomResult(): void
    {
        $provider = new MockIdentityProvider();
        $session = $provider->createSession(1, 1, 'document_only', ['mock_result' => 'fail']);

        $this->assertSame('fail', $session['mock_result']);
    }

    public function testMockProviderGetSessionStatus(): void
    {
        $provider = new MockIdentityProvider();
        $status = $provider->getSessionStatus('mock_abc123');

        $this->assertArrayHasKey('status', $status);
        $this->assertSame('passed', $status['status']);
        $this->assertNull($status['failure_reason']);
    }

    public function testMockProviderHandleWebhook(): void
    {
        $provider = new MockIdentityProvider();

        $result = $provider->handleWebhook(
            ['session_id' => 'mock_abc', 'result' => 'passed'],
            []
        );

        $this->assertSame('mock_abc', $result['provider_session_id']);
        $this->assertSame('passed', $result['status']);
        $this->assertSame('approved', $result['decision']);
        $this->assertNull($result['failure_reason']);
    }

    public function testMockProviderHandleWebhookFailure(): void
    {
        $provider = new MockIdentityProvider();

        $result = $provider->handleWebhook(
            ['session_id' => 'mock_xyz', 'result' => 'failed'],
            []
        );

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
        $this->assertNotNull($result['failure_reason']);
    }

    public function testMockProviderVerifyWebhookSignature(): void
    {
        $provider = new MockIdentityProvider();
        // Mock always trusts
        $this->assertTrue($provider->verifyWebhookSignature('any body', []));
    }

    public function testMockProviderCancelSession(): void
    {
        $provider = new MockIdentityProvider();
        $this->assertTrue($provider->cancelSession('mock_abc'));
    }

    public function testMockProviderIsAlwaysAvailable(): void
    {
        $provider = new MockIdentityProvider();
        $this->assertTrue($provider->isAvailable(1));
        $this->assertTrue($provider->isAvailable(999));
    }

    // ─── Provider Registry Tests ─────────────────────────────────────────

    public function testRegistryRegisterAndGet(): void
    {
        // Trigger auto-initialization first, then override with our instance
        IdentityProviderRegistry::has('mock');

        $provider = new MockIdentityProvider();
        IdentityProviderRegistry::register($provider);

        $retrieved = IdentityProviderRegistry::get('mock');
        $this->assertSame($provider, $retrieved);
    }

    public function testRegistryHas(): void
    {
        $this->assertFalse(IdentityProviderRegistry::has('nonexistent'));

        IdentityProviderRegistry::register(new MockIdentityProvider());
        $this->assertTrue(IdentityProviderRegistry::has('mock'));
    }

    public function testRegistryGetUnknownThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Identity verification provider 'unknown' is not registered");
        IdentityProviderRegistry::get('unknown');
    }

    public function testRegistryAutoInitializesWithMock(): void
    {
        // On first access, registry auto-registers built-in providers
        $this->assertTrue(IdentityProviderRegistry::has('mock'));
    }

    public function testRegistryListForAdmin(): void
    {
        $list = IdentityProviderRegistry::listForAdmin();

        $this->assertIsArray($list);
        $this->assertGreaterThanOrEqual(1, count($list));

        $mock = $list[0];
        $this->assertSame('mock', $mock['slug']);
        $this->assertSame('Mock Provider (Testing)', $mock['name']);
        $this->assertIsArray($mock['levels']);
    }

    public function testRegistryReset(): void
    {
        IdentityProviderRegistry::register(new MockIdentityProvider());
        $this->assertTrue(IdentityProviderRegistry::has('mock'));

        IdentityProviderRegistry::reset();
        // After reset, auto-init will re-register mock on next access
        // But if we check without triggering init, it should be empty
        // Since has() triggers init, mock will be back
        $this->assertTrue(IdentityProviderRegistry::has('mock'));
    }

    // ─── Registration Policy Validation Tests ────────────────────────────

    public function testValidRegistrationModes(): void
    {
        $this->assertContains('open', RegistrationPolicyService::MODES);
        $this->assertContains('open_with_approval', RegistrationPolicyService::MODES);
        $this->assertContains('verified_identity', RegistrationPolicyService::MODES);
        $this->assertContains('government_id', RegistrationPolicyService::MODES);
        $this->assertContains('invite_only', RegistrationPolicyService::MODES);
        $this->assertContains('waitlist', RegistrationPolicyService::MODES);
        $this->assertCount(6, RegistrationPolicyService::MODES);
    }

    public function testValidVerificationLevels(): void
    {
        $this->assertContains('none', RegistrationPolicyService::VERIFICATION_LEVELS);
        $this->assertContains('document_only', RegistrationPolicyService::VERIFICATION_LEVELS);
        $this->assertContains('document_selfie', RegistrationPolicyService::VERIFICATION_LEVELS);
        $this->assertContains('reusable_digital_id', RegistrationPolicyService::VERIFICATION_LEVELS);
        $this->assertContains('manual_review', RegistrationPolicyService::VERIFICATION_LEVELS);
        $this->assertCount(5, RegistrationPolicyService::VERIFICATION_LEVELS);
    }

    public function testValidPostVerificationActions(): void
    {
        $this->assertContains('activate', RegistrationPolicyService::POST_VERIFICATION_ACTIONS);
        $this->assertContains('admin_approval', RegistrationPolicyService::POST_VERIFICATION_ACTIONS);
        $this->assertContains('limited_access', RegistrationPolicyService::POST_VERIFICATION_ACTIONS);
        $this->assertContains('reject_on_fail', RegistrationPolicyService::POST_VERIFICATION_ACTIONS);
        $this->assertCount(4, RegistrationPolicyService::POST_VERIFICATION_ACTIONS);
    }

    public function testValidFallbackModes(): void
    {
        $this->assertContains('none', RegistrationPolicyService::FALLBACK_MODES);
        $this->assertContains('admin_review', RegistrationPolicyService::FALLBACK_MODES);
        $this->assertContains('native_registration', RegistrationPolicyService::FALLBACK_MODES);
        $this->assertCount(3, RegistrationPolicyService::FALLBACK_MODES);
    }

    // ─── Encryption Tests (RegistrationPolicyService) ────────────────────

    public function testDecryptConfigHandlesPlainJsonFallback(): void
    {
        // When APP_KEY is set, decryptConfig tries AES decryption first,
        // which fails on plain JSON and returns empty array.
        // When APP_KEY is not set, it falls back to json_decode.
        $plainJson = json_encode(['api_key' => 'test123']);
        $result = RegistrationPolicyService::decryptConfig($plainJson);

        $this->assertIsArray($result);
        if (!empty(\App\Core\Env::get('APP_KEY'))) {
            // AES decryption of plain JSON fails → empty array
            $this->assertEmpty($result);
        } else {
            $this->assertSame('test123', $result['api_key']);
        }
    }

    public function testDecryptConfigHandlesInvalidInput(): void
    {
        $result = RegistrationPolicyService::decryptConfig('not-valid-base64-or-json');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testDecryptConfigHandlesEmptyInput(): void
    {
        $result = RegistrationPolicyService::decryptConfig('');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
