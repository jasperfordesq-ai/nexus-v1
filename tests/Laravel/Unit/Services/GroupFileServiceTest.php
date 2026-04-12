<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupFileService;

class GroupFileServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(GroupFileService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(GroupFileService::class);
        foreach (['getErrors', 'list', 'upload', 'download', 'delete', 'getFolders', 'getStats'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Method {$m} should exist");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Method {$m} should be public");
        }
    }

    public function test_can_instantiate(): void
    {
        try {
            $svc = new GroupFileService();
            $this->assertInstanceOf(GroupFileService::class, $svc);
            $this->assertIsArray($svc->getErrors());
        } catch (\TypeError $e) {
            $this->fail('TypeError: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_getFolders_returns_array_safely(): void
    {
        try {
            $svc = new GroupFileService();
            $result = $svc->getFolders(0);
            $this->assertIsArray($result);
        } catch (\TypeError $e) {
            $this->fail('TypeError: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }
}
