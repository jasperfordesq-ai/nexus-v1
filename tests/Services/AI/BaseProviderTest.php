<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services\AI;

use PHPUnit\Framework\TestCase;
use Nexus\Services\AI\Providers\BaseProvider;
use Nexus\Services\AI\Contracts\AIProviderInterface;

/**
 * Unit tests for the abstract BaseProvider class.
 *
 * A concrete stub (ConcreteTestProvider) is used to instantiate and test
 * the non-abstract methods provided by BaseProvider.
 */
class BaseProviderTest extends TestCase
{
    // ---------------------------------------------------------------
    // Constructor & property initialization
    // ---------------------------------------------------------------

    public function testConstructorSetsApiKey(): void
    {
        $provider = new ConcreteTestProvider(['api_key' => 'sk-test-123']);
        $this->assertEquals('sk-test-123', $this->getProtectedProperty($provider, 'apiKey'));
    }

    public function testConstructorSetsApiUrl(): void
    {
        $provider = new ConcreteTestProvider(['api_url' => 'https://api.example.com']);
        $this->assertEquals('https://api.example.com', $this->getProtectedProperty($provider, 'apiUrl'));
    }

    public function testConstructorSetsDefaultModel(): void
    {
        $provider = new ConcreteTestProvider(['default_model' => 'gpt-4']);
        $this->assertEquals('gpt-4', $this->getProtectedProperty($provider, 'defaultModel'));
    }

    public function testConstructorSetsFullConfig(): void
    {
        $config = [
            'api_key' => 'key',
            'api_url' => 'url',
            'default_model' => 'model',
            'extra_setting' => 'value',
        ];
        $provider = new ConcreteTestProvider($config);
        $this->assertEquals($config, $this->getProtectedProperty($provider, 'config'));
    }

    public function testConstructorDefaultsToEmptyStrings(): void
    {
        $provider = new ConcreteTestProvider([]);
        $this->assertEquals('', $this->getProtectedProperty($provider, 'apiKey'));
        $this->assertEquals('', $this->getProtectedProperty($provider, 'apiUrl'));
        $this->assertEquals('', $this->getProtectedProperty($provider, 'defaultModel'));
    }

    // ---------------------------------------------------------------
    // isConfigured()
    // ---------------------------------------------------------------

    public function testIsConfiguredReturnsTrueWhenApiKeySet(): void
    {
        $provider = new ConcreteTestProvider(['api_key' => 'sk-test']);
        $this->assertTrue($provider->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenApiKeyEmpty(): void
    {
        $provider = new ConcreteTestProvider(['api_key' => '']);
        $this->assertFalse($provider->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenApiKeyMissing(): void
    {
        $provider = new ConcreteTestProvider([]);
        $this->assertFalse($provider->isConfigured());
    }

    public function testIsConfiguredReturnsTrueWhenSelfHosted(): void
    {
        $provider = new ConcreteTestProvider([
            'api_key' => '',
            'self_hosted' => true,
        ]);
        $this->assertTrue($provider->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenSelfHostedFalseAndNoKey(): void
    {
        $provider = new ConcreteTestProvider([
            'api_key' => '',
            'self_hosted' => false,
        ]);
        $this->assertFalse($provider->isConfigured());
    }

    // ---------------------------------------------------------------
    // getModel()
    // ---------------------------------------------------------------

    public function testGetModelReturnsOptionModelWhenProvided(): void
    {
        $provider = new ConcreteTestProvider(['default_model' => 'default-model']);
        $model = $this->invokeProtectedMethod($provider, 'getModel', [['model' => 'custom-model']]);
        $this->assertEquals('custom-model', $model);
    }

    public function testGetModelReturnsDefaultModelWhenNoOption(): void
    {
        $provider = new ConcreteTestProvider(['default_model' => 'default-model']);
        $model = $this->invokeProtectedMethod($provider, 'getModel', [[]]);
        $this->assertEquals('default-model', $model);
    }

    public function testGetModelReturnsEmptyStringWhenNothingSet(): void
    {
        $provider = new ConcreteTestProvider([]);
        $model = $this->invokeProtectedMethod($provider, 'getModel', [[]]);
        $this->assertEquals('', $model);
    }

    // ---------------------------------------------------------------
    // getModels()
    // ---------------------------------------------------------------

    public function testGetModelsReturnsConfigModels(): void
    {
        $models = ['gpt-4' => ['name' => 'GPT-4'], 'gpt-3.5' => ['name' => 'GPT-3.5']];
        $provider = new ConcreteTestProvider(['models' => $models]);
        $this->assertEquals($models, $provider->getModels());
    }

    public function testGetModelsReturnsEmptyArrayWhenNotSet(): void
    {
        $provider = new ConcreteTestProvider([]);
        $this->assertEquals([], $provider->getModels());
    }

    // ---------------------------------------------------------------
    // complete()
    // ---------------------------------------------------------------

    public function testCompleteCallsChatAndReturnsContent(): void
    {
        $provider = $this->getMockBuilder(ConcreteTestProvider::class)
            ->setConstructorArgs([['api_key' => 'sk-test']])
            ->onlyMethods(['chat'])
            ->getMock();

        $provider->expects($this->once())
            ->method('chat')
            ->with(
                [['role' => 'user', 'content' => 'Hello world']],
                ['temperature' => 0.5]
            )
            ->willReturn(['content' => 'Hi there!', 'tokens_used' => 10]);

        $result = $provider->complete('Hello world', ['temperature' => 0.5]);
        $this->assertEquals('Hi there!', $result);
    }

    public function testCompleteReturnsEmptyStringWhenNoContent(): void
    {
        $provider = $this->getMockBuilder(ConcreteTestProvider::class)
            ->setConstructorArgs([['api_key' => 'sk-test']])
            ->onlyMethods(['chat'])
            ->getMock();

        $provider->expects($this->once())
            ->method('chat')
            ->willReturn(['tokens_used' => 0]);

        $result = $provider->complete('prompt');
        $this->assertEquals('', $result);
    }

    // ---------------------------------------------------------------
    // embed() default implementation
    // ---------------------------------------------------------------

    public function testEmbedThrowsExceptionByDefault(): void
    {
        $provider = new ConcreteTestProvider(['api_key' => 'sk-test']);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Embeddings not supported by Test Provider');
        $provider->embed('some text');
    }

    // ---------------------------------------------------------------
    // testConnection()
    // ---------------------------------------------------------------

    public function testTestConnectionReturnsFailureWhenNotConfigured(): void
    {
        $provider = new ConcreteTestProvider([]);
        $result = $provider->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not configured', $result['message']);
        $this->assertEquals(0, $result['latency_ms']);
    }

    public function testTestConnectionReturnsSuccessOnValidChat(): void
    {
        $provider = $this->getMockBuilder(ConcreteTestProvider::class)
            ->setConstructorArgs([['api_key' => 'sk-test', 'default_model' => 'test-model']])
            ->onlyMethods(['chat'])
            ->getMock();

        $provider->expects($this->once())
            ->method('chat')
            ->willReturn([
                'content' => 'OK',
                'model' => 'test-model',
                'tokens_used' => 5,
            ]);

        $result = $provider->testConnection();
        $this->assertTrue($result['success']);
        $this->assertEquals('Connection successful', $result['message']);
        $this->assertArrayHasKey('latency_ms', $result);
        $this->assertEquals('Test Provider', $result['provider']);
    }

    public function testTestConnectionReturnsFailureOnChatException(): void
    {
        $provider = $this->getMockBuilder(ConcreteTestProvider::class)
            ->setConstructorArgs([['api_key' => 'sk-test', 'default_model' => 'test-model']])
            ->onlyMethods(['chat'])
            ->getMock();

        $provider->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('Connection refused'));

        $result = $provider->testConnection();
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Connection refused', $result['message']);
    }

    // ---------------------------------------------------------------
    // Interface contract
    // ---------------------------------------------------------------

    public function testBaseProviderImplementsAIProviderInterface(): void
    {
        $provider = new ConcreteTestProvider(['api_key' => 'sk-test']);
        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function getProtectedProperty(object $object, string $property): mixed
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        return $ref->getValue($object);
    }

    private function invokeProtectedMethod(object $object, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }
}

/**
 * Concrete stub that extends BaseProvider so we can test its non-abstract methods.
 */
class ConcreteTestProvider extends BaseProvider
{
    public function getId(): string
    {
        return 'test';
    }

    public function getName(): string
    {
        return 'Test Provider';
    }

    public function chat(array $messages, array $options = []): array
    {
        return ['content' => 'test response', 'tokens_used' => 0];
    }

    public function streamChat(array $messages, callable $onChunk, array $options = []): void
    {
        $onChunk(['content' => 'test', 'done' => true]);
    }
}
