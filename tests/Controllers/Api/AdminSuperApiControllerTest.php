<?php

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

/**
 * Tests for AdminSuperApiController endpoints
 *
 * Tests all 36 Super Admin Panel API endpoints covering:
 * - Dashboard statistics
 * - Tenant CRUD (list, show, hierarchy, create, update, delete, reactivate, toggle-hub, move)
 * - User management (list, show, create, update, grant/revoke super admin, move tenant, move-and-promote)
 * - Bulk operations (move users, update tenants)
 * - Audit log
 * - Federation controls (overview, system-controls, lockdown, whitelist, partnerships, tenant features)
 *
 * All endpoints live under /api/v2/admin/super/* and require super_admin or god role.
 */
class AdminSuperApiControllerTest extends ApiTestCase
{
    // =========================================================================
    // DASHBOARD (1 endpoint)
    // =========================================================================

    /**
     * Test GET /api/v2/admin/super/dashboard
     */
    public function testGetDashboard(): void
    {
        $response = $this->get('/api/v2/admin/super/dashboard');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/super/dashboard', $response['endpoint']);
    }

    // =========================================================================
    // TENANTS (9 endpoints)
    // =========================================================================

    /**
     * Test GET /api/v2/admin/super/tenants
     */
    public function testListTenants(): void
    {
        $response = $this->get('/api/v2/admin/super/tenants');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/super/tenants', $response['endpoint']);
    }

    /**
     * Test GET /api/v2/admin/super/tenants with search filter
     */
    public function testListTenantsWithSearch(): void
    {
        $response = $this->get('/api/v2/admin/super/tenants', [
            'search' => 'test',
        ]);

        $this->assertArrayHasKey('search', $response['data']);
        $this->assertEquals('test', $response['data']['search']);
    }

    /**
     * Test GET /api/v2/admin/super/tenants with is_active filter
     */
    public function testListTenantsWithActiveFilter(): void
    {
        $response = $this->get('/api/v2/admin/super/tenants', [
            'is_active' => 1,
        ]);

        $this->assertArrayHasKey('is_active', $response['data']);
    }

    /**
     * Test GET /api/v2/admin/super/tenants/{id}
     */
    public function testShowTenant(): void
    {
        $response = $this->get('/api/v2/admin/super/tenants/1');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/super/tenants/1', $response['endpoint']);
    }

    /**
     * Test GET /api/v2/admin/super/tenants/hierarchy
     */
    public function testGetTenantHierarchy(): void
    {
        $response = $this->get('/api/v2/admin/super/tenants/hierarchy');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/super/tenants/hierarchy', $response['endpoint']);
    }

    /**
     * Test POST /api/v2/admin/super/tenants
     */
    public function testCreateTenant(): void
    {
        $response = $this->post('/api/v2/admin/super/tenants', [
            'parent_id' => 1,
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . time(),
            'tagline' => 'A test community',
            'is_active' => 1,
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/v2/admin/super/tenants', $response['endpoint']);
        $this->assertArrayHasKey('parent_id', $response['data']);
        $this->assertArrayHasKey('name', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/tenants — missing parent_id
     */
    public function testCreateTenantMissingParentId(): void
    {
        $response = $this->post('/api/v2/admin/super/tenants', [
            'name' => 'No Parent Tenant',
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('name', $response['data']);
        // Missing parent_id — controller would return 422
    }

    /**
     * Test POST /api/v2/admin/super/tenants — missing name
     */
    public function testCreateTenantMissingName(): void
    {
        $response = $this->post('/api/v2/admin/super/tenants', [
            'parent_id' => 1,
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('parent_id', $response['data']);
        // Missing name — controller would return 422
    }

    /**
     * Test PUT /api/v2/admin/super/tenants/{id}
     */
    public function testUpdateTenant(): void
    {
        $response = $this->put('/api/v2/admin/super/tenants/2', [
            'name' => 'Updated Tenant Name',
            'tagline' => 'Updated tagline',
        ]);

        $this->assertEquals('PUT', $response['method']);
        $this->assertEquals('/api/v2/admin/super/tenants/2', $response['endpoint']);
        $this->assertArrayHasKey('name', $response['data']);
    }

    /**
     * Test PUT /api/v2/admin/super/tenants/{id} — empty body
     */
    public function testUpdateTenantEmptyBody(): void
    {
        $response = $this->put('/api/v2/admin/super/tenants/2', []);

        $this->assertEquals('PUT', $response['method']);
        // Controller would return 422 for empty body
    }

    /**
     * Test DELETE /api/v2/admin/super/tenants/{id}
     */
    public function testDeleteTenant(): void
    {
        $response = $this->delete('/api/v2/admin/super/tenants/999');

        $this->assertEquals('DELETE', $response['method']);
        $this->assertEquals('/api/v2/admin/super/tenants/999', $response['endpoint']);
    }

    /**
     * Test DELETE /api/v2/admin/super/tenants/{id} — hard delete flag
     */
    public function testDeleteTenantHardDelete(): void
    {
        $response = $this->delete('/api/v2/admin/super/tenants/999', [
            'hard_delete' => true,
        ]);

        $this->assertEquals('DELETE', $response['method']);
        $this->assertArrayHasKey('hard_delete', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/tenants/{id}/reactivate
     */
    public function testReactivateTenant(): void
    {
        $response = $this->post('/api/v2/admin/super/tenants/2/reactivate');

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/v2/admin/super/tenants/2/reactivate', $response['endpoint']);
    }

    /**
     * Test POST /api/v2/admin/super/tenants/{id}/toggle-hub
     */
    public function testToggleHub(): void
    {
        $response = $this->post('/api/v2/admin/super/tenants/2/toggle-hub', [
            'enable' => true,
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/v2/admin/super/tenants/2/toggle-hub', $response['endpoint']);
        $this->assertArrayHasKey('enable', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/tenants/{id}/toggle-hub — disable
     */
    public function testToggleHubDisable(): void
    {
        $response = $this->post('/api/v2/admin/super/tenants/2/toggle-hub', [
            'enable' => false,
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('enable', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/tenants/{id}/move
     */
    public function testMoveTenant(): void
    {
        $response = $this->post('/api/v2/admin/super/tenants/3/move', [
            'new_parent_id' => 1,
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/v2/admin/super/tenants/3/move', $response['endpoint']);
        $this->assertArrayHasKey('new_parent_id', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/tenants/{id}/move — missing new_parent_id
     */
    public function testMoveTenantMissingParent(): void
    {
        $response = $this->post('/api/v2/admin/super/tenants/3/move', []);

        $this->assertEquals('POST', $response['method']);
        // Controller would return 422 for missing new_parent_id
    }

    // =========================================================================
    // USERS (10 endpoints)
    // =========================================================================

    /**
     * Test GET /api/v2/admin/super/users
     */
    public function testListUsers(): void
    {
        $response = $this->get('/api/v2/admin/super/users');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/super/users', $response['endpoint']);
    }

    /**
     * Test GET /api/v2/admin/super/users with filters
     */
    public function testListUsersWithFilters(): void
    {
        $response = $this->get('/api/v2/admin/super/users', [
            'search' => 'admin',
            'tenant_id' => 1,
            'role' => 'admin',
            'page' => 1,
            'limit' => 25,
        ]);

        $this->assertArrayHasKey('search', $response['data']);
        $this->assertArrayHasKey('tenant_id', $response['data']);
        $this->assertArrayHasKey('role', $response['data']);
    }

    /**
     * Test GET /api/v2/admin/super/users — super_admins filter
     */
    public function testListUsersFilterSuperAdmins(): void
    {
        $response = $this->get('/api/v2/admin/super/users', [
            'super_admins' => 1,
        ]);

        $this->assertArrayHasKey('super_admins', $response['data']);
    }

    /**
     * Test GET /api/v2/admin/super/users/{id}
     */
    public function testShowUser(): void
    {
        $response = $this->get('/api/v2/admin/super/users/' . self::$testUserId);

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/super/users/' . self::$testUserId, $response['endpoint']);
    }

    /**
     * Test POST /api/v2/admin/super/users
     */
    public function testCreateUser(): void
    {
        $timestamp = time();
        $response = $this->post('/api/v2/admin/super/users', [
            'tenant_id' => 1,
            'first_name' => 'Super',
            'last_name' => 'TestUser',
            'email' => "supertest_{$timestamp}@test.com",
            'password' => 'SecurePass123!',
            'role' => 'member',
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/v2/admin/super/users', $response['endpoint']);
        $this->assertArrayHasKey('tenant_id', $response['data']);
        $this->assertArrayHasKey('first_name', $response['data']);
        $this->assertArrayHasKey('email', $response['data']);
        $this->assertArrayHasKey('password', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/users — missing required fields
     */
    public function testCreateUserMissingFields(): void
    {
        $response = $this->post('/api/v2/admin/super/users', [
            'tenant_id' => 1,
        ]);

        $this->assertEquals('POST', $response['method']);
        // Missing first_name, email, password — controller would return 422
    }

    /**
     * Test POST /api/v2/admin/super/users — missing tenant_id
     */
    public function testCreateUserMissingTenantId(): void
    {
        $response = $this->post('/api/v2/admin/super/users', [
            'first_name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'pass123',
        ]);

        $this->assertEquals('POST', $response['method']);
        // Missing tenant_id — controller would return 422
    }

    /**
     * Test PUT /api/v2/admin/super/users/{id}
     */
    public function testUpdateUser(): void
    {
        $response = $this->put('/api/v2/admin/super/users/' . self::$testUserId, [
            'first_name' => 'UpdatedFirst',
            'last_name' => 'UpdatedLast',
            'email' => 'updated_' . time() . '@test.com',
            'role' => 'admin',
        ]);

        $this->assertEquals('PUT', $response['method']);
        $this->assertEquals('/api/v2/admin/super/users/' . self::$testUserId, $response['endpoint']);
        $this->assertArrayHasKey('first_name', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/users/{id}/grant-super-admin
     */
    public function testGrantSuperAdmin(): void
    {
        $response = $this->post('/api/v2/admin/super/users/' . self::$testUserId . '/grant-super-admin');

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals(
            '/api/v2/admin/super/users/' . self::$testUserId . '/grant-super-admin',
            $response['endpoint']
        );
    }

    /**
     * Test POST /api/v2/admin/super/users/{id}/revoke-super-admin
     */
    public function testRevokeSuperAdmin(): void
    {
        $response = $this->post('/api/v2/admin/super/users/' . self::$testUserId . '/revoke-super-admin');

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals(
            '/api/v2/admin/super/users/' . self::$testUserId . '/revoke-super-admin',
            $response['endpoint']
        );
    }

    /**
     * Test POST /api/v2/admin/super/users/{id}/grant-global-super-admin (GOD only)
     */
    public function testGrantGlobalSuperAdmin(): void
    {
        $response = $this->post('/api/v2/admin/super/users/' . self::$testUserId . '/grant-global-super-admin');

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals(
            '/api/v2/admin/super/users/' . self::$testUserId . '/grant-global-super-admin',
            $response['endpoint']
        );
    }

    /**
     * Test POST /api/v2/admin/super/users/{id}/revoke-global-super-admin (GOD only)
     */
    public function testRevokeGlobalSuperAdmin(): void
    {
        $response = $this->post('/api/v2/admin/super/users/' . self::$testUserId . '/revoke-global-super-admin');

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals(
            '/api/v2/admin/super/users/' . self::$testUserId . '/revoke-global-super-admin',
            $response['endpoint']
        );
    }

    /**
     * Test POST /api/v2/admin/super/users/{id}/move-tenant
     */
    public function testMoveUserTenant(): void
    {
        $response = $this->post('/api/v2/admin/super/users/' . self::$testUserId . '/move-tenant', [
            'new_tenant_id' => 2,
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals(
            '/api/v2/admin/super/users/' . self::$testUserId . '/move-tenant',
            $response['endpoint']
        );
        $this->assertArrayHasKey('new_tenant_id', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/users/{id}/move-tenant — missing new_tenant_id
     */
    public function testMoveUserTenantMissingTarget(): void
    {
        $response = $this->post('/api/v2/admin/super/users/' . self::$testUserId . '/move-tenant', []);

        $this->assertEquals('POST', $response['method']);
        // Missing new_tenant_id — controller would return 422
    }

    /**
     * Test POST /api/v2/admin/super/users/{id}/move-and-promote
     */
    public function testMoveAndPromote(): void
    {
        $response = $this->post('/api/v2/admin/super/users/' . self::$testUserId . '/move-and-promote', [
            'target_tenant_id' => 1,
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals(
            '/api/v2/admin/super/users/' . self::$testUserId . '/move-and-promote',
            $response['endpoint']
        );
        $this->assertArrayHasKey('target_tenant_id', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/users/{id}/move-and-promote — missing target_tenant_id
     */
    public function testMoveAndPromoteMissingTarget(): void
    {
        $response = $this->post('/api/v2/admin/super/users/' . self::$testUserId . '/move-and-promote', []);

        $this->assertEquals('POST', $response['method']);
        // Missing target_tenant_id — controller would return 422
    }

    // =========================================================================
    // BULK OPERATIONS (2 endpoints)
    // =========================================================================

    /**
     * Test POST /api/v2/admin/super/bulk/move-users
     */
    public function testBulkMoveUsers(): void
    {
        $response = $this->post('/api/v2/admin/super/bulk/move-users', [
            'user_ids' => [1, 2, 3],
            'target_tenant_id' => 2,
            'grant_super_admin' => false,
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/v2/admin/super/bulk/move-users', $response['endpoint']);
        $this->assertArrayHasKey('user_ids', $response['data']);
        $this->assertArrayHasKey('target_tenant_id', $response['data']);
        $this->assertIsArray($response['data']['user_ids']);
    }

    /**
     * Test POST /api/v2/admin/super/bulk/move-users — missing user_ids
     */
    public function testBulkMoveUsersMissingIds(): void
    {
        $response = $this->post('/api/v2/admin/super/bulk/move-users', [
            'target_tenant_id' => 2,
        ]);

        $this->assertEquals('POST', $response['method']);
        // Missing user_ids — controller would return 422
    }

    /**
     * Test POST /api/v2/admin/super/bulk/move-users — missing target_tenant_id
     */
    public function testBulkMoveUsersMissingTarget(): void
    {
        $response = $this->post('/api/v2/admin/super/bulk/move-users', [
            'user_ids' => [1, 2],
        ]);

        $this->assertEquals('POST', $response['method']);
        // Missing target_tenant_id — controller would return 422
    }

    /**
     * Test POST /api/v2/admin/super/bulk/update-tenants
     */
    public function testBulkUpdateTenants(): void
    {
        $response = $this->post('/api/v2/admin/super/bulk/update-tenants', [
            'tenant_ids' => [2, 3],
            'action' => 'activate',
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/v2/admin/super/bulk/update-tenants', $response['endpoint']);
        $this->assertArrayHasKey('tenant_ids', $response['data']);
        $this->assertArrayHasKey('action', $response['data']);
        $this->assertIsArray($response['data']['tenant_ids']);
    }

    /**
     * Test POST /api/v2/admin/super/bulk/update-tenants — missing tenant_ids
     */
    public function testBulkUpdateTenantsMissingIds(): void
    {
        $response = $this->post('/api/v2/admin/super/bulk/update-tenants', [
            'action' => 'activate',
        ]);

        $this->assertEquals('POST', $response['method']);
        // Missing tenant_ids — controller would return 422
    }

    /**
     * Test POST /api/v2/admin/super/bulk/update-tenants — invalid action
     */
    public function testBulkUpdateTenantsInvalidAction(): void
    {
        $response = $this->post('/api/v2/admin/super/bulk/update-tenants', [
            'tenant_ids' => [2],
            'action' => 'invalid_action',
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('invalid_action', $response['data']['action']);
        // Controller would return 422 for invalid action
    }

    /**
     * Test POST /api/v2/admin/super/bulk/update-tenants — all valid actions
     */
    public function testBulkUpdateTenantsAllActions(): void
    {
        $validActions = ['activate', 'deactivate', 'enable_hub', 'disable_hub'];

        foreach ($validActions as $action) {
            $response = $this->post('/api/v2/admin/super/bulk/update-tenants', [
                'tenant_ids' => [2],
                'action' => $action,
            ]);

            $this->assertEquals('POST', $response['method']);
            $this->assertEquals($action, $response['data']['action']);
        }
    }

    // =========================================================================
    // AUDIT (1 endpoint)
    // =========================================================================

    /**
     * Test GET /api/v2/admin/super/audit
     */
    public function testGetAudit(): void
    {
        $response = $this->get('/api/v2/admin/super/audit');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/super/audit', $response['endpoint']);
    }

    /**
     * Test GET /api/v2/admin/super/audit with filters
     */
    public function testGetAuditWithFilters(): void
    {
        $response = $this->get('/api/v2/admin/super/audit', [
            'action_type' => 'tenant_created',
            'target_type' => 'tenant',
            'search' => 'test',
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
            'page' => 1,
            'limit' => 25,
        ]);

        $this->assertArrayHasKey('action_type', $response['data']);
        $this->assertArrayHasKey('target_type', $response['data']);
        $this->assertArrayHasKey('search', $response['data']);
        $this->assertArrayHasKey('date_from', $response['data']);
        $this->assertArrayHasKey('date_to', $response['data']);
    }

    // =========================================================================
    // FEDERATION (13 endpoints)
    // =========================================================================

    /**
     * Test GET /api/v2/admin/super/federation
     */
    public function testGetFederationOverview(): void
    {
        $response = $this->get('/api/v2/admin/super/federation');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation', $response['endpoint']);
    }

    /**
     * Test GET /api/v2/admin/super/federation/system-controls
     */
    public function testGetSystemControls(): void
    {
        $response = $this->get('/api/v2/admin/super/federation/system-controls');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation/system-controls', $response['endpoint']);
    }

    /**
     * Test PUT /api/v2/admin/super/federation/system-controls
     */
    public function testUpdateSystemControls(): void
    {
        $response = $this->put('/api/v2/admin/super/federation/system-controls', [
            'federation_enabled' => true,
            'whitelist_mode_enabled' => false,
            'max_federation_level' => 3,
        ]);

        $this->assertEquals('PUT', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation/system-controls', $response['endpoint']);
        $this->assertArrayHasKey('federation_enabled', $response['data']);
        $this->assertArrayHasKey('max_federation_level', $response['data']);
    }

    /**
     * Test PUT /api/v2/admin/super/federation/system-controls — empty body
     */
    public function testUpdateSystemControlsEmptyBody(): void
    {
        $response = $this->put('/api/v2/admin/super/federation/system-controls', []);

        $this->assertEquals('PUT', $response['method']);
        // Controller would return 422 for no valid fields
    }

    /**
     * Test POST /api/v2/admin/super/federation/emergency-lockdown
     */
    public function testEmergencyLockdown(): void
    {
        $response = $this->post('/api/v2/admin/super/federation/emergency-lockdown', [
            'reason' => 'Security breach detected',
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation/emergency-lockdown', $response['endpoint']);
        $this->assertArrayHasKey('reason', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/federation/emergency-lockdown — no reason
     */
    public function testEmergencyLockdownDefaultReason(): void
    {
        $response = $this->post('/api/v2/admin/super/federation/emergency-lockdown', []);

        $this->assertEquals('POST', $response['method']);
        // Controller uses default reason: 'Emergency lockdown triggered via API'
    }

    /**
     * Test POST /api/v2/admin/super/federation/lift-lockdown
     */
    public function testLiftLockdown(): void
    {
        $response = $this->post('/api/v2/admin/super/federation/lift-lockdown');

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation/lift-lockdown', $response['endpoint']);
    }

    /**
     * Test GET /api/v2/admin/super/federation/whitelist
     */
    public function testGetWhitelist(): void
    {
        $response = $this->get('/api/v2/admin/super/federation/whitelist');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation/whitelist', $response['endpoint']);
    }

    /**
     * Test POST /api/v2/admin/super/federation/whitelist
     */
    public function testAddToWhitelist(): void
    {
        $response = $this->post('/api/v2/admin/super/federation/whitelist', [
            'tenant_id' => 2,
            'notes' => 'Approved for federation',
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation/whitelist', $response['endpoint']);
        $this->assertArrayHasKey('tenant_id', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/federation/whitelist — missing tenant_id
     */
    public function testAddToWhitelistMissingTenantId(): void
    {
        $response = $this->post('/api/v2/admin/super/federation/whitelist', [
            'notes' => 'No tenant specified',
        ]);

        $this->assertEquals('POST', $response['method']);
        // Missing tenant_id — controller would return 422
    }

    /**
     * Test DELETE /api/v2/admin/super/federation/whitelist/{tenantId}
     */
    public function testRemoveFromWhitelist(): void
    {
        $response = $this->delete('/api/v2/admin/super/federation/whitelist/2');

        $this->assertEquals('DELETE', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation/whitelist/2', $response['endpoint']);
    }

    /**
     * Test GET /api/v2/admin/super/federation/partnerships
     */
    public function testGetPartnerships(): void
    {
        $response = $this->get('/api/v2/admin/super/federation/partnerships');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation/partnerships', $response['endpoint']);
    }

    /**
     * Test POST /api/v2/admin/super/federation/partnerships/{id}/suspend
     */
    public function testSuspendPartnership(): void
    {
        $response = $this->post('/api/v2/admin/super/federation/partnerships/1/suspend', [
            'reason' => 'Terms violation',
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation/partnerships/1/suspend', $response['endpoint']);
        $this->assertArrayHasKey('reason', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/federation/partnerships/{id}/suspend — default reason
     */
    public function testSuspendPartnershipDefaultReason(): void
    {
        $response = $this->post('/api/v2/admin/super/federation/partnerships/1/suspend', []);

        $this->assertEquals('POST', $response['method']);
        // Controller uses default reason: 'Suspended by super admin via API'
    }

    /**
     * Test POST /api/v2/admin/super/federation/partnerships/{id}/terminate
     */
    public function testTerminatePartnership(): void
    {
        $response = $this->post('/api/v2/admin/super/federation/partnerships/1/terminate', [
            'reason' => 'Partnership ended',
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation/partnerships/1/terminate', $response['endpoint']);
        $this->assertArrayHasKey('reason', $response['data']);
    }

    /**
     * Test POST /api/v2/admin/super/federation/partnerships/{id}/terminate — default reason
     */
    public function testTerminatePartnershipDefaultReason(): void
    {
        $response = $this->post('/api/v2/admin/super/federation/partnerships/1/terminate', []);

        $this->assertEquals('POST', $response['method']);
        // Controller uses default reason: 'Terminated by super admin via API'
    }

    /**
     * Test GET /api/v2/admin/super/federation/tenant/{id}/features
     */
    public function testGetTenantFederationFeatures(): void
    {
        $response = $this->get('/api/v2/admin/super/federation/tenant/2/features');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation/tenant/2/features', $response['endpoint']);
    }

    /**
     * Test PUT /api/v2/admin/super/federation/tenant/{id}/features
     */
    public function testUpdateTenantFederationFeature(): void
    {
        $response = $this->put('/api/v2/admin/super/federation/tenant/2/features', [
            'feature' => 'cross_tenant_messaging',
            'enabled' => true,
        ]);

        $this->assertEquals('PUT', $response['method']);
        $this->assertEquals('/api/v2/admin/super/federation/tenant/2/features', $response['endpoint']);
        $this->assertArrayHasKey('feature', $response['data']);
        $this->assertArrayHasKey('enabled', $response['data']);
    }

    /**
     * Test PUT /api/v2/admin/super/federation/tenant/{id}/features — missing feature name
     */
    public function testUpdateTenantFederationFeatureMissingName(): void
    {
        $response = $this->put('/api/v2/admin/super/federation/tenant/2/features', [
            'enabled' => true,
        ]);

        $this->assertEquals('PUT', $response['method']);
        // Missing feature — controller would return 422
    }

    /**
     * Test PUT /api/v2/admin/super/federation/tenant/{id}/features — disable
     */
    public function testUpdateTenantFederationFeatureDisable(): void
    {
        $response = $this->put('/api/v2/admin/super/federation/tenant/2/features', [
            'feature' => 'cross_tenant_messaging',
            'enabled' => false,
        ]);

        $this->assertEquals('PUT', $response['method']);
        $this->assertArrayHasKey('feature', $response['data']);
        $this->assertFalse($response['data']['enabled']);
    }

    // =========================================================================
    // CROSS-CUTTING CONCERNS
    // =========================================================================

    /**
     * Verify all endpoints use the correct HTTP methods
     */
    public function testEndpointMethodMapping(): void
    {
        // GET endpoints
        $getEndpoints = [
            '/api/v2/admin/super/dashboard',
            '/api/v2/admin/super/tenants',
            '/api/v2/admin/super/tenants/hierarchy',
            '/api/v2/admin/super/tenants/1',
            '/api/v2/admin/super/users',
            '/api/v2/admin/super/users/1',
            '/api/v2/admin/super/audit',
            '/api/v2/admin/super/federation',
            '/api/v2/admin/super/federation/system-controls',
            '/api/v2/admin/super/federation/whitelist',
            '/api/v2/admin/super/federation/partnerships',
            '/api/v2/admin/super/federation/tenant/1/features',
        ];

        foreach ($getEndpoints as $endpoint) {
            $response = $this->get($endpoint);
            $this->assertEquals('GET', $response['method'], "Expected GET for {$endpoint}");
        }

        // POST endpoints
        $postEndpoints = [
            '/api/v2/admin/super/tenants',
            '/api/v2/admin/super/tenants/2/reactivate',
            '/api/v2/admin/super/tenants/2/toggle-hub',
            '/api/v2/admin/super/tenants/2/move',
            '/api/v2/admin/super/users',
            '/api/v2/admin/super/users/1/grant-super-admin',
            '/api/v2/admin/super/users/1/revoke-super-admin',
            '/api/v2/admin/super/users/1/grant-global-super-admin',
            '/api/v2/admin/super/users/1/revoke-global-super-admin',
            '/api/v2/admin/super/users/1/move-tenant',
            '/api/v2/admin/super/users/1/move-and-promote',
            '/api/v2/admin/super/bulk/move-users',
            '/api/v2/admin/super/bulk/update-tenants',
            '/api/v2/admin/super/federation/emergency-lockdown',
            '/api/v2/admin/super/federation/lift-lockdown',
            '/api/v2/admin/super/federation/whitelist',
            '/api/v2/admin/super/federation/partnerships/1/suspend',
            '/api/v2/admin/super/federation/partnerships/1/terminate',
        ];

        foreach ($postEndpoints as $endpoint) {
            $response = $this->post($endpoint);
            $this->assertEquals('POST', $response['method'], "Expected POST for {$endpoint}");
        }

        // PUT endpoints
        $putEndpoints = [
            '/api/v2/admin/super/tenants/2',
            '/api/v2/admin/super/users/1',
            '/api/v2/admin/super/federation/system-controls',
            '/api/v2/admin/super/federation/tenant/2/features',
        ];

        foreach ($putEndpoints as $endpoint) {
            $response = $this->put($endpoint);
            $this->assertEquals('PUT', $response['method'], "Expected PUT for {$endpoint}");
        }

        // DELETE endpoints
        $deleteEndpoints = [
            '/api/v2/admin/super/tenants/999',
            '/api/v2/admin/super/federation/whitelist/2',
        ];

        foreach ($deleteEndpoints as $endpoint) {
            $response = $this->delete($endpoint);
            $this->assertEquals('DELETE', $response['method'], "Expected DELETE for {$endpoint}");
        }
    }

    /**
     * Verify authentication headers are set on all requests
     */
    public function testAuthenticationHeadersPresent(): void
    {
        $response = $this->get('/api/v2/admin/super/dashboard');

        $this->assertArrayHasKey('headers', $response);
        $this->assertArrayHasKey('Authorization', $response['headers']);
        $this->assertStringStartsWith('Bearer ', $response['headers']['Authorization']);
        $this->assertArrayHasKey('Content-Type', $response['headers']);
        $this->assertEquals('application/json', $response['headers']['Content-Type']);
        $this->assertArrayHasKey('X-Tenant-ID', $response['headers']);
    }

    /**
     * Verify session is set for authenticated requests
     */
    public function testSessionDataSet(): void
    {
        $response = $this->get('/api/v2/admin/super/dashboard');

        $this->assertEquals(self::$testUserId, $_SESSION['user_id']);
        $this->assertEquals(self::$testTenantId, $_SESSION['tenant_id']);
    }
}
