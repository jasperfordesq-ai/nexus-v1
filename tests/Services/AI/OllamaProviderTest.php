<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services\AI;

use PHPUnit\Framework\TestCase;
use Nexus\Services\AI\Providers\OllamaProvider;
use Nexus\Services\AI\Contracts\AIProviderInterface;

/**
 * Unit tests for OllamaProvider
 *
 * Uses partial mocks to override the protected `request()` method so that
 * no real HTTP calls are made.
 */
class OllamaProviderTest extends TestCase
{
    // ---------------------------------------------------------------
    // Identity methods
    // ---------------------------------------------------------------

    public function testGetIdReturnsOllama(): void
    {
        $provider = new OllamaProvider(['api_url' => 'http://localhost:11434']);
        $this->assertEquals('ollama', $provider->getId());
    }

    public function testGetNameReturnsOllamaSelfHosted(): void
    {
        $provider = new OllamaProvider(['api_url' => 'http://localhost:11434']);
        $this->assertEquals('Ollama (Self-hosted)', $provider->getName());
    }

    public function testImplementsAIProviderInterface(): void
    {
        $provider = new OllamaProvider(['api_url' => 'http://localhost:11434']);
        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    // ---------------------------------------------------------------
    // Constructor
    // ---------------------------------------------------------------

    public function testConstructorSetsDefaultApiUrl(): void
    {
        $provider = new OllamaProvider([]);
        $ref = new \ReflectionProperty($provider, 'apiUrl');
        $ref->setAccessible(true);
        $this->assertEquals('http://localhost:11434', $ref->getValue($provider));
    }

    public function testConstructorSetsCustomApiUrl(): void
    {
        $provider = new OllamaProvider(['api_url' => 'http://192.168.1.100:11434']);
        $ref = new \ReflectionProperty($provider, 'apiUrl');
        $ref->setAccessible(true);
        $this->assertEquals('http://192.168.1.100:11434', $ref->getValue($provider));
    }

    // ---------------------------------------------------------------
    // isConfigured() — checks base_url, NOT api_key
    // ---------------------------------------------------------------

    public function testIsConfiguredReturnsTrueWhenApiUrlSet(): void
    {
        $provider = new OllamaProvider(['api_url' => 'http://localhost:11434']);
        $this->assertTrue($provider->isConfigured());
    }

    public function testIsConfiguredReturnsTrueWithDefaultUrl(): void
    {
        // Even with empty config, the constructor defaults to localhost
        $provider = new OllamaProvider([]);
        $this->assertTrue($provider->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenApiUrlEmpty(): void
    {
        $provider = new OllamaProvider([]);
        // Force apiUrl to empty via reflection
        $ref = new \ReflectionProperty($provider, 'apiUrl');
        $ref->setAccessible(true);
        $ref->setValue($provider, '');
        $this->assertFalse($provider->isConfigured());
    }

    public function testIsConfiguredDoesNotRequireApiKey(): void
    {
        // Ollama is self-hosted and does not require an API key
        $provider = new OllamaProvider([
            'api_url' => 'http://localhost:11434',
            'api_key' => '',
        ]);
        $this->assertTrue($provider->isConfigured());
    }

    // ---------------------------------------------------------------
    // chat() – throws when not configured
    // ---------------------------------------------------------------

    public function testChatThrowsWhenNotConfigured(): void
    {
        $provider = new OllamaProvider([]);
        // Force apiUrl to empty
        $ref = new \ReflectionProperty($provider, 'apiUrl');
        $ref->setAccessible(true);
        $ref->setValue($provider, '');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ollama host URL not configured');
        $provider->chat([['role' => 'user', 'content' => 'hello']]);
    }

    // ---------------------------------------------------------------
    // chat() – response parsing
    // ---------------------------------------------------------------

    public function testChatParsesResponseCorrectly(): void
    {
        $mockResponse = [
            'message' => [
                'content' => 'Hello from Ollama!',
                'role' => 'assistant',
            ],
            'done' => true,
        ];

        $provider = $this->createMockProvider([
            'api_url' => 'http://localhost:11434',
            'default_model' => 'llama2',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'api/chat',
                $this->callback(function ($data) {
                    return $data['model'] === 'llama2'
                        && $data['stream'] === false
                        && isset($data['options']['temperature']);
                })
            )
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertEquals('Hello from Ollama!', $result['content']);
        $this->assertEquals('llama2', $result['model']);
        $this->assertEquals('stop', $result['finish_reason']);
        $this->assertEquals('ollama', $result['provider']);
        // Token count is estimated from content length
        $expectedTokens = (int)(strlen('Hello from Ollama!') / 4);
        $this->assertEquals($expectedTokens, $result['tokens_used']);
        $this->assertEquals(0, $result['tokens_input']);
        $this->assertEquals($expectedTokens, $result['tokens_output']);
    }

    public function testChatUsesCustomModel(): void
    {
        $mockResponse = [
            'message' => ['content' => 'ok', 'role' => 'assistant'],
            'done' => true,
        ];

        $provider = $this->createMockProvider([
            'api_url' => 'http://localhost:11434',
            'default_model' => 'llama2',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'api/chat',
                $this->callback(fn($data) => $data['model'] === 'mistral')
            )
            ->willReturn($mockResponse);

        $result = $provider->chat(
            [['role' => 'user', 'content' => 'test']],
            ['model' => 'mistral']
        );
        $this->assertEquals('mistral', $result['model']);
    }

    public function testChatSetsNumPredictWhenMaxTokensProvided(): void
    {
        $mockResponse = [
            'message' => ['content' => 'ok', 'role' => 'assistant'],
            'done' => true,
        ];

        $provider = $this->createMockProvider([
            'api_url' => 'http://localhost:11434',
            'default_model' => 'llama2',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'api/chat',
                $this->callback(fn($data) => $data['options']['num_predict'] === 500)
            )
            ->willReturn($mockResponse);

        $provider->chat(
            [['role' => 'user', 'content' => 'test']],
            ['max_tokens' => 500]
        );
    }

    public function testChatPassesMessagesDirectly(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'Hello'],
        ];

        $mockResponse = [
            'message' => ['content' => 'ok', 'role' => 'assistant'],
            'done' => true,
        ];

        $provider = $this->createMockProvider([
            'api_url' => 'http://localhost:11434',
            'default_model' => 'llama2',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'api/chat',
                $this->callback(fn($data) => $data['messages'] === $messages)
            )
            ->willReturn($mockResponse);

        $provider->chat($messages);
    }

    public function testChatHandlesEmptyContent(): void
    {
        $mockResponse = [
            'message' => ['content' => '', 'role' => 'assistant'],
            'done' => true,
        ];

        $provider = $this->createMockProvider([
            'api_url' => 'http://localhost:11434',
            'default_model' => 'llama2',
        ]);
        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->chat([['role' => 'user', 'content' => 'test']]);
        $this->assertEquals('', $result['content']);
        $this->assertEquals(0, $result['tokens_used']);
    }

    // ---------------------------------------------------------------
    // chat() – connection refused error handling
    // ---------------------------------------------------------------

    public function testChatThrowsFriendlyErrorWhenConnectionRefused(): void
    {
        $provider = $this->createMockProvider([
            'api_url' => 'http://localhost:11434',
            'default_model' => 'llama2',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('AI API request failed: Connection refused'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ollama is not running');
        $provider->chat([['role' => 'user', 'content' => 'hello']]);
    }

    public function testChatRethrowsNonConnectionErrors(): void
    {
        $provider = $this->createMockProvider([
            'api_url' => 'http://localhost:11434',
            'default_model' => 'llama2',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('AI API error (500): Internal server error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('AI API error (500)');
        $provider->chat([['role' => 'user', 'content' => 'hello']]);
    }

    // ---------------------------------------------------------------
    // streamChat() – throws when not configured
    // ---------------------------------------------------------------

    public function testStreamChatThrowsWhenNotConfigured(): void
    {
        $provider = new OllamaProvider([]);
        // Force apiUrl to empty
        $ref = new \ReflectionProperty($provider, 'apiUrl');
        $ref->setAccessible(true);
        $ref->setValue($provider, '');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ollama host URL not configured');
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
        $provider = new OllamaProvider([]);
        $ref = new \ReflectionProperty($provider, 'apiUrl');
        $ref->setAccessible(true);
        $ref->setValue($provider, '');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ollama host URL not configured');
        $provider->embed('test text');
    }

    public function testEmbedParsesResponseCorrectly(): void
    {
        $mockResponse = [
            'embedding' => [0.1, 0.2, 0.3],
        ];

        $provider = $this->createMockProvider([
            'api_url' => 'http://localhost:11434',
            'default_model' => 'llama2',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'api/embeddings',
                $this->callback(function ($data) {
                    return $data['model'] === 'llama2'
                        && $data['prompt'] === 'embed this';
                })
            )
            ->willReturn($mockResponse);

        $result = $provider->embed('embed this');
        $this->assertEquals([0.1, 0.2, 0.3], $result);
    }

    public function testEmbedReturnsEmptyArrayWhenNoEmbedding(): void
    {
        $mockResponse = [];

        $provider = $this->createMockProvider([
            'api_url' => 'http://localhost:11434',
            'default_model' => 'llama2',
        ]);
        $provider->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $provider->embed('test');
        $this->assertEquals([], $result);
    }

    // ---------------------------------------------------------------
    // testConnection()
    // ---------------------------------------------------------------

    public function testTestConnectionReturnsFailureWhenNotConfigured(): void
    {
        $provider = new OllamaProvider([]);
        $ref = new \ReflectionProperty($provider, 'apiUrl');
        $ref->setAccessible(true);
        $ref->setValue($provider, '');

        $result = $provider->testConnection();
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not configured', $result['message']);
    }

    // ---------------------------------------------------------------
    // getModels() — overridden to query Ollama API
    // ---------------------------------------------------------------

    public function testGetModelsMethodExists(): void
    {
        $this->assertTrue(method_exists(OllamaProvider::class, 'getModels'));
    }

    // ---------------------------------------------------------------
    // chat() – temperature option
    // ---------------------------------------------------------------

    public function testChatUsesCustomTemperature(): void
    {
        $mockResponse = [
            'message' => ['content' => 'ok', 'role' => 'assistant'],
            'done' => true,
        ];

        $provider = $this->createMockProvider([
            'api_url' => 'http://localhost:11434',
            'default_model' => 'llama2',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'api/chat',
                $this->callback(fn($data) => $data['options']['temperature'] === 0.2)
            )
            ->willReturn($mockResponse);

        $provider->chat(
            [['role' => 'user', 'content' => 'test']],
            ['temperature' => 0.2]
        );
    }

    public function testChatUsesDefaultTemperature(): void
    {
        $mockResponse = [
            'message' => ['content' => 'ok', 'role' => 'assistant'],
            'done' => true,
        ];

        $provider = $this->createMockProvider([
            'api_url' => 'http://localhost:11434',
            'default_model' => 'llama2',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'api/chat',
                $this->callback(fn($data) => $data['options']['temperature'] === 0.7)
            )
            ->willReturn($mockResponse);

        $provider->chat([['role' => 'user', 'content' => 'test']]);
    }

    // ---------------------------------------------------------------
    // chat() – stream flag
    // ---------------------------------------------------------------

    public function testChatSetsStreamFalse(): void
    {
        $mockResponse = [
            'message' => ['content' => 'ok', 'role' => 'assistant'],
            'done' => true,
        ];

        $provider = $this->createMockProvider([
            'api_url' => 'http://localhost:11434',
            'default_model' => 'llama2',
        ]);

        $provider->expects($this->once())
            ->method('request')
            ->with(
                'api/chat',
                $this->callback(fn($data) => $data['stream'] === false)
            )
            ->willReturn($mockResponse);

        $provider->chat([['role' => 'user', 'content' => 'test']]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Create a partial mock of OllamaProvider that overrides the protected
     * request() method so no real HTTP calls are made.
     */
    private function createMockProvider(array $config): OllamaProvider
    {
        return $this->getMockBuilder(OllamaProvider::class)
            ->setConstructorArgs([$config])
            ->onlyMethods(['request'])
            ->getMock();
    }
}
