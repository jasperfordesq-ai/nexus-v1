<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI;

use Tests\Laravel\TestCase;
use App\Services\AI\Providers\AnthropicProvider;

class AnthropicProviderTest extends TestCase
{
    public function test_getId_returns_anthropic(): void
    {
        $provider = new AnthropicProvider([]);
        $this->assertEquals('anthropic', $provider->getId());
    }

    public function test_getName_returns_anthropic_claude(): void
    {
        $provider = new AnthropicProvider([]);
        $this->assertEquals('Anthropic Claude', $provider->getName());
    }

    public function test_isConfigured_returns_false_without_key(): void
    {
        $provider = new AnthropicProvider([]);
        $this->assertFalse($provider->isConfigured());
    }

    public function test_isConfigured_returns_true_with_key(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'sk-ant-test']);
        $this->assertTrue($provider->isConfigured());
    }

    public function test_chat_throws_when_not_configured(): void
    {
        $provider = new AnthropicProvider([]);
        $this->expectException(\Exception::class);
        $provider->chat([['role' => 'user', 'content' => 'hello']]);
    }

    public function test_streamChat_throws_when_not_configured(): void
    {
        $provider = new AnthropicProvider([]);
        $this->expectException(\Exception::class);
        $provider->streamChat([['role' => 'user', 'content' => 'hello']], fn() => null);
    }

    public function test_embed_throws_unsupported(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'test']);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('embeddings');
        $provider->embed('test');
    }

    public function test_testConnection_fails_without_key(): void
    {
        $provider = new AnthropicProvider([]);
        $result = $provider->testConnection();
        $this->assertFalse($result['success']);
    }
}
