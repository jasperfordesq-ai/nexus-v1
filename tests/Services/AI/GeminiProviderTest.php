<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services\AI;

use PHPUnit\Framework\TestCase;
use Nexus\Services\AI\Providers\GeminiProvider;
use Nexus\Services\AI\Contracts\AIProviderInterface;

/**
 * Unit tests for GeminiProvider
 *
 * Uses partial mocks to override the protected `request()` method so that
 * no real HTTP calls are made.
 */
class GeminiProviderTest extends TestCase
{
    // ---------------------------------------------------------------
    // Identity methods
    // ---------------------------------------------------------------

    public function testGetIdReturnsGemini(): void
    {
        $provider = new GeminiProvider(['api_key' => 'test-gemini-key']);
        $this->assertEquals('gemini', $provider->getId());
    }

    public function testGetNameReturnsGoogleGemini(): void
    {
        $provider = new GeminiProvider(['api_key' => 'test-gemini-key']);
        $this->assertEquals('Google Gemini', $provider->getName());
    }

    public function testImplementsAIProviderInterface(): void
    {
        $provider = new GeminiProvider(['api_key' => 'test-gemini-key']);
        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    // ---------------------------------------------------------------
    // isConfigured()
    // ---------------------------------------------------------------

    public function testIsConfiguredReturnsTrueWithApiKey(): void
    {
        $provider = new GeminiProvider(['api_key' => 'test-key']);
        $this->assertTrue($provider->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWithoutApiKey(): void
    {
        $provider = new GeminiProvider([]);
        $this->assertFalse($provider->isConfigured());
    }

    // ---------------------------------------------------------------
    // chat() – throws when not configured
    // ---------------------------------------------------------------

    public function testChatThrowsWhenNotConfigured(): void
    {
        $provider = new GeminiProvider([]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Gemini API key not configured');
        $provider->chat([['role' => 'user', 'content' => 'hello']]);
    }

    // ---------------------------------------------------------------
    // chat() – response parsing
    // ---------------------------------------------------------------

    public function testChatParsesResponseCorrectly(): void
    {
        $mockResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Hello from Gemini!'],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 8,
            ],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertEquals('Hello from Gemini!', $result['content']);
        $this->assertEquals(18, $result['tokens_used']); // 10 + 8
        $this->assertEquals(10, $result['tokens_input']);
        $this->assertEquals(8, $result['tokens_output']);
        $this->assertEquals('gemini-2.0-flash', $result['model']);
        $this->assertEquals('stop', $result['finish_reason']); // lowercased
        $this->assertEquals('gemini', $result['provider']);
    }

    public function testChatHandlesEmptyCandidates(): void
    {
        $mockResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => ''],
                        ],
                    ],
                ],
            ],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);
        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'test']]);
        $this->assertEquals('', $result['content']);
        $this->assertEquals(0, $result['tokens_used']);
    }

    public function testChatHandlesMissingCandidateText(): void
    {
        $mockResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [],
                    ],
                ],
            ],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);
        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'test']]);
        $this->assertEquals('', $result['content']);
    }

    // ---------------------------------------------------------------
    // chat() – endpoint routing with model name
    // ---------------------------------------------------------------

    public function testChatUsesCorrectEndpointWithDefaultModel(): void
    {
        $mockResponse = [
            'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                $this->callback(fn($endpoint) =>
                    str_contains($endpoint, 'models/gemini-2.0-flash:generateContent')
                    && str_contains($endpoint, 'key=test-key')
                ),
                $this->isType('array')
            )
            ->willReturn($mockResponse);

        $provider->chat([['role' => 'user', 'content' => 'test']]);
    }

    public function testChatUsesCustomModelEndpoint(): void
    {
        $mockResponse = [
            'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                $this->callback(fn($endpoint) =>
                    str_contains($endpoint, 'models/gemini-1.5-pro:generateContent')
                ),
                $this->isType('array')
            )
            ->willReturn($mockResponse);

        $provider->chat(
            [['role' => 'user', 'content' => 'test']],
            ['model' => 'gemini-1.5-pro']
        );
    }

    // ---------------------------------------------------------------
    // chat() – message format conversion
    // ---------------------------------------------------------------

    public function testChatConvertsMessagesToGeminiFormat(): void
    {
        $mockResponse = [
            'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                $this->isType('string'),
                $this->callback(function ($data) {
                    $contents = $data['contents'];
                    // Should have user and model roles (not assistant)
                    return count($contents) === 2
                        && $contents[0]['role'] === 'user'
                        && $contents[0]['parts'][0]['text'] === 'Hello'
                        && $contents[1]['role'] === 'model'
                        && $contents[1]['parts'][0]['text'] === 'Hi there';
                })
            )
            ->willReturn($mockResponse);

        $provider->chat([
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there'],
        ]);
    }

    public function testChatPrependsSystemMessageToFirstUserMessage(): void
    {
        $mockResponse = [
            'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                $this->isType('string'),
                $this->callback(function ($data) {
                    $contents = $data['contents'];
                    // System message should be prepended to first user message
                    return count($contents) === 1
                        && $contents[0]['role'] === 'user'
                        && str_contains($contents[0]['parts'][0]['text'], 'You are helpful')
                        && str_contains($contents[0]['parts'][0]['text'], 'Hi');
                })
            )
            ->willReturn($mockResponse);

        $provider->chat([
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'Hi'],
        ]);
    }

    // ---------------------------------------------------------------
    // chat() – safety settings and generation config
    // ---------------------------------------------------------------

    public function testChatIncludesSafetySettings(): void
    {
        $mockResponse = [
            'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                $this->isType('string'),
                $this->callback(function ($data) {
                    return isset($data['safetySettings'])
                        && is_array($data['safetySettings'])
                        && count($data['safetySettings']) === 4;
                })
            )
            ->willReturn($mockResponse);

        $provider->chat([['role' => 'user', 'content' => 'test']]);
    }

    public function testChatIncludesGenerationConfig(): void
    {
        $mockResponse = [
            'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                $this->isType('string'),
                $this->callback(function ($data) {
                    $gen = $data['generationConfig'];
                    return $gen['temperature'] === 0.5
                        && $gen['maxOutputTokens'] === 1000
                        && $gen['topP'] === 0.8;
                })
            )
            ->willReturn($mockResponse);

        $provider->chat(
            [['role' => 'user', 'content' => 'test']],
            ['temperature' => 0.5, 'max_tokens' => 1000, 'top_p' => 0.8]
        );
    }

    public function testChatUsesDefaultGenerationConfigValues(): void
    {
        $mockResponse = [
            'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                $this->isType('string'),
                $this->callback(function ($data) {
                    $gen = $data['generationConfig'];
                    return $gen['temperature'] === 0.7
                        && $gen['maxOutputTokens'] === 2048
                        && $gen['topP'] === 0.95;
                })
            )
            ->willReturn($mockResponse);

        $provider->chat([['role' => 'user', 'content' => 'test']]);
    }

    // ---------------------------------------------------------------
    // chat() – finish reason normalization
    // ---------------------------------------------------------------

    public function testChatLowercasesFinishReason(): void
    {
        $mockResponse = [
            'candidates' => [
                [
                    'content' => ['parts' => [['text' => 'ok']]],
                    'finishReason' => 'MAX_TOKENS',
                ],
            ],
            'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 3],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);
        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'test']]);
        $this->assertEquals('max_tokens', $result['finish_reason']);
    }

    // ---------------------------------------------------------------
    // streamChat() – throws when not configured
    // ---------------------------------------------------------------

    public function testStreamChatThrowsWhenNotConfigured(): void
    {
        $provider = new GeminiProvider([]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Gemini API key not configured');
        $provider->streamChat(
            [['role' => 'user', 'content' => 'hello']],
            function () {}
        );
    }

    // ---------------------------------------------------------------
    // embed() – throws when not configured
    // ---------------------------------------------------------------

    public function testEmbedThrowsWhenNotConfigured(): void
    {
        $provider = new GeminiProvider([]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Gemini API key not configured');
        $provider->embed('test text');
    }

    public function testEmbedParsesResponseCorrectly(): void
    {
        $mockResponse = [
            'embedding' => [
                'values' => [0.01, 0.02, 0.03],
            ],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                $this->callback(fn($endpoint) =>
                    str_contains($endpoint, 'text-embedding-004:embedContent')
                    && str_contains($endpoint, 'key=test-key')
                ),
                $this->callback(function ($data) {
                    return $data['content']['parts'][0]['text'] === 'embed this text'
                        && $data['model'] === 'models/text-embedding-004';
                })
            )
            ->willReturn($mockResponse);

        $result = $provider->embed('embed this text');
        $this->assertEquals([0.01, 0.02, 0.03], $result);
    }

    public function testEmbedReturnsEmptyArrayWhenNoEmbedding(): void
    {
        $mockResponse = [];

        $provider = $this->createMockProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-2.0-flash',
        ]);
        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->embed('test');
        $this->assertEquals([], $result);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Create a partial mock of GeminiProvider that overrides the protected
     * request() method so no real HTTP calls are made.
     */
    private function createMockProvider(array $config): GeminiProvider
    {
        return $this->getMockBuilder(GeminiProvider::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['request'])
            ->getMock();
    }
}
