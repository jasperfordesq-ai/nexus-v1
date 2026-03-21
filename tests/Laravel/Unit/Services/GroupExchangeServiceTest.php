<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupExchangeService;
use Illuminate\Support\Facades\DB;

class GroupExchangeServiceTest extends TestCase
{
    private GroupExchangeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupExchangeService();
    }

    public function test_create_returns_id(): void
    {
        DB::shouldReceive('table->insertGetId')->andReturn(42);

        $result = $this->service->create(5, [
            'title' => 'Group Exchange',
            'total_hours' => 10,
            'split_type' => 'equal',
        ]);
        $this->assertEquals(42, $result);
    }

    public function test_get_returns_null_when_not_found(): void
    {
        DB::shouldReceive('table->where->where->first')->andReturn(null);

        $this->assertNull($this->service->get(999));
    }
}
