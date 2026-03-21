<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\EmailMonitorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmailMonitorServiceTest extends TestCase
{
    private EmailMonitorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailMonitorService();
    }

    // =========================================================================
    // recordEmailSend / recordEmailSendStatic
    // =========================================================================

    public function test_recordEmailSend_increments_cache_counters_on_success(): void
    {
        Cache::shouldReceive('get')->andReturn(0);
        Cache::shouldReceive('put')->times(4); // 3 counter keys + 1 timestamp

        $this->service->recordEmailSend('gmail_api', true, 2);
    }

    public function test_recordEmailSend_records_failure_timestamp_on_failure(): void
    {
        Cache::shouldReceive('get')->andReturn(0);
        Cache::shouldReceive('put')->times(5); // 3 counters + last_send + last_failure

        $this->service->recordEmailSend('sendgrid', false, 2);
    }

    public function test_recordEmailSend_uses_global_scope_when_no_tenant(): void
    {
        Cache::shouldReceive('get')->withArgs(function ($key) {
            return str_contains($key, 'global:');
        })->andReturn(0);
        Cache::shouldReceive('put')->times(4);

        $this->service->recordEmailSend('smtp', true, null);
    }

    public function test_recordEmailSend_never_throws_on_cache_failure(): void
    {
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('Cache down'));
        Log::shouldReceive('debug')->once();

        // Should not throw
        $this->service->recordEmailSend('gmail_api', true, 2);
    }

    // =========================================================================
    // recordTokenRefresh
    // =========================================================================

    public function test_recordTokenRefresh_increments_counter(): void
    {
        Cache::shouldReceive('get')->andReturn(0);
        Cache::shouldReceive('put')->times(2); // counter + timestamp

        $this->service->recordTokenRefresh(true, 2);
    }

    // =========================================================================
    // recordFallbackToSmtp
    // =========================================================================

    public function test_recordFallbackToSmtp_stores_reason(): void
    {
        Cache::shouldReceive('get')->andReturn(0);
        Cache::shouldReceive('put')->times(3); // count + reason + timestamp

        $this->service->recordFallbackToSmtp('Token expired', 2);
    }

    // =========================================================================
    // recordCircuitBreakerOpen
    // =========================================================================

    public function test_recordCircuitBreakerOpen_increments_counter(): void
    {
        Cache::shouldReceive('get')->andReturn(0);
        Cache::shouldReceive('put')->times(2);

        $this->service->recordCircuitBreakerOpen(2);
    }

    // =========================================================================
    // recordRateLimitHit
    // =========================================================================

    public function test_recordRateLimitHit_increments_counter(): void
    {
        Cache::shouldReceive('get')->andReturn(0);
        Cache::shouldReceive('put')->times(2);

        $this->service->recordRateLimitHit(2);
    }

    // =========================================================================
    // getHealthSummary
    // =========================================================================

    public function test_getHealthSummary_returns_expected_structure(): void
    {
        Cache::shouldReceive('get')->andReturn(0);

        $result = $this->service->getHealthSummary(2);

        $this->assertArrayHasKey('total_sent', $result);
        $this->assertArrayHasKey('total_failed', $result);
        $this->assertArrayHasKey('success_rate', $result);
        $this->assertArrayHasKey('providers', $result);
        $this->assertArrayHasKey('token_refreshes', $result);
        $this->assertArrayHasKey('smtp_fallbacks', $result);
        $this->assertArrayHasKey('circuit_breaker', $result);
        $this->assertArrayHasKey('rate_limits', $result);
        $this->assertEquals('rolling_24h', $result['period']);
    }

    public function test_getHealthSummary_calculates_success_rate(): void
    {
        Cache::shouldReceive('get')
            ->andReturnUsing(function ($key, $default = 0) {
                if (str_contains($key, 'total:success')) return 80;
                if (str_contains($key, 'total:failure')) return 20;
                return $default;
            });

        $result = $this->service->getHealthSummary(2);

        $this->assertEquals(80.0, $result['success_rate']);
    }

    public function test_getHealthSummary_returns_100_percent_when_no_sends(): void
    {
        Cache::shouldReceive('get')->andReturn(0);

        $result = $this->service->getHealthSummary(2);
        $this->assertEquals(100.0, $result['success_rate']);
    }

    public function test_getHealthSummary_returns_safe_defaults_on_cache_error(): void
    {
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('Cache down'));
        Log::shouldReceive('warning')->once();

        $result = $this->service->getHealthSummary(2);

        $this->assertEquals(0, $result['total_sent']);
        $this->assertArrayHasKey('error', $result);
    }
}
