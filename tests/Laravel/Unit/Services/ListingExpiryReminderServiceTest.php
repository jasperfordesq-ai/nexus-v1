<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\ListingExpiryReminderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;

class ListingExpiryReminderServiceTest extends TestCase
{
    private ListingExpiryReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ListingExpiryReminderService();
    }

    public function test_sendDueReminders_no_listings_returns_zero(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNotNull')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->sendDueReminders();
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['errors']);
    }

    public function test_sendDueReminders_handles_query_error(): void
    {
        DB::shouldReceive('table')->andThrow(new \Exception('Error'));
        Log::shouldReceive('error')->once();

        $result = $this->service->sendDueReminders();
        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['errors']);
    }

    public function test_cleanupOldRecords_returns_count(): void
    {
        DB::shouldReceive('affectingStatement')->once()->andReturn(5);

        $result = $this->service->cleanupOldRecords();
        $this->assertSame(5, $result);
    }

    public function test_cleanupOldRecords_handles_error(): void
    {
        DB::shouldReceive('affectingStatement')->andThrow(new \Exception('Error'));
        Log::shouldReceive('error')->once();

        $result = $this->service->cleanupOldRecords();
        $this->assertSame(0, $result);
    }
}
