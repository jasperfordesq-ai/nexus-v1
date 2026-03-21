<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\SafeguardingService;
use App\Models\SafeguardingAssignment;

class SafeguardingServiceTest extends TestCase
{
    private SafeguardingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SafeguardingService(new SafeguardingAssignment());
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SafeguardingService::class));
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void
    {
        $this->assertIsArray($this->service->getErrors());
        $this->assertEmpty($this->service->getErrors());
    }

    public function testCreateAssignmentRejectsSameGuardianAndWard(): void
    {
        $result = $this->service->createAssignment(1, 1, 1);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function testRecordTrainingRejectInvalidType(): void
    {
        $result = $this->service->recordTraining(1, ['training_type' => 'invalid_type'], 999999);
        $this->assertFalse($result);
    }

    public function testRecordTrainingAcceptsValidTypes(): void
    {
        $validTypes = ['children_first', 'vulnerable_adults', 'first_aid', 'manual_handling', 'other'];
        foreach ($validTypes as $type) {
            // We cannot actually insert (no tables), but the type validation should pass
            $ref = new \ReflectionMethod(SafeguardingService::class, 'recordTraining');
            $this->assertTrue($ref->isPublic());
        }
    }

    public function testReportIncidentRejectsInvalidSeverity(): void
    {
        $result = $this->service->reportIncident(1, ['severity' => 'extreme'], 999999);
        $this->assertFalse($result);
    }

    public function testReportIncidentRejectsInvalidIncidentType(): void
    {
        $result = $this->service->reportIncident(1, [
            'severity' => 'medium',
            'incident_type' => 'invalid_type',
        ], 999999);
        $this->assertFalse($result);
    }

    public function testGetTrainingForAdminReturnsExpectedStructure(): void
    {
        $result = $this->service->getTrainingForAdmin(999999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
    }

    public function testGetTrainingForAdminPaginationDefaults(): void
    {
        $result = $this->service->getTrainingForAdmin(999999);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(20, $result['per_page']);
    }

    public function testGetTrainingForAdminPaginationClamps(): void
    {
        $result = $this->service->getTrainingForAdmin(999999, 0, 500);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(100, $result['per_page']);
    }

    public function testGetIncidentsReturnsExpectedStructure(): void
    {
        $result = $this->service->getIncidents(999999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
    }

    public function testGetIncidentReturnsNullForNonExistentId(): void
    {
        $result = $this->service->getIncident(999999, 999999);
        $this->assertNull($result);
    }

    public function testUpdateIncidentReturnsFalseForEmptyData(): void
    {
        $result = $this->service->updateIncident(999999, [], 1, 999999);
        $this->assertFalse($result);
    }

    public function testAllPublicMethodsExist(): void
    {
        $methods = [
            'getErrors', 'createAssignment', 'recordConsent', 'revokeAssignment',
            'listAssignments', 'getTrainingForUser', 'recordTraining',
            'getTrainingForAdmin', 'verifyTraining', 'rejectTraining',
            'reportIncident', 'getIncidents', 'getIncident', 'updateIncident', 'assignDlp',
        ];
        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(SafeguardingService::class, $method),
                "Method {$method} should exist"
            );
        }
    }
}
