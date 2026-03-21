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
 * Feature tests for AdminConfigController.
 *
 * Covers config, features, modules, cache, jobs, settings, AI, feed algorithm,
 * algorithm config/health, images, SEO, languages, native app, cron jobs.
 */
class AdminConfigControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // GET CONFIG — GET /v2/admin/config
    // ================================================================

    public function test_get_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/config');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_get_config_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/config');

        $response->assertStatus(403);
    }

    public function test_get_config_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/config');

        $response->assertStatus(401);
    }

    // ================================================================
    // CACHE STATS — GET /v2/admin/cache/stats
    // ================================================================

    public function test_cache_stats_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/cache/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_cache_stats_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/cache/stats');

        $response->assertStatus(403);
    }

    // ================================================================
    // CLEAR CACHE — POST /v2/admin/cache/clear
    // ================================================================

    public function test_clear_cache_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/cache/clear');

        $response->assertStatus(200);
    }

    public function test_clear_cache_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/cache/clear');

        $response->assertStatus(403);
    }

    // ================================================================
    // SETTINGS — GET /v2/admin/settings
    // ================================================================

    public function test_get_settings_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/settings');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_get_settings_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/settings');

        $response->assertStatus(403);
    }

    // ================================================================
    // BACKGROUND JOBS — GET /v2/admin/background-jobs
    // ================================================================

    public function test_get_jobs_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/background-jobs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // AI CONFIG — GET /v2/admin/config/ai
    // ================================================================

    public function test_get_ai_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/config/ai');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_get_ai_config_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/config/ai');

        $response->assertStatus(403);
    }

    // ================================================================
    // FEED ALGORITHM — GET /v2/admin/config/feed-algorithm
    // ================================================================

    public function test_get_feed_algorithm_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/config/feed-algorithm');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // ALGORITHM CONFIG — GET /v2/admin/config/algorithms
    // ================================================================

    public function test_get_algorithm_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/config/algorithms');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // ALGORITHM HEALTH — GET /v2/admin/config/algorithm-health
    // ================================================================

    public function test_get_algorithm_health_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/config/algorithm-health');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // IMAGE CONFIG — GET /v2/admin/config/images
    // ================================================================

    public function test_get_image_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/config/images');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // SEO CONFIG — GET /v2/admin/config/seo
    // ================================================================

    public function test_get_seo_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/config/seo');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // LANGUAGE CONFIG — GET /v2/admin/config/languages
    // ================================================================

    public function test_get_language_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/config/languages');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // NATIVE APP CONFIG — GET /v2/admin/config/native-app
    // ================================================================

    public function test_get_native_app_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/config/native-app');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // CRON JOBS — GET /v2/admin/system/cron-jobs
    // ================================================================

    public function test_get_cron_jobs_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/system/cron-jobs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_get_cron_jobs_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/system/cron-jobs');

        $response->assertStatus(401);
    }

    // ================================================================
    // UPDATE FEATURES — PUT /v2/admin/config/features
    // ================================================================

    public function test_update_feature_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/config/features', [
            'feature' => 'events',
            'enabled' => true,
        ]);

        $response->assertStatus(200);
    }

    public function test_update_feature_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPut('/v2/admin/config/features', [
            'feature' => 'events',
            'enabled' => true,
        ]);

        $response->assertStatus(403);
    }
}
