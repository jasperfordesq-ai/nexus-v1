<?php

declare(strict_types=1);

namespace Nexus\Tests\Enterprise;

use Nexus\Services\Enterprise\ConfigService;
use Nexus\Tests\TestCase;

/**
 * Configuration Service Tests
 *
 * Tests for the enterprise configuration service including
 * Vault integration and environment variable fallback.
 */
class ConfigServiceTest extends TestCase
{
    private ConfigService $configService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configService = ConfigService::getInstance();
    }

    /**
     * Test getting a configuration value.
     */
    public function testGetConfigValue(): void
    {
        // Set environment variable for testing
        putenv('APP_NAME=TestApp');

        $value = $this->configService->get('APP_NAME', 'DefaultApp');

        $this->assertEquals('TestApp', $value);
    }

    /**
     * Test default value is returned when key not found.
     */
    public function testGetDefaultValue(): void
    {
        putenv('NONEXISTENT_KEY');

        $value = $this->configService->get('NONEXISTENT_KEY', 'DefaultValue');

        $this->assertEquals('DefaultValue', $value);
    }

    /**
     * Test environment detection.
     */
    public function testGetEnvironment(): void
    {
        putenv('APP_ENV=testing');

        $env = $this->configService->getEnvironment();

        $this->assertEquals('testing', $env);
    }

    /**
     * Test debug mode detection.
     */
    public function testIsDebug(): void
    {
        putenv('APP_DEBUG=true');
        $this->assertTrue($this->configService->isDebug());

        putenv('APP_DEBUG=false');
        // Need to clear cache or create new instance
        $newConfigService = new ConfigService();
        $this->setPrivateProperty($newConfigService, 'cache', []);
        $this->assertFalse($newConfigService->isDebug());
    }

    /**
     * Test production environment check.
     */
    public function testIsProduction(): void
    {
        putenv('APP_ENV=production');

        $configService = new ConfigService();

        $this->assertTrue($configService->isProduction());
    }

    /**
     * Test getting required configuration throws exception when missing.
     */
    public function testGetRequiredThrowsExceptionWhenMissing(): void
    {
        putenv('REQUIRED_KEY');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required configuration key not found: REQUIRED_KEY');

        $this->configService->getRequired('REQUIRED_KEY');
    }

    /**
     * Test getting integer configuration value.
     */
    public function testGetInt(): void
    {
        putenv('TEST_INT=42');

        $value = $this->configService->getInt('TEST_INT');

        $this->assertIsInt($value);
        $this->assertEquals(42, $value);
    }

    /**
     * Test getting boolean configuration value.
     */
    public function testGetBool(): void
    {
        putenv('TEST_BOOL_TRUE=true');
        putenv('TEST_BOOL_ONE=1');
        putenv('TEST_BOOL_FALSE=false');
        putenv('TEST_BOOL_ZERO=0');

        $this->assertTrue($this->configService->getBool('TEST_BOOL_TRUE'));
        $this->assertTrue($this->configService->getBool('TEST_BOOL_ONE'));
        $this->assertFalse($this->configService->getBool('TEST_BOOL_FALSE'));
        $this->assertFalse($this->configService->getBool('TEST_BOOL_ZERO'));
    }

    /**
     * Test getting array configuration value.
     */
    public function testGetArray(): void
    {
        putenv('TEST_ARRAY=one,two,three');

        $value = $this->configService->getArray('TEST_ARRAY');

        $this->assertIsArray($value);
        $this->assertEquals(['one', 'two', 'three'], $value);
    }

    /**
     * Test configuration caching.
     */
    public function testConfigurationCaching(): void
    {
        putenv('CACHED_KEY=original');

        // First call - should cache
        $value1 = $this->configService->get('CACHED_KEY');
        $this->assertEquals('original', $value1);

        // Change environment variable
        putenv('CACHED_KEY=modified');

        // Second call - should return cached value
        $value2 = $this->configService->get('CACHED_KEY');
        $this->assertEquals('original', $value2);

        // Clear cache and get fresh value
        $this->configService->clearCache();
        $value3 = $this->configService->get('CACHED_KEY');
        $this->assertEquals('modified', $value3);
    }

    /**
     * Test getting all configuration values.
     */
    public function testGetAll(): void
    {
        putenv('TEST_ALL_A=valueA');
        putenv('TEST_ALL_B=valueB');

        $all = $this->configService->getAll(['TEST_ALL_A', 'TEST_ALL_B']);

        $this->assertIsArray($all);
        $this->assertEquals('valueA', $all['TEST_ALL_A']);
        $this->assertEquals('valueB', $all['TEST_ALL_B']);
    }

    /**
     * Test configuration validation.
     */
    public function testValidateConfiguration(): void
    {
        putenv('REQUIRED_A=valueA');
        putenv('REQUIRED_B=');

        $result = $this->configService->validate(['REQUIRED_A', 'REQUIRED_B']);

        $this->assertFalse($result['valid']);
        $this->assertContains('REQUIRED_B', $result['missing']);
    }

    /**
     * Test singleton pattern.
     */
    public function testSingletonPattern(): void
    {
        $instance1 = ConfigService::getInstance();
        $instance2 = ConfigService::getInstance();

        $this->assertSame($instance1, $instance2);
    }
}
