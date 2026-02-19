<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\GroupRecommendationController;

class GroupRecommendationControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(GroupRecommendationController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(GroupRecommendationController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasIndexMethod(): void
    {
        $reflection = new \ReflectionClass(GroupRecommendationController::class);
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    public function testHasTrackMethod(): void
    {
        $reflection = new \ReflectionClass(GroupRecommendationController::class);
        $this->assertTrue($reflection->hasMethod('track'));
        $this->assertTrue($reflection->getMethod('track')->isPublic());
    }

    public function testHasMetricsMethod(): void
    {
        $reflection = new \ReflectionClass(GroupRecommendationController::class);
        $this->assertTrue($reflection->hasMethod('metrics'));
        $this->assertTrue($reflection->getMethod('metrics')->isPublic());
    }

    public function testHasSimilarMethod(): void
    {
        $reflection = new \ReflectionClass(GroupRecommendationController::class);
        $this->assertTrue($reflection->hasMethod('similar'));
        $method = $reflection->getMethod('similar');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('groupId', $params[0]->getName());
    }
}
