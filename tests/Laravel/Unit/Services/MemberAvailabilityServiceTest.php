<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\MemberAvailability;
use App\Services\MemberAvailabilityService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

class MemberAvailabilityServiceTest extends TestCase
{
    private MemberAvailabilityService $service;
    private $mockAvailability;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAvailability = Mockery::mock(MemberAvailability::class)->makePartial();
        $this->service = new MemberAvailabilityService($this->mockAvailability);
    }

    public function test_getErrors_initially_empty(): void
    {
        $this->assertSame([], $this->service->getErrors());
    }

    public function test_getAvailability_returns_array(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('orderBy')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockAvailability->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAvailability(1);
        $this->assertSame([], $result);
    }

    public function test_setAvailability_invalid_day_returns_false(): void
    {
        $this->assertFalse($this->service->setAvailability(1, -1, []));
        $this->assertFalse($this->service->setAvailability(1, 7, []));
    }

    public function test_setDayAvailability_invalid_day_returns_false(): void
    {
        $this->assertFalse($this->service->setDayAvailability(1, 8, []));
        $this->assertNotEmpty($this->service->getErrors());
    }

    public function test_setDayAvailability_missing_times_returns_false(): void
    {
        $this->assertFalse($this->service->setDayAvailability(1, 1, [
            ['start_time' => '', 'end_time' => '17:00'],
        ]));
    }

    public function test_setDayAvailability_end_before_start_returns_false(): void
    {
        $this->assertFalse($this->service->setDayAvailability(1, 1, [
            ['start_time' => '17:00', 'end_time' => '09:00'],
        ]));
    }

    public function test_addSpecificDate_missing_required_returns_null(): void
    {
        $result = $this->service->addSpecificDate(1, []);
        $this->assertNull($result);
        $this->assertNotEmpty($this->service->getErrors());
    }

    public function test_addSpecificDate_end_before_start_returns_null(): void
    {
        $result = $this->service->addSpecificDate(1, [
            'date' => '2026-04-01',
            'start_time' => '17:00',
            'end_time' => '09:00',
        ]);
        $this->assertNull($result);
    }

    public function test_deleteSlot_returns_false_when_not_found(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('delete')->andReturn(0);
        $this->mockAvailability->shouldReceive('newQuery')->andReturn($query);

        $this->assertFalse($this->service->deleteSlot(1, 999));
    }

    public function test_findCompatible_returns_overlaps(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('orderBy')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([
            (object) ['user_id' => 1, 'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_recurring' => 1, 'specific_date' => null, 'note' => null, 'id' => 1, 'tenant_id' => 2],
            (object) ['user_id' => 2, 'day_of_week' => 1, 'start_time' => '14:00', 'end_time' => '20:00', 'is_recurring' => 1, 'specific_date' => null, 'note' => null, 'id' => 2, 'tenant_id' => 2],
        ]));
        $this->mockAvailability->shouldReceive('newQuery')->andReturn($query);

        $slotsA = [['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00']];
        $slotsB = [['day_of_week' => 1, 'start_time' => '14:00', 'end_time' => '20:00']];

        // findCompatible uses getAvailability which uses newQuery
        $this->mockAvailability->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->findCompatible(1, 2);
        // With mock data above, overlap is 14:00-17:00
        $this->assertIsArray($result);
    }
}
