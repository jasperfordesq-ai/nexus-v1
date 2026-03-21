<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI;

use Tests\Laravel\TestCase;
use App\Services\AI\Providers\GeminiProvider;

class GeminiProviderTest extends TestCase
{
    public function test_getId_returns_gemini(): void
    {
        $provider = new GeminiProvider([]);
        $this->assertEquals('gemini', $provider->getId());
    }

    public function test_getName_returns_google_gemini(): void
    {
        $provider = new GeminiProvider([]);
        $this->assertEquals('Google Gemini', $provider->getName());
    }

    public function test_isConfigured_returns_false_without_key(): void
    {
        $provider = new GeminiProvider([]);
        $this->assertFalse($provider->isConfigured());
    }

    public function test_isConfigured_returns_true_with_key(): void
    {
        $provider = new GeminiProvider(['api_key' => 'AIzaSy-test']);
        $this->assertTrue($provider->isConfigured());
    }

    public function test_chat_throws_when_not_configured(): void
    {
        $provider = new GeminiProvider([]);
        $this->expectException(\Exception::class);
        $provider->chat([['role' => 'user', 'content' => 'hello']]);
    }

    public function test_streamChat_throws_when_not_configured(): void
    {
        $provider = new GeminiProvider([]);
        $this->expectException(\Exception::class);
        $provider->streamChat([['role' => 'user', 'content' => 'hello']], fn() => null);
    }

    public function test_embed_throws_when_not_configured(): void
    {
        $provider = new GeminiProvider([]);
        $this->expectException(\Exception::class);
        $provider->embed('test');
    }

    public function test_testConnection_fails_without_key(): void
    {
        $provider = new GeminiProvider([]);
        $result = $provider->testConnection();
        $this->assertFalse($result['success']);
    }

    public function test_complete_delegates_to_chat(): void
    {
        $provider = new GeminiProvider([]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not configured');
        $provider->complete('hello');
    }
}
