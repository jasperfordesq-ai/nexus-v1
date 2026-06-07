<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI;

use Tests\Laravel\TestCase;
use App\Services\AI\Providers\OllamaProvider;

class OllamaProviderTest extends TestCase
{
    public function test_getId_returns_ollama(): void
    {
        $provider = new OllamaProvider([]);
        $this->assertEquals('ollama', $provider->getId());
    }

    public function test_getName_returns_ollama_self_hosted(): void
    {
        $provider = new OllamaProvider([]);
        $this->assertEquals('Ollama (Self-hosted)', $provider->getName());
    }

    public function test_isConfigured_returns_true_with_public_url(): void
    {
        // isConfigured() now runs the OutboundUrlGuard SSRF check, so the
        // configured host must resolve to a public IP. A public-IP URL passes;
        // loopback/private hosts (incl. the localhost default) are rejected.
        $provider = new OllamaProvider(['api_url' => 'https://93.184.216.34:11434']);
        $this->assertTrue($provider->isConfigured());
    }

    public function test_isConfigured_returns_false_for_loopback_default(): void
    {
        // The default URL is http://localhost:11434. The SSRF guard
        // (OutboundUrlGuard) blocks loopback/local hostnames, so a self-hosted
        // Ollama on localhost is intentionally treated as not safe-configured
        // for server-side outbound calls.
        $provider = new OllamaProvider([]);
        $this->assertFalse($provider->isConfigured());
    }

    public function test_isConfigured_returns_false_with_empty_url(): void
    {
        $provider = new OllamaProvider(['api_url' => '']);
        $this->assertFalse($provider->isConfigured());
    }

    public function test_chat_throws_when_not_configured(): void
    {
        $provider = new OllamaProvider(['api_url' => '']);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not configured');
        $provider->chat([['role' => 'user', 'content' => 'hello']]);
    }

    public function test_embed_throws_when_not_configured(): void
    {
        $provider = new OllamaProvider(['api_url' => '']);
        $this->expectException(\Exception::class);
        $provider->embed('test');
    }

    public function test_testConnection_fails_when_not_configured(): void
    {
        $provider = new OllamaProvider(['api_url' => '']);
        $result = $provider->testConnection();
        $this->assertFalse($result['success']);
    }

    public function test_getModels_returns_config_models_on_failure(): void
    {
        $provider = new OllamaProvider([
            'api_url' => 'http://nonexistent:11434',
            'models' => ['llama3' => ['name' => 'Llama 3']],
        ]);

        $models = $provider->getModels();
        $this->assertArrayHasKey('llama3', $models);
    }
}
