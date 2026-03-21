<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\VolunteerEmergencyAlertService;

class VolunteerEmergencyAlertServiceTest extends TestCase
{
    public function test_createAlert_returns_null_without_shift_id(): void
    {
        $result = VolunteerEmergencyAlertService::createAlert(1, ['message' => 'Help needed']);

        $this->assertNull($result);
        $this->assertEquals('VALIDATION_ERROR', VolunteerEmergencyAlertService::getErrors()[0]['code']);
    }

    public function test_createAlert_returns_null_without_message(): void
    {
        $result = VolunteerEmergencyAlertService::createAlert(1, ['shift_id' => 1]);

        $this->assertNull($result);
        $this->assertEquals('VALIDATION_ERROR', VolunteerEmergencyAlertService::getErrors()[0]['code']);
    }

    public function test_createAlert_returns_null_for_invalid_priority(): void
    {
        $result = VolunteerEmergencyAlertService::createAlert(1, [
            'shift_id' => 1,
            'message' => 'Help',
            'priority' => 'mega_urgent',
        ]);

        $this->assertNull($result);
    }

    public function test_respond_returns_false_for_invalid_response(): void
    {
        $result = VolunteerEmergencyAlertService::respond(1, 1, 'maybe');

        $this->assertFalse($result);
        $this->assertEquals('VALIDATION_ERROR', VolunteerEmergencyAlertService::getErrors()[0]['code']);
    }

    public function test_getErrors_returns_array(): void
    {
        $this->assertIsArray(VolunteerEmergencyAlertService::getErrors());
    }

    public function test_getCancelErrors_returns_array(): void
    {
        $this->assertIsArray(VolunteerEmergencyAlertService::getCancelErrors());
    }
}
