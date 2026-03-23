<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\VolunteerFormService;
use App\Models\VolCustomField;
use App\Models\VolAccessibilityNeed;
use Mockery;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class VolunteerFormServiceTest extends TestCase
{
    public function test_getCustomFields_returns_empty_on_error(): void
    {
        // Mock the model to throw
        $mock = Mockery::mock('alias:' . VolCustomField::class);
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        $result = VolunteerFormService::getCustomFields(null, 'application');
        $this->assertIsArray($result);
    }

    public function test_deleteField_returns_false_when_not_found(): void
    {
        $mock = Mockery::mock('alias:' . VolCustomField::class);
        $mock->shouldReceive('find')->with(999)->andReturn(null);

        $this->assertFalse(VolunteerFormService::deleteField(999));
    }

    public function test_updateField_returns_false_when_not_found(): void
    {
        $mock = Mockery::mock('alias:' . VolCustomField::class);
        $mock->shouldReceive('find')->with(999)->andReturn(null);

        $this->assertFalse(VolunteerFormService::updateField(999, ['field_label' => 'New Label']));
    }

    public function test_updateField_returns_false_when_no_allowed_fields(): void
    {
        $fieldMock = Mockery::mock();
        $fieldMock->shouldReceive('update')->never();

        $mock = Mockery::mock('alias:' . VolCustomField::class);
        $mock->shouldReceive('find')->with(1)->andReturn($fieldMock);

        $this->assertFalse(VolunteerFormService::updateField(1, ['nonexistent_field' => 'value']));
    }

    public function test_getAccessibilityNeeds_returns_empty_on_error(): void
    {
        $mock = Mockery::mock('alias:' . VolAccessibilityNeed::class);
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        $result = VolunteerFormService::getAccessibilityNeeds(1, 2);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
