<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Tests\Services;

use Nexus\Tests\TestCase;
use App\Services\PusherService;

class PusherServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(PusherService::class));
    }

    public function testGetInstanceMethodExists(): void
    {
        $this->assertTrue(method_exists(PusherService::class, 'getInstance'));
        $ref = new \ReflectionMethod(PusherService::class, 'getInstance');
        $this->assertTrue($ref->isStatic());
    }
}
