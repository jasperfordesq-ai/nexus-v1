<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\SmartMatchingAnalyticsService;
use App\Core\TenantContext;

/**
 * SmartMatchingAnalyticsService Tests
 *
 * Tests analytics and reporting for the smart matching engine,
 * including dashboard summaries, score/distance distributions,
 * and conversion funnels.
 */
class SmartMatchingAnalyticsServiceTest extends TestCase
{
    private static int $testTenantId = 1;
    private static bool $dbAvailable = false;

    private SmartMatchingAnalyticsService $service;

    public static function setUpBeforeClass(): void
    {
        try {
            TenantContext::setById(self::$testTenantId);
            self::$dbAvailable = true;
        } catch (\Throwable $e) {
            // DB not available
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available for integration test');
        }

        TenantContext::setById(self::$testTenantId);
        $this->service = new SmartMatchingAnalyticsService();
    }

    // ==========================================
    // getOverallStats Tests
    // ==========================================

    public function testGetOverallStatsReturnsExpectedStructure(): void
    {
        $stats = $this->service->getOverallStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_cached_matches', $stats);
        $this->assertArrayHasKey('total_matches_month', $stats);
        $this->assertArrayHasKey('average_score', $stats);
        $this->assertArrayHasKey('average_distance_km', $stats);
        $this->assertArrayHasKey('match_type_breakdown', $stats);
        $this->assertArrayHasKey('active_users_with_matches', $stats);
        $this->assertArrayHasKey('hot_matches', $stats);
    }

    public function testGetOverallStatsValuesAreNumeric(): void
    {
        $stats = $this->service->getOverallStats();

        $this->assertIsInt($stats['total_cached_matches']);
        $this->assertIsInt($stats['total_matches_month']);
        $this->assertIsFloat($stats['average_score']);
        $this->assertIsFloat($stats['average_distance_km']);
        $this->assertIsArray($stats['match_type_breakdown']);
        $this->assertIsInt($stats['active_users_with_matches']);
        $this->assertIsInt($stats['hot_matches']);
    }

    public function testGetOverallStatsNonNegativeValues(): void
    {
        $stats = $this->service->getOverallStats();

        $this->assertGreaterThanOrEqual(0, $stats['total_cached_matches']);
        $this->assertGreaterThanOrEqual(0, $stats['total_matches_month']);
        $this->assertGreaterThanOrEqual(0, $stats['average_score']);
        $this->assertGreaterThanOrEqual(0, $stats['average_distance_km']);
        $this->assertGreaterThanOrEqual(0, $stats['active_users_with_matches']);
        $this->assertGreaterThanOrEqual(0, $stats['hot_matches']);
    }

    public function testGetOverallStatsHotMatchesNotExceedTotal(): void
    {
        $stats = $this->service->getOverallStats();

        $this->assertLessThanOrEqual(
            $stats['total_cached_matches'],
            $stats['hot_matches'],
            'Hot matches should not exceed total cached matches'
        );
    }

    public function testGetOverallStatsMonthNotExceedTotal(): void
    {
        $stats = $this->service->getOverallStats();

        $this->assertLessThanOrEqual(
            $stats['total_cached_matches'],
            $stats['total_matches_month'],
            'Monthly matches should not exceed total cached matches'
        );
    }

    // ==========================================
    // getScoreDistribution Tests
    // ==========================================

    public function testGetScoreDistributionReturnsArray(): void
    {
        $distribution = $this->service->getScoreDistribution();

        $this->assertIsArray($distribution);
    }

    public function testGetScoreDistributionHasFiveBuckets(): void
    {
        $distribution = $this->service->getScoreDistribution();

        $this->assertCount(5, $distribution);
    }

    public function testGetScoreDistributionBucketStructure(): void
    {
        $distribution = $this->service->getScoreDistribution();

        foreach ($distribution as $bucket) {
            $this->assertArrayHasKey('range', $bucket);
            $this->assertArrayHasKey('count', $bucket);
            $this->assertIsString($bucket['range']);
            $this->assertIsInt($bucket['count']);
            $this->assertGreaterThanOrEqual(0, $bucket['count']);
        }
    }

    public function testGetScoreDistributionExpectedRangeLabels(): void
    {
        $distribution = $this->service->getScoreDistribution();

        $expectedLabels = ['0-20', '21-40', '41-60', '61-80', '81-100'];
        $actualLabels = array_map(fn($b) => $b['range'], $distribution);

        $this->assertEquals($expectedLabels, $actualLabels);
    }

    public function testGetScoreDistributionSumsToTotal(): void
    {
        $distribution = $this->service->getScoreDistribution();
        $stats = $this->service->getOverallStats();

        $distributionTotal = array_sum(array_map(fn($b) => $b['count'], $distribution));

        $this->assertEquals(
            $stats['total_cached_matches'],
            $distributionTotal,
            'Sum of score distribution buckets should equal total cached matches'
        );
    }

    // ==========================================
    // getDistanceDistribution Tests
    // ==========================================

    public function testGetDistanceDistributionReturnsArray(): void
    {
        $distribution = $this->service->getDistanceDistribution();

        $this->assertIsArray($distribution);
    }

    public function testGetDistanceDistributionBucketStructure(): void
    {
        $distribution = $this->service->getDistanceDistribution();

        foreach ($distribution as $bucket) {
            $this->assertArrayHasKey('range', $bucket);
            $this->assertArrayHasKey('count', $bucket);
            $this->assertIsString($bucket['range']);
            $this->assertIsInt($bucket['count']);
            $this->assertGreaterThanOrEqual(0, $bucket['count']);
        }
    }

    public function testGetDistanceDistributionHasAtLeastFiveBuckets(): void
    {
        $distribution = $this->service->getDistanceDistribution();

        // 5 distance buckets + optional "Unknown" bucket
        $this->assertGreaterThanOrEqual(5, count($distribution));
        $this->assertLessThanOrEqual(6, count($distribution));
    }

    public function testGetDistanceDistributionExpectedBucketLabels(): void
    {
        $distribution = $this->service->getDistanceDistribution();

        $labels = array_map(fn($b) => $b['range'], $distribution);

        $this->assertContains('0-5km', $labels);
        $this->assertContains('5-15km', $labels);
        $this->assertContains('15-30km', $labels);
        $this->assertContains('30-50km', $labels);
        $this->assertContains('50+km', $labels);
    }

    // ==========================================
    // getConversionFunnel Tests
    // ==========================================

    public function testGetConversionFunnelReturnsExpectedStructure(): void
    {
        $funnel = $this->service->getConversionFunnel();

        $this->assertIsArray($funnel);
        $this->assertArrayHasKey('total_generated', $funnel);
        $this->assertArrayHasKey('viewed', $funnel);
        $this->assertArrayHasKey('contacted', $funnel);
        $this->assertArrayHasKey('saved', $funnel);
        $this->assertArrayHasKey('dismissed', $funnel);
        $this->assertArrayHasKey('conversion_rate', $funnel);
    }

    public function testGetConversionFunnelValuesAreNumeric(): void
    {
        $funnel = $this->service->getConversionFunnel();

        $this->assertIsInt($funnel['total_generated']);
        $this->assertIsInt($funnel['viewed']);
        $this->assertIsInt($funnel['contacted']);
        $this->assertIsInt($funnel['saved']);
        $this->assertIsInt($funnel['dismissed']);
        $this->assertIsNumeric($funnel['conversion_rate']);
    }

    public function testGetConversionFunnelNonNegativeValues(): void
    {
        $funnel = $this->service->getConversionFunnel();

        $this->assertGreaterThanOrEqual(0, $funnel['total_generated']);
        $this->assertGreaterThanOrEqual(0, $funnel['viewed']);
        $this->assertGreaterThanOrEqual(0, $funnel['contacted']);
        $this->assertGreaterThanOrEqual(0, $funnel['saved']);
        $this->assertGreaterThanOrEqual(0, $funnel['dismissed']);
        $this->assertGreaterThanOrEqual(0, $funnel['conversion_rate']);
    }

    public function testGetConversionFunnelConversionRateBounded(): void
    {
        $funnel = $this->service->getConversionFunnel();

        $this->assertGreaterThanOrEqual(0, $funnel['conversion_rate']);
        $this->assertLessThanOrEqual(100, $funnel['conversion_rate']);
    }

    public function testGetConversionFunnelContactedNotExceedTotal(): void
    {
        $funnel = $this->service->getConversionFunnel();

        $this->assertLessThanOrEqual(
            $funnel['total_generated'],
            $funnel['contacted'],
            'Contacted count should not exceed total generated'
        );
    }

    // ==========================================
    // getDashboardSummary Tests
    // ==========================================

    public function testGetDashboardSummaryReturnsExpectedStructure(): void
    {
        $summary = $this->service->getDashboardSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('overview', $summary);
        $this->assertArrayHasKey('conversion', $summary);
        $this->assertArrayHasKey('period', $summary);
    }

    public function testGetDashboardSummaryPeriodIsLast30Days(): void
    {
        $summary = $this->service->getDashboardSummary();

        $this->assertEquals('last_30_days', $summary['period']);
    }

    public function testGetDashboardSummaryOverviewMatchesGetOverallStats(): void
    {
        $summary = $this->service->getDashboardSummary();
        $stats = $this->service->getOverallStats();

        // The overview in dashboard should contain the same keys
        $this->assertArrayHasKey('total_cached_matches', $summary['overview']);
        $this->assertArrayHasKey('average_score', $summary['overview']);
    }

    public function testGetDashboardSummaryConversionMatchesGetConversionFunnel(): void
    {
        $summary = $this->service->getDashboardSummary();

        $this->assertArrayHasKey('total_generated', $summary['conversion']);
        $this->assertArrayHasKey('conversion_rate', $summary['conversion']);
    }

    // ==========================================
    // Consistency Checks
    // ==========================================

    public function testOverallStatsConsistentAcrossCalls(): void
    {
        $stats1 = $this->service->getOverallStats();
        $stats2 = $this->service->getOverallStats();

        // Within the same test, these should be identical (no concurrent writes)
        $this->assertEquals($stats1['total_cached_matches'], $stats2['total_cached_matches']);
        $this->assertEquals($stats1['hot_matches'], $stats2['hot_matches']);
    }
}
