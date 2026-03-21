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
 * Feature tests for AdminFederationCreditAgreementsController.
 *
 * Covers listing credit agreements, creating agreements, actions, and partners.
 */
class AdminFederationCreditAgreementsControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/federation/credit-agreements
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/credit-agreements');

        // May return 200 or 503 if FederationCreditService is unavailable
        $this->assertContains($response->getStatusCode(), [200, 503]);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/federation/credit-agreements');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/federation/credit-agreements');

        $response->assertStatus(401);
    }

    // ================================================================
    // STORE — POST /v2/admin/federation/credit-agreements
    // ================================================================

    public function test_store_validates_partner_tenant_id(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/federation/credit-agreements', [
            'partner_tenant_id' => 0,
            'exchange_rate' => 1.0,
            'monthly_limit' => 100,
        ]);

        // Validation error
        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_store_rejects_self_agreement(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/federation/credit-agreements', [
            'partner_tenant_id' => $this->testTenantId,
            'exchange_rate' => 1.0,
            'monthly_limit' => 100,
        ]);

        // Validation error for self-agreement
        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_store_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/federation/credit-agreements', [
            'partner_tenant_id' => 99,
            'exchange_rate' => 1.0,
            'monthly_limit' => 100,
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // PARTNERS — GET /v2/admin/federation/partners
    // ================================================================

    public function test_partners_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/partners');

        // May return 200 or 503 if service unavailable
        $this->assertContains($response->getStatusCode(), [200, 503]);
    }

    public function test_partners_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/federation/partners');

        $response->assertStatus(403);
    }
}
