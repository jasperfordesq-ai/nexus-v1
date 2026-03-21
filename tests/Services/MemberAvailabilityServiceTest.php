<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\MemberAvailabilityService;
use App\Models\MemberAvailability;
use App\Core\TenantContext;

/**
 * MemberAvailabilityService Tests
 */
class MemberAvailabilityServiceTest extends TestCase
{
    private MemberAvailabilityService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new MemberAvailabilityService(new MemberAvailability());
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(MemberAvailabilityService::class, $this->service);
    }

    public function test_get_errors_returns_empty_array_initially(): void
    {
        $this->assertSame([], $this->service->getErrors());
    }

    public function test_get_availability_returns_array(): void
    {
        $result = $this->service->getAvailability(999999);
        $this->assertIsArray($result);
    }

    public function test_get_availability_empty_for_nonexistent_user(): void
    {
        $result = $this->service->getAvailability(999999);
        $this->assertEmpty($result);
    }

    public function test_get_user_availability_returns_array(): void
    {
        $result = $this->service->getUserAvailability(999999);
        $this->assertIsArray($result);
    }

    public function test_set_availability_rejects_invalid_day(): void
    {
        $result = $this->service->setAvailability(999999, 7, []);
        $this->assertFalse($result);

        $result = $this->service->setAvailability(999999, -1, []);
        $this->assertFalse($result);
    }

    public function test_set_day_availability_rejects_invalid_day(): void
    {
        $result = $this->service->setDayAvailability(999999, 7, []);
        $this->assertFalse($result);

        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function test_set_day_availability_validates_slot_times(): void
    {
        $result = $this->service->setDayAvailability(999999, 1, [
            ['start_time' => '14:00', 'end_time' => '10:00'],
        ]);
        $this->assertFalse($result);

        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function test_set_day_availability_validates_required_times(): void
    {
        $result = $this->service->setDayAvailability(999999, 1, [
            ['start_time' => '', 'end_time' => '10:00'],
        ]);
        $this->assertFalse($result);
    }

    public function test_add_specific_date_validates_required_fields(): void
    {
        $result = $this->service->addSpecificDate(999999, []);
        $this->assertNull($result);

        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function test_add_specific_date_validates_time_order(): void
    {
        $result = $this->service->addSpecificDate(999999, [
            'date' => '2026-06-15',
            'start_time' => '14:00',
            'end_time' => '10:00',
        ]);
        $this->assertNull($result);

        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('end_time', $errors[0]['message']);
    }

    public function test_delete_slot_returns_false_for_nonexistent(): void
    {
        $result = $this->service->deleteSlot(999999, 999999);
        $this->assertFalse($result);
    }

    public function test_find_compatible_returns_array(): void
    {
        $result = $this->service->findCompatible(999999, 999998);
        $this->assertIsArray($result);
    }

    public function test_find_compatible_empty_for_nonexistent_users(): void
    {
        $result = $this->service->findCompatible(999999, 999998);
        $this->assertEmpty($result);
    }

    public function test_find_compatible_times_returns_array(): void
    {
        $result = $this->service->findCompatibleTimes(999999, 999998);
        $this->assertIsArray($result);
    }

    public function test_get_available_members_returns_array(): void
    {
        $result = $this->service->getAvailableMembers(1);
        $this->assertIsArray($result);
    }

    public function test_get_available_members_with_time_filter(): void
    {
        $result = $this->service->getAvailableMembers(1, '10:00');
        $this->assertIsArray($result);
    }

    public function test_get_available_members_respects_limit(): void
    {
        $result = $this->service->getAvailableMembers(1, null, 3);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(3, count($result));
    }

    public function test_set_bulk_availability_returns_bool(): void
    {
        $result = $this->service->setBulkAvailability(999999, []);
        $this->assertIsBool($result);
    }

    public function test_errors_reset_between_calls(): void
    {
        // First call sets errors
        $this->service->setDayAvailability(999999, 7, []);
        $this->assertNotEmpty($this->service->getErrors());

        // Second call resets errors
        $this->service->addSpecificDate(999999, []);
        $errors = $this->service->getErrors();
        $this->assertCount(1, $errors);
    }
}
