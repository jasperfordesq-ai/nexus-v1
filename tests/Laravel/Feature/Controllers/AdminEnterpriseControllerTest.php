<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminEnterpriseController.
 *
 * Covers dashboard, roles, permissions, GDPR, monitoring, health, logs, config, secrets, legal docs.
 */
class AdminEnterpriseControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // DASHBOARD — GET /v2/admin/enterprise/dashboard
    // ================================================================

    public function test_dashboard_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/enterprise/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['user_count', 'role_count', 'pending_gdpr_requests', 'health_status'],
        ]);
    }

    public function test_dashboard_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/enterprise/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/enterprise/dashboard');

        $response->assertStatus(401);
    }

    // ================================================================
    // ROLES — GET /v2/admin/enterprise/roles
    // ================================================================

    public function test_roles_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/enterprise/roles');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_roles_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/enterprise/roles');

        $response->assertStatus(403);
    }

    // ================================================================
    // PERMISSIONS — GET /v2/admin/enterprise/permissions
    // ================================================================

    public function test_permissions_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/enterprise/permissions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // GDPR DASHBOARD — GET /v2/admin/enterprise/gdpr/dashboard
    // ================================================================

    public function test_gdpr_dashboard_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/enterprise/gdpr/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_gdpr_dashboard_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/enterprise/gdpr/dashboard');

        $response->assertStatus(403);
    }

    // ================================================================
    // GDPR REQUESTS — GET /v2/admin/enterprise/gdpr/requests
    // ================================================================

    public function test_gdpr_requests_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/enterprise/gdpr/requests');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // MONITORING — GET /v2/admin/enterprise/monitoring
    // ================================================================

    public function test_monitoring_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/enterprise/monitoring');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_monitoring_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/enterprise/monitoring');

        $response->assertStatus(403);
    }

    // ================================================================
    // HEALTH CHECK — GET /v2/admin/enterprise/monitoring/health
    // ================================================================

    public function test_health_check_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/enterprise/monitoring/health');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // LOGS — GET /v2/admin/enterprise/monitoring/logs
    // ================================================================

    public function test_logs_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/enterprise/monitoring/logs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // CONFIG — GET /v2/admin/enterprise/config
    // ================================================================

    public function test_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/enterprise/config');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_config_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/enterprise/config');

        $response->assertStatus(403);
    }

    // ================================================================
    // LEGAL DOCS — GET /v2/admin/legal-documents
    // ================================================================

    public function test_legal_docs_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/legal-documents');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_legal_docs_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/legal-documents');

        $response->assertStatus(403);
    }

    public function test_legal_docs_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/legal-documents');

        $response->assertStatus(401);
    }
}
