<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ShiftSwapService;
use Illuminate\Support\Facades\DB;

class ShiftSwapServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset static errors
        $ref = new \ReflectionClass(ShiftSwapService::class);
        $prop = $ref->getProperty('errors');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    // ── requestSwap ──

    public function test_requestSwap_fails_with_missing_fields(): void
    {
        $result = ShiftSwapService::requestSwap(1, []);
        $this->assertNull($result);
        $this->assertEquals('VALIDATION_ERROR', ShiftSwapService::getErrors()[0]['code']);
    }

    public function test_requestSwap_fails_for_self_swap(): void
    {
        $result = ShiftSwapService::requestSwap(1, [
            'from_shift_id' => 1,
            'to_shift_id' => 2,
            'to_user_id' => 1,
        ]);
        $this->assertNull($result);
        $this->assertStringContainsString('yourself', ShiftSwapService::getErrors()[0]['message']);
    }

    // ── respond ──

    public function test_respond_rejects_invalid_action(): void
    {
        $result = ShiftSwapService::respond(1, 1, 'invalid');
        $this->assertFalse($result);
        $this->assertEquals('VALIDATION_ERROR', ShiftSwapService::getErrors()[0]['code']);
    }

    public function test_respond_fails_when_swap_not_found(): void
    {
        DB::shouldReceive('table->where->where->where->first')->andReturnNull();

        $result = ShiftSwapService::respond(999, 1, 'accept');
        $this->assertFalse($result);
        $this->assertEquals('NOT_FOUND', ShiftSwapService::getErrors()[0]['code']);
    }

    // ── adminDecision ──

    public function test_adminDecision_rejects_invalid_action(): void
    {
        $result = ShiftSwapService::adminDecision(1, 1, 'invalid');
        $this->assertFalse($result);
    }

    public function test_adminDecision_fails_when_not_pending(): void
    {
        DB::shouldReceive('table->where->where->where->first')->andReturnNull();

        $result = ShiftSwapService::adminDecision(999, 1, 'approve');
        $this->assertFalse($result);
    }

    // ── cancel ──

    public function test_cancel_fails_when_not_found(): void
    {
        DB::shouldReceive('table->where->where->whereIn->where->first')->andReturnNull();

        $result = ShiftSwapService::cancel(999, 1, $this->testTenantId);
        $this->assertFalse($result);
    }

    // ── getCancelErrors ──

    public function test_getCancelErrors_is_alias_for_getErrors(): void
    {
        $this->assertEquals(ShiftSwapService::getErrors(), ShiftSwapService::getCancelErrors());
    }

    // ── getSwapRequests ──

    public function test_getSwapRequests_returns_array(): void
    {
        $result = ShiftSwapService::getSwapRequests(1);
        $this->assertIsArray($result);
    }
}
