<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SafeguardingService;
use App\Models\SafeguardingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Mockery;

class SafeguardingServiceTest extends TestCase
{
    private SafeguardingService $service;
    private $mockAssignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAssignment = Mockery::mock(SafeguardingAssignment::class);
        $this->service = new SafeguardingService($this->mockAssignment);
    }

    // ── createAssignment ──

    public function test_createAssignment_fails_for_same_person(): void
    {
        $result = $this->service->createAssignment(1, 1, 2);
        $this->assertFalse($result['success']);
        $errors = $this->service->getErrors();
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function test_createAssignment_fails_when_guardian_not_found(): void
    {
        // User::where returns query builder that ->exists() returns false
        $result = $this->service->createAssignment(9999, 9998, 2);
        $this->assertFalse($result['success']);
    }

    // ── recordTraining ──

    public function test_recordTraining_rejects_invalid_type(): void
    {
        $result = $this->service->recordTraining(1, ['training_type' => 'invalid'], $this->testTenantId);
        $this->assertFalse($result);
    }

    public function test_recordTraining_accepts_valid_types(): void
    {
        $validTypes = ['children_first', 'vulnerable_adults', 'first_aid', 'manual_handling', 'other'];
        foreach ($validTypes as $type) {
            DB::shouldReceive('table->insertGetId')->andReturn(1);
            DB::shouldReceive('table->where->where->first')->andReturn((object) ['id' => 1]);

            $result = $this->service->recordTraining(1, ['training_type' => $type, 'training_name' => 'Test'], $this->testTenantId);
            $this->assertIsArray($result);
        }
    }

    // ── reportIncident ──

    public function test_reportIncident_rejects_invalid_severity(): void
    {
        $result = $this->service->reportIncident(1, ['severity' => 'extreme'], $this->testTenantId);
        $this->assertFalse($result);
    }

    public function test_reportIncident_rejects_invalid_incident_type(): void
    {
        $result = $this->service->reportIncident(1, [
            'severity' => 'high',
            'incident_type' => 'invalid_type',
        ], $this->testTenantId);
        $this->assertFalse($result);
    }

    // ── updateIncident ──

    public function test_updateIncident_returns_false_for_empty_data(): void
    {
        $result = $this->service->updateIncident(1, [], 1, $this->testTenantId);
        $this->assertFalse($result);
    }

    public function test_updateIncident_sets_resolved_at_for_closed_status(): void
    {
        DB::shouldReceive('table->where->where->first')->andReturn((object) [
            'reported_by' => 1,
            'assigned_to' => null,
            'severity' => 'low',
        ]);
        DB::shouldReceive('table->where->where->update')->once()->andReturn(1);
        DB::shouldReceive('table->insert')->andReturn(true);

        $result = $this->service->updateIncident(1, ['status' => 'resolved'], 1, $this->testTenantId);
        $this->assertTrue($result);
    }

    // ── getErrors ──

    public function test_getErrors_initially_empty(): void
    {
        $this->assertEquals([], $this->service->getErrors());
    }
}
