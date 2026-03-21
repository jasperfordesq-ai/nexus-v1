<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FeedSidebarService;
use Illuminate\Support\Facades\DB;

class FeedSidebarServiceTest extends TestCase
{
    private FeedSidebarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeedSidebarService();
    }

    public function test_communityStats_returns_expected_keys(): void
    {
        DB::shouldReceive('table->where->where->count')->andReturn(100);
        DB::shouldReceive('table->where->where->sum')->andReturn(500.5);
        DB::shouldReceive('table->where->where->count')->andReturn(50);
        DB::shouldReceive('table->where->where->count')->andReturn(10);
        DB::shouldReceive('table->where->whereIn->count')->andReturn(5);

        $this->markTestIncomplete('Complex chained DB mock — requires integration test');
    }
}
