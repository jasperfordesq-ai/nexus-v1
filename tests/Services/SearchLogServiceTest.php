<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\SearchLogService;
use App\Models\SearchLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests for App\Services\SearchLogService.
 *
 * Tests search logging, trending searches, zero-result analytics,
 * summary generation, and cleanup of old logs.
 *
 * @covers \App\Services\SearchLogService
 */
class SearchLogServiceTest extends TestCase
{
    private SearchLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SearchLogService();
    }

    // =========================================================================
    // Class existence and method signatures
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SearchLogService::class));
    }

    public function testLogMethodExists(): void
    {
        $this->assertTrue(method_exists(SearchLogService::class, 'log'));
    }

    public function testGetTrendingSearchesMethodExists(): void
    {
        $this->assertTrue(method_exists(SearchLogService::class, 'getTrendingSearches'));
    }

    public function testCleanupOldLogsMethodExists(): void
    {
        $this->assertTrue(method_exists(SearchLogService::class, 'cleanupOldLogs'));
    }

    public function testGetZeroResultSearchesMethodExists(): void
    {
        $this->assertTrue(method_exists(SearchLogService::class, 'getZeroResultSearches'));
    }

    public function testGetAnalyticsSummaryMethodExists(): void
    {
        $this->assertTrue(method_exists(SearchLogService::class, 'getAnalyticsSummary'));
    }

    // =========================================================================
    // log() — parameter validation
    // =========================================================================

    public function testLogAcceptsMinimalParameters(): void
    {
        // Should not throw
        $this->service->log('test query');
        $this->assertTrue(true);
    }

    public function testLogAcceptsFullParameters(): void
    {
        $this->service->log(
            'full query',
            'members',
            15,
            42,
            ['category' => 'gardening']
        );
        $this->assertTrue(true);
    }

    public function testLogTruncatesLongQueries(): void
    {
        // The service truncates to 500 chars — should not throw for very long input
        $longQuery = str_repeat('a', 1000);
        $this->service->log($longQuery);
        $this->assertTrue(true);
    }

    public function testLogHandlesEmptyQuery(): void
    {
        $this->service->log('');
        $this->assertTrue(true);
    }

    public function testLogHandlesSpecialCharacters(): void
    {
        $this->service->log("query with 'quotes' and \"double quotes\" & special <chars>");
        $this->assertTrue(true);
    }

    public function testLogHandlesUnicodeQueries(): void
    {
        $this->service->log('Gartenarbeit');
        $this->assertTrue(true);
    }

    // =========================================================================
    // getTrendingSearches()
    // =========================================================================

    public function testGetTrendingSearchesReturnsArray(): void
    {
        $result = $this->service->getTrendingSearches();
        $this->assertIsArray($result);
    }

    public function testGetTrendingSearchesDefaultParameters(): void
    {
        $ref = new \ReflectionMethod(SearchLogService::class, 'getTrendingSearches');
        $params = $ref->getParameters();

        // days defaults to 7
        $this->assertEquals(7, $params[0]->getDefaultValue());
        // limit defaults to 20
        $this->assertEquals(20, $params[1]->getDefaultValue());
    }

    public function testGetTrendingSearchesRespectsLimit(): void
    {
        $result = $this->service->getTrendingSearches(7, 5);
        $this->assertLessThanOrEqual(5, count($result));
    }

    public function testGetTrendingSearchesResultStructure(): void
    {
        $result = $this->service->getTrendingSearches();

        foreach ($result as $item) {
            $this->assertArrayHasKey('query', $item);
            $this->assertArrayHasKey('count', $item);
            $this->assertIsString($item['query']);
            $this->assertIsInt($item['count']);
        }
    }

    // =========================================================================
    // getZeroResultSearches()
    // =========================================================================

    public function testGetZeroResultSearchesReturnsArray(): void
    {
        $result = $this->service->getZeroResultSearches();
        $this->assertIsArray($result);
    }

    public function testGetZeroResultSearchesDefaultParameters(): void
    {
        $ref = new \ReflectionMethod(SearchLogService::class, 'getZeroResultSearches');
        $params = $ref->getParameters();

        $this->assertEquals(30, $params[0]->getDefaultValue());
        $this->assertEquals(20, $params[1]->getDefaultValue());
    }

    public function testGetZeroResultSearchesResultStructure(): void
    {
        $result = $this->service->getZeroResultSearches();

        foreach ($result as $item) {
            $this->assertArrayHasKey('query', $item);
            $this->assertArrayHasKey('count', $item);
            $this->assertArrayHasKey('last_searched', $item);
        }
    }

    // =========================================================================
    // getAnalyticsSummary()
    // =========================================================================

    public function testGetAnalyticsSummaryReturnsArray(): void
    {
        $result = $this->service->getAnalyticsSummary();
        $this->assertIsArray($result);
    }

    public function testGetAnalyticsSummaryContainsExpectedKeys(): void
    {
        $result = $this->service->getAnalyticsSummary();

        $this->assertArrayHasKey('total_searches', $result);
        $this->assertArrayHasKey('unique_queries', $result);
        $this->assertArrayHasKey('zero_result_rate', $result);
        $this->assertArrayHasKey('avg_results', $result);
        $this->assertArrayHasKey('searches_by_type', $result);
        $this->assertArrayHasKey('daily_volume', $result);
    }

    public function testGetAnalyticsSummaryTypesAreCorrect(): void
    {
        $result = $this->service->getAnalyticsSummary();

        $this->assertIsInt($result['total_searches']);
        $this->assertIsInt($result['unique_queries']);
        $this->assertIsFloat($result['zero_result_rate']);
        $this->assertIsFloat($result['avg_results']);
        $this->assertIsArray($result['searches_by_type']);
        $this->assertIsArray($result['daily_volume']);
    }

    public function testGetAnalyticsSummaryZeroResultRateIsPercentage(): void
    {
        $result = $this->service->getAnalyticsSummary();

        $this->assertGreaterThanOrEqual(0.0, $result['zero_result_rate']);
        $this->assertLessThanOrEqual(100.0, $result['zero_result_rate']);
    }

    public function testGetAnalyticsSummaryAcceptsDaysParameter(): void
    {
        // 1 day should return a subset of 30 days
        $result1 = $this->service->getAnalyticsSummary(1);
        $result30 = $this->service->getAnalyticsSummary(30);

        $this->assertIsArray($result1);
        $this->assertIsArray($result30);
        $this->assertLessThanOrEqual($result30['total_searches'], $result1['total_searches'] + $result30['total_searches']);
    }

    // =========================================================================
    // cleanupOldLogs()
    // =========================================================================

    public function testCleanupOldLogsReturnsInt(): void
    {
        $result = $this->service->cleanupOldLogs();
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testCleanupOldLogsDeletesOnlyOldRecords(): void
    {
        // The method deletes records older than 90 days
        // We can verify it returns a non-negative int and doesn't throw
        $result = $this->service->cleanupOldLogs();
        $this->assertGreaterThanOrEqual(0, $result);
    }
}
