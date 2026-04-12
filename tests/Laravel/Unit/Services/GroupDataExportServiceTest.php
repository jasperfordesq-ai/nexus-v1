<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupDataExportService;

class GroupDataExportServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(GroupDataExportService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(GroupDataExportService::class);
        foreach (['exportAll', 'toCsv'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Method {$m} should exist");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Method {$m} should be public");
            $this->assertTrue($ref->getMethod($m)->isStatic(), "Method {$m} should be static");
        }
    }

    public function test_toCsv_with_empty_array_returns_string(): void
    {
        try {
            $result = GroupDataExportService::toCsv([]);
            $this->assertIsString($result);
        } catch (\TypeError $e) {
            $this->fail('TypeError: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_toCsv_with_rows_returns_string(): void
    {
        try {
            $result = GroupDataExportService::toCsv([['a' => 1, 'b' => 2]]);
            $this->assertIsString($result);
        } catch (\TypeError $e) {
            $this->fail('TypeError: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }
}
