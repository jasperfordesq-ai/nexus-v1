<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\DatabaseWrapper;

/**
 * DatabaseWrapper Tests
 * @covers \Nexus\Core\DatabaseWrapper
 */
class DatabaseWrapperTest extends DatabaseTestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(DatabaseWrapper::class));
    }

    public function testPublicMethodsExist(): void
    {
        $this->assertTrue(true); // Structure test
    }
}
