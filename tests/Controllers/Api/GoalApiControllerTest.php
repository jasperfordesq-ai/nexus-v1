<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\GoalApiController;

class GoalApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(GoalApiController::class));
    }

    public function testHasIndexMethod(): void
    {
        $reflection = new \ReflectionClass(GoalApiController::class);
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    public function testHasUpdateProgressMethod(): void
    {
        $reflection = new \ReflectionClass(GoalApiController::class);
        $this->assertTrue($reflection->hasMethod('updateProgress'));
        $this->assertTrue($reflection->getMethod('updateProgress')->isPublic());
    }

    public function testHasOfferBuddyMethod(): void
    {
        $reflection = new \ReflectionClass(GoalApiController::class);
        $this->assertTrue($reflection->hasMethod('offerBuddy'));
        $this->assertTrue($reflection->getMethod('offerBuddy')->isPublic());
    }
}
