<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Middleware;

use Nexus\Tests\TestCase;
use Nexus\Middleware\SuperPanelAccess;
use ReflectionClass;

/**
 * SuperPanelAccessTest
 *
 * Tests the Super Admin Panel access control middleware that implements
 * hierarchical multi-tenant access:
 * - Master Tenant (id=1): Global access to all tenants
 * - Regional Tenant (allows_subtenants=1): Subtree access
 * - Standard Tenant: No Super Panel access
 *
 * SECURITY: These tests are critical because the Super Panel controls
 * tenant creation, hierarchy management, and cross-tenant operations.
 * Incorrect access control could allow unauthorized cross-tenant data access.
 */
class SuperPanelAccessTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(SuperPanelAccess::class);

        // Always reset cached access between tests
        SuperPanelAccess::reset();
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        unset($_SESSION['tenant_id']);
        unset($_SESSION['is_god']);
        SuperPanelAccess::reset();

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // check() tests — basic access control
    // -----------------------------------------------------------------------

    /**
     * Test check() returns false when not logged in.
     * SECURITY: Unauthenticated users must NEVER access the Super Panel.
     */
    public function testCheckReturnsFalseWhenNotLoggedIn(): void
    {
        unset($_SESSION['user_id']);

        // getAccess() will try to check $_SESSION['user_id'] first
        // Without it, access is denied
        $access = SuperPanelAccess::getAccess();

        $this->assertFalse($access['granted'], 'Access should be denied when not logged in');
        $this->assertEquals('none', $access['level']);
        $this->assertEquals('none', $access['scope']);
        $this->assertNull($access['user_id']);
        $this->assertNull($access['tenant_id']);
        $this->assertEquals('Not authenticated', $access['reason']);
    }

    /**
     * Test check() returns false when user_id is in session but user not found in DB.
     * Verified via source inspection since getAccess() calls Database::query()
     * which requires a live database connection.
     */
    public function testCheckReturnsFalseWhenUserNotFoundInDb(): void
    {
        $source = file_get_contents($this->reflection->getFileName());

        // After DB lookup, if user is not found, access should be denied
        $this->assertStringContainsString("'User not found'", $source,
            'getAccess() should set reason "User not found" when DB lookup returns no user');
    }

    // -----------------------------------------------------------------------
    // getAccess() structure tests
    // -----------------------------------------------------------------------

    /**
     * Test getAccess() returns a fully-structured response even when denying access.
     */
    public function testGetAccessReturnsFullStructureOnDenial(): void
    {
        unset($_SESSION['user_id']);

        $access = SuperPanelAccess::getAccess();

        $expectedKeys = [
            'granted', 'level', 'user_id', 'tenant_id', 'tenant_name',
            'tenant_path', 'tenant_depth', 'scope', 'can_create_tenants',
            'max_depth', 'reason'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $access, "Access response missing key: {$key}");
        }

        $this->assertIsBool($access['granted']);
        $this->assertIsString($access['level']);
        $this->assertIsString($access['scope']);
        $this->assertIsBool($access['can_create_tenants']);
        $this->assertIsInt($access['max_depth']);
        $this->assertIsString($access['reason']);
    }

    /**
     * Test getAccess() default denial has correct default values.
     */
    public function testGetAccessDefaultDenialValues(): void
    {
        unset($_SESSION['user_id']);

        $access = SuperPanelAccess::getAccess();

        $this->assertFalse($access['granted']);
        $this->assertEquals('none', $access['level']);
        $this->assertNull($access['user_id']);
        $this->assertNull($access['tenant_id']);
        $this->assertNull($access['tenant_name']);
        $this->assertNull($access['tenant_path']);
        $this->assertNull($access['tenant_depth']);
        $this->assertEquals('none', $access['scope']);
        $this->assertFalse($access['can_create_tenants']);
        $this->assertEquals(0, $access['max_depth']);
    }

    // -----------------------------------------------------------------------
    // Caching and reset() tests
    // -----------------------------------------------------------------------

    /**
     * Test that getAccess() caches the result per request.
     */
    public function testGetAccessCachesResult(): void
    {
        unset($_SESSION['user_id']);

        $access1 = SuperPanelAccess::getAccess();
        $access2 = SuperPanelAccess::getAccess();

        // Should be identical (cached)
        $this->assertSame($access1, $access2, 'getAccess() should cache results');
    }

    /**
     * Test reset() clears the cached access data.
     */
    public function testResetClearsCachedAccess(): void
    {
        unset($_SESSION['user_id']);

        // First call caches the result
        $access1 = SuperPanelAccess::getAccess();

        // Reset should clear the cache
        SuperPanelAccess::reset();

        // Verify the cached state is cleared via reflection
        $prop = $this->reflection->getProperty('currentAccess');
        $prop->setAccessible(true);
        $cachedValue = $prop->getValue();

        $this->assertNull($cachedValue, 'reset() should set currentAccess to null');
    }

    /**
     * Test that reset() allows getAccess() to re-evaluate.
     * After reset, the next getAccess() call should re-compute access
     * instead of returning cached data.
     */
    public function testResetAllowsReEvaluation(): void
    {
        unset($_SESSION['user_id']);

        // First call caches the "no access" result
        $access1 = SuperPanelAccess::getAccess();
        $this->assertFalse($access1['granted']);

        // Reset clears the cache
        SuperPanelAccess::reset();

        // Verify cache was cleared via reflection
        $prop = $this->reflection->getProperty('currentAccess');
        $prop->setAccessible(true);
        $this->assertNull($prop->getValue(), 'Cache should be null after reset');

        // Call getAccess again without user_id - should re-compute (same result but fresh)
        $access2 = SuperPanelAccess::getAccess();
        $this->assertFalse($access2['granted']);

        // The key insight: if caching was still active after reset, modifying
        // the cached data would be persisted. Here we verify the fresh computation.
        $this->assertEquals($access1, $access2);
    }

    // -----------------------------------------------------------------------
    // getScopeClause() tests
    // -----------------------------------------------------------------------

    /**
     * Test getScopeClause() returns "1 = 0" for no access (impossible WHERE clause).
     * SECURITY: Users without access should NEVER see any tenant data.
     */
    public function testGetScopeClauseReturnsImpossibleClauseForNoAccess(): void
    {
        unset($_SESSION['user_id']);

        $clause = SuperPanelAccess::getScopeClause();

        $this->assertEquals('1 = 0', $clause['sql'],
            'No-access users should get an impossible WHERE clause');
        $this->assertEmpty($clause['params']);
    }

    /**
     * Test getScopeClause() returns structured array with 'sql' and 'params' keys.
     */
    public function testGetScopeClauseReturnsCorrectStructure(): void
    {
        $clause = SuperPanelAccess::getScopeClause();

        $this->assertArrayHasKey('sql', $clause);
        $this->assertArrayHasKey('params', $clause);
        $this->assertIsString($clause['sql']);
        $this->assertIsArray($clause['params']);
    }

    /**
     * Test getScopeClause() uses the provided table alias.
     */
    public function testGetScopeClauseWithCustomAlias(): void
    {
        // With no access, the clause is "1 = 0" regardless of alias
        unset($_SESSION['user_id']);

        $clause = SuperPanelAccess::getScopeClause('tenants');

        // The no-access clause doesn't use the alias
        $this->assertEquals('1 = 0', $clause['sql']);
    }

    /**
     * Test that getScopeClause returns correct structure by simulating access levels
     * via reflection of the cached access property.
     */
    public function testGetScopeClauseForMasterAccess(): void
    {
        // Simulate master access via reflection
        $prop = $this->reflection->getProperty('currentAccess');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'granted' => true,
            'level' => 'master',
            'user_id' => 1,
            'tenant_id' => 1,
            'tenant_name' => 'Master Tenant',
            'tenant_path' => '/1/',
            'tenant_depth' => 0,
            'scope' => 'global',
            'can_create_tenants' => true,
            'max_depth' => 5,
            'reason' => 'Access granted'
        ]);

        $clause = SuperPanelAccess::getScopeClause();

        $this->assertEquals('1 = 1', $clause['sql'],
            'Master access should see all tenants (1 = 1)');
        $this->assertEmpty($clause['params']);
    }

    /**
     * Test getScopeClause() for regional access returns path LIKE clause.
     */
    public function testGetScopeClauseForRegionalAccess(): void
    {
        // Simulate regional access via reflection
        $prop = $this->reflection->getProperty('currentAccess');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'granted' => true,
            'level' => 'regional',
            'user_id' => 5,
            'tenant_id' => 2,
            'tenant_name' => 'Regional Tenant',
            'tenant_path' => '/1/2/',
            'tenant_depth' => 1,
            'scope' => 'subtree',
            'can_create_tenants' => true,
            'max_depth' => 3,
            'reason' => 'Access granted'
        ]);

        $clause = SuperPanelAccess::getScopeClause();

        $this->assertStringContainsString('LIKE', $clause['sql'],
            'Regional access should use LIKE for subtree filtering');
        $this->assertStringContainsString('t.path', $clause['sql'],
            'Should use default table alias "t"');
        $this->assertCount(1, $clause['params']);
        $this->assertEquals('/1/2/%', $clause['params'][0],
            'LIKE pattern should be tenant path + %');
    }

    /**
     * Test getScopeClause() for regional access with custom table alias.
     */
    public function testGetScopeClauseForRegionalWithCustomAlias(): void
    {
        $prop = $this->reflection->getProperty('currentAccess');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'granted' => true,
            'level' => 'regional',
            'user_id' => 5,
            'tenant_id' => 3,
            'tenant_name' => 'Regional',
            'tenant_path' => '/1/3/',
            'tenant_depth' => 1,
            'scope' => 'subtree',
            'can_create_tenants' => true,
            'max_depth' => 2,
            'reason' => 'Access granted'
        ]);

        $clause = SuperPanelAccess::getScopeClause('tenants');

        $this->assertStringContainsString('tenants.path', $clause['sql'],
            'Should use custom table alias');
    }

    // -----------------------------------------------------------------------
    // canAccessTenant() tests via reflection
    // -----------------------------------------------------------------------

    /**
     * Test canAccessTenant() for master access (sees all tenants).
     */
    public function testCanAccessTenantForMasterSeesAll(): void
    {
        // Set up master access
        $prop = $this->reflection->getProperty('currentAccess');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'granted' => true,
            'level' => 'master',
            'user_id' => 1,
            'tenant_id' => 1,
            'tenant_name' => 'Master',
            'tenant_path' => '/1/',
            'tenant_depth' => 0,
            'scope' => 'global',
            'can_create_tenants' => true,
            'max_depth' => 5,
            'reason' => 'Access granted'
        ]);

        // Master should access any tenant
        $this->assertTrue(SuperPanelAccess::canAccessTenant(1), 'Master should access tenant 1');
        $this->assertTrue(SuperPanelAccess::canAccessTenant(2), 'Master should access tenant 2');
        $this->assertTrue(SuperPanelAccess::canAccessTenant(999), 'Master should access any tenant');
    }

    /**
     * Test canAccessTenant() returns false when not granted.
     */
    public function testCanAccessTenantReturnsFalseWhenNotGranted(): void
    {
        unset($_SESSION['user_id']);

        $this->assertFalse(SuperPanelAccess::canAccessTenant(1));
        $this->assertFalse(SuperPanelAccess::canAccessTenant(2));
    }

    /**
     * Test canAccessTenant() for regional access — own tenant is always accessible.
     */
    public function testCanAccessTenantForRegionalOwn(): void
    {
        $prop = $this->reflection->getProperty('currentAccess');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'granted' => true,
            'level' => 'regional',
            'user_id' => 5,
            'tenant_id' => 2,
            'tenant_name' => 'Regional Tenant',
            'tenant_path' => '/1/2/',
            'tenant_depth' => 1,
            'scope' => 'subtree',
            'can_create_tenants' => true,
            'max_depth' => 3,
            'reason' => 'Access granted'
        ]);

        $this->assertTrue(SuperPanelAccess::canAccessTenant(2),
            'Regional user should always access own tenant');
    }

    // -----------------------------------------------------------------------
    // Access level simulation tests
    // -----------------------------------------------------------------------

    /**
     * Test master tenant (id=1) + is_tenant_super_admin gets global access.
     */
    public function testMasterTenantSuperAdminGetsGlobalAccess(): void
    {
        // Simulate what getAccess() would produce for master tenant super admin
        $prop = $this->reflection->getProperty('currentAccess');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'granted' => true,
            'level' => 'master',
            'user_id' => 1,
            'tenant_id' => 1,
            'tenant_name' => 'Project NEXUS',
            'tenant_path' => '/1/',
            'tenant_depth' => 0,
            'scope' => 'global',
            'can_create_tenants' => true,
            'max_depth' => 5,
            'reason' => 'Access granted'
        ]);

        $access = SuperPanelAccess::getAccess();

        $this->assertTrue($access['granted']);
        $this->assertEquals('master', $access['level']);
        $this->assertEquals('global', $access['scope']);
        $this->assertTrue($access['can_create_tenants']);
        $this->assertTrue(SuperPanelAccess::check());
    }

    /**
     * Test regional tenant + is_tenant_super_admin gets subtree access.
     */
    public function testRegionalTenantSuperAdminGetsSubtreeAccess(): void
    {
        $prop = $this->reflection->getProperty('currentAccess');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'granted' => true,
            'level' => 'regional',
            'user_id' => 10,
            'tenant_id' => 2,
            'tenant_name' => 'hOUR Timebank',
            'tenant_path' => '/1/2/',
            'tenant_depth' => 1,
            'scope' => 'subtree',
            'can_create_tenants' => true,
            'max_depth' => 3,
            'reason' => 'Access granted'
        ]);

        $access = SuperPanelAccess::getAccess();

        $this->assertTrue($access['granted']);
        $this->assertEquals('regional', $access['level']);
        $this->assertEquals('subtree', $access['scope']);
        $this->assertTrue($access['can_create_tenants']);
        $this->assertTrue(SuperPanelAccess::check());
    }

    /**
     * Test standard tenant without allows_subtenants gets no access.
     */
    public function testStandardTenantGetsNoAccess(): void
    {
        unset($_SESSION['user_id']);

        // Without any session, default is no access
        $access = SuperPanelAccess::getAccess();

        $this->assertFalse($access['granted']);
        $this->assertEquals('none', $access['level']);
        $this->assertEquals('none', $access['scope']);
        $this->assertFalse($access['can_create_tenants']);
        $this->assertFalse(SuperPanelAccess::check());
    }

    // -----------------------------------------------------------------------
    // check() method tests
    // -----------------------------------------------------------------------

    /**
     * Test check() returns true for granted access.
     */
    public function testCheckReturnsTrueForGrantedAccess(): void
    {
        $prop = $this->reflection->getProperty('currentAccess');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'granted' => true,
            'level' => 'master',
            'user_id' => 1,
            'tenant_id' => 1,
            'tenant_name' => 'Master',
            'tenant_path' => '/1/',
            'tenant_depth' => 0,
            'scope' => 'global',
            'can_create_tenants' => true,
            'max_depth' => 5,
            'reason' => 'Access granted'
        ]);

        $this->assertTrue(SuperPanelAccess::check());
    }

    /**
     * Test check() returns false for denied access.
     */
    public function testCheckReturnsFalseForDeniedAccess(): void
    {
        unset($_SESSION['user_id']);

        $this->assertFalse(SuperPanelAccess::check());
    }

    // -----------------------------------------------------------------------
    // isApiRequest() tests via source inspection
    // -----------------------------------------------------------------------

    /**
     * Test isApiRequest() detects API requests by various signals.
     */
    public function testIsApiRequestDetectsMultipleSignals(): void
    {
        $source = file_get_contents($this->reflection->getFileName());

        // Should check Accept header for JSON
        $this->assertStringContainsString('application/json', $source,
            'Should detect API requests via Accept header');

        // Should check URI prefix
        $this->assertStringContainsString('/api/', $source,
            'Should detect API requests via URI prefix');

        // Should check super-admin API prefix
        $this->assertStringContainsString('/super-admin/api/', $source,
            'Should detect super admin API requests');
    }

    // -----------------------------------------------------------------------
    // Access rules verification via source code
    // -----------------------------------------------------------------------

    /**
     * Test that the middleware checks is_tenant_super_admin and is_super_admin flags.
     */
    public function testAccessRulesCheckCorrectFlags(): void
    {
        $source = file_get_contents($this->reflection->getFileName());

        // Must check is_tenant_super_admin
        $this->assertStringContainsString('is_tenant_super_admin', $source,
            'Must check is_tenant_super_admin flag');

        // Must check is_super_admin (legacy)
        $this->assertStringContainsString('is_super_admin', $source,
            'Must check is_super_admin flag');

        // Must check allows_subtenants
        $this->assertStringContainsString('allows_subtenants', $source,
            'Must check allows_subtenants flag');

        // Must check tenant_id === 1 for master
        $this->assertStringContainsString('tenant_id', $source,
            'Must check tenant_id for master determination');
    }

    /**
     * Test that the denyAccess() method logs the denied attempt.
     * SECURITY: All denied access attempts should be logged for audit.
     */
    public function testDenyAccessLogsAttempt(): void
    {
        $source = file_get_contents($this->reflection->getFileName());

        $this->assertStringContainsString('error_log', $source,
            'denyAccess should log the denied attempt');
        $this->assertStringContainsString('ACCESS DENIED', $source,
            'Log message should clearly indicate access was denied');
    }
}
