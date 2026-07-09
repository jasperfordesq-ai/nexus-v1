<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Security audit tests for admin controllers.
 *
 * These tests verify that critical security boundaries are enforced:
 * - Tenant isolation (cross-tenant IDOR prevention)
 * - Privilege escalation prevention
 * - Input validation on sensitive operations
 */
class AdminSecurityAuditTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // SEC-001: setSuperAdmin() cross-tenant IDOR
    //
    // An admin from tenant 2 should NOT be able to promote a user
    // from tenant 999 to tenant super admin, because the query
    // lacks tenant_id scoping.
    // ================================================================

    public function test_set_super_admin_rejects_cross_tenant_user(): void
    {
        // Create an admin (super admin) in tenant 2
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'is_super_admin' => true,
            'is_tenant_super_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        // Create a user in tenant 999 (different tenant)
        $otherTenantUser = User::factory()->forTenant(999)->create();

        // Admin of tenant 2 tries to promote a user in tenant 999
        $response = $this->apiPut('/v2/admin/users/' . $otherTenantUser->id . '/super-admin', [
            'grant' => true,
        ]);

        // Should be 404 (user not found in this tenant), NOT 200
        $this->assertNotEquals(200, $response->getStatusCode(),
            'SEC-001: setSuperAdmin allowed cross-tenant user modification (IDOR)');

        // Verify user was NOT promoted
        $otherTenantUser->refresh();
        $this->assertFalse((bool) $otherTenantUser->is_tenant_super_admin,
            'SEC-001: Cross-tenant user was promoted to super admin');
    }

    // ================================================================
    // SEC-002: setGlobalSuperAdmin() cross-tenant IDOR
    //
    // A god-level admin from tenant 2 should NOT be able to grant
    // global super admin to a user from tenant 999 without tenant
    // scoping checks.
    // ================================================================

    public function test_set_global_super_admin_rejects_cross_tenant_user(): void
    {
        // Create a god-level admin in tenant 2
        $godAdmin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'is_super_admin' => true,
            'is_tenant_super_admin' => true,
            'is_god' => true,
        ]);
        Sanctum::actingAs($godAdmin);

        // Create a user in tenant 999
        $otherTenantUser = User::factory()->forTenant(999)->create();

        // God admin of tenant 2 tries to grant global super admin to user in tenant 999
        $response = $this->apiPut('/v2/admin/users/' . $otherTenantUser->id . '/global-super-admin', [
            'grant' => true,
        ]);

        // Should be 404, NOT 200
        $this->assertNotEquals(200, $response->getStatusCode(),
            'SEC-002: setGlobalSuperAdmin allowed cross-tenant user modification (IDOR)');

        // Verify user was NOT promoted
        $otherTenantUser->refresh();
        $this->assertFalse((bool) $otherTenantUser->is_super_admin,
            'SEC-002: Cross-tenant user was granted global super admin');
    }

    // ================================================================
    // SEC-003: AdminSettingsController::update() unrestricted key injection
    //
    // An admin should NOT be able to set arbitrary keys like
    // 'maintenance_mode' via the settings update endpoint without
    // an allowlist.
    // ================================================================

    // SEC-003 (RESOLVED 2026-06-21): maintenance_mode now requires a super-admin.
    // Decision: only super-admins may take a community offline. A regular delegated
    // tenant admin is rejected with 403; the community's own super-admin (or a platform
    // super-admin) is allowed. Enforced via SUPER_ADMIN_ONLY_KEYS + requireSuperAdmin()
    // in AdminConfigController::updateSettings.

    public function test_settings_update_maintenance_mode_rejects_regular_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'is_super_admin' => false,
            'is_tenant_super_admin' => false,
            'is_god' => false,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/settings', [
            'maintenance_mode' => '1',
        ]);

        $this->assertSame(403, $response->getStatusCode(),
            'SEC-003: a regular (non-super) admin must receive 403 when setting maintenance_mode.');
    }

    public function test_settings_update_maintenance_mode_allows_tenant_super_admin(): void
    {
        $superAdmin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'is_super_admin' => false,
            'is_tenant_super_admin' => true,
            'is_god' => false,
        ]);
        Sanctum::actingAs($superAdmin);

        $response = $this->apiPut('/v2/admin/settings', [
            'maintenance_mode' => '1',
        ]);

        $this->assertSame(200, $response->getStatusCode(),
            'SEC-003: a tenant super-admin must be allowed to set maintenance_mode for their community.');
    }

    // ================================================================
    // SEC-004: AdminFederationController::timebanks() leaks all tenants
    //
    // The timebanks endpoint should not expose ALL tenants in the
    // system to a regular admin.
    // ================================================================

    // SEC-004 (RESOLVED 2026-06-21): the unscoped AdminFederationController::timebanks()
    // method — which ran `SELECT id,name,slug,domain FROM tenants WHERE is_active=1` with
    // no tenant scope, gated only by requireAdmin() — was deleted. It was unrouted dead
    // code that would have leaked the full tenant directory had a route ever been wired.
    // The live federation directory (GET /v1/federation/timebanks) is partnership-scoped
    // + fedAuth-gated.

    public function test_federation_timebanks_admin_endpoint_does_not_exist(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/timebanks');

        $this->assertSame(404, $response->getStatusCode(),
            'SEC-004: the unscoped admin timebanks endpoint must not exist (dead code removed).');
    }

    // ================================================================
    // SEC-005: impersonate() requires super-admin, not just admin
    //
    // A regular admin should NOT be able to impersonate users.
    // Only super admins should have this privilege.
    // ================================================================

    public function test_impersonate_requires_super_admin_privilege(): void
    {
        // Create a regular admin (not super admin)
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'is_super_admin' => false,
            'is_tenant_super_admin' => false,
        ]);
        Sanctum::actingAs($admin);

        $targetUser = User::factory()->forTenant($this->testTenantId)->create();

        $response = $this->apiPost('/v2/admin/users/' . $targetUser->id . '/impersonate');

        // The /v2/admin/users/{id}/impersonate route sits behind the super-admin
        // middleware (EnsureIsSuperAdmin), which rejects a regular/tenant admin with
        // 403 before the controller runs — not a vague "any non-200".
        $this->assertSame(403, $response->getStatusCode(),
            'SEC-005: a regular admin must receive 403 (impersonation is super-admin only).');
    }

    // ================================================================
    // SEC-006: Information disclosure via exception messages
    //
    // Error responses should not leak internal exception details.
    // ================================================================

    public function test_badge_award_does_not_leak_exception_details(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        // Try to award a non-existent badge to trigger an error
        $response = $this->apiPost('/v2/admin/users/' . $user->id . '/badges', [
            'badge_slug' => 'nonexistent_badge_that_does_not_exist_' . time(),
        ]);

        // Assert response was received regardless of status code
        $this->assertNotNull($response->getStatusCode(),
            'SEC-006: Badge award endpoint should return a response.');
        if ($response->getStatusCode() === 500) {
            $body = $response->json();
            $errorMessage = $body['errors'][0]['message'] ?? '';
            // Check if internal exception details are exposed
            $this->assertStringNotContainsString('SQL', $errorMessage,
                'SEC-006: Error response leaks SQL details');
            $this->assertStringNotContainsString('Stack trace', $errorMessage,
                'SEC-006: Error response leaks stack trace');
        }
    }

    // ================================================================
    // SEC-007: Admin user update role field allows privilege escalation
    //
    // The update() method accepts 'role' from input. Verify an admin
    // cannot set role to 'super_admin' or 'god' to escalate privileges.
    // ================================================================

    public function test_admin_update_user_cannot_set_super_admin_role(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'is_super_admin' => false,
            'is_tenant_super_admin' => false,
        ]);
        Sanctum::actingAs($admin);

        $targetUser = User::factory()->forTenant($this->testTenantId)->create();

        // Try to escalate target user to super_admin role via update
        $response = $this->apiPut('/v2/admin/users/' . $targetUser->id, [
            'role' => 'super_admin',
        ]);

        // After the role refactor, AdminUsersController::update() rejects
        // 'tenant_admin'/'super_admin'/'god' as role-field assignment targets for
        // EVERY caller (super-admin is granted via the dedicated toggle endpoints),
        // so the attempt is refused with a 422 VALIDATION_ERROR before any update is
        // applied — assert both the status AND the security property rather than
        // accepting any non-200.
        $this->assertSame(422, $response->getStatusCode(),
            'SEC-007: setting super_admin via the role field must be refused with 422.');
        $targetUser->refresh();
        $this->assertNotEquals('super_admin', $targetUser->role,
            'SEC-007: target role must not have been escalated to super_admin.');
    }

    // ================================================================
    // SEC-008/009/010: Tenant super-admin cross-tenant moderation IDOR
    //
    // A TENANT super-admin (is_tenant_super_admin) is scoped to a single
    // tenant. AdminReviewsController, AdminReportsController and
    // AdminSupportReportController previously treated is_tenant_super_admin
    // as a PLATFORM super-admin in their isSuperAdmin() helper, taking an
    // unscoped fetch branch and then writing back using the fetched row's
    // tenant_id — letting one tenant's super-admin delete/resolve/edit
    // another tenant's records. The fix routes those helpers through
    // BaseApiController::isPlatformSuperAdmin() (platform-only, explicitly
    // excluding is_tenant_super_admin).
    // ================================================================

    private function actingAsTenantSuperAdmin(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'is_super_admin' => false,
            'is_god' => false,
            'is_tenant_super_admin' => true,
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_tenant_super_admin_cannot_delete_cross_tenant_review(): void
    {
        $this->actingAsTenantSuperAdmin();
        $foreign = User::factory()->forTenant(999)->create();

        $reviewId = DB::table('reviews')->insertGetId([
            'tenant_id' => 999,
            'reviewer_id' => $foreign->id,
            'receiver_id' => $foreign->id,
            'rating' => 5,
            'comment' => 'cross-tenant review',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiDelete('/v2/admin/reviews/' . $reviewId);

        $this->assertNotEquals(200, $response->getStatusCode(),
            'SEC-008: tenant super-admin deleted a review owned by another tenant (cross-tenant IDOR).');
        $this->assertNotNull(DB::table('reviews')->where('id', $reviewId)->first(),
            'SEC-008: cross-tenant review was deleted by a tenant super-admin.');
    }

    public function test_tenant_super_admin_cannot_resolve_cross_tenant_report(): void
    {
        $this->actingAsTenantSuperAdmin();
        $foreign = User::factory()->forTenant(999)->create();

        $reportId = DB::table('reports')->insertGetId([
            'tenant_id' => 999,
            'reporter_id' => $foreign->id,
            'target_type' => 'post',
            'target_id' => 4242,
            'reason' => 'cross-tenant report',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiPost('/v2/admin/reports/' . $reportId . '/resolve');

        $this->assertNotEquals(200, $response->getStatusCode(),
            'SEC-009: tenant super-admin resolved a report owned by another tenant (cross-tenant IDOR).');
        $report = DB::table('reports')->where('id', $reportId)->first();
        $this->assertNotNull($report);
        $this->assertSame('open', $report->status,
            'SEC-009: cross-tenant report was resolved by a tenant super-admin.');
    }

    public function test_tenant_super_admin_cannot_update_cross_tenant_support_report(): void
    {
        $this->actingAsTenantSuperAdmin();
        $foreign = User::factory()->forTenant(999)->create();

        $supportId = DB::table('support_reports')->insertGetId([
            'tenant_id' => 999,
            'user_id' => $foreign->id,
            'reference' => 'SR-' . substr(md5((string) $foreign->id . microtime()), 0, 12),
            'source' => 'in_app',
            'summary' => 'cross-tenant support report',
            'description' => 'should not be editable cross-tenant',
            'impact' => 'minor',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiPut('/v2/admin/support-reports/' . $supportId, [
            'status' => 'triaged',
            'triage_notes' => 'attempted cross-tenant edit',
        ]);

        $this->assertNotEquals(200, $response->getStatusCode(),
            'SEC-010: tenant super-admin updated a support report owned by another tenant (cross-tenant IDOR).');
        $support = DB::table('support_reports')->where('id', $supportId)->first();
        $this->assertNotNull($support);
        $this->assertSame('open', $support->status,
            'SEC-010: cross-tenant support report was modified by a tenant super-admin.');
    }
}
