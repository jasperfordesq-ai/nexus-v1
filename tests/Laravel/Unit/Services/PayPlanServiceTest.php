<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\TenantBillingService;

/**
 * The legacy `App\Services\PayPlanService` was a no-op delegation stub
 * (every method logged "Legacy delegation removed" and returned null). It was
 * deleted in the Laravel migration (commit aa27af479). The only real survivor
 * is plan assignment, which now lives on TenantBillingService as a static
 * method with a richer signature. These tests assert the current reality.
 */
class PayPlanServiceTest extends \Tests\Laravel\TestCase
{
    public function testLegacyPayPlanServiceIsRemoved(): void
    {
        $this->assertFalse(
            class_exists(\App\Services\PayPlanService::class),
            'Legacy PayPlanService stub should remain deleted; use TenantBillingService.'
        );
    }

    public function testTenantBillingServiceExists(): void
    {
        $this->assertTrue(class_exists(TenantBillingService::class));
    }

    public function testAssignPlanMethodExists(): void
    {
        $this->assertTrue(
            method_exists(TenantBillingService::class, 'assignPlan'),
            'Method assignPlan should exist on TenantBillingService'
        );
    }

    public function testAssignPlanSignature(): void
    {
        $ref = new \ReflectionClass(TenantBillingService::class);
        $method = $ref->getMethod('assignPlan');

        $this->assertTrue($method->isStatic(), 'assignPlan is a static method');

        $params = $method->getParameters();
        // assignPlan(int $tenantId, int $planId, ?string $expiresAt, ?string $notes, int $assignedBy, ...)
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('planId', $params[1]->getName());
        $this->assertEquals('expiresAt', $params[2]->getName());
        $this->assertEquals('notes', $params[3]->getName());
        $this->assertEquals('assignedBy', $params[4]->getName());

        // assignedBy is required; the trailing pricing/discount params are optional.
        $this->assertFalse($params[4]->isOptional());
        $this->assertTrue($params[5]->isOptional(), 'customPriceMonthly is optional');
        $this->assertEquals('void', (string) $method->getReturnType());
    }
}
