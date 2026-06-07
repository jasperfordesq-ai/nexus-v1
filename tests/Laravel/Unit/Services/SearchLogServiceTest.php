<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SearchLogService;
use App\Models\SearchLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;

class SearchLogServiceTest extends TestCase
{
    use DatabaseTransactions;

    private SearchLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SearchLogService();
    }

    // ── log ──

    public function test_log_truncates_long_queries(): void
    {
        // log() persists via SearchLog::create() (HasTenantScope auto-fills tenant_id).
        // Assert the stored query is truncated to 500 chars against the real DB.
        $longQuery = str_repeat('a', 600);
        $this->service->log($longQuery);

        $stored = SearchLog::query()->latest('id')->first();
        $this->assertNotNull($stored, 'log() should have persisted a SearchLog row');
        $this->assertLessThanOrEqual(500, mb_strlen($stored->query));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_log_does_not_throw_on_failure(): void
    {
        // log() must swallow any persistence error. Overload-mock the model so the
        // static SearchLog::create() throws, proving the service's try/catch does
        // not propagate the exception. Runs in a separate process because the
        // overload alias replaces the real class for the whole process.
        $mock = Mockery::mock('overload:' . SearchLog::class);
        $mock->shouldReceive('create')->andThrow(new \RuntimeException('fail'));

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
