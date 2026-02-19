<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\OpenApiController;

class OpenApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(OpenApiController::class));
    }

    public function testHasJsonMethod(): void
    {
        $reflection = new \ReflectionClass(OpenApiController::class);
        $this->assertTrue($reflection->hasMethod('json'));
        $this->assertTrue($reflection->getMethod('json')->isPublic());
    }

    public function testHasYamlMethod(): void
    {
        $reflection = new \ReflectionClass(OpenApiController::class);
        $this->assertTrue($reflection->hasMethod('yaml'));
        $this->assertTrue($reflection->getMethod('yaml')->isPublic());
    }

    public function testHasUiMethod(): void
    {
        $reflection = new \ReflectionClass(OpenApiController::class);
        $this->assertTrue($reflection->hasMethod('ui'));
        $this->assertTrue($reflection->getMethod('ui')->isPublic());
    }
}
