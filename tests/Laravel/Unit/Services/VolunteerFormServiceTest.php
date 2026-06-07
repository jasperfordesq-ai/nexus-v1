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
    private $customFieldAlias;
    private $accessibilityNeedAlias;

    protected function setUp(): void
    {
        // App\Models\VolCustomField / VolAccessibilityNeed may already be
        // autoloaded by app boot or an earlier test in the combined run, so the
        // alias mocks MUST be created before parent::setUp() and tolerate the
        // classes already existing. shouldIgnoreMissing() makes boot-time/static
        // calls no-ops; per-test expectations are layered on the shared instances.
        $this->customFieldAlias = Mockery::mock('alias:' . VolCustomField::class)->shouldIgnoreMissing();
        $this->accessibilityNeedAlias = Mockery::mock('alias:' . VolAccessibilityNeed::class)->shouldIgnoreMissing();
        parent::setUp();
    }

    public function test_getCustomFields_returns_empty_on_error(): void
    {
        // Mock the model to throw
        $mock = $this->customFieldAlias;
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        $result = VolunteerFormService::getCustomFields(null, 'application');
        $this->assertIsArray($result);
    }

    public function test_deleteField_returns_false_when_not_found(): void
    {
        $mock = $this->customFieldAlias;
        $mock->shouldReceive('find')->with(999)->andReturn(null);

        $this->assertFalse(VolunteerFormService::deleteField(999));
    }

    public function test_updateField_returns_false_when_not_found(): void
    {
        $mock = $this->customFieldAlias;
        $mock->shouldReceive('find')->with(999)->andReturn(null);

        $this->assertFalse(VolunteerFormService::updateField(999, ['field_label' => 'New Label']));
    }

    public function test_updateField_returns_false_when_no_allowed_fields(): void
    {
        $fieldMock = Mockery::mock();
        $fieldMock->shouldReceive('update')->never();

        $mock = $this->customFieldAlias;
        $mock->shouldReceive('find')->with(1)->andReturn($fieldMock);

        $this->assertFalse(VolunteerFormService::updateField(1, ['nonexistent_field' => 'value']));
    }

    public function test_getAccessibilityNeeds_returns_empty_on_error(): void
    {
        $mock = $this->accessibilityNeedAlias;
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        $result = VolunteerFormService::getAccessibilityNeeds(1, 2);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
