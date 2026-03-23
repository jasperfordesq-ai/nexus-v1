<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ShiftWaitlistService;
use App\Models\VolShift;
use Illuminate\Support\Facades\DB;
use Mockery;
use Carbon\Carbon;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class ShiftWaitlistServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(ShiftWaitlistService::class);
        $prop = $ref->getProperty('errors');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    // ── join ──

    public function test_join_fails_when_shift_not_found(): void
    {
        $mock = Mockery::mock('alias:' . VolShift::class . 'Wait');
        // VolShift::find returns null for the real model
        $result = ShiftWaitlistService::join(0, 1);
        $this->assertNull($result);
    }

    // ── leave ──

    public function test_leave_fails_when_not_on_waitlist(): void
    {
        DB::shouldReceive('table->where->where->where->where->first')->andReturnNull();

        $result = ShiftWaitlistService::leave(1, 1);
        $this->assertFalse($result);
        $this->assertEquals('NOT_FOUND', ShiftWaitlistService::getErrors()[0]['code']);
    }

    // ── getWaitlist ──

    public function test_getWaitlist_returns_array(): void
    {
        $result = ShiftWaitlistService::getWaitlist(1);
        $this->assertIsArray($result);
    }

    // ── getUserPosition ──

    public function test_getUserPosition_returns_null_when_not_on_waitlist(): void
    {
        DB::shouldReceive('table->where->where->where->where->first')->andReturnNull();

        $result = ShiftWaitlistService::getUserPosition(1, 1);
        $this->assertNull($result);
    }

    // ── getUserWaitlists ──

    public function test_getUserWaitlists_returns_array(): void
    {
        $result = ShiftWaitlistService::getUserWaitlists(1, $this->testTenantId);
        $this->assertIsArray($result);
    }

    // ── promoteUser ──

    public function test_promoteUser_returns_false_when_not_found(): void
    {
        DB::shouldReceive('table->where->where->where->first')->andReturnNull();

        $result = ShiftWaitlistService::promoteUser(999, $this->testTenantId);
        $this->assertFalse($result);
    }

    // ── getErrors ──

    public function test_getErrors_initially_empty(): void
    {
        $this->assertEquals([], ShiftWaitlistService::getErrors());
    }
}
