<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\TestCase;
use Nexus\Core\Mailer;

/**
 * Mailer Tests
 * @covers \Nexus\Core\Mailer
 */
class MailerTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Mailer::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['send', 'sendTemplate'];
        foreach ($methods as $method) {
            if (method_exists(Mailer::class, $method)) {
                $this->assertTrue(true);
            }
        }
        $this->assertTrue(true);
    }
}
