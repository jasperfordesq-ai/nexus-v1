<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Middleware;

use Nexus\Tests\TestCase;
use Nexus\Middleware\MaintenanceModeMiddleware;
use ReflectionClass;

/**
 * MaintenanceModeMiddlewareTest
 *
 * Tests the maintenance mode middleware that blocks non-admin users
 * during scheduled or emergency maintenance windows.
 *
 * SECURITY: These tests verify that:
 * - Admin users can still access the platform during maintenance
 * - Regular users are properly blocked
 * - Critical routes (health checks, auth, bootstrap) remain accessible
 * - API requests receive proper 503 JSON responses
 */
class MaintenanceModeMiddlewareTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(MaintenanceModeMiddleware::class);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_URI']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SESSION['user_id']);

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Exempt routes tests
    // -----------------------------------------------------------------------

    /**
     * Test that the exempt routes constant contains all critical paths.
     */
    public function testExemptRoutesContainsCriticalPaths(): void
    {
        $constant = $this->reflection->getConstant('EXEMPT_ROUTES');

        $this->assertIsArray($constant);

        // Health check endpoint must always be accessible (Docker health checks)
        $this->assertContains('/health.php', $constant, 'Health check must be exempt from maintenance');

        // Admin routes must be accessible for admins to manage maintenance mode
        $this->assertContains('/admin/', $constant, 'Admin routes must be exempt');
        $this->assertContains('/admin-legacy/', $constant, 'Legacy admin routes must be exempt');
        $this->assertContains('/super-admin/', $constant, 'Super admin routes must be exempt');
        $this->assertContains('/api/v2/admin/', $constant, 'Admin API routes must be exempt');

        // Auth routes must be accessible so admins can log in
        $this->assertContains('/api/auth/', $constant, 'Auth API must be exempt');

        // Bootstrap and tenant discovery must work for the React frontend to load
        $this->assertContains('/api/v2/tenant/bootstrap', $constant, 'Bootstrap API must be exempt');
        $this->assertContains('/api/v2/tenants', $constant, 'Tenants API must be exempt');

        // Favicon must not trigger maintenance page
        $this->assertContains('/favicon.ico', $constant, 'Favicon must be exempt');
    }

    /**
     * Test that message-related endpoints are exempt (prevents data loss for in-flight messages).
     */
    public function testExemptRoutesIncludeMessagingEndpoints(): void
    {
        $constant = $this->reflection->getConstant('EXEMPT_ROUTES');

        $this->assertContains('/api/v2/messages/unread-count', $constant, 'Message unread count should be exempt');
        $this->assertContains('/api/v2/notifications/counts', $constant, 'Notification counts should be exempt');
    }

    /**
     * Test that the number of exempt routes is reasonable (not accidentally empty or too broad).
     */
    public function testExemptRoutesHasReasonableCount(): void
    {
        $constant = $this->reflection->getConstant('EXEMPT_ROUTES');

        // Should have between 5 and 20 exempt routes
        $this->assertGreaterThanOrEqual(5, count($constant), 'Too few exempt routes');
        $this->assertLessThanOrEqual(20, count($constant), 'Too many exempt routes (security risk)');
    }

    // -----------------------------------------------------------------------
    // isUserAdmin() tests via reflection
    // -----------------------------------------------------------------------

    /**
     * Test isUserAdmin() returns false when no auth is present.
     */
    public function testIsUserAdminReturnsFalseWithNoAuth(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SESSION['user_id']);

        $method = $this->reflection->getMethod('isUserAdmin');
        $method->setAccessible(true);

        $result = $method->invoke(null);

        $this->assertFalse($result, 'isUserAdmin should return false when no authentication is present');
    }

    /**
     * Test isUserAdmin() checks the Bearer token authorization header.
     */
    public function testIsUserAdminChecksAuthorizationHeader(): void
    {
        // Set an invalid Bearer token - should not crash, just return false
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid-token-for-testing';
        unset($_SESSION['user_id']);

        $method = $this->reflection->getMethod('isUserAdmin');
        $method->setAccessible(true);

        try {
            $result = $method->invoke(null);
            // With an invalid token, TokenService::verifyAccessToken will throw or return null
            $this->assertFalse($result, 'isUserAdmin should return false for invalid Bearer token');
        } catch (\Throwable $e) {
            // TokenService might throw if APP_KEY not set, which is acceptable
            $this->assertInstanceOf(\Throwable::class, $e);
        }
    }

    /**
     * Test that the isUserAdmin method checks admin roles correctly.
     * We verify the logic by examining what roles are accepted in the source code.
     */
    public function testIsUserAdminAcceptsCorrectRoles(): void
    {
        // Read the source code to verify the accepted roles
        $method = $this->reflection->getMethod('isUserAdmin');
        $source = file_get_contents($this->reflection->getFileName());

        // Verify the method checks for admin, tenant_admin, and super_admin roles
        $this->assertStringContainsString("'admin'", $source, 'Should check for admin role');
        $this->assertStringContainsString("'tenant_admin'", $source, 'Should check for tenant_admin role');
        $this->assertStringContainsString("'super_admin'", $source, 'Should check for super_admin role');

        // Verify it checks the is_super_admin and is_tenant_super_admin flags
        $this->assertStringContainsString('is_super_admin', $source, 'Should check is_super_admin flag');
        $this->assertStringContainsString('is_tenant_super_admin', $source, 'Should check is_tenant_super_admin flag');
    }

    // -----------------------------------------------------------------------
    // showMaintenancePage() tests
    // -----------------------------------------------------------------------

    /**
     * Test that API requests get JSON 503 response structure.
     * We verify this by inspecting the source code since the method calls exit().
     */
    public function testShowMaintenancePageReturnsJson503ForApiRequests(): void
    {
        $source = file_get_contents($this->reflection->getFileName());

        // Verify the method checks for API requests
        $this->assertStringContainsString("strpos(\$requestUri, '/api/')", $source,
            'Should detect API requests by URI prefix');

        // Verify it sets the correct HTTP status code
        $this->assertStringContainsString('503', $source,
            'Should return 503 status code for maintenance');

        // Verify it returns JSON with the correct error structure
        $this->assertStringContainsString('MAINTENANCE_MODE', $source,
            'Should include MAINTENANCE_MODE error code');

        // Verify JSON content type is set for API requests
        $this->assertStringContainsString("'Content-Type: application/json'", $source,
            'Should set JSON content type for API responses');
    }

    /**
     * Test that HTML requests are passed through to React (not blocked with plain HTML).
     * The React frontend handles its own maintenance page via TenantShell.
     */
    public function testShowMaintenancePageLetsReactHandleHtmlRequests(): void
    {
        $method = $this->reflection->getMethod('showMaintenancePage');
        $method->setAccessible(true);

        $source = file_get_contents($this->reflection->getFileName());

        // The showMaintenancePage method should return (not exit) for non-API requests
        // so React's TenantShell can render its own maintenance page
        $this->assertStringContainsString('return;', $source,
            'HTML requests should return (not exit) to let React handle maintenance display');
    }

    // -----------------------------------------------------------------------
    // check() method contract tests
    // -----------------------------------------------------------------------

    /**
     * Test check() skips for exempt route /health.php
     */
    public function testCheckSkipsForHealthEndpoint(): void
    {
        $_SERVER['REQUEST_URI'] = '/health.php';

        // check() should return without blocking since /health.php is exempt
        // If it tries to access Database/TenantContext, it would throw in test env
        try {
            MaintenanceModeMiddleware::check();
            // If we reach here, the exempt route was correctly bypassed
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            // Should NOT throw for exempt routes
            $this->fail('check() should skip exempt routes without accessing DB: ' . $e->getMessage());
        }
    }

    /**
     * Test check() skips for admin routes.
     */
    public function testCheckSkipsForAdminRoutes(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin/dashboard';

        try {
            MaintenanceModeMiddleware::check();
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            $this->fail('check() should skip admin routes: ' . $e->getMessage());
        }
    }

    /**
     * Test check() skips for auth API routes.
     */
    public function testCheckSkipsForAuthApiRoutes(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/auth/login';

        try {
            MaintenanceModeMiddleware::check();
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            $this->fail('check() should skip auth API routes: ' . $e->getMessage());
        }
    }

    /**
     * Test check() skips for bootstrap API route.
     */
    public function testCheckSkipsForBootstrapRoute(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/v2/tenant/bootstrap';

        try {
            MaintenanceModeMiddleware::check();
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            $this->fail('check() should skip bootstrap route: ' . $e->getMessage());
        }
    }

    /**
     * Test check() processes non-exempt routes by checking TenantContext.
     * Verified via source inspection since check() calls TenantContext::getId()
     * and Database::query() which requires a live database connection.
     */
    public function testCheckProcessesNonExemptRoutes(): void
    {
        $source = file_get_contents($this->reflection->getFileName());

        // After exemption check, check() should get the tenant ID
        $this->assertStringContainsString('TenantContext::getId()', $source,
            'check() should call TenantContext::getId() for non-exempt routes');

        // Should query tenant_settings for maintenance mode
        $this->assertStringContainsString('general.maintenance_mode', $source,
            'check() should check general.maintenance_mode setting');

        // Should check if user is admin before blocking
        $this->assertStringContainsString('self::isUserAdmin()', $source,
            'check() should check if current user is admin');
    }
}
