<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\PayPlanService;

class PayPlanServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(PayPlanService::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'validateLayoutAccess', 'validateFeatureAccess', 'validateMenuCreation',
            'getUpgradeSuggestions', 'assignPlan', 'startTrial',
            'getPlanStatus', 'canDowngradeTo'
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(PayPlanService::class, $method),
                "Method {$method} should exist on PayPlanService"
            );
        }
    }

    public function testMethodSignatures(): void
    {
        $ref = new \ReflectionClass(PayPlanService::class);

        // validateLayoutAccess($layout, $tenantId = null)
        $method = $ref->getMethod('validateLayoutAccess');
        $this->assertTrue($method->isStatic());
        $params = $method->getParameters();
        $this->assertEquals('layout', $params[0]->getName());
        $this->assertTrue($params[1]->isOptional());

        // startTrial($tenantId, $planId, $trialDays = 14)
        $method = $ref->getMethod('startTrial');
        $params = $method->getParameters();
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('planId', $params[1]->getName());
        $this->assertEquals('trialDays', $params[2]->getName());
        $this->assertEquals(14, $params[2]->getDefaultValue());
    }
}
