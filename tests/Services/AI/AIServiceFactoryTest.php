<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services\AI;

use PHPUnit\Framework\TestCase;
use Nexus\Services\AI\AIServiceFactory;
use Nexus\Services\AI\Contracts\AIProviderInterface;
use Nexus\Services\AI\Providers\OpenAIProvider;
use Nexus\Services\AI\Providers\AnthropicProvider;
use Nexus\Services\AI\Providers\GeminiProvider;
use Nexus\Services\AI\Providers\OllamaProvider;
use Nexus\Core\TenantContext;

/**
 * Unit tests for AIServiceFactory
 *
 * Tests provider creation, configuration loading, caching, and error handling.
 * Does not require a database connection since it uses reflection to set
 * TenantContext::$tenant to a stub with a null ID, causing the factory to
 * skip all database-dependent code paths.
 */
class AIServiceFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear cached instances and config between tests
        AIServiceFactory::clearCache();
        // Prevent TenantContext from hitting the database by pre-setting a
        // stub tenant with a null ID. The factory checks `if ($tenantId)` and
        // skips all DB calls when the ID is falsy.
        $this->setTenantContextStub();
    }

    protected function tearDown(): void
    {
        AIServiceFactory::clearCache();
        $this->clearTenantContextStub();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Class existence and interface checks
    // ---------------------------------------------------------------

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AIServiceFactory::class));
    }

    public function testGetProviderMethodExists(): void
    {
        $this->assertTrue(method_exists(AIServiceFactory::class, 'getProvider'));
    }

    public function testGetAvailableProvidersMethodExists(): void
    {
        $this->assertTrue(method_exists(AIServiceFactory::class, 'getAvailableProviders'));
    }

    public function testGetDefaultProviderMethodExists(): void
    {
        $this->assertTrue(method_exists(AIServiceFactory::class, 'getDefaultProvider'));
    }

    public function testIsEnabledMethodExists(): void
    {
        $this->assertTrue(method_exists(AIServiceFactory::class, 'isEnabled'));
    }

    public function testIsFeatureEnabledMethodExists(): void
    {
        $this->assertTrue(method_exists(AIServiceFactory::class, 'isFeatureEnabled'));
    }

    public function testClearCacheMethodExists(): void
    {
        $this->assertTrue(method_exists(AIServiceFactory::class, 'clearCache'));
    }

    // ---------------------------------------------------------------
    // createProvider (private) via reflection
    // ---------------------------------------------------------------

    public function testCreateProviderReturnsOpenAIProvider(): void
    {
        $provider = $this->invokeCreateProvider('openai', ['api_key' => 'test-key-openai']);
        $this->assertInstanceOf(OpenAIProvider::class, $provider);
        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateProviderReturnsAnthropicProvider(): void
    {
        $provider = $this->invokeCreateProvider('anthropic', ['api_key' => 'test-key-anthropic']);
        $this->assertInstanceOf(AnthropicProvider::class, $provider);
    }

    public function testCreateProviderReturnsGeminiProvider(): void
    {
        $provider = $this->invokeCreateProvider('gemini', ['api_key' => 'test-key-gemini']);
        $this->assertInstanceOf(GeminiProvider::class, $provider);
    }

    public function testCreateProviderReturnsOllamaProvider(): void
    {
        $provider = $this->invokeCreateProvider('ollama', ['api_url' => 'http://localhost:11434']);
        $this->assertInstanceOf(OllamaProvider::class, $provider);
    }

    public function testCreateProviderThrowsOnUnknownProvider(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown AI provider: unknown_provider');
        // Provide a fake API key so getProviderConfig's validation passes and
        // the error actually comes from the match() statement in createProvider.
        $this->invokeCreateProvider('unknown_provider', ['api_key' => 'fake-key']);
    }

    // ---------------------------------------------------------------
    // Singleton / caching behaviour
    // ---------------------------------------------------------------

    public function testClearCacheResetsInstances(): void
    {
        // Use reflection to inspect the static $instances property
        $ref = new \ReflectionClass(AIServiceFactory::class);
        $instancesProp = $ref->getProperty('instances');
        $instancesProp->setAccessible(true);

        // Set a fake cached instance
        $instancesProp->setValue(null, ['fake_provider' => 'fake_value']);
        $this->assertNotEmpty($instancesProp->getValue());

        AIServiceFactory::clearCache();

        $this->assertEmpty($instancesProp->getValue());
    }

    public function testClearCacheResetsConfig(): void
    {
        $ref = new \ReflectionClass(AIServiceFactory::class);
        $configProp = $ref->getProperty('config');
        $configProp->setAccessible(true);

        // Set a fake config
        $configProp->setValue(null, ['some' => 'config']);
        $this->assertNotNull($configProp->getValue());

        AIServiceFactory::clearCache();

        $this->assertNull($configProp->getValue());
    }

    // ---------------------------------------------------------------
    // getProviderConfig validation
    // ---------------------------------------------------------------

    public function testGetProviderConfigThrowsWhenApiKeyMissing(): void
    {
        // Force config to have a provider with no API key and no DB fallback
        $this->setFactoryConfig([
            'providers' => [
                'openai' => [
                    'name' => 'OpenAI',
                    'api_url' => 'https://api.openai.com/v1',
                    'api_key' => '',
                    'default_model' => 'gpt-4-turbo',
                ],
            ],
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("AI Provider 'openai' is not configured");
        AIServiceFactory::getProviderConfig('openai');
    }

    public function testGetProviderConfigOllamaDoesNotRequireApiKey(): void
    {
        $this->setFactoryConfig([
            'providers' => [
                'ollama' => [
                    'name' => 'Ollama',
                    'api_url' => 'http://localhost:11434',
                    'default_model' => 'llama2',
                    'self_hosted' => true,
                ],
            ],
        ]);

        // Should not throw even though api_key is missing
        $config = AIServiceFactory::getProviderConfig('ollama');
        $this->assertIsArray($config);
        $this->assertEquals('http://localhost:11434', $config['api_url']);
    }

    // ---------------------------------------------------------------
    // getDefaultProvider fallback
    // ---------------------------------------------------------------

    public function testGetDefaultProviderFallsBackToConfigValue(): void
    {
        $this->setFactoryConfig([
            'default_provider' => 'anthropic',
            'providers' => [],
        ]);

        $default = AIServiceFactory::getDefaultProvider();
        $this->assertEquals('anthropic', $default);
    }

    public function testGetDefaultProviderFallsBackToGeminiWhenNoConfig(): void
    {
        $this->setFactoryConfig([
            'providers' => [],
        ]);

        $default = AIServiceFactory::getDefaultProvider();
        $this->assertEquals('gemini', $default);
    }

    // ---------------------------------------------------------------
    // isEnabled / isFeatureEnabled (config-only paths)
    // ---------------------------------------------------------------

    public function testIsEnabledReturnsTrueByDefault(): void
    {
        $this->setFactoryConfig([
            'enabled' => true,
            'providers' => [],
        ]);

        $this->assertTrue(AIServiceFactory::isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenConfigDisabled(): void
    {
        $this->setFactoryConfig([
            'enabled' => false,
            'providers' => [],
        ]);

        $this->assertFalse(AIServiceFactory::isEnabled());
    }

    public function testIsFeatureEnabledReturnsFalseWhenAiDisabled(): void
    {
        $this->setFactoryConfig([
            'enabled' => false,
            'providers' => [],
            'features' => ['chat' => true],
        ]);

        $this->assertFalse(AIServiceFactory::isFeatureEnabled('chat'));
    }

    public function testIsFeatureEnabledReturnsConfigValue(): void
    {
        $this->setFactoryConfig([
            'enabled' => true,
            'providers' => [],
            'features' => ['chat' => true, 'analytics' => false],
        ]);

        $this->assertTrue(AIServiceFactory::isFeatureEnabled('chat'));
        $this->assertFalse(AIServiceFactory::isFeatureEnabled('analytics'));
    }

    public function testIsFeatureEnabledReturnsFalseForUndefinedFeature(): void
    {
        $this->setFactoryConfig([
            'enabled' => true,
            'providers' => [],
            'features' => [],
        ]);

        $this->assertFalse(AIServiceFactory::isFeatureEnabled('nonexistent'));
    }

    // ---------------------------------------------------------------
    // getSystemPrompt
    // ---------------------------------------------------------------

    public function testGetSystemPromptReturnsConfigValue(): void
    {
        $this->setFactoryConfig([
            'system_prompt' => 'You are a helpful assistant.',
            'providers' => [],
        ]);

        $this->assertEquals('You are a helpful assistant.', AIServiceFactory::getSystemPrompt());
    }

    public function testGetSystemPromptReturnsEmptyStringWhenNotSet(): void
    {
        $this->setFactoryConfig([
            'providers' => [],
        ]);

        $this->assertEquals('', AIServiceFactory::getSystemPrompt());
    }

    // ---------------------------------------------------------------
    // getLimitsConfig
    // ---------------------------------------------------------------

    public function testGetLimitsConfigReturnsConfigValues(): void
    {
        $this->setFactoryConfig([
            'providers' => [],
            'limits' => [
                'daily_limit' => 50,
                'monthly_limit' => 1000,
            ],
        ]);

        $limits = AIServiceFactory::getLimitsConfig();
        $this->assertEquals(50, $limits['daily_limit']);
        $this->assertEquals(1000, $limits['monthly_limit']);
    }

    public function testGetLimitsConfigReturnsEmptyArrayWhenNotSet(): void
    {
        $this->setFactoryConfig([
            'providers' => [],
        ]);

        $limits = AIServiceFactory::getLimitsConfig();
        $this->assertIsArray($limits);
    }

    // ---------------------------------------------------------------
    // chatWithFallback
    // ---------------------------------------------------------------

    public function testChatWithFallbackThrowsWhenNoProviders(): void
    {
        $this->setFactoryConfig([
            'default_provider' => 'nonexistent',
            'providers' => [],
        ]);

        $this->expectException(\Exception::class);
        AIServiceFactory::chatWithFallback(
            [['role' => 'user', 'content' => 'hello']],
            [],
            'nonexistent'
        );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Invoke the private createProvider method via reflection.
     * We also inject provider config so the constructor gets valid data.
     */
    private function invokeCreateProvider(string $providerId, array $config): AIProviderInterface
    {
        // Set up the factory config so getProviderConfig can resolve
        $providers = [];
        $providers[$providerId] = $config;

        $this->setFactoryConfig([
            'providers' => $providers,
        ]);

        $ref = new \ReflectionClass(AIServiceFactory::class);
        $method = $ref->getMethod('createProvider');
        $method->setAccessible(true);

        return $method->invoke(null, $providerId);
    }

    /**
     * Inject a custom config array into AIServiceFactory via reflection
     * so we don't depend on the real config file or DB.
     */
    private function setFactoryConfig(array $config): void
    {
        $ref = new \ReflectionClass(AIServiceFactory::class);
        $prop = $ref->getProperty('config');
        $prop->setAccessible(true);
        $prop->setValue(null, $config);
    }

    /**
     * Pre-set TenantContext::$tenant to a stub with a null ID so that
     * TenantContext::getId() returns null without hitting the database.
     * This causes all `if ($tenantId)` guards in AIServiceFactory to be
     * skipped, making the tests pure unit tests with no DB dependency.
     */
    private function setTenantContextStub(): void
    {
        $ref = new \ReflectionClass(TenantContext::class);
        $prop = $ref->getProperty('tenant');
        $prop->setAccessible(true);
        $prop->setValue(null, ['id' => null]);
    }

    /**
     * Reset TenantContext::$tenant to null after tests.
     */
    private function clearTenantContextStub(): void
    {
        $ref = new \ReflectionClass(TenantContext::class);
        $prop = $ref->getProperty('tenant');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}
