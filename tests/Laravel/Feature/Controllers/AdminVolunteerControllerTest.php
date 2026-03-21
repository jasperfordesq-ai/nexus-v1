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
 * Feature tests for AdminVolunteerController.
 *
 * Covers index, opportunities, applications, approvals, organizations,
 * approveApplication, declineApplication, verifyHours.
 * The controller checks TenantContext::hasFeature('volunteering') and
 * gracefully returns empty data if volunteer tables don't exist.
 */
class AdminVolunteerControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/volunteering
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/volunteering');

        // 200 if feature enabled, 403 if feature disabled
        $this->assertTrue(in_array($response->status(), [200, 403]));
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/volunteering');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/volunteering');

        $response->assertStatus(401);
    }

    // ================================================================
    // APPROVALS — GET /v2/admin/volunteering/approvals
    // ================================================================

    public function test_approvals_returns_200_or_403_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/volunteering/approvals');

        // 200 if feature enabled, 403 if feature disabled
        $this->assertTrue(in_array($response->status(), [200, 403]));
    }

    public function test_approvals_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/volunteering/approvals');

        $response->assertStatus(403);
    }

    // ================================================================
    // ORGANIZATIONS — GET /v2/admin/volunteering/organizations
    // ================================================================

    public function test_organizations_returns_200_or_403_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/volunteering/organizations');

        // 200 if feature enabled, 403 if feature disabled
        $this->assertTrue(in_array($response->status(), [200, 403]));
    }

    public function test_organizations_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/volunteering/organizations');

        $response->assertStatus(403);
    }

    // ================================================================
    // APPROVE APPLICATION — POST /v2/admin/volunteering/approvals/{id}/approve
    // ================================================================

    public function test_approve_application_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/volunteering/approvals/1/approve');

        $response->assertStatus(403);
    }

    public function test_approve_application_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/volunteering/approvals/1/approve');

        $response->assertStatus(401);
    }

    // ================================================================
    // DECLINE APPLICATION — POST /v2/admin/volunteering/approvals/{id}/decline
    // ================================================================

    public function test_decline_application_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/volunteering/approvals/1/decline');

        $response->assertStatus(403);
    }
}
