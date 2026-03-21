<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SearchLogService;
use App\Models\SearchLog;
use Mockery;

class SearchLogServiceTest extends TestCase
{
    private SearchLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SearchLogService();
    }

    // ── log ──

    public function test_log_truncates_long_queries(): void
    {
        SearchLog::shouldReceive('create')->once()->withArgs(function ($args) {
            return strlen($args['query']) <= 500;
        });

        $longQuery = str_repeat('a', 600);
        $this->service->log($longQuery);
    }

    public function test_log_does_not_throw_on_failure(): void
    {
        SearchLog::shouldReceive('create')->andThrow(new \RuntimeException('fail'));

        // Should not throw
        $this->service->log('test query');
        $this->assertTrue(true);
    }

    // ── getTrendingSearches ──

    public function test_getTrendingSearches_returns_array(): void
    {
        $result = $this->service->getTrendingSearches();
        $this->assertIsArray($result);
    }

    // ── getZeroResultSearches ──

    public function test_getZeroResultSearches_returns_array(): void
    {
        $result = $this->service->getZeroResultSearches();
        $this->assertIsArray($result);
    }

    // ── getAnalyticsSummary ──

    public function test_getAnalyticsSummary_returns_expected_keys(): void
    {
        $result = $this->service->getAnalyticsSummary();
        $this->assertArrayHasKey('total_searches', $result);
        $this->assertArrayHasKey('unique_queries', $result);
        $this->assertArrayHasKey('zero_result_rate', $result);
        $this->assertArrayHasKey('avg_results', $result);
        $this->assertArrayHasKey('searches_by_type', $result);
        $this->assertArrayHasKey('daily_volume', $result);
    }

    // ── cleanupOldLogs ──

    public function test_cleanupOldLogs_returns_integer(): void
    {
        $result = $this->service->cleanupOldLogs();
        $this->assertIsInt($result);
    }
}
