<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\ListingAnalyticsService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class ListingAnalyticsServiceTest extends TestCase
{
    private ListingAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ListingAnalyticsService();
    }

    public function test_recordView_deduplicates_by_user(): void
    {
        DB::shouldReceive('selectOne')->once()->andReturn((object) ['id' => 1]); // Recent exists
        $result = $this->service->recordView(1, 10);
        $this->assertFalse($result);
    }

    public function test_recordView_new_view_increments_counter(): void
    {
        DB::shouldReceive('selectOne')->once()->andReturn(null); // No recent
        DB::shouldReceive('insert')->once()->andReturn(true);
        DB::shouldReceive('update')->once()->andReturn(1);

        $result = $this->service->recordView(1, 10);
        $this->assertTrue($result);
    }

    public function test_recordView_anonymous_with_ip(): void
    {
        DB::shouldReceive('selectOne')->once()->andReturn(null);
        DB::shouldReceive('insert')->once()->andReturn(true);
        DB::shouldReceive('update')->once()->andReturn(1);

        $result = $this->service->recordView(1, null, '192.168.1.1');
        $this->assertTrue($result);
    }

    public function test_recordView_anonymous_no_ip_no_dedup(): void
    {
        DB::shouldReceive('insert')->once()->andReturn(true);
        DB::shouldReceive('update')->once()->andReturn(1);

        $result = $this->service->recordView(1, null, null);
        $this->assertTrue($result);
    }

    public function test_recordContact_valid_type(): void
    {
        DB::shouldReceive('insert')->once()->andReturn(true);
        DB::shouldReceive('update')->once()->andReturn(1);

        $result = $this->service->recordContact(1, 10, 'email');
        $this->assertTrue($result);
    }

    public function test_recordContact_invalid_type_defaults_to_message(): void
    {
        DB::shouldReceive('insert')->once()->andReturn(true);
        DB::shouldReceive('update')->once()->andReturn(1);

        $result = $this->service->recordContact(1, 10, 'invalid_type');
        $this->assertTrue($result);
    }

    public function test_getAnalytics_listing_not_found(): void
    {
        DB::shouldReceive('selectOne')->once()->andReturn(null);

        $result = $this->service->getAnalytics(999);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_updateSaveCount_increment(): void
    {
        DB::shouldReceive('update')->once()->andReturn(1);

        $this->service->updateSaveCount(1, true);
        $this->assertTrue(true); // void method
    }

    public function test_cleanupOldRecords_returns_count(): void
    {
        DB::shouldReceive('delete')->once()->andReturn(10);

        $result = $this->service->cleanupOldRecords();
        $this->assertSame(10, $result);
    }

    public function test_cleanupOldRecords_handles_error(): void
    {
        DB::shouldReceive('delete')->andThrow(new \Exception('Error'));

        $result = $this->service->cleanupOldRecords();
        $this->assertSame(0, $result);
    }
}
