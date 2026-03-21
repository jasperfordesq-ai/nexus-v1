<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\PollExportService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Mockery;

class PollExportServiceTest extends TestCase
{
    private PollExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PollExportService();
    }

    public function test_exportToCsv_returns_null_when_poll_not_found(): void
    {
        DB::shouldReceive('table->where->where->first')->andReturnNull();

        $result = $this->service->exportToCsv(999, 1);
        $this->assertNull($result);
    }

    public function test_exportToCsv_returns_null_for_unauthorized_user(): void
    {
        $poll = (object) ['id' => 1, 'user_id' => 5];
        DB::shouldReceive('table->where->where->first')->andReturn($poll);

        $result = $this->service->exportToCsv(1, 99);
        $this->assertNull($result);
    }

    public function test_exportToCsv_returns_csv_string(): void
    {
        $poll = (object) ['id' => 1, 'user_id' => 5];
        DB::shouldReceive('table->where->where->first')->once()->andReturn($poll);

        $options = collect([
            (object) ['id' => 10, 'option_text' => 'Option A'],
            (object) ['id' => 11, 'option_text' => 'Option B'],
        ]);
        DB::shouldReceive('table->where->get')->once()->andReturn($options);
        DB::shouldReceive('table->where->count')->twice()->andReturn(3, 2);

        $result = $this->service->exportToCsv(1, 5);
        $this->assertNotNull($result);
        $this->assertStringContainsString('Option,Votes', $result);
        $this->assertStringContainsString('Option A', $result);
        $this->assertStringContainsString('3', $result);
    }
}
