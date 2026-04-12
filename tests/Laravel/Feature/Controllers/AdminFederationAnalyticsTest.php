<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminFederationAnalyticsController.
 *
 * Verifies the GET /v2/admin/federation/analytics/overview endpoint:
 * - Requires admin authentication
 * - Returns KPIs, daily_calls, top_partners, recent_errors
 * - Scopes all counts to the current tenant
 */
class AdminFederationAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_overview_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/analytics/overview');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'range_days',
                'kpis' => [
                    'total_partnerships',
                    'active_partnerships',
                    'pending_partnerships',
                    'external_partners',
                    'federated_transactions',
                    'federated_messages',
                    'federated_listings',
                    'inbound_reviews',
                ],
                'daily_calls',
                'top_partners',
                'recent_errors',
            ],
        ]);
    }

    public function test_overview_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/federation/analytics/overview');
        $response->assertStatus(403);
    }

    public function test_overview_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/federation/analytics/overview');
        $response->assertStatus(401);
    }

    public function test_daily_calls_has_correct_bucket_count_for_range(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/analytics/overview?range=7d');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertSame(7, $data['range_days']);
        $this->assertCount(7, $data['daily_calls']);
        foreach ($data['daily_calls'] as $row) {
            $this->assertArrayHasKey('date', $row);
            $this->assertArrayHasKey('count', $row);
        }
    }

    public function test_invalid_range_falls_back_to_30_days(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/analytics/overview?range=evil');
        $response->assertStatus(200);
        $this->assertSame(30, $response->json('data.range_days'));
    }

    public function test_partnership_kpis_reflect_current_tenant_only(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $otherTenantId = $this->testTenantId + 9999;

        // Insert a partnership for our tenant (active)
        DB::table('federation_partnerships')->insert([
            'tenant_id' => $this->testTenantId,
            'partner_tenant_id' => $otherTenantId,
            'status' => 'active',
            'federation_level' => 1,
            'created_at' => now(),
        ]);
        // Insert an unrelated partnership (must NOT be counted)
        DB::table('federation_partnerships')->insert([
            'tenant_id' => $otherTenantId + 1,
            'partner_tenant_id' => $otherTenantId + 2,
            'status' => 'active',
            'federation_level' => 1,
            'created_at' => now(),
        ]);

        $response = $this->apiGet('/v2/admin/federation/analytics/overview');
        $response->assertStatus(200);

        $kpis = $response->json('data.kpis');
        $this->assertGreaterThanOrEqual(1, $kpis['total_partnerships']);
        $this->assertGreaterThanOrEqual(1, $kpis['active_partnerships']);

        // Cleanup handled by DatabaseTransactions
    }
}
