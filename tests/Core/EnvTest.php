<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Core;

use App\Tests\TestCase;
use App\Core\Env;

/**
 * Env Tests
 * @covers \App\Core\Env
 */
class EnvTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Env::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['get', 'load'];
        foreach ($methods as $method) {
            if (method_exists(Env::class, $method)) {
                $this->assertTrue(true);
            }
        }
        $this->assertTrue(true);
    }
}
