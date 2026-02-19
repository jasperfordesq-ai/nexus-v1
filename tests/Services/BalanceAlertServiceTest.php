<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\BalanceAlertService;

class BalanceAlertServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(BalanceAlertService::class));
    }

    public function testDefaultThresholdConstants(): void
    {
        $this->assertEquals(50, BalanceAlertService::DEFAULT_LOW_BALANCE_THRESHOLD);
        $this->assertEquals(10, BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD);
    }

    public function testCriticalThresholdIsLowerThanLow(): void
    {
        $this->assertLessThan(
            BalanceAlertService::DEFAULT_LOW_BALANCE_THRESHOLD,
            BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD
        );
    }

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'checkAllBalances', 'checkBalance', 'getThresholds',
            'setThresholds', 'getBalanceStatus'
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(BalanceAlertService::class, $method),
                "Method {$method} should exist on BalanceAlertService"
            );
        }
    }

    public function testMethodsAreStatic(): void
    {
        $ref = new \ReflectionClass(BalanceAlertService::class);

        $publicMethods = ['checkAllBalances', 'checkBalance', 'getThresholds', 'setThresholds', 'getBalanceStatus'];
        foreach ($publicMethods as $methodName) {
            $method = $ref->getMethod($methodName);
            $this->assertTrue($method->isStatic(), "Method {$methodName} should be static");
        }
    }
}
