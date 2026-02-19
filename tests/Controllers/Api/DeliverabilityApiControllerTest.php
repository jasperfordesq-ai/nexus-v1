<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\DeliverabilityApiController;

class DeliverabilityApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(DeliverabilityApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(DeliverabilityApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasCrudMethods(): void
    {
        $reflection = new \ReflectionClass(DeliverabilityApiController::class);
        $methods = ['index', 'show', 'create', 'update', 'delete'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasStatusMethods(): void
    {
        $reflection = new \ReflectionClass(DeliverabilityApiController::class);
        $methods = ['updateStatus', 'updateProgress', 'complete', 'assign'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasMilestoneMethods(): void
    {
        $reflection = new \ReflectionClass(DeliverabilityApiController::class);
        $methods = ['milestones', 'createMilestone', 'updateMilestone', 'completeMilestone', 'deleteMilestone'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasCommentMethods(): void
    {
        $reflection = new \ReflectionClass(DeliverabilityApiController::class);
        $methods = ['comments', 'createComment', 'updateComment', 'deleteComment'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
        }
    }

    public function testHasDashboardAndAnalyticsMethods(): void
    {
        $reflection = new \ReflectionClass(DeliverabilityApiController::class);
        $this->assertTrue($reflection->hasMethod('dashboard'));
        $this->assertTrue($reflection->hasMethod('analytics'));
    }
}
