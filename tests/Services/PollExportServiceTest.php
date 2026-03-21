<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\PollExportService;

class PollExportServiceTest extends TestCase
{
    private PollExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PollExportService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(PollExportService::class));
    }

    public function testExportToCsvMethodExists(): void
    {
        $this->assertTrue(method_exists(PollExportService::class, 'exportToCsv'));
    }

    public function testExportToCsvReturnsNullForNonExistentPoll(): void
    {
        $result = $this->service->exportToCsv(999999, 1);
        $this->assertNull($result);
    }

    public function testExportToCsvSignature(): void
    {
        $ref = new \ReflectionMethod(PollExportService::class, 'exportToCsv');
        $params = $ref->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('pollId', $params[0]->getName());
        $this->assertEquals('userId', $params[1]->getName());
    }

    public function testExportToCsvReturnType(): void
    {
        $ref = new \ReflectionMethod(PollExportService::class, 'exportToCsv');
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertTrue($returnType->allowsNull());
    }
}
