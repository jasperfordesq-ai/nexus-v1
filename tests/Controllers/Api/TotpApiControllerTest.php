<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\TotpApiController;

class TotpApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(TotpApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(TotpApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasVerifyMethod(): void
    {
        $reflection = new \ReflectionClass(TotpApiController::class);
        $this->assertTrue($reflection->hasMethod('verify'));
        $this->assertTrue($reflection->getMethod('verify')->isPublic());
    }

    public function testHasStatusMethod(): void
    {
        $reflection = new \ReflectionClass(TotpApiController::class);
        $this->assertTrue($reflection->hasMethod('status'));
        $this->assertTrue($reflection->getMethod('status')->isPublic());
    }
}
