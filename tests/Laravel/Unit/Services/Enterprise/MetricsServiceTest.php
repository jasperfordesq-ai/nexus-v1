<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Enterprise;

use Tests\Laravel\TestCase;
use App\Services\Enterprise\MetricsService;

class MetricsServiceTest extends TestCase
{
    private MetricsService $metrics;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset singleton
        $reflector = new \ReflectionClass(MetricsService::class);
        $instanceProp = $reflector->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null, null);

        $this->metrics = MetricsService::getInstance();
    }

    public function test_getInstance_returns_singleton(): void
    {
        $this->assertSame($this->metrics, MetricsService::getInstance());
    }

    public function test_status_constants_are_defined(): void
    {
        $this->assertEquals(0, MetricsService::STATUS_OK);
        $this->assertEquals(1, MetricsService::STATUS_WARNING);
        $this->assertEquals(2, MetricsService::STATUS_CRITICAL);
        $this->assertEquals(3, MetricsService::STATUS_UNKNOWN);
    }

    public function test_isEnabled_returns_bool(): void
    {
        $this->assertIsBool($this->metrics->isEnabled());
    }

    public function test_increment_does_not_throw_when_disabled(): void
    {
        $this->metrics->increment('test.counter');
        $this->assertTrue(true);
    }

    public function test_decrement_does_not_throw_when_disabled(): void
    {
        $this->metrics->decrement('test.counter');
        $this->assertTrue(true);
    }

    public function test_gauge_does_not_throw_when_disabled(): void
    {
        $this->metrics->gauge('test.gauge', 42.0);
        $this->assertTrue(true);
    }

    public function test_histogram_does_not_throw_when_disabled(): void
    {
        $this->metrics->histogram('test.histogram', 100.5);
        $this->assertTrue(true);
    }

    public function test_timing_does_not_throw_when_disabled(): void
    {
        $this->metrics->timing('test.timing', 250.0);
        $this->assertTrue(true);
    }

    public function test_time_executes_callable_and_returns_result(): void
    {
        $result = $this->metrics->time('test.time', fn() => 42);
        $this->assertEquals(42, $result);
    }

    public function test_time_propagates_exceptions(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->metrics->time('test.time', fn() => throw new \RuntimeException('fail'));
    }

    public function test_increment_accepts_tags_as_second_argument(): void
    {
        $this->metrics->increment('test.counter', ['tag' => 'value']);
        $this->assertTrue(true);
    }

    public function test_addGlobalTag_does_not_throw(): void
    {
        $this->metrics->addGlobalTag('custom', 'value');
        $this->assertTrue(true);
    }

    public function test_flush_does_not_throw_when_empty(): void
    {
        $this->metrics->flush();
        $this->assertTrue(true);
    }

    public function test_business_metric_does_not_throw(): void
    {
        $this->metrics->business('revenue', 100.0);
        $this->assertTrue(true);
    }
}
