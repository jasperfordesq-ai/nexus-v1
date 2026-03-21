<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\VolunteerCheckInService;
use App\Models\VolShift;
use App\Models\VolShiftCheckin;
use Mockery;

class VolunteerCheckInServiceTest extends TestCase
{
    private VolunteerCheckInService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VolunteerCheckInService();
    }

    public function test_getErrors_returns_empty_initially(): void
    {
        $this->assertEmpty($this->service->getErrors());
    }

    public function test_checkOut_returns_false_when_token_not_found(): void
    {
        $mock = Mockery::mock('alias:' . VolShiftCheckin::class);
        $mock->shouldReceive('where')->with('qr_token', 'bad-token')->andReturnSelf();
        $mock->shouldReceive('first')->andReturn(null);

        $result = $this->service->checkOut('bad-token');

        $this->assertFalse($result);
        $this->assertEquals('NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_verifyCheckIn_returns_null_for_invalid_token(): void
    {
        $mock = Mockery::mock('alias:' . VolShiftCheckin::class);
        $mock->shouldReceive('with')->andReturnSelf();
        $mock->shouldReceive('where')->andReturnSelf();
        $mock->shouldReceive('first')->andReturn(null);

        $result = $this->service->verifyCheckIn('bad-token');

        $this->assertNull($result);
        $this->assertEquals('NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_getUserIdByToken_returns_null_when_not_found(): void
    {
        $mock = Mockery::mock('alias:' . VolShiftCheckin::class);
        $mock->shouldReceive('where')->andReturnSelf();
        $mock->shouldReceive('value')->andReturn(null);

        $this->assertNull(VolunteerCheckInService::getUserIdByToken('bad'));
    }

    public function test_getShiftIdByToken_returns_null_when_not_found(): void
    {
        $mock = Mockery::mock('alias:' . VolShiftCheckin::class);
        $mock->shouldReceive('where')->andReturnSelf();
        $mock->shouldReceive('value')->andReturn(null);

        $this->assertNull(VolunteerCheckInService::getShiftIdByToken('bad', 2));
    }
}
