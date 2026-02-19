<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\AbuseDetectionService;

class AbuseDetectionServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AbuseDetectionService::class));
    }

    public function testThresholdConstants(): void
    {
        $this->assertEquals(50, AbuseDetectionService::LARGE_TRANSFER_THRESHOLD);
        $this->assertEquals(10, AbuseDetectionService::HIGH_VELOCITY_THRESHOLD);
        $this->assertEquals(24, AbuseDetectionService::CIRCULAR_WINDOW_HOURS);
        $this->assertEquals(90, AbuseDetectionService::INACTIVE_DAYS_THRESHOLD);
        $this->assertEquals(10, AbuseDetectionService::HIGH_BALANCE_THRESHOLD);
    }

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'runAllChecks', 'checkLargeTransfers', 'checkHighVelocity',
            'checkCircularTransfers', 'checkInactiveHighBalances'
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(AbuseDetectionService::class, $method),
                "Method {$method} should exist on AbuseDetectionService"
            );
        }
    }

    public function testAllMethodsAreStatic(): void
    {
        $ref = new \ReflectionClass(AbuseDetectionService::class);
        $publicMethods = ['runAllChecks', 'checkLargeTransfers', 'checkHighVelocity', 'checkCircularTransfers', 'checkInactiveHighBalances'];

        foreach ($publicMethods as $methodName) {
            $method = $ref->getMethod($methodName);
            $this->assertTrue($method->isStatic(), "Method {$methodName} should be static");
        }
    }
}
