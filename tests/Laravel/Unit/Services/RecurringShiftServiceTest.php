<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\RecurringShiftService;
use Illuminate\Support\Facades\DB;

class RecurringShiftServiceTest extends TestCase
{
    private RecurringShiftService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecurringShiftService();
    }

    // ── createPattern ──

    public function test_createPattern_fails_when_opportunity_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturnNull();

        $result = $this->service->createPattern(999, 1, ['start_time' => '09:00', 'end_time' => '17:00']);
        $this->assertNull($result);
        $this->assertFirstErrorCode('NOT_FOUND');
    }

    public function test_createPattern_fails_with_invalid_frequency(): void
    {
        $opp = (object) ['id' => 1, 'created_by' => 1];
        DB::shouldReceive('selectOne')->andReturn($opp);

        $result = $this->service->createPattern(1, 1, [
            'frequency' => 'quarterly',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $this->assertNull($result);
        $this->assertFirstErrorCode('VALIDATION_ERROR');
    }

    public function test_createPattern_fails_without_times(): void
    {
        $opp = (object) ['id' => 1, 'created_by' => 1];
        DB::shouldReceive('selectOne')->andReturn($opp);

        $result = $this->service->createPattern(1, 1, ['frequency' => 'weekly']);
        $this->assertNull($result);
        $this->assertFirstErrorCode('VALIDATION_ERROR');
    }

    // ── generateOccurrences ──

    public function test_generateOccurrences_returns_zero_when_pattern_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturnNull();

        $result = $this->service->generateOccurrences(999);
        $this->assertEquals(0, $result);
        $this->assertNotEmpty($this->service->getErrors());
    }

    // ── getPattern ──

    public function test_getPattern_returns_null_when_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturnNull();

        $result = $this->service->getPattern(999);
        $this->assertNull($result);
    }

    // ── updatePattern ──

    public function test_updatePattern_fails_when_pattern_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturnNull();

        $result = $this->service->updatePattern(999, ['title' => 'New'], 1);
        $this->assertFalse($result);
        $this->assertFirstErrorCode('NOT_FOUND');
    }

    public function test_updatePattern_returns_true_with_no_updates(): void
    {
        $pattern = (object) ['id' => 1, 'created_by' => 1, 'opportunity_id' => 1];
        DB::shouldReceive('selectOne')->andReturn($pattern);

        $result = $this->service->updatePattern(1, [], 1);
        $this->assertTrue($result);
    }

    public function test_updatePattern_rejects_invalid_frequency(): void
    {
        $pattern = (object) ['id' => 1, 'created_by' => 1, 'opportunity_id' => 1];
        DB::shouldReceive('selectOne')->andReturn($pattern);

        $result = $this->service->updatePattern(1, ['frequency' => 'yearly'], 1);
        $this->assertFalse($result);
        $this->assertFirstErrorCode('VALIDATION_ERROR');
    }

    // ── deactivatePattern ──

    public function test_deactivatePattern_fails_when_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturnNull();

        $result = $this->service->deactivatePattern(999, 1);
        $this->assertFalse($result);
    }

    // ── getErrors ──

    public function test_getErrors_initially_empty(): void
    {
        $this->assertEquals([], $this->service->getErrors());
    }

    private function assertFirstErrorCode(string $expectedCode): void
    {
        $errors = $this->service->getErrors();

        $this->assertNotEmpty($errors);
        $this->assertIsArray($errors[0]);
        $this->assertSame($expectedCode, $errors[0]['code'] ?? null);
    }
}
