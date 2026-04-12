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
 * Smoke tests for AdminFederationExternalPartnersController.
 */
class AdminFederationExternalPartnersControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_requires_auth(): void
    {
        $this->apiGet('/v2/admin/federation/external-partners')->assertStatus(401);
    }

    public function test_index_rejects_non_admin(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->create());
        $this->apiGet('/v2/admin/federation/external-partners')->assertStatus(403);
    }

    public function test_index_allows_admin(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());
        $response = $this->apiGet('/v2/admin/federation/external-partners');
        $this->assertLessThan(500, $response->status());
    }

    public function test_store_requires_auth(): void
    {
        $this->apiPost('/v2/admin/federation/external-partners', [])->assertStatus(401);
    }

    public function test_logs_requires_auth(): void
    {
        $this->apiGet('/v2/admin/federation/external-partners/1/logs')->assertStatus(401);
    }

    public function test_health_check_requires_auth(): void
    {
        $this->apiPost('/v2/admin/federation/external-partners/1/health-check', [])->assertStatus(401);
    }

    public function test_destroy_requires_auth(): void
    {
        $this->apiDelete('/v2/admin/federation/external-partners/1')->assertStatus(401);
    }
}
