<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Services\AiChatService;
use App\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class AiChatServiceTest extends TestCase
{
    private AiChatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiChatService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AiChatService::class));
    }

    public function testChatReturnsErrorWhenApiKeyMissing(): void
    {
        config(['services.openai.key' => null]);

        $result = $this->service->chat(1, 'Hello');
        $this->assertIsArray($result);
        $this->assertTrue($result['error']);
        $this->assertSame('AI chat is not configured.', $result['reply']);
    }

    public function testChatReturnsArrayWithReplyAndError(): void
    {
        config(['services.openai.key' => null]);

        $result = $this->service->chat(1, 'test message');
        $this->assertArrayHasKey('reply', $result);
        $this->assertArrayHasKey('error', $result);
    }

    public function testChatWithFakeHttpResponse(): void
    {
        config(['services.openai.key' => 'test-key']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Hello from AI']],
                ],
            ], 200),
        ]);

        $result = $this->service->chat(1, 'Hello');
        $this->assertIsArray($result);
        $this->assertFalse($result['error']);
        $this->assertSame('Hello from AI', $result['reply']);
    }

    public function testChatHandlesHttpFailure(): void
    {
        config(['services.openai.key' => 'test-key']);

        Http::fake([
            'api.openai.com/*' => Http::response([], 500),
        ]);

        $result = $this->service->chat(1, 'Hello');
        $this->assertIsArray($result);
        // Should return a reply (possibly the default "No response." or error)
        $this->assertArrayHasKey('reply', $result);
    }

    public function testStreamChatReturnsConfigArray(): void
    {
        $result = $this->service->streamChat(1, 'Hello');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertTrue($result['stream']);
    }

    public function testStreamChatUsesCustomModel(): void
    {
        $result = $this->service->streamChat(1, 'Hello', ['model' => 'gpt-4']);
        $this->assertSame('gpt-4', $result['model']);
    }

    public function testStreamChatCapsMaxTokens(): void
    {
        $result = $this->service->streamChat(1, 'Hello', ['max_tokens' => 9999]);
        $this->assertSame(4096, $result['max_tokens']);
    }

    public function testStreamChatDefaultMaxTokens(): void
    {
        $result = $this->service->streamChat(1, 'Hello');
        $this->assertSame(1024, $result['max_tokens']);
    }

    public function testStreamChatIncludesSystemPrompt(): void
    {
        $result = $this->service->streamChat(1, 'Hello', ['system_prompt' => 'Be brief.']);
        $systemMsg = $result['messages'][0];
        $this->assertSame('system', $systemMsg['role']);
        $this->assertSame('Be brief.', $systemMsg['content']);
    }

    public function testStreamChatIncludesUserMessage(): void
    {
        $result = $this->service->streamChat(1, 'What is timebanking?');
        $lastMsg = end($result['messages']);
        $this->assertSame('user', $lastMsg['role']);
        $this->assertSame('What is timebanking?', $lastMsg['content']);
    }

    public function testGetHistoryReturnsArray(): void
    {
        $result = $this->service->getHistory(1);
        $this->assertIsArray($result);
    }

    public function testGetHistoryLimitsCapped(): void
    {
        // Should not error even with a large limit (capped at 200 internally)
        $result = $this->service->getHistory(1, 500);
        $this->assertIsArray($result);
    }
}
