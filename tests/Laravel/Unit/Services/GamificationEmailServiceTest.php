<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GamificationEmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GamificationEmailServiceTest extends TestCase
{
    private GamificationEmailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GamificationEmailService();
    }

    public function test_sendWeeklyDigests_returns_expected_structure(): void
    {
        DB::shouldReceive('table->where->select->get')->andReturn(collect([]));

        $result = $this->service->sendWeeklyDigests();
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(0, $result['sent']);
    }

    public function test_sendWeeklyDigests_handles_tenant_query_failure(): void
    {
        DB::shouldReceive('table->where->select->get')->andThrow(new \Exception('DB error'));
        Log::shouldReceive('error')->once();

        $result = $this->service->sendWeeklyDigests();
        $this->assertEquals(1, $result['errors']);
    }
}
