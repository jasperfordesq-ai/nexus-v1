<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Auth;

/**
 * Auth Tests
 *
 * Tests core authentication helper methods.
 *
 * @covers \Nexus\Core\Auth
 */
class AuthTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        unset($_SESSION['user_id']);
    }

    protected function tearDown(): void
    {
        if (isset($_SESSION)) {
            unset($_SESSION['user_id']);
        }
        parent::tearDown();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Auth::class));
    }

    public function testCheckReturnsFalseWhenNotLoggedIn(): void
    {
        $this->assertFalse(Auth::check());
    }

    public function testCheckReturnsTrueWhenLoggedIn(): void
    {
        $_SESSION['user_id'] = 1;
        $this->assertTrue(Auth::check());
    }

    public function testIdReturnsNullWhenNotLoggedIn(): void
    {
        $this->assertNull(Auth::id());
    }

    public function testIdReturnsUserIdWhenLoggedIn(): void
    {
        $_SESSION['user_id'] = 123;
        $this->assertEquals(123, Auth::id());
    }

    public function testUserReturnsNullWhenNotLoggedIn(): void
    {
        $this->assertNull(Auth::user());
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['user', 'check', 'id', 'require'];
        foreach ($methods as $method) {
            $this->assertTrue(method_exists(Auth::class, $method));
        }
    }
}
