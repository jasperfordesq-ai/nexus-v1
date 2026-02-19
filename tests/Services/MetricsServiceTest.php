<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\TestCase;
use Nexus\Services\Enterprise\MetricsService;

/**
 * MetricsService Tests
 *
 * Tests system monitoring and metrics collection including:
 * - Singleton pattern
 * - Counter increment/decrement
 * - Gauge values
 * - Histogram recording
 * - Distribution values
 * - Timing measurement
 * - Service checks
 * - Event recording
 * - Business metrics
 * - Buffered metric sending
 * - Global tags
 * - Metric formatting
 *
 * @covers \Nexus\Services\Enterprise\MetricsService
 */
class MetricsServiceTest extends TestCase
{
    private MetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset singleton to get a fresh instance
        $ref = new \ReflectionClass(MetricsService::class);
        $instanceProp = $ref->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null, null);

        // Ensure metrics are disabled (no StatsD/DD_AGENT_HOST set)
        putenv('DD_AGENT_HOST=');
        putenv('STATSD_HOST=');

        $this->service = MetricsService::getInstance();
    }

    protected function tearDown(): void
    {
        // Reset singleton
        $ref = new \ReflectionClass(MetricsService::class);
        $instanceProp = $ref->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null, null);

        putenv('DD_AGENT_HOST=');
        putenv('STATSD_HOST=');
        putenv('METRICS_PREFIX=');
        putenv('APP_ENV=');
        putenv('DD_SERVICE=');
        putenv('APP_VERSION=');

        parent::tearDown();
    }

    // =========================================================================
    // SINGLETON PATTERN TESTS
    // =========================================================================

    public function testGetInstanceReturnsSameInstance(): void
    {
        $instance1 = MetricsService::getInstance();
        $instance2 = MetricsService::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testConstructorIsPrivate(): void
    {
        $ref = new \ReflectionClass(MetricsService::class);
        $constructor = $ref->getConstructor();

        $this->assertTrue($constructor->isPrivate());
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(MetricsService::class));
    }

    // =========================================================================
    // ENABLED STATE TESTS
    // =========================================================================

    public function testIsDisabledWithoutStatsD(): void
    {
        $this->assertFalse($this->service->isEnabled());
    }

    public function testIsEnabledMethodExists(): void
    {
        $this->assertTrue(method_exists(MetricsService::class, 'isEnabled'));
    }

    // =========================================================================
    // PUBLIC METHOD EXISTENCE TESTS
    // =========================================================================

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'getInstance',
            'isEnabled',
            'increment',
            'decrement',
            'gauge',
            'histogram',
            'distribution',
            'timing',
            'time',
            'set',
            'serviceCheck',
            'event',
            'business',
            'flush',
            'addGlobalTag',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(MetricsService::class, $method),
                "Method {$method} should exist on MetricsService"
            );
        }
    }

    // =========================================================================
    // INCREMENT / DECREMENT TESTS
    // =========================================================================

    public function testIncrementMethodSignature(): void
    {
        $ref = new \ReflectionMethod(MetricsService::class, 'increment');
        $params = $ref->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('metric', $params[0]->getName());
        $this->assertEquals('tags', $params[1]->getName());
        $this->assertEquals('value', $params[2]->getName());

        $this->assertEquals([], $params[1]->getDefaultValue());
        $this->assertEquals(1, $params[2]->getDefaultValue());
    }

    public function testDecrementMethodSignature(): void
    {
        $ref = new \ReflectionMethod(MetricsService::class, 'decrement');
        $params = $ref->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals(1, $params[2]->getDefaultValue());
    }

    public function testIncrementDoesNotThrowWhenDisabled(): void
    {
        // Should silently do nothing when metrics are disabled
        $this->service->increment('test.counter', ['env' => 'test']);

        $this->assertFalse($this->service->isEnabled());
    }

    public function testDecrementDoesNotThrowWhenDisabled(): void
    {
        $this->service->decrement('test.counter', ['env' => 'test']);

        $this->assertFalse($this->service->isEnabled());
    }

    // =========================================================================
    // GAUGE TESTS
    // =========================================================================

    public function testGaugeMethodSignature(): void
    {
        $ref = new \ReflectionMethod(MetricsService::class, 'gauge');
        $params = $ref->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('metric', $params[0]->getName());
        $this->assertEquals('value', $params[1]->getName());
        $this->assertEquals('tags', $params[2]->getName());

        // value is float
        $this->assertEquals('float', $params[1]->getType()->getName());
    }

    public function testGaugeDoesNotThrowWhenDisabled(): void
    {
        $this->service->gauge('test.gauge', 42.5, ['service' => 'test']);

        $this->assertFalse($this->service->isEnabled());
    }

    // =========================================================================
    // HISTOGRAM TESTS
    // =========================================================================

    public function testHistogramMethodSignature(): void
    {
        $ref = new \ReflectionMethod(MetricsService::class, 'histogram');
        $params = $ref->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('metric', $params[0]->getName());
        $this->assertEquals('value', $params[1]->getName());
        $this->assertEquals('tags', $params[2]->getName());
    }

    public function testHistogramDoesNotThrowWhenDisabled(): void
    {
        $this->service->histogram('test.response_time', 150.5);

        $this->assertFalse($this->service->isEnabled());
    }

    // =========================================================================
    // DISTRIBUTION TESTS
    // =========================================================================

    public function testDistributionDoesNotThrowWhenDisabled(): void
    {
        $this->service->distribution('test.latency', 25.3, ['endpoint' => '/api/test']);

        $this->assertFalse($this->service->isEnabled());
    }

    // =========================================================================
    // TIMING TESTS
    // =========================================================================

    public function testTimingMethodSignature(): void
    {
        $ref = new \ReflectionMethod(MetricsService::class, 'timing');
        $params = $ref->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('metric', $params[0]->getName());
        $this->assertEquals('milliseconds', $params[1]->getName());
        $this->assertEquals('tags', $params[2]->getName());
    }

    public function testTimingDoesNotThrowWhenDisabled(): void
    {
        $this->service->timing('test.query_time', 45.2);

        $this->assertFalse($this->service->isEnabled());
    }

    // =========================================================================
    // TIME CALLABLE TESTS
    // =========================================================================

    public function testTimeExecutesCallableAndReturnsResult(): void
    {
        $result = $this->service->time('test.operation', function () {
            return 'hello world';
        });

        $this->assertEquals('hello world', $result);
    }

    public function testTimeReThrowsExceptions(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test error');

        $this->service->time('test.failing_operation', function () {
            throw new \RuntimeException('Test error');
        });
    }

    public function testTimeHandlesCallableThatReturnsNull(): void
    {
        $result = $this->service->time('test.void_operation', function () {
            return null;
        });

        $this->assertNull($result);
    }

    public function testTimeHandlesCallableThatReturnsArray(): void
    {
        $result = $this->service->time('test.array_operation', function () {
            return ['key' => 'value', 'count' => 42];
        });

        $this->assertIsArray($result);
        $this->assertEquals('value', $result['key']);
    }

    // =========================================================================
    // SET (UNIQUE OCCURRENCES) TESTS
    // =========================================================================

    public function testSetDoesNotThrowWhenDisabled(): void
    {
        $this->service->set('test.unique_users', 'user_123');

        $this->assertFalse($this->service->isEnabled());
    }

    // =========================================================================
    // SERVICE CHECK TESTS
    // =========================================================================

    public function testServiceCheckMethodSignature(): void
    {
        $ref = new \ReflectionMethod(MetricsService::class, 'serviceCheck');
        $params = $ref->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('name', $params[0]->getName());
        $this->assertEquals('status', $params[1]->getName());
        $this->assertEquals('options', $params[2]->getName());
    }

    public function testServiceCheckDoesNotThrowWhenDisabled(): void
    {
        $this->service->serviceCheck('database', 0, ['tags' => ['db' => 'mysql']]);

        $this->assertFalse($this->service->isEnabled());
    }

    // =========================================================================
    // EVENT TESTS
    // =========================================================================

    public function testEventMethodSignature(): void
    {
        $ref = new \ReflectionMethod(MetricsService::class, 'event');
        $params = $ref->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('title', $params[0]->getName());
        $this->assertEquals('text', $params[1]->getName());
        $this->assertEquals('options', $params[2]->getName());
    }

    public function testEventDoesNotThrowWhenDisabled(): void
    {
        $this->service->event('Deployment', 'Version 2.0 deployed', [
            'alert_type' => 'info',
            'priority' => 'normal',
        ]);

        $this->assertFalse($this->service->isEnabled());
    }

    // =========================================================================
    // BUSINESS METRIC TESTS
    // =========================================================================

    public function testBusinessDoesNotThrowWhenDisabled(): void
    {
        $this->service->business('time_credits_transferred', 150.0, ['tenant' => 'hour-timebank']);

        $this->assertFalse($this->service->isEnabled());
    }

    // =========================================================================
    // BUFFER TESTS
    // =========================================================================

    public function testFlushDoesNotThrowWhenEmpty(): void
    {
        $this->service->flush();

        // Should not throw
        $this->assertTrue(true);
    }

    public function testBufferPropertyExists(): void
    {
        $ref = new \ReflectionClass(MetricsService::class);
        $this->assertTrue($ref->hasProperty('buffer'));

        $bufferProp = $ref->getProperty('buffer');
        $bufferProp->setAccessible(true);

        $this->assertIsArray($bufferProp->getValue($this->service));
    }

    public function testBufferSizePropertyExists(): void
    {
        $ref = new \ReflectionClass(MetricsService::class);
        $bufferSizeProp = $ref->getProperty('bufferSize');
        $bufferSizeProp->setAccessible(true);

        $this->assertEquals(50, $bufferSizeProp->getValue($this->service));
    }

    public function testUseBufferEnabledByDefault(): void
    {
        $ref = new \ReflectionClass(MetricsService::class);
        $useBufferProp = $ref->getProperty('useBuffer');
        $useBufferProp->setAccessible(true);

        $this->assertTrue($useBufferProp->getValue($this->service));
    }

    // =========================================================================
    // GLOBAL TAGS TESTS
    // =========================================================================

    public function testDefaultGlobalTags(): void
    {
        $ref = new \ReflectionClass(MetricsService::class);
        $tagsProp = $ref->getProperty('globalTags');
        $tagsProp->setAccessible(true);

        $tags = $tagsProp->getValue($this->service);

        $this->assertArrayHasKey('env', $tags);
        $this->assertArrayHasKey('service', $tags);
        $this->assertArrayHasKey('version', $tags);
    }

    public function testAddGlobalTagAddsTag(): void
    {
        $this->service->addGlobalTag('region', 'eu-west-1');

        $ref = new \ReflectionClass(MetricsService::class);
        $tagsProp = $ref->getProperty('globalTags');
        $tagsProp->setAccessible(true);

        $tags = $tagsProp->getValue($this->service);

        $this->assertArrayHasKey('region', $tags);
        $this->assertEquals('eu-west-1', $tags['region']);
    }

    public function testAddGlobalTagOverwritesExisting(): void
    {
        $this->service->addGlobalTag('env', 'staging');

        $ref = new \ReflectionClass(MetricsService::class);
        $tagsProp = $ref->getProperty('globalTags');
        $tagsProp->setAccessible(true);

        $tags = $tagsProp->getValue($this->service);

        $this->assertEquals('staging', $tags['env']);
    }

    // =========================================================================
    // TAG FORMATTING TESTS
    // =========================================================================

    public function testFormatTagsReturnsFormattedArray(): void
    {
        $ref = new \ReflectionClass(MetricsService::class);
        $method = $ref->getMethod('formatTags');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, ['env' => 'production', 'service' => 'nexus']);

        $this->assertContains('env:production', $result);
        $this->assertContains('service:nexus', $result);
    }

    public function testFormatTagsStringReturnsCommaSeparated(): void
    {
        $ref = new \ReflectionClass(MetricsService::class);
        $method = $ref->getMethod('formatTagsString');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, ['env' => 'test', 'host' => 'local']);

        $this->assertStringContainsString('env:test', $result);
        $this->assertStringContainsString('host:local', $result);
        $this->assertStringContainsString(',', $result);
    }

    public function testFormatTagsWithEmptyArray(): void
    {
        $ref = new \ReflectionClass(MetricsService::class);
        $method = $ref->getMethod('formatTags');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // METRIC PREFIX TESTS
    // =========================================================================

    public function testDefaultPrefixIsNexus(): void
    {
        $ref = new \ReflectionClass(MetricsService::class);
        $prefixProp = $ref->getProperty('prefix');
        $prefixProp->setAccessible(true);

        $this->assertEquals('nexus', $prefixProp->getValue($this->service));
    }

    // =========================================================================
    // STATSD CLIENT TESTS
    // =========================================================================

    public function testStatsdClientIsNullWhenDisabled(): void
    {
        $ref = new \ReflectionClass(MetricsService::class);
        $statsdProp = $ref->getProperty('statsd');
        $statsdProp->setAccessible(true);

        $this->assertNull($statsdProp->getValue($this->service));
    }
}
