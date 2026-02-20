<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;

/**
 * Database Tests
 * @covers \Nexus\Core\Database
 */
class DatabaseTest extends DatabaseTestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Database::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['getInstance', 'query', 'lastInsertId', 'beginTransaction', 'commit', 'rollback'];
        foreach ($methods as $method) {
            $this->assertTrue(method_exists(Database::class, $method));
        }
    }

    public function testGetInstanceReturnsPDO(): void
    {
        $instance = Database::getInstance();
        $this->assertInstanceOf(\PDO::class, $instance);
    }
}
