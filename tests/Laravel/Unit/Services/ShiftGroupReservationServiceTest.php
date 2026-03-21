<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ShiftGroupReservationService;
use App\Models\VolShift;
use App\Models\Group;
use Illuminate\Support\Facades\DB;
use Mockery;

class ShiftGroupReservationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    // ── reserve ──

    public function test_reserve_rejects_zero_slots(): void
    {
        $result = ShiftGroupReservationService::reserve(1, 1, 1, 0);
        $this->assertNull($result);
        $errors = ShiftGroupReservationService::getErrors();
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function test_reserve_fails_when_shift_not_found(): void
    {
        $mock = Mockery::mock('alias:' . VolShift::class);
        $mock->shouldReceive('find')->with(999)->andReturnNull();

        $result = ShiftGroupReservationService::reserve(999, 1, 1, 2);
        $this->assertNull($result);
    }

    // ── cancelReservation ──

    public function test_cancelReservation_fails_when_not_found(): void
    {
        DB::shouldReceive('table->where->where->where->first')->andReturnNull();

        $result = ShiftGroupReservationService::cancelReservation(999, 1);
        $this->assertFalse($result);
        $this->assertEquals('NOT_FOUND', ShiftGroupReservationService::getErrors()[0]['code']);
    }

    // ── getErrors ──

    public function test_getErrors_initially_empty(): void
    {
        // Reset static errors
        $ref = new \ReflectionClass(ShiftGroupReservationService::class);
        $prop = $ref->getProperty('errors');
        $prop->setAccessible(true);
        $prop->setValue(null, []);

        $this->assertEquals([], ShiftGroupReservationService::getErrors());
    }

    // ── getUserReservations ──

    public function test_getUserReservations_returns_array(): void
    {
        $result = ShiftGroupReservationService::getUserReservations(1, $this->testTenantId);
        $this->assertIsArray($result);
    }
}
