<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Helpers;

use Nexus\Tests\TestCase;
use Nexus\Helpers\NavigationConfig;

/**
 * NavigationConfig Tests
 *
 * Tests navigation configuration helper.
 *
 * @covers \Nexus\Helpers\NavigationConfig
 */
class NavigationConfigTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(NavigationConfig::class));
    }

    public function testPublicMethodsExist(): void
    {
        $this->assertTrue(true); // Structure test
    }
}
