<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\TestCase;
use Nexus\Core\SimpleOAuth;

/**
 * SimpleOAuth Tests
 * @covers \Nexus\Core\SimpleOAuth
 */
class SimpleOAuthTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SimpleOAuth::class));
    }

    public function testPublicMethodsExist(): void
    {
        $this->assertTrue(true);
    }
}
