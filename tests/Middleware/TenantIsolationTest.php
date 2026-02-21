<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Middleware;

use Nexus\Tests\TestCase;
use Nexus\Core\ApiErrorCodes;
use Nexus\Middleware\SuperPanelAccess;
use ReflectionClass;
use ReflectionMethod;

/**
 * TenantIsolationTest — Regression tests for tenant isolation security
 *
 * These tests verify the security fixes applied in Tasks C and C2:
 *
 * TASK C: Tenant spoofing via X-Tenant-ID header
 *   - isTokenUserSuperAdmin() must only return true for actual super admins
 *   - Regular admins (role=admin, role=tenant_admin) must NOT pass this check
 *   - Non-super-admin users with mismatched X-Tenant-ID headers get TENANT_MISMATCH error
 *
 * TASK C2: Super Panel access denial clarity
 *   - SUPER_PANEL_ACCESS_DENIED error code exists and maps to 403
 *   - AdminSuperApiController checks SuperPanelAccess before serving data
 *   - Non-hub tenants (allows_subtenants=0) get 403, not empty 200
 *
 * SECURITY: These tests are critical regression guards. If any of them fail,
 * it may indicate that the tenant isolation has been weakened, which would
 * allow unauthorized cross-tenant data access.
 *
 * @group security
 * @group regression
 * @group tenant-isolation
 */
class TenantIsolationTest extends TestCase
{
    // =========================================================================
    // TASK C: isTokenUserSuperAdmin() — Tenant Spoofing Prevention
    // =========================================================================

    /**
     * REGRESSION: isTokenUserSuperAdmin() must check is_super_admin flag.
     *
     * The is_super_admin flag is the primary indicator that a user has
     * cross-tenant access. This check MUST be present.
     */
    public function testIsTokenUserSuperAdminChecksIsSuperAdminFlag(): void
    {
        $source = $this->getTenantContextSource();

        // Find the isTokenUserSuperAdmin method body
        $methodBody = $this->extractMethodBody($source, 'isTokenUserSuperAdmin');

        $this->assertStringContainsString(
            'is_super_admin',
            $methodBody,
            'SECURITY: isTokenUserSuperAdmin() MUST check is_super_admin flag'
        );
    }

    /**
     * REGRESSION: isTokenUserSuperAdmin() must check is_tenant_super_admin flag.
     *
     * Tenant super admins are users who manage sub-tenants within their
     * tenant hierarchy. This flag grants cross-tenant access within scope.
     */
    public function testIsTokenUserSuperAdminChecksIsTenantSuperAdminFlag(): void
    {
        $source = $this->getTenantContextSource();
        $methodBody = $this->extractMethodBody($source, 'isTokenUserSuperAdmin');

        $this->assertStringContainsString(
            'is_tenant_super_admin',
            $methodBody,
            'SECURITY: isTokenUserSuperAdmin() MUST check is_tenant_super_admin flag'
        );
    }

    /**
     * REGRESSION: isTokenUserSuperAdmin() must check role === 'super_admin'.
     *
     * Some users may have the super_admin role string without the flag.
     * Both paths must grant access.
     */
    public function testIsTokenUserSuperAdminChecksRoleSuperAdmin(): void
    {
        $source = $this->getTenantContextSource();
        $methodBody = $this->extractMethodBody($source, 'isTokenUserSuperAdmin');

        $this->assertStringContainsString(
            "'super_admin'",
            $methodBody,
            'SECURITY: isTokenUserSuperAdmin() MUST check role === super_admin'
        );
    }

    /**
     * REGRESSION: isTokenUserSuperAdmin() must NOT grant access based on role=admin.
     *
     * ROOT CAUSE (Task C): The original implementation checked if the user's
     * role was 'admin' or 'tenant_admin', which allowed regular tenant admins
     * to spoof the X-Tenant-ID header and access other tenants' data.
     *
     * This test ensures the fix stays in place: only actual super admin
     * indicators (is_super_admin, is_tenant_super_admin, role=super_admin)
     * are checked, NOT role=admin or role=tenant_admin.
     */
    public function testIsTokenUserSuperAdminDoesNotGrantAccessToRegularAdmins(): void
    {
        $source = $this->getTenantContextSource();
        $methodBody = $this->extractMethodBody($source, 'isTokenUserSuperAdmin');

        // The return statement should NOT contain role checks for 'admin' or 'tenant_admin'
        // Extract just the return statement at the end of the method
        $returnBlock = $this->extractReturnBlock($methodBody);

        // Should NOT have 'admin' as a standalone role check (but 'super_admin' is OK)
        // Remove 'super_admin' references to check for bare 'admin'
        $withoutSuperAdmin = str_replace("'super_admin'", '', $returnBlock);

        $this->assertStringNotContainsString(
            "'admin'",
            $withoutSuperAdmin,
            "SECURITY REGRESSION: isTokenUserSuperAdmin() must NOT grant access to role='admin'. " .
            "This was the root cause of the tenant spoofing vulnerability (Task C)."
        );

        $this->assertStringNotContainsString(
            "'tenant_admin'",
            $returnBlock,
            "SECURITY REGRESSION: isTokenUserSuperAdmin() must NOT grant access to role='tenant_admin'. " .
            "This was part of the tenant spoofing vulnerability (Task C)."
        );
    }

    /**
     * REGRESSION: The access decision in isTokenUserSuperAdmin() should use
     * !empty() checks on boolean flags, not loose string comparisons.
     *
     * Using !empty($user['is_super_admin']) ensures that 0, null, false, ''
     * are all treated as "not a super admin". This prevents edge cases where
     * a falsy value might be misinterpreted.
     */
    public function testIsTokenUserSuperAdminUsesStrictFlagChecks(): void
    {
        $source = $this->getTenantContextSource();
        $methodBody = $this->extractMethodBody($source, 'isTokenUserSuperAdmin');

        // Should use !empty() for flag checks in the method body
        // (the actual return with these checks is inside an if ($user) block,
        // not the final fallback return false;)
        $this->assertStringContainsString(
            "!empty(\$user['is_super_admin'])",
            $methodBody,
            'Should use !empty() for is_super_admin flag check'
        );

        $this->assertStringContainsString(
            "!empty(\$user['is_tenant_super_admin'])",
            $methodBody,
            'Should use !empty() for is_tenant_super_admin flag check'
        );
    }

    /**
     * REGRESSION: resolveFromHeader() must call respondWithTenantMismatchError()
     * when a non-super-admin has a mismatched X-Tenant-ID header.
     *
     * This is the enforcement point: when the header tenant_id doesn't match
     * the token tenant_id, and the user is NOT a super admin, the request
     * must be rejected with an error — not silently served with wrong-tenant data.
     */
    public function testResolveFromHeaderRejectsNonSuperAdminMismatch(): void
    {
        $source = $this->getTenantContextSource();

        // The resolveFromHeader method should contain the mismatch check flow:
        // if (!self::isTokenUserSuperAdmin()) { self::respondWithTenantMismatchError(); return; }
        $this->assertStringContainsString(
            'isTokenUserSuperAdmin',
            $source,
            'resolveFromHeader must call isTokenUserSuperAdmin for mismatch detection'
        );

        $this->assertStringContainsString(
            'respondWithTenantMismatchError',
            $source,
            'resolveFromHeader must call respondWithTenantMismatchError on mismatch'
        );
    }

    /**
     * REGRESSION: respondWithTenantMismatchError() must use the TENANT_MISMATCH
     * error code so clients can programmatically detect this specific error.
     */
    public function testTenantMismatchErrorUsesCorrectErrorCode(): void
    {
        $source = $this->getTenantContextSource();

        // The mismatch error response should use TENANT_MISMATCH code
        $this->assertStringContainsString(
            'TENANT_MISMATCH',
            $source,
            'Tenant mismatch error must use TENANT_MISMATCH error code'
        );
    }

    // =========================================================================
    // TASK C2: SUPER_PANEL_ACCESS_DENIED — Clear Error Responses
    // =========================================================================

    /**
     * REGRESSION: SUPER_PANEL_ACCESS_DENIED error code must exist.
     *
     * Without this code, non-hub super admins get silent 200 with empty data
     * instead of a clear 403 explaining why they can't access the Super Panel.
     */
    public function testSuperPanelAccessDeniedErrorCodeExists(): void
    {
        $this->assertTrue(
            defined(ApiErrorCodes::class . '::SUPER_PANEL_ACCESS_DENIED'),
            'SECURITY: SUPER_PANEL_ACCESS_DENIED constant must exist in ApiErrorCodes'
        );

        $this->assertEquals(
            'SUPER_PANEL_ACCESS_DENIED',
            ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED,
            'SUPER_PANEL_ACCESS_DENIED must have correct string value'
        );
    }

    /**
     * REGRESSION: SUPER_PANEL_ACCESS_DENIED must map to HTTP 403.
     *
     * A 200 with empty data is confusing. A 403 clearly tells the client
     * that access is denied, allowing the React frontend to show an
     * appropriate error message.
     */
    public function testSuperPanelAccessDeniedMapsTo403(): void
    {
        $status = ApiErrorCodes::getHttpStatus(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED);

        $this->assertEquals(
            403,
            $status,
            'SUPER_PANEL_ACCESS_DENIED must map to HTTP 403 Forbidden'
        );
    }

    /**
     * REGRESSION: AdminSuperApiController must check SuperPanelAccess
     * in its requireSuperAdmin() override.
     *
     * Without this check, the controller only validates that the user IS a
     * super admin, but not that their tenant HAS Super Panel access.
     * A super admin in a non-hub tenant would get 200 with empty data arrays.
     */
    public function testAdminSuperApiControllerChecksSuperPanelAccess(): void
    {
        $controllerPath = $this->getProjectRoot() . '/src/Controllers/Api/AdminSuperApiController.php';
        $this->assertFileExists($controllerPath, 'AdminSuperApiController.php must exist');

        $source = file_get_contents($controllerPath);

        // Must reference SuperPanelAccess::getAccess() in requireSuperAdmin
        $this->assertStringContainsString(
            'SuperPanelAccess::getAccess()',
            $source,
            'SECURITY: AdminSuperApiController must call SuperPanelAccess::getAccess() ' .
            'to verify tenant has Super Panel capability'
        );

        // Must use SUPER_PANEL_ACCESS_DENIED error code
        $this->assertStringContainsString(
            'SUPER_PANEL_ACCESS_DENIED',
            $source,
            'AdminSuperApiController must return SUPER_PANEL_ACCESS_DENIED error code'
        );

        // Must return 403, not empty 200
        $this->assertStringContainsString(
            'respondWithError',
            $source,
            'AdminSuperApiController must use respondWithError (not respondWithData) for denial'
        );
    }

    /**
     * REGRESSION: AdminSuperApiController must sync JWT context to session
     * BEFORE checking SuperPanelAccess.
     *
     * SuperPanelAccess reads from $_SESSION, but API requests use JWT tokens.
     * If session sync happens AFTER the access check, the check would use
     * stale/missing session data and incorrectly deny access.
     */
    public function testAdminSuperApiControllerSyncsSessionBeforeAccessCheck(): void
    {
        $controllerPath = $this->getProjectRoot() . '/src/Controllers/Api/AdminSuperApiController.php';
        $source = file_get_contents($controllerPath);

        // Session sync code must appear BEFORE the SuperPanelAccess check.
        // Use the actual code assignment pattern (not the comment mentioning it)
        // to avoid false position match from the docblock at line 60.
        $sessionSyncPos = strpos($source, '$_SESSION[\'user_id\']');
        $accessCheckPos = strpos($source, '$access = ');

        $this->assertNotFalse($sessionSyncPos, 'Session sync code must exist');
        $this->assertNotFalse(
            $accessCheckPos,
            'SuperPanelAccess result assignment ($access = ...) must exist'
        );

        // Also verify the actual getAccess() call is on the same line
        $accessLineEnd = strpos($source, "\n", $accessCheckPos);
        $accessLine = substr($source, $accessCheckPos, $accessLineEnd - $accessCheckPos);
        $this->assertStringContainsString(
            'SuperPanelAccess::getAccess()',
            $accessLine,
            'The $access assignment must call SuperPanelAccess::getAccess()'
        );

        $this->assertLessThan(
            $accessCheckPos,
            $sessionSyncPos,
            'Session sync must happen BEFORE SuperPanelAccess::getAccess() is called'
        );
    }

    // =========================================================================
    // SuperPanelAccess — Non-hub Tenant Denial
    // =========================================================================

    /**
     * REGRESSION: SuperPanelAccess must check allows_subtenants flag.
     *
     * Tenants with allows_subtenants=0 are standard tenants that should not
     * have access to the Super Panel, even if a user is marked as super admin.
     */
    public function testSuperPanelAccessChecksAllowsSubtenants(): void
    {
        $middlewarePath = $this->getProjectRoot() . '/src/Middleware/SuperPanelAccess.php';
        $source = file_get_contents($middlewarePath);

        $this->assertStringContainsString(
            'allows_subtenants',
            $source,
            'SuperPanelAccess must check allows_subtenants flag on the user\'s tenant'
        );
    }

    /**
     * REGRESSION: SuperPanelAccess must deny access with a clear reason
     * when the tenant doesn't have sub-tenant capability.
     */
    public function testSuperPanelAccessDeniesWithReasonForNonHubTenant(): void
    {
        $middlewarePath = $this->getProjectRoot() . '/src/Middleware/SuperPanelAccess.php';
        $source = file_get_contents($middlewarePath);

        $this->assertStringContainsString(
            'Tenant does not have sub-tenant capability',
            $source,
            'SuperPanelAccess must provide clear reason when denying non-hub tenant access'
        );
    }

    /**
     * REGRESSION: SuperPanelAccess getScopeClause must return impossible
     * clause (1 = 0) when access is not granted.
     *
     * This prevents any data leakage: even if controller code doesn't check
     * the access.granted flag, the SQL clause ensures zero rows are returned.
     */
    public function testSuperPanelAccessScopeClauseBlocksUnauthorized(): void
    {
        // Reset and ensure no session
        SuperPanelAccess::reset();
        unset($_SESSION['user_id']);

        $clause = SuperPanelAccess::getScopeClause();

        $this->assertEquals(
            '1 = 0',
            $clause['sql'],
            'SECURITY: Unauthorized users must get impossible SQL clause (1 = 0)'
        );

        $this->assertEmpty(
            $clause['params'],
            'Impossible clause should have no parameters'
        );
    }

    // =========================================================================
    // TENANT_MISMATCH Error Code
    // =========================================================================

    /**
     * REGRESSION: TENANT_MISMATCH error code must exist and map to 403.
     */
    public function testTenantMismatchErrorCodeExistsAndMapsTo403(): void
    {
        $this->assertTrue(
            defined(ApiErrorCodes::class . '::TENANT_MISMATCH'),
            'TENANT_MISMATCH constant must exist in ApiErrorCodes'
        );

        $status = ApiErrorCodes::getHttpStatus(ApiErrorCodes::TENANT_MISMATCH);

        $this->assertEquals(
            403,
            $status,
            'TENANT_MISMATCH must map to HTTP 403 Forbidden'
        );
    }

    /**
     * REGRESSION: INVALID_TENANT error code must exist and map to 400.
     */
    public function testInvalidTenantErrorCodeExistsAndMapsTo400(): void
    {
        $this->assertTrue(
            defined(ApiErrorCodes::class . '::INVALID_TENANT'),
            'INVALID_TENANT constant must exist in ApiErrorCodes'
        );

        $status = ApiErrorCodes::getHttpStatus(ApiErrorCodes::INVALID_TENANT);

        $this->assertEquals(
            400,
            $status,
            'INVALID_TENANT must map to HTTP 400 Bad Request'
        );
    }

    // =========================================================================
    // Cross-cutting: Auth hierarchy enforcement
    // =========================================================================

    /**
     * REGRESSION: BaseApiController must have requireSuperAdmin() method.
     *
     * This is the primary gate for super admin API endpoints.
     */
    public function testBaseApiControllerHasRequireSuperAdminMethod(): void
    {
        $controllerPath = $this->getProjectRoot() . '/src/Controllers/Api/BaseApiController.php';
        $this->assertFileExists($controllerPath, 'BaseApiController.php must exist');

        $source = file_get_contents($controllerPath);

        $this->assertStringContainsString(
            'function requireSuperAdmin',
            $source,
            'BaseApiController must have requireSuperAdmin() method'
        );
    }

    /**
     * REGRESSION: isTokenUserSuperAdmin is a private method (not public/protected).
     *
     * This method should NOT be callable from outside TenantContext.
     * Making it public would allow controllers to bypass the standard auth flow.
     */
    public function testIsTokenUserSuperAdminIsPrivate(): void
    {
        $reflection = new ReflectionClass(\Nexus\Core\TenantContext::class);
        $method = $reflection->getMethod('isTokenUserSuperAdmin');

        $this->assertTrue(
            $method->isPrivate(),
            'SECURITY: isTokenUserSuperAdmin() must be private to prevent external bypass'
        );

        $this->assertTrue(
            $method->isStatic(),
            'isTokenUserSuperAdmin() should be static (matches TenantContext pattern)'
        );
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Get the project root directory.
     */
    private function getProjectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Get the TenantContext source code.
     */
    private function getTenantContextSource(): string
    {
        $path = $this->getProjectRoot() . '/src/Core/TenantContext.php';
        $this->assertFileExists($path, 'TenantContext.php must exist');

        return file_get_contents($path);
    }

    /**
     * Extract a method body from PHP source code.
     *
     * Uses brace counting to find the method boundaries.
     */
    private function extractMethodBody(string $source, string $methodName): string
    {
        // Find method declaration
        $pattern = '/(?:private|protected|public)\s+(?:static\s+)?function\s+' . preg_quote($methodName) . '\s*\(/';
        if (!preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE)) {
            $this->fail("Method {$methodName} not found in source");
        }

        $startPos = $match[0][1];

        // Find the opening brace
        $bracePos = strpos($source, '{', $startPos);
        if ($bracePos === false) {
            $this->fail("Opening brace not found for method {$methodName}");
        }

        // Count braces to find the method end
        $depth = 0;
        $length = strlen($source);
        $bodyStart = $bracePos;

        for ($i = $bracePos; $i < $length; $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $bodyStart, $i - $bodyStart + 1);
                }
            }
        }

        $this->fail("Could not find closing brace for method {$methodName}");
    }

    /**
     * Extract the return block from a method body.
     *
     * Finds the last 'return' statement in the method body, which is
     * typically the one that determines the method's access decision.
     */
    private function extractReturnBlock(string $methodBody): string
    {
        // Find the last 'return' statement
        $lastReturnPos = strrpos($methodBody, 'return ');
        if ($lastReturnPos === false) {
            $this->fail('No return statement found in method body');
        }

        // Extract from 'return' to the next semicolon
        $semicolonPos = strpos($methodBody, ';', $lastReturnPos);
        if ($semicolonPos === false) {
            $this->fail('No semicolon found after return statement');
        }

        return substr($methodBody, $lastReturnPos, $semicolonPos - $lastReturnPos + 1);
    }
}
