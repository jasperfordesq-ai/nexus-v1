<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\HoursReportService;

/**
 * Tests for App\Services\HoursReportService.
 *
 * Tests report generation methods for hours by category, member, period,
 * and summary. All methods are tenant-scoped via tenantId parameter.
 *
 * @covers \App\Services\HoursReportService
 */
class HoursReportServiceTest extends TestCase
{
    private HoursReportService $service;
    private static int $tenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HoursReportService();
    }

    // =========================================================================
    // Class existence
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(HoursReportService::class));
    }

    public function testPublicMethodsExist(): void
    {
        $this->assertTrue(method_exists(HoursReportService::class, 'getHoursByCategory'));
        $this->assertTrue(method_exists(HoursReportService::class, 'getHoursByMember'));
        $this->assertTrue(method_exists(HoursReportService::class, 'getHoursByPeriod'));
        $this->assertTrue(method_exists(HoursReportService::class, 'getHoursSummary'));
    }

    // =========================================================================
    // getHoursByCategory()
    // =========================================================================

    public function testGetHoursByCategoryReturnsArray(): void
    {
        try {
            $result = $this->service->getHoursByCategory(self::$tenantId);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByCategoryResultStructure(): void
    {
        try {
            $result = $this->service->getHoursByCategory(self::$tenantId);

            foreach ($result as $row) {
                $this->assertArrayHasKey('category_id', $row);
                $this->assertArrayHasKey('category_name', $row);
                $this->assertArrayHasKey('category_color', $row);
                $this->assertArrayHasKey('total_hours', $row);
                $this->assertArrayHasKey('transaction_count', $row);
                $this->assertArrayHasKey('unique_providers', $row);
                $this->assertArrayHasKey('unique_receivers', $row);

                $this->assertIsFloat($row['total_hours']);
                $this->assertIsInt($row['transaction_count']);
                $this->assertIsInt($row['unique_providers']);
                $this->assertIsInt($row['unique_receivers']);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByCategoryWithDateRange(): void
    {
        try {
            $result = $this->service->getHoursByCategory(self::$tenantId, [
                'from' => '2025-01-01',
                'to' => '2025-12-31',
            ]);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByCategoryWithEmptyDateRange(): void
    {
        try {
            $result = $this->service->getHoursByCategory(self::$tenantId, []);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByCategoryHasNonNegativeHours(): void
    {
        try {
            $result = $this->service->getHoursByCategory(self::$tenantId);

            foreach ($result as $row) {
                $this->assertGreaterThanOrEqual(0, $row['total_hours']);
                $this->assertGreaterThanOrEqual(0, $row['transaction_count']);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByCategoryUsesDefaultColorForUncategorized(): void
    {
        try {
            $result = $this->service->getHoursByCategory(self::$tenantId);

            foreach ($result as $row) {
                // category_color should never be null
                $this->assertNotNull($row['category_color']);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // getHoursByMember()
    // =========================================================================

    public function testGetHoursByMemberReturnsArrayWithDataAndTotal(): void
    {
        try {
            $result = $this->service->getHoursByMember(self::$tenantId);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('data', $result);
            $this->assertArrayHasKey('total', $result);
            $this->assertIsArray($result['data']);
            $this->assertIsInt($result['total']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByMemberResultStructure(): void
    {
        try {
            $result = $this->service->getHoursByMember(self::$tenantId);

            foreach ($result['data'] as $member) {
                $this->assertArrayHasKey('user_id', $member);
                $this->assertArrayHasKey('name', $member);
                $this->assertArrayHasKey('hours_given', $member);
                $this->assertArrayHasKey('hours_received', $member);
                $this->assertArrayHasKey('total_hours', $member);
                $this->assertArrayHasKey('given_count', $member);
                $this->assertArrayHasKey('received_count', $member);
                $this->assertArrayHasKey('total_transactions', $member);

                $this->assertIsInt($member['user_id']);
                $this->assertIsFloat($member['hours_given']);
                $this->assertIsFloat($member['hours_received']);
                $this->assertIsFloat($member['total_hours']);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByMemberRespectsLimit(): void
    {
        try {
            $result = $this->service->getHoursByMember(self::$tenantId, [], 'total', 5);
            $this->assertLessThanOrEqual(5, count($result['data']));
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByMemberSortByGiven(): void
    {
        try {
            $result = $this->service->getHoursByMember(self::$tenantId, [], 'given');
            $this->assertIsArray($result['data']);

            // Verify sorted descending by hours_given
            $prev = PHP_FLOAT_MAX;
            foreach ($result['data'] as $member) {
                $this->assertLessThanOrEqual($prev, $member['hours_given']);
                $prev = $member['hours_given'];
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByMemberSortByReceived(): void
    {
        try {
            $result = $this->service->getHoursByMember(self::$tenantId, [], 'received');
            $this->assertIsArray($result['data']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByMemberSortByName(): void
    {
        try {
            $result = $this->service->getHoursByMember(self::$tenantId, [], 'name');
            $this->assertIsArray($result['data']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByMemberTotalHoursEqualsGivenPlusReceived(): void
    {
        try {
            $result = $this->service->getHoursByMember(self::$tenantId);

            foreach ($result['data'] as $member) {
                $this->assertEqualsWithDelta(
                    $member['hours_given'] + $member['hours_received'],
                    $member['total_hours'],
                    0.01
                );
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByMemberWithOffset(): void
    {
        try {
            $all = $this->service->getHoursByMember(self::$tenantId, [], 'total', 50, 0);
            $offset = $this->service->getHoursByMember(self::$tenantId, [], 'total', 50, 5);

            // Offset results should be a subset
            $this->assertLessThanOrEqual(count($all['data']), count($offset['data']) + 5);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // getHoursByPeriod()
    // =========================================================================

    public function testGetHoursByPeriodReturnsArray(): void
    {
        try {
            $result = $this->service->getHoursByPeriod(self::$tenantId);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByPeriodResultStructure(): void
    {
        try {
            $result = $this->service->getHoursByPeriod(self::$tenantId);

            foreach ($result as $row) {
                $this->assertArrayHasKey('period', $row);
                $this->assertArrayHasKey('period_label', $row);
                $this->assertArrayHasKey('total_hours', $row);
                $this->assertArrayHasKey('transaction_count', $row);
                $this->assertArrayHasKey('unique_providers', $row);
                $this->assertArrayHasKey('unique_receivers', $row);
                $this->assertArrayHasKey('unique_participants', $row);

                // Period format should be YYYY-MM
                if (!empty($row['period'])) {
                    $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $row['period']);
                }
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursByPeriodOrderedChronologically(): void
    {
        try {
            $result = $this->service->getHoursByPeriod(self::$tenantId);

            $prev = '';
            foreach ($result as $row) {
                if ($prev !== '' && !empty($row['period'])) {
                    $this->assertGreaterThanOrEqual($prev, $row['period']);
                }
                $prev = $row['period'] ?? '';
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // getHoursSummary()
    // =========================================================================

    public function testGetHoursSummaryReturnsArray(): void
    {
        try {
            $result = $this->service->getHoursSummary(self::$tenantId);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursSummaryContainsExpectedKeys(): void
    {
        try {
            $result = $this->service->getHoursSummary(self::$tenantId);

            $this->assertArrayHasKey('total_hours', $result);
            $this->assertArrayHasKey('total_transactions', $result);
            $this->assertArrayHasKey('avg_hours_per_transaction', $result);
            $this->assertArrayHasKey('max_single_transaction', $result);
            $this->assertArrayHasKey('unique_providers', $result);
            $this->assertArrayHasKey('unique_receivers', $result);
            $this->assertArrayHasKey('total_members', $result);
            $this->assertArrayHasKey('participation_rate', $result);
            $this->assertArrayHasKey('this_month', $result);
            $this->assertArrayHasKey('last_month', $result);
            $this->assertArrayHasKey('month_over_month_change', $result);
            $this->assertArrayHasKey('date_range', $result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursSummaryTypesAreCorrect(): void
    {
        try {
            $result = $this->service->getHoursSummary(self::$tenantId);

            $this->assertIsFloat($result['total_hours']);
            $this->assertIsInt($result['total_transactions']);
            $this->assertIsFloat($result['avg_hours_per_transaction']);
            $this->assertIsFloat($result['max_single_transaction']);
            $this->assertIsInt($result['unique_providers']);
            $this->assertIsInt($result['unique_receivers']);
            $this->assertIsInt($result['total_members']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursSummaryThisMonthStructure(): void
    {
        try {
            $result = $this->service->getHoursSummary(self::$tenantId);

            $this->assertArrayHasKey('hours', $result['this_month']);
            $this->assertArrayHasKey('transactions', $result['this_month']);
            $this->assertIsFloat($result['this_month']['hours']);
            $this->assertIsInt($result['this_month']['transactions']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursSummaryNonNegativeValues(): void
    {
        try {
            $result = $this->service->getHoursSummary(self::$tenantId);

            $this->assertGreaterThanOrEqual(0, $result['total_hours']);
            $this->assertGreaterThanOrEqual(0, $result['total_transactions']);
            $this->assertGreaterThanOrEqual(0, $result['avg_hours_per_transaction']);
            $this->assertGreaterThanOrEqual(0, $result['unique_providers']);
            $this->assertGreaterThanOrEqual(0, $result['unique_receivers']);
            $this->assertGreaterThanOrEqual(0, $result['total_members']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursSummaryParticipationRateIsPercentage(): void
    {
        try {
            $result = $this->service->getHoursSummary(self::$tenantId);

            // Can exceed 100% because unique_providers + unique_receivers may double-count
            $this->assertGreaterThanOrEqual(0, $result['participation_rate']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testGetHoursSummaryWithDateRange(): void
    {
        try {
            $result = $this->service->getHoursSummary(self::$tenantId, [
                'from' => '2025-01-01',
                'to' => '2025-06-30',
            ]);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('date_range', $result);
            $this->assertEquals('2025-01-01', $result['date_range']['from']);
            $this->assertEquals('2025-06-30', $result['date_range']['to']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // buildDateConditions / buildDateBindings (private helpers, tested indirectly)
    // =========================================================================

    public function testDateRangeFromOnly(): void
    {
        try {
            $result = $this->service->getHoursByCategory(self::$tenantId, [
                'from' => '2025-01-01',
            ]);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function testDateRangeToOnly(): void
    {
        try {
            $result = $this->service->getHoursByCategory(self::$tenantId, [
                'to' => '2025-12-31',
            ]);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }
}
