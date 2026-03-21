<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI;

use Tests\Laravel\TestCase;
use App\Services\AI\Providers\OpenAIProvider;

class OpenAIProviderTest extends TestCase
{
    public function test_getId_returns_openai(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'test-key', 'api_url' => 'https://api.openai.com/v1', 'default_model' => 'gpt-4']);
        $this->assertEquals('openai', $provider->getId());
    }

    public function test_getName_returns_openai(): void
    {
        $provider = new OpenAIProvider([]);
        $this->assertEquals('OpenAI', $provider->getName());
    }

    public function test_isConfigured_returns_false_without_key(): void
    {
        $provider = new OpenAIProvider([]);
        $this->assertFalse($provider->isConfigured());
    }

    public function test_isConfigured_returns_true_with_key(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'sk-test123']);
        $this->assertTrue($provider->isConfigured());
    }

    public function test_chat_throws_when_not_configured(): void
    {
        $provider = new OpenAIProvider([]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not configured');

        $provider->chat([['role' => 'user', 'content' => 'hello']]);
    }

    public function test_streamChat_throws_when_not_configured(): void
    {
        $provider = new OpenAIProvider([]);
        $this->expectException(\Exception::class);

        $provider->streamChat([['role' => 'user', 'content' => 'hello']], fn() => null);
    }

    public function test_embed_throws_when_not_configured(): void
    {
        $provider = new OpenAIProvider([]);
        $this->expectException(\Exception::class);

        $provider->embed('test text');
    }

    public function test_getModels_returns_config_models(): void
    {
        $provider = new OpenAIProvider(['models' => ['gpt-4' => [], 'gpt-3.5-turbo' => []]]);
        $this->assertCount(2, $provider->getModels());
    }

    public function test_testConnection_returns_failure_when_not_configured(): void
    {
        $provider = new OpenAIProvider([]);
        $result = $provider->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not configured', $result['message']);
    }
}
