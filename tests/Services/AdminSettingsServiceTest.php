<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Services\AdminSettingsService;
use App\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class AdminSettingsServiceTest extends TestCase
{
    private AdminSettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminSettingsService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AdminSettingsService::class));
    }

    public function testGetAllReturnsArray(): void
    {
        $result = $this->service->getAll(1);
        $this->assertIsArray($result);
    }

    public function testUpdateReturnsTrue(): void
    {
        $result = $this->service->update(1, ['test_key' => 'test_value']);
        $this->assertTrue($result);
    }

    public function testUpdateHandlesArrayValues(): void
    {
        $result = $this->service->update(1, ['json_setting' => ['a' => 1, 'b' => 2]]);
        $this->assertTrue($result);
    }

    public function testUpdateHandlesMultipleSettings(): void
    {
        $result = $this->service->update(1, [
            'key_one' => 'val1',
            'key_two' => 'val2',
            'key_three' => 'val3',
        ]);
        $this->assertTrue($result);
    }

    public function testGetFeaturesReturnsArray(): void
    {
        $features = $this->service->getFeatures(1);
        $this->assertIsArray($features);
    }

    public function testGetFeaturesReturnsEmptyArrayForNonExistentTenant(): void
    {
        $features = $this->service->getFeatures(999999);
        $this->assertIsArray($features);
    }

    public function testToggleFeatureReturnsBool(): void
    {
        $result = $this->service->toggleFeature(1, 'events', true);
        $this->assertIsBool($result);
    }

    public function testToggleFeatureReturnsFalseForNonExistentTenant(): void
    {
        $result = $this->service->toggleFeature(999999, 'events', true);
        $this->assertFalse($result);
    }

    public function testMethodSignatures(): void
    {
        $ref = new \ReflectionMethod(AdminSettingsService::class, 'getAll');
        $this->assertCount(1, $ref->getParameters());

        $ref = new \ReflectionMethod(AdminSettingsService::class, 'update');
        $this->assertCount(2, $ref->getParameters());

        $ref = new \ReflectionMethod(AdminSettingsService::class, 'toggleFeature');
        $this->assertCount(3, $ref->getParameters());
    }
}
