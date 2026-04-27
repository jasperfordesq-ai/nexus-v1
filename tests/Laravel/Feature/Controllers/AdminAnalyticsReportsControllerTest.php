<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminAnalyticsReportsController.
 *
 * Covers social value, member reports, hours reports, export types,
 * inactive members, moderation queue, and moderation stats.
 */
class AdminAnalyticsReportsControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function setCaringCommunityFeature(bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first();
        $features = [];
        if ($tenant && !empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }

        $features['caring_community'] = $enabled;
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById($this->testTenantId);
    }

    // ================================================================
    // SOCIAL VALUE — GET /v2/admin/reports/social-value
    // ================================================================

    public function test_social_value_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/reports/social-value');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_social_value_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/reports/social-value');

        $response->assertStatus(403);
    }

    public function test_social_value_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/reports/social-value');

        $response->assertStatus(401);
    }

    // ================================================================
    // UPDATE SOCIAL VALUE CONFIG — PUT /v2/admin/reports/social-value/config
    // ================================================================

    public function test_update_social_value_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/reports/social-value/config', [
            'hour_value_currency' => 'EUR',
            'hour_value_amount' => 20.00,
            'social_multiplier' => 4.0,
            'reporting_period' => 'quarterly',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_update_social_value_config_returns_403_for_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPut('/v2/admin/reports/social-value/config', [
            'hour_value_amount' => 20.00,
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // MEMBER REPORTS — GET /v2/admin/reports/members
    // ================================================================

    public function test_member_reports_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/reports/members?type=active');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_member_reports_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/reports/members');

        $response->assertStatus(403);
    }

    // ================================================================
    // HOURS REPORTS — GET /v2/admin/reports/hours
    // ================================================================

    public function test_hours_reports_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/reports/hours?group_by=category');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_hours_reports_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/reports/hours');

        $response->assertStatus(403);
    }

    // ================================================================
    // EXPORT TYPES — GET /v2/admin/reports/export-types
    // ================================================================

    public function test_export_types_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/reports/export-types');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_export_types_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/reports/export-types');

        $response->assertStatus(401);
    }

    public function test_export_types_hide_municipal_impact_when_caring_community_disabled(): void
    {
        $this->setCaringCommunityFeature(false);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/reports/export-types');

        $response->assertStatus(200);
        $response->assertJsonMissing(['type' => 'municipal_impact']);
    }

    public function test_municipal_impact_export_returns_403_when_caring_community_disabled(): void
    {
        $this->setCaringCommunityFeature(false);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/reports/municipal_impact/export?format=pdf');

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }
}
