<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ShiftGroupReservationService;

class ShiftGroupReservationServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ShiftGroupReservationService::class));
    }

    public function testReserveMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(ShiftGroupReservationService::class, 'reserve');
        $this->assertTrue($ref->isStatic());
    }

    public function testAddMemberMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(ShiftGroupReservationService::class, 'addMember');
        $this->assertTrue($ref->isStatic());
    }

    public function testRemoveMemberMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(ShiftGroupReservationService::class, 'removeMember');
        $this->assertTrue($ref->isStatic());
    }

    public function testCancelReservationMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(ShiftGroupReservationService::class, 'cancelReservation');
        $this->assertTrue($ref->isStatic());
    }

    public function testGetUserReservationsMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(ShiftGroupReservationService::class, 'getUserReservations');
        $this->assertTrue($ref->isStatic());
    }

    public function testGetErrorsMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(ShiftGroupReservationService::class, 'getErrors');
        $this->assertTrue($ref->isStatic());
    }

    public function testGetErrorsReturnsArray(): void
    {
        $errors = ShiftGroupReservationService::getErrors();
        $this->assertIsArray($errors);
    }

    public function testReserveRejectsZeroSlots(): void
    {
        $result = ShiftGroupReservationService::reserve(1, 1, 1, 0);
        $this->assertNull($result);

        $errors = ShiftGroupReservationService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
        $this->assertStringContainsString('at least 1 slot', $errors[0]['message']);
    }

    public function testReserveRejectsNegativeSlots(): void
    {
        $result = ShiftGroupReservationService::reserve(1, 1, 1, -5);
        $this->assertNull($result);

        $errors = ShiftGroupReservationService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function testReserveSignature(): void
    {
        $ref = new \ReflectionMethod(ShiftGroupReservationService::class, 'reserve');
        $params = $ref->getParameters();
        $this->assertCount(5, $params);
        $this->assertEquals('shiftId', $params[0]->getName());
        $this->assertEquals('groupId', $params[1]->getName());
        $this->assertEquals('reservedBy', $params[2]->getName());
        $this->assertEquals('slots', $params[3]->getName());
        $this->assertEquals('notes', $params[4]->getName());
        $this->assertTrue($params[4]->isOptional());
    }
}
