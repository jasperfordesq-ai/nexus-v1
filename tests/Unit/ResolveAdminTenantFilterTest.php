<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Http\Controllers\Api\BaseApiController;

/**
 * Regression test for resolveAdminTenantFilter()
 *
 * Verifies that admin listing endpoints default to the current tenant context,
 * preventing cross-tenant data leaks when no explicit ?tenant_id is passed.
 *
 * Root cause: Commit 634e76fe introduced super admin cross-tenant access that
 * removed ALL tenant scoping by default. This caused admin pages to show data
 * from all tenants instead of the current tenant.
 *
 * @covers \App\Http\Controllers\Api\BaseApiController::resolveAdminTenantFilter
 * @group unit
 * @group regression
 */
class ResolveAdminTenantFilterTest extends TestCase
{
    private TestableBaseApiController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TestableBaseApiController();
        // Clear any leftover $_GET state
        unset($_GET['tenant_id']);
    }

    protected function tearDown(): void
    {
        unset($_GET['tenant_id']);
        parent::tearDown();
    }

    // ─────────────────────────────────────────────
    // Regular Admin Tests
    // ─────────────────────────────────────────────

    public function testRegularAdminAlwaysScopedToOwnTenant(): void
    {
        $result = $this->controller->publicResolveAdminTenantFilter(false, 2);
        $this->assertSame(2, $result, 'Regular admin must always be scoped to their own tenant');
    }

    public function testRegularAdminCannotOverrideWithQueryParam(): void
    {
        $_GET['tenant_id'] = '5';
        $result = $this->controller->publicResolveAdminTenantFilter(false, 2);
        $this->assertSame(2, $result, 'Regular admin must be scoped to own tenant even with ?tenant_id');
    }

    public function testRegularAdminCannotUseAllTenants(): void
    {
        $_GET['tenant_id'] = 'all';
        $result = $this->controller->publicResolveAdminTenantFilter(false, 2);
        $this->assertSame(2, $result, 'Regular admin must never see all tenants');
    }

    // ─────────────────────────────────────────────
    // Super Admin Tests — Default Behavior (CRITICAL)
    // ─────────────────────────────────────────────

    public function testSuperAdminDefaultsToCurrentTenant(): void
    {
        // THIS IS THE BUG REGRESSION: before the fix, this returned null (no tenant filter)
        $result = $this->controller->publicResolveAdminTenantFilter(true, 2);
        $this->assertSame(2, $result, 'Super admin without ?tenant_id must default to current tenant, not all tenants');
    }

    public function testSuperAdminDefaultsToCurrentTenantForTenant1(): void
    {
        $result = $this->controller->publicResolveAdminTenantFilter(true, 1);
        $this->assertSame(1, $result, 'Super admin on tenant 1 should default to tenant 1');
    }

    public function testSuperAdminDefaultsToCurrentTenantForTenant4(): void
    {
        $result = $this->controller->publicResolveAdminTenantFilter(true, 4);
        $this->assertSame(4, $result, 'Super admin on tenant 4 should default to tenant 4');
    }

    // ─────────────────────────────────────────────
    // Super Admin Tests — Explicit Tenant Filter
    // ─────────────────────────────────────────────

    public function testSuperAdminCanFilterToSpecificTenant(): void
    {
        $_GET['tenant_id'] = '3';
        $result = $this->controller->publicResolveAdminTenantFilter(true, 2);
        $this->assertSame(3, $result, 'Super admin with ?tenant_id=3 should see tenant 3');
    }

    public function testSuperAdminCanFilterToOwnTenantExplicitly(): void
    {
        $_GET['tenant_id'] = '2';
        $result = $this->controller->publicResolveAdminTenantFilter(true, 2);
        $this->assertSame(2, $result, 'Super admin with ?tenant_id=2 while on tenant 2 should see tenant 2');
    }

    // ─────────────────────────────────────────────
    // Super Admin Tests — Cross-Tenant "All" View
    // ─────────────────────────────────────────────

    public function testSuperAdminCanExplicitlyRequestAllTenants(): void
    {
        $_GET['tenant_id'] = 'all';
        $result = $this->controller->publicResolveAdminTenantFilter(true, 2);
        $this->assertNull($result, 'Super admin with ?tenant_id=all should get null (no tenant filter)');
    }

    // ─────────────────────────────────────────────
    // Edge Cases
    // ─────────────────────────────────────────────

    public function testNonNumericTenantIdTreatedAsNoFilter(): void
    {
        $_GET['tenant_id'] = 'invalid';
        $result = $this->controller->publicResolveAdminTenantFilter(true, 2);
        $this->assertSame(2, $result, 'Non-numeric, non-"all" tenant_id should fall back to current tenant');
    }

    public function testEmptyStringTenantIdDefaultsToCurrentTenant(): void
    {
        $_GET['tenant_id'] = '';
        $result = $this->controller->publicResolveAdminTenantFilter(true, 2);
        $this->assertSame(2, $result, 'Empty string tenant_id should fall back to current tenant');
    }

    public function testZeroTenantIdTreatedAsNoFilter(): void
    {
        $_GET['tenant_id'] = '0';
        $result = $this->controller->publicResolveAdminTenantFilter(true, 2);
        // 0 is_numeric() === true, so (int)'0' = 0
        $this->assertSame(0, $result, 'Zero tenant_id should return 0 (which the controller treats as a specific ID)');
    }
}

/**
 * Exposes the protected resolveAdminTenantFilter() for unit testing.
 */
class TestableBaseApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function publicResolveAdminTenantFilter(bool $isSuperAdmin, int $tenantId): ?int
    {
        return $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
    }
}
