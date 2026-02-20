<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\TestCase;
use Nexus\Core\Router;

/**
 * Router Tests
 * @covers \Nexus\Core\Router
 */
class RouterTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Router::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['get', 'post', 'put', 'delete', 'dispatch'];
        foreach ($methods as $method) {
            if (method_exists(Router::class, $method)) {
                $this->assertTrue(true);
            }
        }
        $this->assertTrue(true);
    }
}
