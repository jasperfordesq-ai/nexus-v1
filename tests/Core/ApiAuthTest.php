<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\ApiAuth;

/**
 * ApiAuth Tests
 * @covers \Nexus\Core\ApiAuth
 */
class ApiAuthTest extends DatabaseTestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ApiAuth::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['authenticate'];
        foreach ($methods as $method) {
            $this->assertTrue(method_exists(ApiAuth::class, $method));
        }
    }
}
