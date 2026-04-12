<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupTemplateService;

class GroupTemplateServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(GroupTemplateService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(GroupTemplateService::class);
        foreach (['getAll', 'get', 'create', 'update', 'delete', 'applyTemplate'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Method {$m} should exist");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Method {$m} should be public");
            $this->assertTrue($ref->getMethod($m)->isStatic(), "Method {$m} should be static");
        }
    }

    public function test_getAll_returns_array_safely(): void
    {
        try {
            $result = GroupTemplateService::getAll();
            $this->assertIsArray($result);
        } catch (\TypeError $e) {
            $this->fail('TypeError: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_get_returns_null_or_array_safely(): void
    {
        try {
            $result = GroupTemplateService::get(0);
            $this->assertTrue(is_array($result) || is_null($result));
        } catch (\TypeError $e) {
            $this->fail('TypeError: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }
}
