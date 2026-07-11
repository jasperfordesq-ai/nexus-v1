<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use App\Services\PrerenderContentInvalidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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

    public function test_bulk_volunteering_config_rejects_missing_settings_with_translated_error(): void
    {
        // VOL-BE-010: the missing-settings validation message must be translated,
        // not a hardcoded English literal.
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/config/volunteering/bulk', []);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.message', __('api.missing_required_field', ['field' => 'settings']));
        $this->assertStringNotContainsString('Settings array is required', $response->getContent());
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

    public function test_settings_validate_every_field_before_mutating_tenant_routing(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create(['is_super_admin' => true]);
        Sanctum::actingAs($admin);
        $originalSlug = (string) DB::table('tenants')->where('id', $this->testTenantId)->value('slug');

        $this->apiPut('/v2/admin/settings', [
            'slug' => 'should-not-persist',
            'welcome_credits' => 101,
        ])->assertStatus(422);

        $this->assertSame(
            $originalSlug,
            (string) DB::table('tenants')->where('id', $this->testTenantId)->value('slug')
        );
    }

    public function test_settings_reject_reserved_tenant_slug(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create(['is_super_admin' => true]);
        Sanctum::actingAs($admin);

        $this->apiPut('/v2/admin/settings', ['slug' => 'about'])
            ->assertStatus(422);
    }

    public function test_routing_setting_schedules_authoritative_prerender_refresh(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create(['is_super_admin' => true]);
        Sanctum::actingAs($admin);
        $this->mock(PrerenderContentInvalidator::class, function ($mock): void {
            $mock->shouldReceive('refreshAllOrFail')->once()->andReturn(987);
        });

        $slug = 'config-route-' . $this->testTenantId;
        $this->apiPut('/v2/admin/settings', ['slug' => $slug])
            ->assertStatus(200);

        $this->assertSame($slug, DB::table('tenants')->where('id', $this->testTenantId)->value('slug'));
    }

    public function test_tenant_admin_cannot_change_tenant_routing_identity(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $this->apiPut('/v2/admin/settings', ['domain' => 'tenant-admin.example'])
            ->assertStatus(403);
    }

    public function test_platform_service_host_cannot_be_claimed_as_tenant_domain(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create(['is_super_admin' => true]);
        Sanctum::actingAs($admin);

        $this->apiPut('/v2/admin/settings', ['domain' => 'api.project-nexus.ie'])
            ->assertStatus(422);
    }

    public function test_render_affecting_setting_schedules_tenant_prerender_refresh(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);
        $this->mock(PrerenderContentInvalidator::class, function ($mock): void {
            $mock->shouldReceive('refreshTenantOrFail')
                ->once()
                ->with($this->testTenantId, true)
                ->andReturn(988);
        });

        $this->apiPut('/v2/admin/settings', ['tagline' => 'Fresh tenant tagline'])
            ->assertStatus(200);
    }

    public function test_settings_roll_back_when_durable_prerender_intent_cannot_be_written(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);
        $original = DB::table('tenants')->where('id', $this->testTenantId)->value('tagline');
        $this->mock(PrerenderContentInvalidator::class, function ($mock): void {
            $mock->shouldReceive('refreshTenantOrFail')
                ->once()
                ->andThrow(new \RuntimeException('queue unavailable'));
        });

        $this->apiPut('/v2/admin/settings', ['tagline' => 'Must roll back'])
            ->assertStatus(503)
            ->assertJsonPath('errors.0.code', 'PRERENDER_REFRESH_FAILED');

        $this->assertSame(
            $original,
            DB::table('tenants')->where('id', $this->testTenantId)->value('tagline')
        );
    }

    public function test_landing_page_config_rolls_back_when_rebuild_intent_fails(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'landing_page.config'],
            ['setting_value' => json_encode(['sections' => []]), 'setting_type' => 'json']
        );
        $original = DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'landing_page.config')
            ->value('setting_value');

        $this->mock(PrerenderContentInvalidator::class, function ($mock): void {
            $mock->shouldReceive('refreshTenantOrFail')
                ->once()
                ->with($this->testTenantId, true)
                ->andThrow(new \RuntimeException('queue unavailable'));
        });

        $this->apiPut('/v2/admin/config/landing-page', [
            'config' => [
                'sections' => [[
                    'id' => 'hero',
                    'type' => 'hero',
                    'enabled' => true,
                    'order' => 0,
                ]],
            ],
        ])->assertStatus(503);

        $this->assertSame(
            $original,
            DB::table('tenant_settings')
                ->where('tenant_id', $this->testTenantId)
                ->where('setting_key', 'landing_page.config')
                ->value('setting_value')
        );
    }

    public function test_feature_toggle_rolls_back_when_rebuild_intent_fails(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);
        $original = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $this->mock(PrerenderContentInvalidator::class, function ($mock): void {
            $mock->shouldReceive('refreshTenantOrFail')
                ->once()
                ->with($this->testTenantId, true, true)
                ->andThrow(new \RuntimeException('queue unavailable'));
        });

        $this->apiPut('/v2/admin/config/features', [
            'feature' => 'events',
            'enabled' => false,
        ])->assertStatus(503);

        $this->assertSame($original, DB::table('tenants')->where('id', $this->testTenantId)->value('features'));
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

    public function test_native_app_config_tracks_tenant_branded_store_readiness(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $update = $this->apiPut('/v2/admin/config/native-app', [
            'native_app_name' => 'KISS Musterstadt',
            'native_app_short_name' => 'KISS',
            'native_app_bundle_id' => 'ch.kiss.musterstadt',
            'native_app_package_name' => 'ch.kiss.musterstadt',
            'native_app_push_enabled' => true,
            'native_app_store_mode' => 'tenant_branded',
            'native_app_build_profile' => 'production',
            'native_app_ios_app_store_id' => '1234567890',
            'native_app_android_play_store_id' => 'ch.kiss.musterstadt',
            'native_app_marketing_url' => 'https://example.test/app',
            'native_app_privacy_url' => 'https://example.test/privacy',
            'native_app_support_url' => 'https://example.test/support',
            'native_app_push_sender_id' => 'kiss-musterstadt',
            'native_app_tenant_channel_prefix' => 'tenant-2-kiss',
        ]);

        $update->assertStatus(200);
        $update->assertJsonPath('data.updated', true);

        $response = $this->apiGet('/v2/admin/config/native-app');

        $response->assertStatus(200);
        $response->assertJsonPath('data.native_app.native_app_store_mode', 'tenant_branded');
        $response->assertJsonPath('data.deployment_readiness.has_ios_identity', true);
        $response->assertJsonPath('data.deployment_readiness.has_android_identity', true);
        $response->assertJsonPath('data.deployment_readiness.has_store_metadata', true);
        $response->assertJsonPath('data.deployment_readiness.push_routing_configured', true);
        $response->assertJsonPath('data.deployment_readiness.tenant_branded_ready', true);

        $manifest = $this->apiGet('/v2/admin/config/native-app/build-manifest');

        $manifest->assertStatus(200);
        $manifest->assertJsonPath('data.manifest_version', 'native-app-build-manifest-v1');
        $manifest->assertJsonPath('data.app.name', 'KISS Musterstadt');
        $manifest->assertJsonPath('data.app.bundle_id', 'ch.kiss.musterstadt');
        $manifest->assertJsonPath('data.store.mode', 'tenant_branded');
        $manifest->assertJsonPath('data.push.enabled', true);
        $manifest->assertJsonPath('data.deployment_readiness.tenant_branded_ready', true);
    }

    public function test_native_app_tenant_branded_readiness_lists_missing_requirements(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $update = $this->apiPut('/v2/admin/config/native-app', [
            'native_app_store_mode' => 'tenant_branded',
            'native_app_name' => 'KISS Musterstadt',
            'native_app_bundle_id' => '',
            'native_app_package_name' => '',
            'native_app_ios_app_store_id' => '',
            'native_app_android_play_store_id' => '',
            'native_app_marketing_url' => '',
            'native_app_privacy_url' => '',
            'native_app_support_url' => '',
            'native_app_push_enabled' => false,
            'native_app_push_sender_id' => '',
            'native_app_tenant_channel_prefix' => '',
        ]);

        $update->assertStatus(200);

        $response = $this->apiGet('/v2/admin/config/native-app');

        $response->assertStatus(200);
        $response->assertJsonPath('data.deployment_readiness.tenant_branded_ready', false);
        $response->assertJsonPath('data.deployment_readiness.missing_requirements', [
            'ios_identity',
            'android_identity',
            'store_metadata',
            'push_routing',
        ]);

        $manifest = $this->apiGet('/v2/admin/config/native-app/build-manifest');

        $manifest->assertStatus(200);
        $manifest->assertJsonPath('data.deployment_readiness.missing_requirements', [
            'ios_identity',
            'android_identity',
            'store_metadata',
            'push_routing',
        ]);
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
