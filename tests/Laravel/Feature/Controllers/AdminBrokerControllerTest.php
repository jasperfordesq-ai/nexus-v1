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
 * Feature tests for AdminBrokerController.
 *
 * Covers dashboard, exchanges, risk tags, messages, monitoring, and configuration.
 */
class AdminBrokerControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // DASHBOARD — GET /v2/admin/broker/dashboard
    // ================================================================

    public function test_dashboard_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_dashboard_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/broker/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/broker/dashboard');

        $response->assertStatus(401);
    }

    // ================================================================
    // EXCHANGES — GET /v2/admin/broker/exchanges
    // ================================================================

    public function test_exchanges_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/exchanges');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_exchanges_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/broker/exchanges');

        $response->assertStatus(403);
    }

    // ================================================================
    // RISK TAGS — GET /v2/admin/broker/risk-tags
    // ================================================================

    public function test_risk_tags_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/risk-tags');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // MESSAGES — GET /v2/admin/broker/messages
    // ================================================================

    public function test_messages_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/messages');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_messages_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/broker/messages');

        $response->assertStatus(403);
    }

    // ================================================================
    // UNREVIEWED COUNT — GET /v2/admin/broker/messages/unreviewed-count
    // ================================================================

    public function test_unreviewed_count_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/messages/unreviewed-count');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // MONITORING — GET /v2/admin/broker/monitoring
    // ================================================================

    public function test_monitoring_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/monitoring');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // CONFIGURATION — GET /v2/admin/broker/configuration
    // ================================================================

    public function test_get_configuration_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/configuration');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_get_configuration_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/broker/configuration');

        $response->assertStatus(403);
    }

    // ================================================================
    // ARCHIVES — GET /v2/admin/broker/archives
    // ================================================================

    public function test_archives_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/broker/archives');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_archives_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/broker/archives');

        $response->assertStatus(401);
    }
}
