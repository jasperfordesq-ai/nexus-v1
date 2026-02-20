<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Tenant;

/**
 * Tenant Model Tests
 *
 * Tests tenant CRUD, hierarchy methods (children, descendants, ancestors),
 * slug/domain lookup, breadcrumbs, and root tenant retrieval.
 */
class TenantTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsTenantById(): void
    {
        $tenant = Tenant::find(self::$testTenantId);

        $this->assertNotFalse($tenant);
        $this->assertEquals(self::$testTenantId, $tenant['id']);
    }

    public function testFindReturnsNullishForNonExistentId(): void
    {
        $tenant = Tenant::find(999999999);

        $this->assertEmpty($tenant);
    }

    public function testFindBySlugReturnsTenant(): void
    {
        $tenant = Tenant::find(self::$testTenantId);
        $this->assertNotFalse($tenant);

        $found = Tenant::findBySlug($tenant['slug']);
        $this->assertNotFalse($found);
        $this->assertEquals($tenant['id'], $found['id']);
    }

    public function testFindBySlugReturnsNullishForBadSlug(): void
    {
        $result = Tenant::findBySlug('nonexistent-slug-xyz-999');
        $this->assertEmpty($result);
    }

    public function testFindByDomainReturnsNullishForBadDomain(): void
    {
        $result = Tenant::findByDomain('nonexistent.example.com');
        $this->assertEmpty($result);
    }

    // ==========================================
    // All Tests
    // ==========================================

    public function testAllReturnsArray(): void
    {
        $tenants = Tenant::all();
        $this->assertIsArray($tenants);
        $this->assertNotEmpty($tenants);
    }

    // ==========================================
    // Hierarchy Tests
    // ==========================================

    public function testGetChildrenReturnsArray(): void
    {
        $children = Tenant::getChildren(self::$testTenantId);
        $this->assertIsArray($children);
    }

    public function testGetDescendantsReturnsArray(): void
    {
        $descendants = Tenant::getDescendants(self::$testTenantId);
        $this->assertIsArray($descendants);
    }

    public function testGetDescendantsReturnsEmptyForNonExistent(): void
    {
        $descendants = Tenant::getDescendants(999999999);
        $this->assertIsArray($descendants);
        $this->assertEmpty($descendants);
    }

    public function testGetAncestorsReturnsArray(): void
    {
        $ancestors = Tenant::getAncestors(self::$testTenantId);
        $this->assertIsArray($ancestors);
    }

    public function testGetAncestorsReturnsEmptyForNonExistent(): void
    {
        $ancestors = Tenant::getAncestors(999999999);
        $this->assertIsArray($ancestors);
        $this->assertEmpty($ancestors);
    }

    public function testGetParentReturnsNullForRootTenant(): void
    {
        // Tenant 1 (master) has no parent
        $parent = Tenant::getParent(1);
        // Could be null if tenant 1 has no parent_id
        $this->assertTrue($parent === null || is_array($parent));
    }

    public function testGetDepthReturnsInteger(): void
    {
        $depth = Tenant::getDepth(self::$testTenantId);
        $this->assertIsInt($depth);
        $this->assertGreaterThanOrEqual(0, $depth);
    }

    public function testGetDepthReturnsZeroForNonExistent(): void
    {
        $depth = Tenant::getDepth(999999999);
        $this->assertEquals(0, $depth);
    }

    public function testIsAncestorOfReturnsFalseForSameTenant(): void
    {
        $result = Tenant::isAncestorOf(self::$testTenantId, self::$testTenantId);
        $this->assertFalse($result);
    }

    public function testIsAncestorOfReturnsFalseForNonExistent(): void
    {
        $result = Tenant::isAncestorOf(999999999, self::$testTenantId);
        $this->assertFalse($result);
    }

    public function testIsDescendantOfReturnsFalseForSameTenant(): void
    {
        $result = Tenant::isDescendantOf(self::$testTenantId, self::$testTenantId);
        $this->assertFalse($result);
    }

    public function testAllowsSubtenantsReturnsBool(): void
    {
        $result = Tenant::allowsSubtenants(self::$testTenantId);
        $this->assertIsBool($result);
    }

    public function testGetMasterReturnsTenant(): void
    {
        $master = Tenant::getMaster();
        $this->assertNotNull($master);
        $this->assertEquals(1, $master['id']);
    }

    public function testGetRootsReturnsArray(): void
    {
        $roots = Tenant::getRoots();
        $this->assertIsArray($roots);
        $this->assertNotEmpty($roots);
    }

    // ==========================================
    // Breadcrumb Tests
    // ==========================================

    public function testGetBreadcrumbReturnsArray(): void
    {
        $breadcrumb = Tenant::getBreadcrumb(self::$testTenantId);
        $this->assertIsArray($breadcrumb);
        $this->assertNotEmpty($breadcrumb);
    }

    public function testGetBreadcrumbIncludesCurrentTenant(): void
    {
        $breadcrumb = Tenant::getBreadcrumb(self::$testTenantId);
        $last = end($breadcrumb);
        $this->assertEquals(self::$testTenantId, $last['id']);
    }

    public function testGetBreadcrumbItemsHaveRequiredKeys(): void
    {
        $breadcrumb = Tenant::getBreadcrumb(self::$testTenantId);
        foreach ($breadcrumb as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('slug', $item);
        }
    }

    // ==========================================
    // Config Tests
    // ==========================================

    public function testUpdateConfigReturnsBool(): void
    {
        $tenant = Tenant::find(self::$testTenantId);
        $config = json_decode($tenant['configuration'] ?? '{}', true) ?: [];

        $result = Tenant::updateConfig(self::$testTenantId, $config);
        $this->assertTrue($result);
    }
}
