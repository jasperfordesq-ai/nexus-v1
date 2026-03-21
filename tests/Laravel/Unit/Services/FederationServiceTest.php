<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationService;
use Illuminate\Support\Facades\DB;

class FederationServiceTest extends TestCase
{
    private FederationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FederationService();
    }

    public function test_getTimebanks_returns_array(): void
    {
        DB::shouldReceive('table->join->where->where->select->get->map->all')->andReturn([]);

        $result = $this->service->getTimebanks(2);
        $this->assertIsArray($result);
    }

    public function test_getMembers_returns_empty_when_not_whitelisted(): void
    {
        DB::shouldReceive('table->where->where->where->exists')->andReturn(false);

        $result = $this->service->getMembers(2, 3);
        $this->assertEquals([], $result);
    }

    public function test_getMembers_returns_members_when_whitelisted(): void
    {
        DB::shouldReceive('table->where->where->where->exists')->andReturn(true);
        DB::shouldReceive('table->where->where->where->select->limit->get->map->all')
            ->andReturn([['id' => 1, 'name' => 'Test']]);

        $result = $this->service->getMembers(2, 3);
        $this->assertCount(1, $result);
    }

    public function test_getListings_returns_empty_when_not_whitelisted(): void
    {
        DB::shouldReceive('table->where->where->where->exists')->andReturn(false);

        $result = $this->service->getListings(2, 3);
        $this->assertEquals([], $result);
    }
}
