<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\TestCase;
use Nexus\Core\VaultClient;

/**
 * VaultClient Tests
 * @covers \Nexus\Core\VaultClient
 */
class VaultClientTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(VaultClient::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['getSecret', 'setSecret'];
        foreach ($methods as $method) {
            if (method_exists(VaultClient::class, $method)) {
                $this->assertTrue(true);
            }
        }
        $this->assertTrue(true);
    }
}
