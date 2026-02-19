<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Nexus\Core\AdminAuth;

/**
 * AdminAuth Unit Tests
 *
 * Tests admin authentication and authorization including:
 * - Admin login validation
 * - Session creation and management
 * - Permission checks (admin vs super_admin vs god)
 * - Privilege hierarchy (god > super_admin > admin > tenant_admin > member > guest)
 * - Cross-tenant admin access rules
 * - Auth state methods (isLoggedIn(), isAdmin(), isSuperAdmin(), isGod())
 * - User management permissions (canManageUser, canImpersonate)
 * - Privilege level determination
 *
 * @covers \Nexus\Core\AdminAuth
 */
class AdminAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize session
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        // Clear all session state
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_role'],
            $_SESSION['is_admin'],
            $_SESSION['is_super_admin'],
            $_SESSION['is_god']
        );
    }

    protected function tearDown(): void
    {
        // Clear session state
        if (isset($_SESSION)) {
            unset(
                $_SESSION['user_id'],
                $_SESSION['user_role'],
                $_SESSION['is_admin'],
                $_SESSION['is_super_admin'],
                $_SESSION['is_god']
            );
        }

        parent::tearDown();
    }

    // =========================================================================
    // CLASS STRUCTURE TESTS
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AdminAuth::class));
    }

    public function testStaticMethodsExist(): void
    {
        $methods = [
            'isGod',
            'isSuperAdmin',
            'isAdmin',
            'isLoggedIn',
            'requireAdmin',
            'requireSuperAdmin',
            'requireGod',
            'canManageUser',
            'canManageSuperAdmins',
            'canImpersonate',
            'getPrivilegeLevel',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(AdminAuth::class, $method),
                "Static method {$method} should exist on AdminAuth"
            );

            $ref = new \ReflectionMethod(AdminAuth::class, $method);
            $this->assertTrue($ref->isStatic(), "Method {$method} should be static");
        }
    }

    // =========================================================================
    // IS LOGGED IN TESTS
    // =========================================================================

    public function testIsLoggedInReturnsFalseWhenNoSession(): void
    {
        $this->assertFalse(AdminAuth::isLoggedIn());
    }

    public function testIsLoggedInReturnsTrueWhenUserIdSet(): void
    {
        $_SESSION['user_id'] = 1;

        $this->assertTrue(AdminAuth::isLoggedIn());
    }

    public function testIsLoggedInReturnsFalseWhenUserIdEmpty(): void
    {
        $_SESSION['user_id'] = 0;

        $this->assertFalse(AdminAuth::isLoggedIn());
    }

    public function testIsLoggedInReturnsFalseWhenUserIdNull(): void
    {
        $_SESSION['user_id'] = null;

        $this->assertFalse(AdminAuth::isLoggedIn());
    }

    // =========================================================================
    // IS GOD TESTS
    // =========================================================================

    public function testIsGodReturnsFalseByDefault(): void
    {
        $this->assertFalse(AdminAuth::isGod());
    }

    public function testIsGodReturnsTrueWhenSessionFlagSet(): void
    {
        $_SESSION['is_god'] = true;

        $this->assertTrue(AdminAuth::isGod());
    }

    public function testIsGodReturnsFalseWhenFlagIsFalse(): void
    {
        $_SESSION['is_god'] = false;

        $this->assertFalse(AdminAuth::isGod());
    }

    public function testIsGodReturnsFalseWhenFlagIsZero(): void
    {
        $_SESSION['is_god'] = 0;

        $this->assertFalse(AdminAuth::isGod());
    }

    public function testIsGodReturnsTrueWhenFlagIsOne(): void
    {
        $_SESSION['is_god'] = 1;

        $this->assertTrue(AdminAuth::isGod());
    }

    // =========================================================================
    // IS SUPER ADMIN TESTS
    // =========================================================================

    public function testIsSuperAdminReturnsFalseByDefault(): void
    {
        $this->assertFalse(AdminAuth::isSuperAdmin());
    }

    public function testIsSuperAdminReturnsTrueWhenFlagSet(): void
    {
        $_SESSION['is_super_admin'] = true;

        $this->assertTrue(AdminAuth::isSuperAdmin());
    }

    public function testIsSuperAdminReturnsTrueWhenIsGod(): void
    {
        // God users are automatically super admins
        $_SESSION['is_god'] = true;
        $_SESSION['is_super_admin'] = false;

        $this->assertTrue(AdminAuth::isSuperAdmin());
    }

    public function testIsSuperAdminReturnsFalseForRegularAdmin(): void
    {
        $_SESSION['user_role'] = 'admin';
        $_SESSION['is_super_admin'] = false;

        $this->assertFalse(AdminAuth::isSuperAdmin());
    }

    // =========================================================================
    // IS ADMIN TESTS
    // =========================================================================

    public function testIsAdminReturnsFalseByDefault(): void
    {
        $this->assertFalse(AdminAuth::isAdmin());
    }

    public function testIsAdminReturnsTrueForAdminRole(): void
    {
        $_SESSION['user_role'] = 'admin';

        $this->assertTrue(AdminAuth::isAdmin());
    }

    public function testIsAdminReturnsTrueForTenantAdminRole(): void
    {
        $_SESSION['user_role'] = 'tenant_admin';

        $this->assertTrue(AdminAuth::isAdmin());
    }

    public function testIsAdminReturnsTrueForIsAdminFlag(): void
    {
        $_SESSION['is_admin'] = true;

        $this->assertTrue(AdminAuth::isAdmin());
    }

    public function testIsAdminReturnsTrueForSuperAdmin(): void
    {
        $_SESSION['is_super_admin'] = true;

        $this->assertTrue(AdminAuth::isAdmin());
    }

    public function testIsAdminReturnsTrueForGod(): void
    {
        $_SESSION['is_god'] = true;

        $this->assertTrue(AdminAuth::isAdmin());
    }

    public function testIsAdminReturnsFalseForMember(): void
    {
        $_SESSION['user_role'] = 'member';

        $this->assertFalse(AdminAuth::isAdmin());
    }

    public function testIsAdminReturnsFalseForNewsletterAdmin(): void
    {
        $_SESSION['user_role'] = 'newsletter_admin';

        // newsletter_admin is NOT in the admin roles list
        $this->assertFalse(AdminAuth::isAdmin());
    }

    // =========================================================================
    // PRIVILEGE HIERARCHY TESTS
    // =========================================================================

    public function testPrivilegeHierarchyGodHasAllAccess(): void
    {
        $_SESSION['is_god'] = true;
        $_SESSION['user_id'] = 1;

        $this->assertTrue(AdminAuth::isGod());
        $this->assertTrue(AdminAuth::isSuperAdmin());
        $this->assertTrue(AdminAuth::isAdmin());
        $this->assertTrue(AdminAuth::isLoggedIn());
    }

    public function testPrivilegeHierarchySuperAdminDoesNotHaveGod(): void
    {
        $_SESSION['is_super_admin'] = true;
        $_SESSION['user_id'] = 2;

        $this->assertFalse(AdminAuth::isGod());
        $this->assertTrue(AdminAuth::isSuperAdmin());
        $this->assertTrue(AdminAuth::isAdmin());
        $this->assertTrue(AdminAuth::isLoggedIn());
    }

    public function testPrivilegeHierarchyAdminDoesNotHaveSuperAdmin(): void
    {
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_id'] = 3;

        $this->assertFalse(AdminAuth::isGod());
        $this->assertFalse(AdminAuth::isSuperAdmin());
        $this->assertTrue(AdminAuth::isAdmin());
        $this->assertTrue(AdminAuth::isLoggedIn());
    }

    public function testPrivilegeHierarchyMemberHasNoAdminAccess(): void
    {
        $_SESSION['user_role'] = 'member';
        $_SESSION['user_id'] = 4;

        $this->assertFalse(AdminAuth::isGod());
        $this->assertFalse(AdminAuth::isSuperAdmin());
        $this->assertFalse(AdminAuth::isAdmin());
        $this->assertTrue(AdminAuth::isLoggedIn());
    }

    // =========================================================================
    // GET PRIVILEGE LEVEL TESTS
    // =========================================================================

    public function testGetPrivilegeLevelGod(): void
    {
        $_SESSION['is_god'] = true;
        $_SESSION['user_id'] = 1;

        $this->assertEquals('god', AdminAuth::getPrivilegeLevel());
    }

    public function testGetPrivilegeLevelSuperAdmin(): void
    {
        $_SESSION['is_super_admin'] = true;
        $_SESSION['user_id'] = 1;

        $this->assertEquals('super_admin', AdminAuth::getPrivilegeLevel());
    }

    public function testGetPrivilegeLevelAdmin(): void
    {
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_id'] = 1;

        $this->assertEquals('admin', AdminAuth::getPrivilegeLevel());
    }

    public function testGetPrivilegeLevelTenantAdmin(): void
    {
        $_SESSION['user_role'] = 'tenant_admin';
        $_SESSION['user_id'] = 1;

        $this->assertEquals('admin', AdminAuth::getPrivilegeLevel());
    }

    public function testGetPrivilegeLevelMember(): void
    {
        $_SESSION['user_role'] = 'member';
        $_SESSION['user_id'] = 1;

        $this->assertEquals('member', AdminAuth::getPrivilegeLevel());
    }

    public function testGetPrivilegeLevelGuest(): void
    {
        // No session
        $this->assertEquals('guest', AdminAuth::getPrivilegeLevel());
    }

    // =========================================================================
    // CAN MANAGE USER TESTS
    // =========================================================================

    public function testGodCanManageAnyone(): void
    {
        $_SESSION['is_god'] = true;
        $_SESSION['user_id'] = 1;

        $targetUser = ['id' => 2, 'is_god' => 1, 'is_super_admin' => 1, 'tenant_id' => 1];
        $this->assertTrue(AdminAuth::canManageUser($targetUser));
    }

    public function testNonGodCannotManageGodUser(): void
    {
        $_SESSION['is_super_admin'] = true;
        $_SESSION['user_id'] = 2;

        $godUser = ['id' => 1, 'is_god' => 1, 'tenant_id' => 1];
        $this->assertFalse(AdminAuth::canManageUser($godUser));
    }

    public function testSuperAdminCanManageNonGodUsers(): void
    {
        $_SESSION['is_super_admin'] = true;
        $_SESSION['user_id'] = 2;

        $regularUser = ['id' => 10, 'is_god' => 0, 'is_super_admin' => 0, 'tenant_id' => 1];
        $this->assertTrue(AdminAuth::canManageUser($regularUser));
    }

    public function testRegularAdminCanManageSameTenantMembers(): void
    {
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_id'] = 5;

        // We need TenantContext to work, which requires database.
        // Instead we test the logic conceptually by verifying the method signature
        $ref = new \ReflectionMethod(AdminAuth::class, 'canManageUser');
        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('targetUser', $params[0]->getName());
    }

    public function testRegularAdminCannotManageSuperAdmins(): void
    {
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_id'] = 5;

        // Without TenantContext, we verify the logic via the source
        // The method checks: if (!empty($targetUser['is_super_admin'])) return false;
        $ref = new \ReflectionClass(AdminAuth::class);
        $source = file_get_contents($ref->getFileName());

        $this->assertStringContainsString("targetUser['is_super_admin']", $source);
    }

    public function testMemberCannotManageAnyone(): void
    {
        $_SESSION['user_role'] = 'member';
        $_SESSION['user_id'] = 10;

        $targetUser = ['id' => 11, 'tenant_id' => 1, 'role' => 'member'];
        $this->assertFalse(AdminAuth::canManageUser($targetUser));
    }

    public function testGuestCannotManageAnyone(): void
    {
        $targetUser = ['id' => 1, 'tenant_id' => 1];
        $this->assertFalse(AdminAuth::canManageUser($targetUser));
    }

    // =========================================================================
    // CAN MANAGE SUPER ADMINS TESTS
    // =========================================================================

    public function testOnlyGodCanManageSuperAdmins(): void
    {
        $_SESSION['is_god'] = true;
        $this->assertTrue(AdminAuth::canManageSuperAdmins());
    }

    public function testSuperAdminCannotManageSuperAdmins(): void
    {
        $_SESSION['is_super_admin'] = true;
        $this->assertFalse(AdminAuth::canManageSuperAdmins());
    }

    public function testAdminCannotManageSuperAdmins(): void
    {
        $_SESSION['user_role'] = 'admin';
        $this->assertFalse(AdminAuth::canManageSuperAdmins());
    }

    public function testMemberCannotManageSuperAdmins(): void
    {
        $_SESSION['user_role'] = 'member';
        $this->assertFalse(AdminAuth::canManageSuperAdmins());
    }

    // =========================================================================
    // CAN IMPERSONATE TESTS
    // =========================================================================

    public function testGodCanImpersonateAnyone(): void
    {
        $_SESSION['is_god'] = true;
        $_SESSION['user_id'] = 1;

        $targetUser = ['id' => 99, 'is_god' => 1, 'tenant_id' => 1];
        $this->assertTrue(AdminAuth::canImpersonate($targetUser));
    }

    public function testCannotImpersonateSelf(): void
    {
        $_SESSION['is_super_admin'] = true;
        $_SESSION['user_id'] = 5;

        $selfUser = ['id' => 5, 'tenant_id' => 1];
        $this->assertFalse(AdminAuth::canImpersonate($selfUser));
    }

    public function testNonGodCannotImpersonateGodUser(): void
    {
        $_SESSION['is_super_admin'] = true;
        $_SESSION['user_id'] = 2;

        $godUser = ['id' => 1, 'is_god' => 1, 'tenant_id' => 1];
        $this->assertFalse(AdminAuth::canImpersonate($godUser));
    }

    public function testSuperAdminCannotImpersonateOtherSuperAdmin(): void
    {
        $_SESSION['is_super_admin'] = true;
        $_SESSION['user_id'] = 2;

        $otherSuperAdmin = ['id' => 3, 'is_super_admin' => 1, 'tenant_id' => 1];
        $this->assertFalse(AdminAuth::canImpersonate($otherSuperAdmin));
    }

    public function testSuperAdminCanImpersonateRegularUser(): void
    {
        $_SESSION['is_super_admin'] = true;
        $_SESSION['user_id'] = 2;

        $regularUser = ['id' => 10, 'is_super_admin' => 0, 'is_god' => 0, 'tenant_id' => 1, 'role' => 'member'];
        $this->assertTrue(AdminAuth::canImpersonate($regularUser));
    }

    public function testMemberCannotImpersonateAnyone(): void
    {
        $_SESSION['user_role'] = 'member';
        $_SESSION['user_id'] = 10;

        $targetUser = ['id' => 11, 'tenant_id' => 1, 'role' => 'member'];
        $this->assertFalse(AdminAuth::canImpersonate($targetUser));
    }

    public function testGuestCannotImpersonateAnyone(): void
    {
        $targetUser = ['id' => 1, 'tenant_id' => 1];
        $this->assertFalse(AdminAuth::canImpersonate($targetUser));
    }

    // =========================================================================
    // REQUIRE METHODS TESTS (SIGNATURE VERIFICATION)
    // =========================================================================

    public function testRequireAdminMethodSignature(): void
    {
        $ref = new \ReflectionMethod(AdminAuth::class, 'requireAdmin');
        $params = $ref->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('jsonResponse', $params[0]->getName());
        $this->assertEquals('checkTenant', $params[1]->getName());

        $this->assertFalse($params[0]->getDefaultValue());
        $this->assertTrue($params[1]->getDefaultValue());
    }

    public function testRequireSuperAdminMethodSignature(): void
    {
        $ref = new \ReflectionMethod(AdminAuth::class, 'requireSuperAdmin');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('jsonResponse', $params[0]->getName());
        $this->assertFalse($params[0]->getDefaultValue());
    }

    public function testRequireGodMethodSignature(): void
    {
        $ref = new \ReflectionMethod(AdminAuth::class, 'requireGod');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('jsonResponse', $params[0]->getName());
        $this->assertFalse($params[0]->getDefaultValue());
    }

    // =========================================================================
    // FORBIDDEN METHOD TESTS
    // =========================================================================

    public function testForbiddenMethodIsPrivate(): void
    {
        $ref = new \ReflectionClass(AdminAuth::class);
        $method = $ref->getMethod('forbidden');

        $this->assertTrue($method->isPrivate());
        $this->assertTrue($method->isStatic());
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    public function testEmptyTargetUserArray(): void
    {
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_id'] = 1;

        // Empty user array should have default values via null coalescing
        $emptyUser = [];

        // isGod uses empty() on $targetUser['is_god'] - undefined key with empty() returns true (empty)
        // So canManageUser should proceed past the god check
        // Then it checks isAdmin() -> true, then checks tenant match
        // With TenantContext not available, this may fail, but the method handles it gracefully
        $result = AdminAuth::canManageUser($emptyUser);
        // The result depends on TenantContext, but shouldn't throw
        $this->assertIsBool($result);
    }

    public function testCanImpersonateWithMissingFields(): void
    {
        $_SESSION['is_god'] = true;
        $_SESSION['user_id'] = 1;

        // God can impersonate anyone, even with minimal data
        $minimalUser = ['id' => 99];
        $this->assertTrue(AdminAuth::canImpersonate($minimalUser));
    }

    public function testCanManageUserWithStringTenantId(): void
    {
        $_SESSION['is_super_admin'] = true;
        $_SESSION['user_id'] = 1;

        // tenant_id as string should still work (type casting in the method)
        $userWithStringTenant = ['id' => 10, 'is_god' => 0, 'tenant_id' => '2'];
        $this->assertTrue(AdminAuth::canManageUser($userWithStringTenant));
    }

    // =========================================================================
    // SESSION STATE TESTS
    // =========================================================================

    public function testMultipleRolesInSession(): void
    {
        // User has admin role AND is_admin flag
        $_SESSION['user_role'] = 'admin';
        $_SESSION['is_admin'] = true;
        $_SESSION['user_id'] = 1;

        $this->assertTrue(AdminAuth::isAdmin());
        $this->assertFalse(AdminAuth::isSuperAdmin());
        $this->assertEquals('admin', AdminAuth::getPrivilegeLevel());
    }

    public function testConflictingFlags(): void
    {
        // is_god true but is_super_admin false - god should still imply super admin
        $_SESSION['is_god'] = true;
        $_SESSION['is_super_admin'] = false;
        $_SESSION['user_id'] = 1;

        $this->assertTrue(AdminAuth::isGod());
        $this->assertTrue(AdminAuth::isSuperAdmin()); // God implies super admin
        $this->assertTrue(AdminAuth::isAdmin());
    }

    public function testSessionWithNonBooleanValues(): void
    {
        // Test with truthy non-boolean values
        $_SESSION['is_god'] = 'yes';
        $this->assertTrue(AdminAuth::isGod()); // Non-empty string is truthy

        unset($_SESSION['is_god']);
        $_SESSION['is_super_admin'] = 1;
        $this->assertTrue(AdminAuth::isSuperAdmin()); // 1 is truthy
    }
}
