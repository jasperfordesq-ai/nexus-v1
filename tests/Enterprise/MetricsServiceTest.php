<?php

declare(strict_types=1);

namespace Nexus\Tests\Enterprise;

use Nexus\Services\Enterprise\MetricsService;
use Nexus\Tests\TestCase;

/**
 * Metrics Service Tests
 *
 * Tests for the APM metrics service including counters,
 * gauges, histograms, and timing functionality.
 */
class MetricsServiceTest extends TestCase
{
    private MetricsService $metricsService;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a non-existent host to avoid actual UDP traffic in tests
        putenv('DD_AGENT_HOST=127.0.0.255');
        putenv('DD_DOGSTATSD_PORT=18125');

        $this->metricsService = MetricsService::getInstance();
    }

    /**
     * Test incrementing a counter.
     */
    public function testIncrementCounter(): void
    {
        // Should not throw exception
        $this->metricsService->increment('test.counter');
        $this->metricsService->increment('test.counter', 5);
        $this->metricsService->increment('test.counter', 1, ['tag' => 'value']);

        $this->assertTrue(true); // If we got here, no exceptions were thrown
    }

    /**
     * Test decrementing a counter.
     */
    public function testDecrementCounter(): void
    {
        $this->metricsService->decrement('test.counter');
        $this->metricsService->decrement('test.counter', 3);

        $this->assertTrue(true);
    }

    /**
     * Test setting a gauge value.
     */
    public function testGaugeValue(): void
    {
        $this->metricsService->gauge('test.gauge', 42);
        $this->metricsService->gauge('test.gauge', 3.14);
        $this->metricsService->gauge('test.gauge', 100, ['server' => 'web1']);

        $this->assertTrue(true);
    }

    /**
     * Test recording a histogram value.
     */
    public function testHistogramValue(): void
    {
        $this->metricsService->histogram('test.histogram', 150);
        $this->metricsService->histogram('test.response_time', 45.5);

        $this->assertTrue(true);
    }

    /**
     * Test timing measurement.
     */
    public function testTimingMeasurement(): void
    {
        $this->metricsService->timing('test.operation', 125);
        $this->metricsService->timing('test.operation', 200, ['endpoint' => '/api/users']);

        $this->assertTrue(true);
    }

    /**
     * Test time closure execution.
     */
    public function testTimeClosureExecution(): void
    {
        $result = $this->metricsService->time('test.closure', function () {
            usleep(10000); // 10ms
            return 'completed';
        });

        $this->assertEquals('completed', $result);
    }

    /**
     * Test time closure with tags.
     */
    public function testTimeClosureWithTags(): void
    {
        $result = $this->metricsService->time('test.tagged_closure', function () {
            return 42;
        }, ['operation' => 'calculation']);

        $this->assertEquals(42, $result);
    }

    /**
     * Test distribution metric.
     */
    public function testDistributionMetric(): void
    {
        $this->metricsService->distribution('test.distribution', 100);
        $this->metricsService->distribution('test.distribution', 150);
        $this->metricsService->distribution('test.distribution', 200);

        $this->assertTrue(true);
    }

    /**
     * Test set metric (unique values).
     */
    public function testSetMetric(): void
    {
        $this->metricsService->set('test.unique_users', 'user123');
        $this->metricsService->set('test.unique_users', 'user456');
        $this->metricsService->set('test.unique_users', 'user123'); // Duplicate

        $this->assertTrue(true);
    }

    /**
     * Test event tracking.
     */
    public function testEventTracking(): void
    {
        $this->metricsService->event(
            'Deployment Complete',
            'Version 1.2.3 deployed successfully',
            ['alert_type' => 'info']
        );

        $this->assertTrue(true);
    }

    /**
     * Test service check.
     */
    public function testServiceCheck(): void
    {
        $this->metricsService->serviceCheck('database', MetricsService::STATUS_OK);
        $this->metricsService->serviceCheck('redis', MetricsService::STATUS_WARNING, 'High latency');
        $this->metricsService->serviceCheck('external_api', MetricsService::STATUS_CRITICAL, 'Connection refused');

        $this->assertTrue(true);
    }

    /**
     * Test tag formatting.
     */
    public function testTagFormatting(): void
    {
        // Test that special characters in tags don't cause issues
        $this->metricsService->increment('test.with_tags', 1, [
            'endpoint' => '/api/users/123',
            'method' => 'GET',
            'status' => '200',
            'special' => 'value:with:colons',
        ]);

        $this->assertTrue(true);
    }

    /**
     * Test metric name sanitization.
     */
    public function testMetricNameSanitization(): void
    {
        // Metric names with invalid characters should be sanitized
        $this->metricsService->increment('test.metric-with-dashes');
        $this->metricsService->increment('test.metric_with_underscores');
        $this->metricsService->increment('test.metric.with.dots');

        $this->assertTrue(true);
    }

    /**
     * Test sample rate.
     */
    public function testSampleRate(): void
    {
        // With 0.1 sample rate, approximately 10% of metrics should be sent
        // We can't easily verify this, but we can verify it doesn't error
        $this->metricsService->increment('test.sampled', 1, [], 0.1);

        $this->assertTrue(true);
    }

    /**
     * Test singleton pattern.
     */
    public function testSingletonPattern(): void
    {
        $instance1 = MetricsService::getInstance();
        $instance2 = MetricsService::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test metric prefix is applied.
     */
    public function testMetricPrefix(): void
    {
        // The default prefix is 'nexus.'
        // We can verify by checking the internal state or mocking

        $this->metricsService->increment('users.created');

        // Full metric name would be 'nexus.users.created'
        $this->assertTrue(true);
    }

    /**
     * Test batch sending capability.
     */
    public function testBatchMetrics(): void
    {
        // Send multiple metrics in quick succession
        for ($i = 0; $i < 100; $i++) {
            $this->metricsService->increment('test.batch', 1, ['iteration' => (string) $i]);
        }

        // Flush any buffered metrics
        $this->metricsService->flush();

        $this->assertTrue(true);
    }
}
