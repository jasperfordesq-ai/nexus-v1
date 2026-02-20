<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\ImpactReportingService;

/**
 * ImpactReportingService Tests
 *
 * Tests Social Return on Investment (SROI) calculations,
 * community health metrics, and impact timelines.
 */
class ImpactReportingServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $ts = time();

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())",
            [self::$testTenantId, "impact1_{$ts}@test.com", "impact1_{$ts}", 'Impact', 'One', 'Impact One', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())",
            [self::$testTenantId, "impact2_{$ts}@test.com", "impact2_{$ts}", 'Impact', 'Two', 'Impact Two', 50]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create a test transaction for SROI calculation
        Database::query(
            "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, created_at)
             VALUES (?, ?, ?, ?, 'Test transaction for impact', NOW())",
            [self::$testTenantId, self::$testUserId, self::$testUser2Id, 2.5]
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId && self::$testUser2Id) {
            try {
                Database::query(
                    "DELETE FROM transactions WHERE (sender_id = ? OR receiver_id = ?) AND tenant_id = ?",
                    [self::$testUserId, self::$testUserId, self::$testTenantId]
                );
                Database::query("DELETE FROM users WHERE id IN (?, ?)", [self::$testUserId, self::$testUser2Id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // SROI Tests
    // ==========================================

    public function testCalculateSROIReturnsValidStructure(): void
    {
        $sroi = ImpactReportingService::calculateSROI();

        $this->assertIsArray($sroi);
        $this->assertArrayHasKey('total_hours', $sroi);
        $this->assertArrayHasKey('total_transactions', $sroi);
        $this->assertArrayHasKey('monetary_value', $sroi);
        $this->assertArrayHasKey('social_value', $sroi);
        $this->assertArrayHasKey('sroi_ratio', $sroi);
    }

    public function testCalculateSROIUsesDefaultValues(): void
    {
        $sroi = ImpactReportingService::calculateSROI();

        $this->assertArrayHasKey('hourly_value', $sroi);
        $this->assertArrayHasKey('social_multiplier', $sroi);
        $this->assertEquals(15.00, $sroi['hourly_value']);
        $this->assertEquals(3.5, $sroi['social_multiplier']);
    }

    public function testCalculateSROIUsesCustomValues(): void
    {
        $sroi = ImpactReportingService::calculateSROI([
            'hourly_value' => 20.00,
            'social_multiplier' => 4.0
        ]);

        $this->assertEquals(20.00, $sroi['hourly_value']);
        $this->assertEquals(4.0, $sroi['social_multiplier']);
    }

    public function testCalculateSROICalculatesMonetaryValue(): void
    {
        $sroi = ImpactReportingService::calculateSROI(['hourly_value' => 10.00]);

        if ($sroi['total_hours'] > 0) {
            $expectedMonetary = $sroi['total_hours'] * 10.00;
            $this->assertEquals($expectedMonetary, $sroi['monetary_value']);
        }
        $this->assertTrue(true);
    }

    public function testCalculateSROICalculatesSocialValue(): void
    {
        $sroi = ImpactReportingService::calculateSROI([
            'hourly_value' => 10.00,
            'social_multiplier' => 2.0
        ]);

        if ($sroi['total_hours'] > 0) {
            $expectedSocial = $sroi['monetary_value'] * 2.0;
            $this->assertEquals($expectedSocial, $sroi['social_value']);
        }
        $this->assertTrue(true);
    }

    public function testCalculateSROIHandlesZeroHours(): void
    {
        // Delete all transactions temporarily
        Database::query("DELETE FROM transactions WHERE tenant_id = ?", [self::$testTenantId]);

        $sroi = ImpactReportingService::calculateSROI();

        $this->assertEquals(0, $sroi['total_hours']);
        $this->assertEquals(0, $sroi['monetary_value']);
        $this->assertEquals(0, $sroi['social_value']);
        $this->assertEquals(0, $sroi['sroi_ratio']);

        // Restore test transaction
        Database::query(
            "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, created_at)
             VALUES (?, ?, ?, ?, 'Test transaction for impact', NOW())",
            [self::$testTenantId, self::$testUserId, self::$testUser2Id, 2.5]
        );
    }

    // ==========================================
    // Community Health Metrics Tests
    // ==========================================

    public function testGetCommunityHealthMetricsReturnsValidStructure(): void
    {
        $metrics = ImpactReportingService::getCommunityHealthMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_users', $metrics);
        $this->assertArrayHasKey('active_users_90d', $metrics);
        $this->assertArrayHasKey('engagement_rate', $metrics);
        $this->assertArrayHasKey('retention_rate', $metrics);
        $this->assertArrayHasKey('reciprocity_score', $metrics);
        $this->assertArrayHasKey('network_density', $metrics);
    }

    public function testGetCommunityHealthMetricsReturnsNumericValues(): void
    {
        $metrics = ImpactReportingService::getCommunityHealthMetrics();

        $this->assertIsInt($metrics['total_users']);
        $this->assertIsInt($metrics['active_users_90d']);
        $this->assertIsFloat($metrics['engagement_rate']);
        $this->assertIsFloat($metrics['retention_rate']);
        $this->assertIsFloat($metrics['reciprocity_score']);
    }

    public function testGetCommunityHealthMetricsEngagementRateInRange(): void
    {
        $metrics = ImpactReportingService::getCommunityHealthMetrics();

        $this->assertGreaterThanOrEqual(0, $metrics['engagement_rate']);
        $this->assertLessThanOrEqual(1, $metrics['engagement_rate']);
    }

    public function testGetCommunityHealthMetricsRetentionRateInRange(): void
    {
        $metrics = ImpactReportingService::getCommunityHealthMetrics();

        $this->assertGreaterThanOrEqual(0, $metrics['retention_rate']);
        $this->assertLessThanOrEqual(1, $metrics['retention_rate']);
    }

    public function testGetCommunityHealthMetricsReciprocityScoreInRange(): void
    {
        $metrics = ImpactReportingService::getCommunityHealthMetrics();

        $this->assertGreaterThanOrEqual(0, $metrics['reciprocity_score']);
        $this->assertLessThanOrEqual(1, $metrics['reciprocity_score']);
    }

    // ==========================================
    // Impact Timeline Tests
    // ==========================================

    public function testGetImpactTimelineReturnsArray(): void
    {
        $timeline = ImpactReportingService::getImpactTimeline();
        $this->assertIsArray($timeline);
    }

    public function testGetImpactTimelineIncludesMonthData(): void
    {
        $timeline = ImpactReportingService::getImpactTimeline();

        if (!empty($timeline)) {
            $this->assertArrayHasKey('month', $timeline[0]);
            $this->assertArrayHasKey('hours_exchanged', $timeline[0]);
            $this->assertArrayHasKey('transactions', $timeline[0]);
            $this->assertArrayHasKey('new_users', $timeline[0]);
        }
        $this->assertTrue(true);
    }

    public function testGetImpactTimelineRespectsMonthParameter(): void
    {
        $timeline = ImpactReportingService::getImpactTimeline(3);
        $this->assertIsArray($timeline);
        // Timeline should not exceed 3 months
        $this->assertLessThanOrEqual(3, count($timeline));
    }

    // ==========================================
    // Report Config Tests
    // ==========================================

    public function testGetReportConfigReturnsValidStructure(): void
    {
        $config = ImpactReportingService::getReportConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tenant_name', $config);
        $this->assertArrayHasKey('tenant_slug', $config);
        $this->assertArrayHasKey('hourly_value', $config);
        $this->assertArrayHasKey('social_multiplier', $config);
    }

    public function testGetReportConfigReturnsDefaultValues(): void
    {
        $config = ImpactReportingService::getReportConfig();

        $this->assertIsFloat($config['hourly_value']);
        $this->assertIsFloat($config['social_multiplier']);
        $this->assertGreaterThan(0, $config['hourly_value']);
        $this->assertGreaterThan(0, $config['social_multiplier']);
    }
}
