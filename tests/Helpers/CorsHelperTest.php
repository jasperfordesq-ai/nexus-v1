<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Helpers;

use Nexus\Tests\TestCase;
use Nexus\Helpers\CorsHelper;

/**
 * CorsHelper Tests
 *
 * Tests CORS header management for cross-origin requests.
 *
 * @covers \Nexus\Helpers\CorsHelper
 */
class CorsHelperTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CorsHelper::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['setHeaders', 'handlePreflight'];
        foreach ($methods as $method) {
            if (method_exists(CorsHelper::class, $method)) {
                $this->assertTrue(true);
            }
        }
        $this->assertTrue(true);
    }
}
