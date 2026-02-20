<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services\AI;

use PHPUnit\Framework\TestCase;
use Nexus\Services\AI\Providers\OpenAIProvider;
use Nexus\Services\AI\Contracts\AIProviderInterface;

/**
 * Unit tests for OpenAIProvider
 *
 * Uses partial mocks to override the protected `request()` method so that
 * no real HTTP calls are made.
 */
class OpenAIProviderTest extends TestCase
{
    // ---------------------------------------------------------------
    // Identity methods
    // ---------------------------------------------------------------

    public function testGetIdReturnsOpenai(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'sk-test']);
        $this->assertEquals('openai', $provider->getId());
    }

    public function testGetNameReturnsOpenAI(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'sk-test']);
        $this->assertEquals('OpenAI', $provider->getName());
    }

    public function testImplementsAIProviderInterface(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'sk-test']);
        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    // ---------------------------------------------------------------
    // isConfigured()
    // ---------------------------------------------------------------

    public function testIsConfiguredReturnsTrueWithApiKey(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'sk-test-key']);
        $this->assertTrue($provider->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWithoutApiKey(): void
    {
        $provider = new OpenAIProvider([]);
        $this->assertFalse($provider->isConfigured());
    }

    // ---------------------------------------------------------------
    // chat() – throws when not configured
    // ---------------------------------------------------------------

    public function testChatThrowsWhenNotConfigured(): void
    {
        $provider = new OpenAIProvider([]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API key not configured');
        $provider->chat([['role' => 'user', 'content' => 'hello']]);
    }

    // ---------------------------------------------------------------
    // chat() – response parsing
    // ---------------------------------------------------------------

    public function testChatParsesResponseCorrectly(): void
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => ['content' => 'Hello! How can I help?'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'total_tokens' => 25,
                'prompt_tokens' => 10,
                'completion_tokens' => 15,
            ],
        ];

        $provider = $this->createMockProvider(['api_key' => 'sk-test', 'default_model' => 'gpt-4']);
        $provider->expects($this->once())
            ->method('request')
            ->with(
                'chat/completions',
                $this->callback(function ($data) {
                    return $data['model'] === 'gpt-4'
                        && $data['messages'] === [['role' => 'user', 'content' => 'Hi']]
                        && $data['temperature'] === 0.7
                        && $data['max_tokens'] === 2048;
                }),
                $this->isType('array')
            )
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertEquals('Hello! How can I help?', $result['content']);
        $this->assertEquals(25, $result['tokens_used']);
        $this->assertEquals(10, $result['tokens_input']);
        $this->assertEquals(15, $result['tokens_output']);
        $this->assertEquals('gpt-4', $result['model']);
        $this->assertEquals('stop', $result['finish_reason']);
        $this->assertEquals('openai', $result['provider']);
    }

    public function testChatUsesCustomModelFromOptions(): void
    {
        $mockResponse = [
            'choices' => [['message' => ['content' => 'response'], 'finish_reason' => 'stop']],
            'usage' => ['total_tokens' => 5, 'prompt_tokens' => 2, 'completion_tokens' => 3],
        ];

        $provider = $this->createMockProvider(['api_key' => 'sk-test', 'default_model' => 'gpt-4']);
        $provider->expects($this->once())
            ->method('request')
            ->with(
                'chat/completions',
                $this->callback(fn($data) => $data['model'] === 'gpt-4o-mini'),
                $this->isType('array')
            )
            ->willReturn($mockResponse);

        $result = $provider->chat(
            [['role' => 'user', 'content' => 'test']],
            ['model' => 'gpt-4o-mini']
        );

        $this->assertEquals('gpt-4o-mini', $result['model']);
    }

    public function testChatHandlesCustomTemperatureAndMaxTokens(): void
    {
        $mockResponse = [
            'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            'usage' => ['total_tokens' => 5, 'prompt_tokens' => 2, 'completion_tokens' => 3],
        ];

        $provider = $this->createMockProvider(['api_key' => 'sk-test', 'default_model' => 'gpt-4']);
        $provider->expects($this->once())
            ->method('request')
            ->with(
                'chat/completions',
                $this->callback(function ($data) {
                    return $data['temperature'] === 0.2 && $data['max_tokens'] === 500;
                }),
                $this->isType('array')
            )
            ->willReturn($mockResponse);

        $provider->chat(
            [['role' => 'user', 'content' => 'test']],
            ['temperature' => 0.2, 'max_tokens' => 500]
        );
    }

    public function testChatIncludesTopPWhenProvided(): void
    {
        $mockResponse = [
            'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            'usage' => ['total_tokens' => 5, 'prompt_tokens' => 2, 'completion_tokens' => 3],
        ];

        $provider = $this->createMockProvider(['api_key' => 'sk-test', 'default_model' => 'gpt-4']);
        $provider->expects($this->once())
            ->method('request')
            ->with(
                'chat/completions',
                $this->callback(fn($data) => isset($data['top_p']) && $data['top_p'] === 0.9),
                $this->isType('array')
            )
            ->willReturn($mockResponse);

        $provider->chat(
            [['role' => 'user', 'content' => 'test']],
            ['top_p' => 0.9]
        );
    }

    public function testChatHandlesEmptyResponse(): void
    {
        $mockResponse = [
            'choices' => [['message' => ['content' => ''], 'finish_reason' => 'stop']],
            'usage' => [],
        ];

        $provider = $this->createMockProvider(['api_key' => 'sk-test', 'default_model' => 'gpt-4']);
        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'test']]);
        $this->assertEquals('', $result['content']);
        $this->assertEquals(0, $result['tokens_used']);
    }

    // ---------------------------------------------------------------
    // Organization header
    // ---------------------------------------------------------------

    public function testChatIncludesOrganizationHeaderWhenConfigured(): void
    {
        $mockResponse = [
            'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            'usage' => ['total_tokens' => 5, 'prompt_tokens' => 2, 'completion_tokens' => 3],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'sk-test',
            'default_model' => 'gpt-4',
            'org_id' => 'org-12345',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'chat/completions',
                $this->isType('array'),
                $this->callback(function ($headers) {
                    return in_array('Authorization: Bearer sk-test', $headers)
                        && in_array('OpenAI-Organization: org-12345', $headers);
                })
            )
            ->willReturn($mockResponse);

        $provider->chat([['role' => 'user', 'content' => 'test']]);
    }

    public function testChatDoesNotIncludeOrganizationHeaderWhenNotConfigured(): void
    {
        $mockResponse = [
            'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            'usage' => ['total_tokens' => 5, 'prompt_tokens' => 2, 'completion_tokens' => 3],
        ];

        $provider = $this->createMockProvider([
            'api_key' => 'sk-test',
            'default_model' => 'gpt-4',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'chat/completions',
                $this->isType('array'),
                $this->callback(function ($headers) {
                    foreach ($headers as $h) {
                        if (str_starts_with($h, 'OpenAI-Organization:')) {
                            return false;
                        }
                    }
                    return true;
                })
            )
            ->willReturn($mockResponse);

        $provider->chat([['role' => 'user', 'content' => 'test']]);
    }

    // ---------------------------------------------------------------
    // streamChat() – throws when not configured
    // ---------------------------------------------------------------

    public function testStreamChatThrowsWhenNotConfigured(): void
    {
        $provider = new OpenAIProvider([]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API key not configured');
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
        $provider = new OpenAIProvider([]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API key not configured');
        $provider->embed('test text');
    }

    public function testEmbedParsesResponseCorrectly(): void
    {
        $mockResponse = [
            'data' => [
                ['embedding' => [0.1, 0.2, 0.3, 0.4]],
            ],
        ];

        $provider = $this->createMockProvider(['api_key' => 'sk-test', 'default_model' => 'gpt-4']);
        $provider->expects($this->once())
            ->method('request')
            ->with(
                'embeddings',
                $this->callback(fn($data) => $data['model'] === 'text-embedding-3-small' && $data['input'] === 'test text'),
                $this->isType('array')
            )
            ->willReturn($mockResponse);

        $result = $provider->embed('test text');
        $this->assertEquals([0.1, 0.2, 0.3, 0.4], $result);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Create a partial mock of OpenAIProvider that overrides the protected
     * request() method so no real HTTP calls are made.
     */
    private function createMockProvider(array $config): OpenAIProvider
    {
        $mock = $this->getMockBuilder(OpenAIProvider::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['request'])
            ->getMock();

        return $mock;
    }
}
