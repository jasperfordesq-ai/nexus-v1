<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\EmailMonitorService;
use Illuminate\Support\Facades\Cache;

/**
 * EmailMonitorServiceTest — tests for email delivery health monitoring.
 */
class EmailMonitorServiceTest extends TestCase
{
    private EmailMonitorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailMonitorService();
        Cache::flush();
    }

    // =========================================================================
    // recordEmailSend
    // =========================================================================

    public function testRecordEmailSendSuccessIncrementsCacheCounters(): void
    {
        $this->service->recordEmailSend('gmail_api', true, 1);

        $this->assertEquals(1, Cache::get('email_monitor:tenant:1:gmail_api:success'));
        $this->assertEquals(1, Cache::get('email_monitor:tenant:1:total:success'));
        $this->assertEquals(1, Cache::get('email_monitor:tenant:1:gmail_api:total'));
    }

    public function testRecordEmailSendFailureIncrementsCacheCounters(): void
    {
        $this->service->recordEmailSend('smtp', false, 2);

        $this->assertEquals(1, Cache::get('email_monitor:tenant:2:smtp:failure'));
        $this->assertEquals(1, Cache::get('email_monitor:tenant:2:total:failure'));
        $this->assertEquals(1, Cache::get('email_monitor:tenant:2:smtp:total'));
    }

    public function testRecordEmailSendFailureSetsLastFailureTimestamp(): void
    {
        $this->service->recordEmailSend('gmail_api', false, 1);

        $lastFailure = Cache::get('email_monitor:tenant:1:gmail_api:last_failure');
        $this->assertNotNull($lastFailure);
    }

    public function testRecordEmailSendSuccessDoesNotSetLastFailure(): void
    {
        $this->service->recordEmailSend('gmail_api', true, 1);

        $lastFailure = Cache::get('email_monitor:tenant:1:gmail_api:last_failure');
        $this->assertNull($lastFailure);
    }

    public function testRecordEmailSendSetsLastSendTimestamp(): void
    {
        $this->service->recordEmailSend('sendgrid', true, 1);

        $lastSend = Cache::get('email_monitor:tenant:1:sendgrid:last_send');
        $this->assertNotNull($lastSend);
    }

    public function testRecordEmailSendGlobalScopeWhenNoTenant(): void
    {
        $this->service->recordEmailSend('smtp', true);

        $this->assertEquals(1, Cache::get('email_monitor:global:smtp:success'));
        $this->assertEquals(1, Cache::get('email_monitor:global:total:success'));
    }

    public function testRecordEmailSendStaticMethodWorks(): void
    {
        EmailMonitorService::recordEmailSendStatic('gmail_api', true, 5);

        $this->assertEquals(1, Cache::get('email_monitor:tenant:5:gmail_api:success'));
    }

    public function testMultipleSendsAccumulate(): void
    {
        $this->service->recordEmailSend('gmail_api', true, 1);
        $this->service->recordEmailSend('gmail_api', true, 1);
        $this->service->recordEmailSend('gmail_api', false, 1);

        $this->assertEquals(2, Cache::get('email_monitor:tenant:1:gmail_api:success'));
        $this->assertEquals(1, Cache::get('email_monitor:tenant:1:gmail_api:failure'));
        $this->assertEquals(3, Cache::get('email_monitor:tenant:1:gmail_api:total'));
    }

    // =========================================================================
    // recordTokenRefresh
    // =========================================================================

    public function testRecordTokenRefreshSuccess(): void
    {
        $this->service->recordTokenRefresh(true, 1);

        $this->assertEquals(1, Cache::get('email_monitor:tenant:1:token_refresh:success'));
        $this->assertNotNull(Cache::get('email_monitor:tenant:1:token_refresh:last_attempt'));
    }

    public function testRecordTokenRefreshFailure(): void
    {
        $this->service->recordTokenRefresh(false, 1);

        $this->assertEquals(1, Cache::get('email_monitor:tenant:1:token_refresh:failure'));
    }

    // =========================================================================
    // recordFallbackToSmtp
    // =========================================================================

    public function testRecordFallbackToSmtp(): void
    {
        $this->service->recordFallbackToSmtp('Token expired', 1);

        $this->assertEquals(1, Cache::get('email_monitor:tenant:1:smtp_fallback:count'));
        $this->assertEquals('Token expired', Cache::get('email_monitor:tenant:1:smtp_fallback:last_reason'));
        $this->assertNotNull(Cache::get('email_monitor:tenant:1:smtp_fallback:last_at'));
    }

    // =========================================================================
    // recordCircuitBreakerOpen
    // =========================================================================

    public function testRecordCircuitBreakerOpen(): void
    {
        $this->service->recordCircuitBreakerOpen(1);

        $this->assertEquals(1, Cache::get('email_monitor:tenant:1:circuit_breaker:opens'));
        $this->assertNotNull(Cache::get('email_monitor:tenant:1:circuit_breaker:last_opened'));
    }

    // =========================================================================
    // recordRateLimitHit
    // =========================================================================

    public function testRecordRateLimitHit(): void
    {
        $this->service->recordRateLimitHit(1);

        $this->assertEquals(1, Cache::get('email_monitor:tenant:1:rate_limit:hits'));
        $this->assertNotNull(Cache::get('email_monitor:tenant:1:rate_limit:last_hit'));
    }

    // =========================================================================
    // getHealthSummary
    // =========================================================================

    public function testGetHealthSummaryReturnsDefaultStructure(): void
    {
        $summary = $this->service->getHealthSummary(1);

        $this->assertArrayHasKey('total_sent', $summary);
        $this->assertArrayHasKey('total_failed', $summary);
        $this->assertArrayHasKey('success_rate', $summary);
        $this->assertArrayHasKey('providers', $summary);
        $this->assertArrayHasKey('token_refreshes', $summary);
        $this->assertArrayHasKey('smtp_fallbacks', $summary);
        $this->assertArrayHasKey('circuit_breaker', $summary);
        $this->assertArrayHasKey('rate_limits', $summary);
        $this->assertArrayHasKey('period', $summary);
        $this->assertEquals('rolling_24h', $summary['period']);
    }

    public function testGetHealthSummaryWithNoDataReturns100PercentSuccessRate(): void
    {
        $summary = $this->service->getHealthSummary(1);

        $this->assertEquals(0, $summary['total_sent']);
        $this->assertEquals(0, $summary['total_failed']);
        $this->assertEquals(100.0, $summary['success_rate']);
    }

    public function testGetHealthSummaryAggregatesCorrectly(): void
    {
        // Simulate some activity
        $this->service->recordEmailSend('gmail_api', true, 1);
        $this->service->recordEmailSend('gmail_api', true, 1);
        $this->service->recordEmailSend('gmail_api', false, 1);
        $this->service->recordFallbackToSmtp('Token expired', 1);
        $this->service->recordCircuitBreakerOpen(1);
        $this->service->recordRateLimitHit(1);

        $summary = $this->service->getHealthSummary(1);

        $this->assertEquals(2, $summary['total_sent']);
        $this->assertEquals(1, $summary['total_failed']);
        $this->assertGreaterThan(0, $summary['success_rate']);
        $this->assertLessThan(100, $summary['success_rate']);
        $this->assertArrayHasKey('gmail_api', $summary['providers']);
        $this->assertEquals(1, $summary['smtp_fallbacks']['count']);
        $this->assertEquals(1, $summary['circuit_breaker']['opens']);
        $this->assertEquals(1, $summary['rate_limits']['hits']);
    }

    public function testGetHealthSummaryGlobalScope(): void
    {
        $this->service->recordEmailSend('smtp', true);
        $summary = $this->service->getHealthSummary();

        $this->assertEquals(1, $summary['total_sent']);
    }

    public function testGetHealthSummaryProviderOnlyIncludedIfActive(): void
    {
        // Only gmail_api has activity
        $this->service->recordEmailSend('gmail_api', true, 1);

        $summary = $this->service->getHealthSummary(1);

        $this->assertArrayHasKey('gmail_api', $summary['providers']);
        $this->assertArrayNotHasKey('sendgrid', $summary['providers']);
        $this->assertArrayNotHasKey('smtp', $summary['providers']);
    }
}
