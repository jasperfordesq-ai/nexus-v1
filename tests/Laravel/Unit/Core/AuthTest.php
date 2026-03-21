<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\Auth;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_role'],
            $_SESSION['is_admin'],
            $_SESSION['is_super_admin'],
            $_SESSION['is_god'],
            $_SESSION['is_tenant_super_admin']
        );
    }

    protected function tearDown(): void
    {
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_role'],
            $_SESSION['is_admin'],
            $_SESSION['is_super_admin'],
            $_SESSION['is_god'],
            $_SESSION['is_tenant_super_admin']
        );
        parent::tearDown();
    }

    // -------------------------------------------------------
    // check()
    // -------------------------------------------------------

    public function test_check_returns_false_when_no_session(): void
    {
        $this->assertFalse(Auth::check());
    }

    public function test_check_returns_true_when_user_id_in_session(): void
    {
        $_SESSION['user_id'] = 42;
        $this->assertTrue(Auth::check());
    }

    // -------------------------------------------------------
    // id()
    // -------------------------------------------------------

    public function test_id_returns_null_when_not_logged_in(): void
    {
        $this->assertNull(Auth::id());
    }

    public function test_id_returns_user_id_when_logged_in(): void
    {
        $_SESSION['user_id'] = 99;
        $this->assertSame(99, Auth::id());
    }

    // -------------------------------------------------------
    // user()
    // -------------------------------------------------------

    public function test_user_returns_null_when_not_logged_in(): void
    {
        $this->assertNull(Auth::user());
    }

    // -------------------------------------------------------
    // isAdmin()
    // -------------------------------------------------------

    public function test_isAdmin_returns_false_with_null_user(): void
    {
        $this->assertFalse(Auth::isAdmin(null));
    }

    public function test_isAdmin_returns_false_with_regular_user(): void
    {
        $user = ['id' => 1, 'role' => 'member'];
        $this->assertFalse(Auth::isAdmin($user));
    }

    public function test_isAdmin_returns_true_with_admin_role(): void
    {
        $user = ['id' => 1, 'role' => 'admin'];
        $this->assertTrue(Auth::isAdmin($user));
    }

    public function test_isAdmin_returns_true_with_tenant_admin_role(): void
    {
        $user = ['id' => 1, 'role' => 'tenant_admin'];
        $this->assertTrue(Auth::isAdmin($user));
    }

    public function test_isAdmin_returns_true_with_is_god_flag(): void
    {
        $user = ['id' => 1, 'role' => 'member', 'is_god' => 1];
        $this->assertTrue(Auth::isAdmin($user));
    }

    public function test_isAdmin_returns_true_with_is_super_admin_flag(): void
    {
        $user = ['id' => 1, 'role' => 'member', 'is_super_admin' => 1];
        $this->assertTrue(Auth::isAdmin($user));
    }

    public function test_isAdmin_returns_true_with_is_tenant_super_admin_flag(): void
    {
        $user = ['id' => 1, 'role' => 'member', 'is_tenant_super_admin' => 1];
        $this->assertTrue(Auth::isAdmin($user));
    }

    public function test_isAdmin_returns_true_with_session_is_god(): void
    {
        $_SESSION['is_god'] = true;
        $user = ['id' => 1, 'role' => 'member'];
        $this->assertTrue(Auth::isAdmin($user));
    }

    public function test_isAdmin_returns_true_with_session_is_admin(): void
    {
        $_SESSION['is_admin'] = true;
        $user = ['id' => 1, 'role' => 'member'];
        $this->assertTrue(Auth::isAdmin($user));
    }

    // -------------------------------------------------------
    // logout()
    // -------------------------------------------------------

    public function test_logout_clears_session_vars(): void
    {
        $_SESSION['user_id'] = 42;
        $_SESSION['user_role'] = 'admin';
        $_SESSION['is_admin'] = true;
        $_SESSION['is_super_admin'] = true;
        $_SESSION['is_god'] = true;

        Auth::logout();

        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertArrayNotHasKey('user_role', $_SESSION);
        $this->assertArrayNotHasKey('is_admin', $_SESSION);
        $this->assertArrayNotHasKey('is_super_admin', $_SESSION);
        $this->assertArrayNotHasKey('is_god', $_SESSION);
    }

    // -------------------------------------------------------
    // role()
    // -------------------------------------------------------

    public function test_role_returns_null_when_not_logged_in(): void
    {
        $this->assertNull(Auth::role());
    }

    public function test_role_returns_role_from_session(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_role'] = 'admin';
        $this->assertSame('admin', Auth::role());
    }

    // -------------------------------------------------------
    // validateCsrf()
    // -------------------------------------------------------

    public function test_validateCsrf_delegates_to_csrf_verify(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['csrf_token'] = 'test-token-123';
        $this->assertTrue(Auth::validateCsrf('test-token-123'));
        $this->assertFalse(Auth::validateCsrf('wrong-token'));
    }
}
