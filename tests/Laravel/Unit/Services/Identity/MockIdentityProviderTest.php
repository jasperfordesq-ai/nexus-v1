<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Services\Identity\MockIdentityProvider;

class MockIdentityProviderTest extends TestCase
{
    private MockIdentityProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new MockIdentityProvider();
    }

    public function test_getSlug_returns_mock(): void
    {
        $this->assertEquals('mock', $this->provider->getSlug());
    }

    public function test_getName_returns_mock_provider(): void
    {
        $this->assertStringContainsString('Mock', $this->provider->getName());
    }

    public function test_getSupportedLevels_returns_expected_levels(): void
    {
        $levels = $this->provider->getSupportedLevels();

        $this->assertContains('document_only', $levels);
        $this->assertContains('document_selfie', $levels);
        $this->assertContains('manual_review', $levels);
    }

    public function test_createSession_returns_expected_structure(): void
    {
        $result = $this->provider->createSession(1, 2, 'document_only');

        $this->assertArrayHasKey('provider_session_id', $result);
        $this->assertArrayHasKey('client_token', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertStringStartsWith('mock_', $result['provider_session_id']);
    }

    public function test_createSession_respects_mock_result_metadata(): void
    {
        $result = $this->provider->createSession(1, 2, 'document_only', ['mock_result' => 'fail']);

        $this->assertEquals('fail', $result['mock_result']);
    }

    public function test_getSessionStatus_returns_passed(): void
    {
        $result = $this->provider->getSessionStatus('mock_session_123');

        $this->assertEquals('passed', $result['status']);
        $this->assertEquals('approved', $result['decision']);
    }

    public function test_handleWebhook_returns_approved_for_passed(): void
    {
        $result = $this->provider->handleWebhook(['session_id' => 'test', 'result' => 'passed'], []);

        $this->assertEquals('approved', $result['decision']);
        $this->assertNull($result['failure_reason']);
    }

    public function test_handleWebhook_returns_declined_for_failed(): void
    {
        $result = $this->provider->handleWebhook(['session_id' => 'test', 'result' => 'failed'], []);

        $this->assertEquals('declined', $result['decision']);
        $this->assertNotNull($result['failure_reason']);
    }

    public function test_verifyWebhookSignature_always_returns_true(): void
    {
        $this->assertTrue($this->provider->verifyWebhookSignature('body', []));
    }

    public function test_cancelSession_always_returns_true(): void
    {
        $this->assertTrue($this->provider->cancelSession('mock_123'));
    }

    public function test_isAvailable_always_returns_true(): void
    {
        $this->assertTrue($this->provider->isAvailable(2));
    }
}
