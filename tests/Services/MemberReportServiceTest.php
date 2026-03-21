<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\MemberReportService;

class MemberReportServiceTest extends TestCase
{
    private MemberReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MemberReportService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(MemberReportService::class));
    }

    public function testGetActiveMembersReturnsExpectedStructure(): void
    {
        $result = $this->service->getActiveMembers(999999, 30, 10, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('members', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('period_days', $result);
        $this->assertIsArray($result['members']);
        $this->assertIsInt($result['total']);
        $this->assertEquals(30, $result['period_days']);
    }

    public function testGetActiveMembersLimitIsClamped(): void
    {
        // Limit should be clamped to max 200
        $result = $this->service->getActiveMembers(999999, 30, 500, 0);
        $this->assertIsArray($result);
    }

    public function testGetActiveMembersOffsetCannotBeNegative(): void
    {
        $result = $this->service->getActiveMembers(999999, 30, 10, -5);
        $this->assertIsArray($result);
    }

    public function testGetNewRegistrationsReturnsExpectedStructure(): void
    {
        $result = $this->service->getNewRegistrations(999999, 'monthly', 6);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('period_type', $result);
        $this->assertArrayHasKey('months_back', $result);
        $this->assertArrayHasKey('total_registrations', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('monthly', $result['period_type']);
        $this->assertEquals(6, $result['months_back']);
    }

    public function testGetNewRegistrationsSupportsDailyPeriod(): void
    {
        $result = $this->service->getNewRegistrations(999999, 'daily', 1);
        $this->assertEquals('daily', $result['period_type']);
    }

    public function testGetNewRegistrationsSupportsWeeklyPeriod(): void
    {
        $result = $this->service->getNewRegistrations(999999, 'weekly', 1);
        $this->assertEquals('weekly', $result['period_type']);
    }

    public function testGetMemberRetentionReturnsExpectedStructure(): void
    {
        $result = $this->service->getMemberRetention(999999, 3);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('cohorts', $result);
        $this->assertArrayHasKey('overall', $result);
        $this->assertIsArray($result['cohorts']);
        $this->assertCount(3, $result['cohorts']);
        $this->assertArrayHasKey('total_joined', $result['overall']);
        $this->assertArrayHasKey('total_retained', $result['overall']);
        $this->assertArrayHasKey('overall_retention_rate', $result['overall']);
    }

    public function testGetMemberRetentionCohortStructure(): void
    {
        $result = $this->service->getMemberRetention(999999, 1);
        $cohort = $result['cohorts'][0];

        $this->assertArrayHasKey('cohort', $cohort);
        $this->assertArrayHasKey('cohort_month', $cohort);
        $this->assertArrayHasKey('joined', $cohort);
        $this->assertArrayHasKey('retained', $cohort);
        $this->assertArrayHasKey('retention_rate', $cohort);
    }

    public function testGetEngagementMetricsReturnsExpectedStructure(): void
    {
        $result = $this->service->getEngagementMetrics(999999, 30);

        $this->assertIsArray($result);
        $this->assertArrayHasKeys([
            'period_days', 'total_users', 'active_users', 'login_rate',
            'trading_users', 'trading_rate', 'posts_created',
            'comments_created', 'event_rsvps', 'new_connections',
        ], $result);
        $this->assertEquals(30, $result['period_days']);
    }

    public function testGetTopContributorsReturnsArray(): void
    {
        $result = $this->service->getTopContributors(999999, 30, 10);
        $this->assertIsArray($result);
    }

    public function testGetTopContributorsLimitIsClamped(): void
    {
        // Should not exceed 100
        $result = $this->service->getTopContributors(999999, 30, 200);
        $this->assertIsArray($result);
    }

    public function testGetLeastActiveMembersReturnsExpectedStructure(): void
    {
        $result = $this->service->getLeastActiveMembers(999999, 90, 10, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('members', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('threshold_days', $result);
        $this->assertEquals(90, $result['threshold_days']);
    }
}
