<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\MemberActivityService;
use App\Core\TenantContext;

/**
 * MemberActivityService Tests
 */
class MemberActivityServiceTest extends TestCase
{
    private MemberActivityService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new MemberActivityService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(MemberActivityService::class, $this->service);
    }

    public function test_get_dashboard_returns_expected_keys(): void
    {
        $result = $this->service->getDashboard(999999);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('timeline', $result);
        $this->assertArrayHasKey('hours', $result);
        $this->assertArrayHasKey('connections', $result);
        $this->assertArrayHasKey('posts_count', $result);
    }

    public function test_get_dashboard_data_returns_expected_keys(): void
    {
        $result = $this->service->getDashboardData(999999);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('timeline', $result);
        $this->assertArrayHasKey('hours_summary', $result);
        $this->assertArrayHasKey('skills_breakdown', $result);
        $this->assertArrayHasKey('connection_stats', $result);
        $this->assertArrayHasKey('engagement', $result);
        $this->assertArrayHasKey('monthly_hours', $result);
    }

    public function test_get_timeline_returns_array(): void
    {
        $result = $this->service->getTimeline(999999);
        $this->assertIsArray($result);
    }

    public function test_get_timeline_respects_limit(): void
    {
        $result = $this->service->getTimeline(999999, 5);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));
    }

    public function test_get_recent_timeline_returns_array(): void
    {
        $result = $this->service->getRecentTimeline(999999);
        $this->assertIsArray($result);
    }

    public function test_get_hours_returns_expected_keys(): void
    {
        $result = $this->service->getHours(999999);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('given', $result);
        $this->assertArrayHasKey('received', $result);
        $this->assertArrayHasKey('balance', $result);
        $this->assertIsFloat($result['given']);
        $this->assertIsFloat($result['received']);
        $this->assertIsFloat($result['balance']);
    }

    public function test_get_hours_balance_calculation(): void
    {
        $result = $this->service->getHours(999999);
        $expectedBalance = $result['received'] - $result['given'];
        $this->assertSame($expectedBalance, $result['balance']);
    }

    public function test_get_hours_summary_returns_expected_keys(): void
    {
        $result = $this->service->getHoursSummary(999999);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('hours_given', $result);
        $this->assertArrayHasKey('hours_received', $result);
        $this->assertArrayHasKey('transactions_given', $result);
        $this->assertArrayHasKey('transactions_received', $result);
        $this->assertArrayHasKey('net_balance', $result);
    }

    public function test_get_skills_breakdown_returns_expected_keys(): void
    {
        $result = $this->service->getSkillsBreakdown(999999);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('skills', $result);
        $this->assertArrayHasKey('offering_count', $result);
        $this->assertArrayHasKey('requesting_count', $result);
        $this->assertIsArray($result['skills']);
    }

    public function test_get_connection_stats_returns_expected_keys(): void
    {
        $result = $this->service->getConnectionStats(999999);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_connections', $result);
        $this->assertArrayHasKey('pending_requests', $result);
        $this->assertArrayHasKey('groups_joined', $result);
        $this->assertIsInt($result['total_connections']);
        $this->assertIsInt($result['pending_requests']);
        $this->assertIsInt($result['groups_joined']);
    }

    public function test_get_engagement_metrics_returns_expected_keys(): void
    {
        $result = $this->service->getEngagementMetrics(999999);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('posts_count', $result);
        $this->assertArrayHasKey('comments_count', $result);
        $this->assertArrayHasKey('likes_given', $result);
        $this->assertArrayHasKey('likes_received', $result);
        $this->assertArrayHasKey('period', $result);
        $this->assertSame('last_30_days', $result['period']);
    }

    public function test_get_monthly_hours_returns_12_months(): void
    {
        $result = $this->service->getMonthlyHours(999999);
        $this->assertIsArray($result);
        $this->assertCount(12, $result);
    }

    public function test_get_monthly_hours_item_structure(): void
    {
        $result = $this->service->getMonthlyHours(999999);
        foreach ($result as $month) {
            $this->assertArrayHasKey('month', $month);
            $this->assertArrayHasKey('label', $month);
            $this->assertArrayHasKey('given', $month);
            $this->assertArrayHasKey('received', $month);
            $this->assertIsString($month['month']);
            $this->assertIsString($month['label']);
            $this->assertIsFloat($month['given']);
            $this->assertIsFloat($month['received']);
        }
    }

    public function test_get_monthly_hours_months_are_chronological(): void
    {
        $result = $this->service->getMonthlyHours(999999);
        $months = array_column($result, 'month');
        $sorted = $months;
        sort($sorted);
        $this->assertSame($sorted, $months);
    }

    public function test_nonexistent_user_returns_zero_values(): void
    {
        $hours = $this->service->getHours(999999);
        $this->assertSame(0.0, $hours['given']);
        $this->assertSame(0.0, $hours['received']);
        $this->assertSame(0.0, $hours['balance']);

        $connections = $this->service->getConnectionStats(999999);
        $this->assertSame(0, $connections['total_connections']);
        $this->assertSame(0, $connections['pending_requests']);
    }
}
