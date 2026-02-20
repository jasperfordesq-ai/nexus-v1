<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Helpers;

use Nexus\Tests\TestCase;
use Nexus\Helpers\IcsHelper;

/**
 * IcsHelper Tests
 *
 * Tests ICS (iCalendar) file generation for events.
 *
 * @covers \Nexus\Helpers\IcsHelper
 */
class IcsHelperTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(IcsHelper::class));
    }

    public function testPublicMethodsExist(): void
    {
        $this->assertTrue(method_exists(IcsHelper::class, 'generateIcs'));
    }

    public function testGenerateIcsMethodSignature(): void
    {
        $ref = new \ReflectionMethod(IcsHelper::class, 'generateIcs');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(4, count($params));
    }

    public function testGenerateIcsIsStatic(): void
    {
        $ref = new \ReflectionMethod(IcsHelper::class, 'generateIcs');
        $this->assertTrue($ref->isStatic());
    }
}
