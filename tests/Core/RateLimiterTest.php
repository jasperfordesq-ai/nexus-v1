<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\RateLimiter;

/**
 * RateLimiter Tests
 * @covers \Nexus\Core\RateLimiter
 */
class RateLimiterTest extends DatabaseTestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(RateLimiter::class));
    }

    public function testDefaultLimitsConstantExists(): void
    {
        $limits = RateLimiter::DEFAULT_LIMITS;
        $this->assertIsArray($limits);
        $this->assertArrayHasKey('read', $limits);
        $this->assertArrayHasKey('write', $limits);
        $this->assertArrayHasKey('upload', $limits);
        $this->assertArrayHasKey('auth', $limits);
        $this->assertArrayHasKey('search', $limits);
    }

    public function testDefaultLimitsArePositiveIntegers(): void
    {
        foreach (RateLimiter::DEFAULT_LIMITS as $type => $limit) {
            $this->assertIsInt($limit);
            $this->assertGreaterThan(0, $limit);
        }
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['check', 'recordAttempt', 'clearAttempts'];
        foreach ($methods as $method) {
            $this->assertTrue(method_exists(RateLimiter::class, $method));
        }
    }
}
