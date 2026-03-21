<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Enterprise;

use Tests\Laravel\TestCase;
use App\Services\Enterprise\ConfigService;

class ConfigServiceTest extends TestCase
{
    private ConfigService $config;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset singleton for test isolation
        $reflector = new \ReflectionClass(ConfigService::class);
        $instanceProp = $reflector->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null, null);

        $this->config = ConfigService::getInstance();
    }

    public function test_getInstance_returns_singleton(): void
    {
        $a = ConfigService::getInstance();
        $b = ConfigService::getInstance();
        $this->assertSame($a, $b);
    }

    public function test_isUsingVault_returns_false_by_default(): void
    {
        $this->assertFalse($this->config->isUsingVault());
    }

    public function test_getDatabase_returns_array_with_expected_keys(): void
    {
        $result = $this->config->getDatabase();

        $this->assertArrayHasKey('host', $result);
        $this->assertArrayHasKey('database', $result);
        $this->assertArrayHasKey('username', $result);
        $this->assertArrayHasKey('charset', $result);
    }

    public function test_getRedis_returns_array_with_expected_keys(): void
    {
        $result = $this->config->getRedis();

        $this->assertArrayHasKey('host', $result);
        $this->assertArrayHasKey('port', $result);
        $this->assertIsInt($result['port']);
    }

    public function test_getPusher_returns_array(): void
    {
        $result = $this->config->getPusher();
        $this->assertArrayHasKey('app_id', $result);
        $this->assertArrayHasKey('key', $result);
    }

    public function test_getOpenAI_returns_array(): void
    {
        $result = $this->config->getOpenAI();
        $this->assertArrayHasKey('api_key', $result);
    }

    public function test_getAnthropic_returns_array(): void
    {
        $result = $this->config->getAnthropic();
        $this->assertArrayHasKey('api_key', $result);
    }

    public function test_getEnvironment_returns_string(): void
    {
        $this->assertIsString($this->config->getEnvironment());
    }

    public function test_getInt_returns_default_when_missing(): void
    {
        $this->assertEquals(42, $this->config->getInt('NONEXISTENT_ENV_VAR_12345', 42));
    }

    public function test_getBool_returns_default_when_missing(): void
    {
        $this->assertFalse($this->config->getBool('NONEXISTENT_ENV_VAR_12345', false));
    }

    public function test_getArray_returns_default_when_missing(): void
    {
        $this->assertEquals(['a'], $this->config->getArray('NONEXISTENT_ENV_VAR_12345', ['a']));
    }

    public function test_getRequired_throws_when_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->config->getRequired('NONEXISTENT_REQUIRED_KEY_12345');
    }

    public function test_validate_returns_missing_keys(): void
    {
        $result = $this->config->validate(['NONEXISTENT_KEY_A', 'NONEXISTENT_KEY_B']);

        $this->assertFalse($result['valid']);
        $this->assertCount(2, $result['missing']);
    }

    public function test_isProduction_returns_bool(): void
    {
        $this->assertIsBool($this->config->isProduction());
    }

    public function test_isDebug_returns_bool(): void
    {
        $this->assertIsBool($this->config->isDebug());
    }

    public function test_clearCache_does_not_throw(): void
    {
        $this->config->clearCache();
        $this->assertTrue(true);
    }

    public function test_getStatus_returns_expected_keys(): void
    {
        $result = $this->config->getStatus();

        $this->assertArrayHasKey('vault_enabled', $result);
        $this->assertArrayHasKey('environment', $result);
        $this->assertArrayHasKey('debug_mode', $result);
    }

    public function test_getAll_returns_array_for_multiple_keys(): void
    {
        $result = $this->config->getAll(['APP_ENV', 'NONEXISTENT']);
        $this->assertCount(2, $result);
    }
}
