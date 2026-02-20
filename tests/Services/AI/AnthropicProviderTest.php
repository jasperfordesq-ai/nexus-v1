<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services\AI;

use PHPUnit\Framework\TestCase;
use Nexus\Services\AI\Providers\AnthropicProvider;
use Nexus\Services\AI\Contracts\AIProviderInterface;

/**
 * Unit tests for AnthropicProvider
 *
 * Uses partial mocks to override the protected `request()` method so that
 * no real HTTP calls are made.
 */
class AnthropicProviderTest extends TestCase
{
    // ---------------------------------------------------------------
    // Identity methods
    // ---------------------------------------------------------------

    public function testGetIdReturnsAnthropic(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'sk-ant-test']);
        $this->assertEquals('anthropic', $provider->getId());
    }

    public function testGetNameReturnsAnthropicClaude(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'sk-ant-test']);
        $this->assertEquals('Anthropic Claude', $provider->getName());
    }

    public function testImplementsAIProviderInterface(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'sk-ant-test']);
        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    // ---------------------------------------------------------------
    // Constructor & API version
    // ---------------------------------------------------------------

    public function testConstructorSetsDefaultApiVersion(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'sk-ant-test']);
        $ref = new \ReflectionProperty($provider, 'apiVersion');
        $ref->setAccessible(true);
        $this->assertEquals('2023-06-01', $ref->getValue($provider));
    }

    public function testConstructorSetsCustomApiVersion(): void
    {
        $provider = new AnthropicProvider([
            'api_key' => 'sk-ant-test',
            'api_version' => '2024-01-01',
        ]);
        $ref = new \ReflectionProperty($provider, 'apiVersion');
        $ref->setAccessible(true);
        $this->assertEquals('2024-01-01', $ref->getValue($provider));
    }

    // ---------------------------------------------------------------
    // isConfigured()
    // ---------------------------------------------------------------

    public function testIsConfiguredReturnsTrueWithApiKey(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'sk-ant-test']);
        $this->assertTrue($provider->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWithoutApiKey(): void
    {
        $provider = new AnthropicProvider([]);
        $this->assertFalse($provider->isConfigured());
    }

    // ---------------------------------------------------------------
    // chat() – throws when not configured
    // ---------------------------------------------------------------

    public function testChatThrowsWhenNotConfigured(): void
    {
        $provider = new AnthropicProvider([]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Anthropic API key not configured');
        $provider->chat([['role' => 'user', 'content' => 'hello']]);
    }

    // ---------------------------------------------------------------
    // chat() – response parsing
    // ---------------------------------------------------------------

    public function testChatParsesResponseCorrectly(): void
    {
        $mockResponse = [
            'content' => [
                ['type' => 'text', 'text' => 'Hello! I am Claude.'],
            ],
            'usage' => [
                'input_tokens' => 12,
                'output_tokens' => 8,
            ],
            'stop_reason' => 'end_turn',
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'sk-ant-test-123',
            'default_model' => 'claude-sonnet-4-20250514',
        ]);
        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertEquals('Hello! I am Claude.', $result['content']);
        $this->assertEquals(20, $result['tokens_used']); // 12 + 8
        $this->assertEquals(12, $result['tokens_input']);
        $this->assertEquals(8, $result['tokens_output']);
        $this->assertEquals('claude-sonnet-4-20250514', $result['model']);
        $this->assertEquals('end_turn', $result['finish_reason']);
        $this->assertEquals('anthropic', $result['provider']);
    }

    public function testChatHandlesMultipleTextBlocks(): void
    {
        $mockResponse = [
            'content' => [
                ['type' => 'text', 'text' => 'Part one. '],
                ['type' => 'text', 'text' => 'Part two.'],
            ],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 10],
            'stop_reason' => 'stop',
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'sk-ant-test',
            'default_model' => 'claude-sonnet-4-20250514',
        ]);
        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'test']]);
        $this->assertEquals('Part one. Part two.', $result['content']);
    }

    public function testChatSkipsNonTextBlocks(): void
    {
        $mockResponse = [
            'content' => [
                ['type' => 'text', 'text' => 'Text content'],
                ['type' => 'tool_use', 'id' => 'tool-1', 'name' => 'calculator'],
            ],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 10],
            'stop_reason' => 'stop',
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'sk-ant-test',
            'default_model' => 'claude-sonnet-4-20250514',
        ]);
        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'test']]);
        $this->assertEquals('Text content', $result['content']);
    }

    // ---------------------------------------------------------------
    // chat() – system message extraction
    // ---------------------------------------------------------------

    public function testChatExtractsSystemMessage(): void
    {
        $mockResponse = [
            'content' => [['type' => 'text', 'text' => 'response']],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 5],
            'stop_reason' => 'stop',
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'sk-ant-test',
            'default_model' => 'claude-sonnet-4-20250514',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'messages',
                $this->callback(function ($data) {
                    // System prompt should be extracted to the top-level 'system' field
                    return isset($data['system'])
                        && str_contains($data['system'], 'You are helpful')
                        // Only user messages should remain in 'messages' array
                        && count($data['messages']) === 1
                        && $data['messages'][0]['role'] === 'user';
                }),
                $this->isType('array')
            )
            ->willReturn($mockResponse);

        $provider->chat([
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'Hi'],
        ]);
    }

    public function testChatDoesNotSetSystemFieldWhenNoSystemMessage(): void
    {
        $mockResponse = [
            'content' => [['type' => 'text', 'text' => 'response']],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 5],
            'stop_reason' => 'stop',
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'sk-ant-test',
            'default_model' => 'claude-sonnet-4-20250514',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'messages',
                $this->callback(fn($data) => !isset($data['system'])),
                $this->isType('array')
            )
            ->willReturn($mockResponse);

        $provider->chat([['role' => 'user', 'content' => 'Hi']]);
    }

    // ---------------------------------------------------------------
    // API key sanitization
    // ---------------------------------------------------------------

    public function testChatTrimsApiKeyWhitespace(): void
    {
        $mockResponse = [
            'content' => [['type' => 'text', 'text' => 'ok']],
            'usage' => ['input_tokens' => 2, 'output_tokens' => 1],
            'stop_reason' => 'stop',
        ];

        // API key has leading/trailing whitespace and newlines
        $provider = $this->createMockProvider([
            'api_key' => "  sk-ant-test-key\n ",
            'default_model' => 'claude-sonnet-4-20250514',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'messages',
                $this->isType('array'),
                $this->callback(function ($headers) {
                    foreach ($headers as $header) {
                        if (str_starts_with($header, 'x-api-key:')) {
                            // The key should be trimmed — no spaces or newlines
                            return $header === 'x-api-key: sk-ant-test-key';
                        }
                    }
                    return false;
                })
            )
            ->willReturn($mockResponse);

        $provider->chat([['role' => 'user', 'content' => 'test']]);
    }

    // ---------------------------------------------------------------
    // chat() – headers include anthropic-version
    // ---------------------------------------------------------------

    public function testChatIncludesAnthropicVersionHeader(): void
    {
        $mockResponse = [
            'content' => [['type' => 'text', 'text' => 'ok']],
            'usage' => ['input_tokens' => 2, 'output_tokens' => 1],
            'stop_reason' => 'stop',
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'sk-ant-test',
            'default_model' => 'claude-sonnet-4-20250514',
            'api_version' => '2023-06-01',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'messages',
                $this->isType('array'),
                $this->callback(function ($headers) {
                    return in_array('anthropic-version: 2023-06-01', $headers);
                })
            )
            ->willReturn($mockResponse);

        $provider->chat([['role' => 'user', 'content' => 'test']]);
    }

    // ---------------------------------------------------------------
    // chat() – custom options
    // ---------------------------------------------------------------

    public function testChatSetsTemperatureWhenProvided(): void
    {
        $mockResponse = [
            'content' => [['type' => 'text', 'text' => 'ok']],
            'usage' => ['input_tokens' => 2, 'output_tokens' => 1],
            'stop_reason' => 'stop',
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'sk-ant-test',
            'default_model' => 'claude-sonnet-4-20250514',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'messages',
                $this->callback(fn($data) => isset($data['temperature']) && $data['temperature'] === 0.3),
                $this->isType('array')
            )
            ->willReturn($mockResponse);

        $provider->chat(
            [['role' => 'user', 'content' => 'test']],
            ['temperature' => 0.3]
        );
    }

    public function testChatDoesNotSetTemperatureWhenNotProvided(): void
    {
        $mockResponse = [
            'content' => [['type' => 'text', 'text' => 'ok']],
            'usage' => ['input_tokens' => 2, 'output_tokens' => 1],
            'stop_reason' => 'stop',
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'sk-ant-test',
            'default_model' => 'claude-sonnet-4-20250514',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'messages',
                $this->callback(fn($data) => !isset($data['temperature'])),
                $this->isType('array')
            )
            ->willReturn($mockResponse);

        $provider->chat([['role' => 'user', 'content' => 'test']]);
    }

    // ---------------------------------------------------------------
    // streamChat() – throws when not configured
    // ---------------------------------------------------------------

    public function testStreamChatThrowsWhenNotConfigured(): void
    {
        $provider = new AnthropicProvider([]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Anthropic API key not configured');
        $provider->streamChat(
            [['role' => 'user', 'content' => 'hello']],
            function () {}
        );
    }

    // ---------------------------------------------------------------
    // embed() – not supported
    // ---------------------------------------------------------------

    public function testEmbedThrowsNotSupportedException(): void
    {
        $provider = new AnthropicProvider(['api_key' => 'sk-ant-test']);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("doesn't support embeddings");
        $provider->embed('test text');
    }

    // ---------------------------------------------------------------
    // chat() – handles empty/missing usage
    // ---------------------------------------------------------------

    public function testChatHandlesMissingUsage(): void
    {
        $mockResponse = [
            'content' => [['type' => 'text', 'text' => 'Hello']],
            'stop_reason' => 'stop',
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'sk-ant-test',
            'default_model' => 'claude-sonnet-4-20250514',
        ]);
        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'Hi']]);
        $this->assertEquals(0, $result['tokens_used']);
        $this->assertEquals(0, $result['tokens_input']);
        $this->assertEquals(0, $result['tokens_output']);
    }

    public function testChatHandlesEmptyContentArray(): void
    {
        $mockResponse = [
            'content' => [],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 0],
            'stop_reason' => 'stop',
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'sk-ant-test',
            'default_model' => 'claude-sonnet-4-20250514',
        ]);
        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'Hi']]);
        $this->assertEquals('', $result['content']);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Create a partial mock of AnthropicProvider that overrides the protected
     * request() method so no real HTTP calls are made.
     */
    private function createMockProvider(array $config): AnthropicProvider
    {
        return $this->getMockBuilder(AnthropicProvider::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['request'])
            ->getMock();
    }
}
