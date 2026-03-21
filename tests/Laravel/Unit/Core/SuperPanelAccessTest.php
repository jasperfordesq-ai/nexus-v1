<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\SuperPanelAccess;
use PHPUnit\Framework\TestCase;

class SuperPanelAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SuperPanelAccess::reset();
        unset($_SESSION['user_id']);
    }

    protected function tearDown(): void
    {
        SuperPanelAccess::reset();
        unset($_SESSION['user_id']);
        parent::tearDown();
    }

    // -------------------------------------------------------
    // reset()
    // -------------------------------------------------------

    public function test_reset_clears_cached_access(): void
    {
        // After reset, getAccess should re-evaluate
        SuperPanelAccess::reset();
        // No session user, so access should be denied
        $access = SuperPanelAccess::getAccess();
        $this->assertFalse($access['granted']);
    }

    // -------------------------------------------------------
    // getAccess() — unauthenticated
    // -------------------------------------------------------

    public function test_getAccess_returns_denied_when_no_user(): void
    {
        $access = SuperPanelAccess::getAccess();
        $this->assertFalse($access['granted']);
        $this->assertSame('none', $access['level']);
        $this->assertSame('Not authenticated', $access['reason']);
    }

    // -------------------------------------------------------
    // check() — unauthenticated
    // -------------------------------------------------------

    public function test_check_returns_false_when_no_user(): void
    {
        $this->assertFalse(SuperPanelAccess::check());
    }

    // -------------------------------------------------------
    // getScopeClause()
    // -------------------------------------------------------

    public function test_getScopeClause_denied_returns_impossible_condition(): void
    {
        $clause = SuperPanelAccess::getScopeClause();
        $this->assertSame('1 = 0', $clause['sql']);
        $this->assertEmpty($clause['params']);
    }

    // -------------------------------------------------------
    // canAccessTenant() — unauthenticated
    // -------------------------------------------------------

    public function test_canAccessTenant_returns_false_when_not_granted(): void
    {
        $this->assertFalse(SuperPanelAccess::canAccessTenant(1));
    }

    // -------------------------------------------------------
    // canManageTenant() — unauthenticated
    // -------------------------------------------------------

    public function test_canManageTenant_returns_false_when_not_granted(): void
    {
        $this->assertFalse(SuperPanelAccess::canManageTenant(1));
    }

    // -------------------------------------------------------
    // canCreateSubtenantUnder() — unauthenticated
    // -------------------------------------------------------

    public function test_canCreateSubtenantUnder_returns_not_allowed_when_no_access(): void
    {
        $result = SuperPanelAccess::canCreateSubtenantUnder(1);
        $this->assertFalse($result['allowed']);
        $this->assertSame('No Super Admin access', $result['reason']);
    }
}
