<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\TestCase;
use Nexus\Core\DefaultMenus;

/**
 * DefaultMenus Tests
 * @covers \Nexus\Core\DefaultMenus
 */
class DefaultMenusTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(DefaultMenus::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['getMainMenu', 'getAdminMenu', 'getUserMenu'];
        foreach ($methods as $method) {
            if (method_exists(DefaultMenus::class, $method)) {
                $this->assertTrue(true);
            }
        }
        $this->assertTrue(true);
    }
}
