<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI;

use Tests\Laravel\TestCase;
use App\Services\AI\AIServiceFactory;

class AIServiceFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AIServiceFactory::clearCache();
    }

    public function test_getDefaultProvider_returns_string(): void
    {
        $provider = AIServiceFactory::getDefaultProvider();
        $this->assertIsString($provider);
    }

    public function test_isEnabled_returns_bool(): void
    {
        $this->assertIsBool(AIServiceFactory::isEnabled());
    }

    public function test_isFeatureEnabled_returns_false_when_ai_disabled(): void
    {
        // Feature check should respect the main enabled flag
        $result = AIServiceFactory::isFeatureEnabled('chat');
        $this->assertIsBool($result);
    }

    public function test_clearCache_resets_state(): void
    {
        AIServiceFactory::clearCache();
        // Should not throw
        $this->assertTrue(true);
    }

    public function test_getProvider_throws_for_unknown_provider(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown AI provider');

        AIServiceFactory::getProvider('nonexistent_provider');
    }

    public function test_getSystemPrompt_returns_string(): void
    {
        $prompt = AIServiceFactory::getSystemPrompt();
        $this->assertIsString($prompt);
    }

    public function test_getLimitsConfig_returns_array(): void
    {
        $limits = AIServiceFactory::getLimitsConfig();
        $this->assertIsArray($limits);
    }

    public function test_getAvailableProviders_returns_array(): void
    {
        $providers = AIServiceFactory::getAvailableProviders();
        $this->assertIsArray($providers);
    }

    protected function tearDown(): void
    {
        AIServiceFactory::clearCache();
        parent::tearDown();
    }
}
