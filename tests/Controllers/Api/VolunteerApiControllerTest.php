<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\VolunteerApiController;

class VolunteerApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(VolunteerApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(VolunteerApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasOpportunityMethods(): void
    {
        $reflection = new \ReflectionClass(VolunteerApiController::class);
        $methods = ['opportunities', 'showOpportunity', 'createOpportunity', 'updateOpportunity', 'deleteOpportunity'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasApplicationMethods(): void
    {
        $reflection = new \ReflectionClass(VolunteerApiController::class);
        $methods = ['apply', 'withdrawApplication', 'myApplications', 'opportunityApplications', 'handleApplication'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasShiftMethods(): void
    {
        $reflection = new \ReflectionClass(VolunteerApiController::class);
        $methods = ['shifts', 'myShifts', 'signUp', 'cancelSignup'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasHoursMethods(): void
    {
        $reflection = new \ReflectionClass(VolunteerApiController::class);
        $methods = ['logHours', 'myHours', 'hoursSummary', 'verifyHours'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasOrganisationMethods(): void
    {
        $reflection = new \ReflectionClass(VolunteerApiController::class);
        $this->assertTrue($reflection->hasMethod('organisations'));
        $this->assertTrue($reflection->hasMethod('showOrganisation'));
    }
}
