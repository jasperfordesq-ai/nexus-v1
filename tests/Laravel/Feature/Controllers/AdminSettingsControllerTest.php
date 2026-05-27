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
 * Feature tests for AdminSettingsController.
 *
 * Note: AdminSettingsController routes are not in the standard admin route group
 * in routes/api.php. These tests verify the endpoints exist and enforce auth.
 * The routes may be registered under a different path — tests will verify 401/403.
 */
class AdminSettingsControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/settings (via AdminConfigController route)
    // ================================================================

    public function test_settings_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/settings');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_settings_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/settings');

        $response->assertStatus(403);
    }

    public function test_settings_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/settings');

        $response->assertStatus(401);
    }

    // ================================================================
    // UPDATE — PUT /v2/admin/settings
    // ================================================================

    public function test_update_settings_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/settings', [
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_update_settings_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPut('/v2/admin/settings', [
            'test_setting_key' => 'test_value',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_settings_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPut('/v2/admin/settings', [
            'test_setting_key' => 'test_value',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_settings_rejects_empty_body(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/settings', []);

        $response->assertStatus(422);
    }

    public function test_update_settings_rejects_unknown_keys(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        // AdminConfigController (which handles the route) validates keys against an allowlist
        $response = $this->apiPut('/v2/admin/settings', [
            "'; DROP TABLE users; --" => 'malicious',
        ]);

        $response->assertStatus(422);
    }

    public function test_update_settings_saves_valid_keys(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        // Use keys from AdminConfigController::GENERAL_SETTING_KEYS
        $response = $this->apiPut('/v2/admin/settings', [
            'timezone' => 'Europe/London',
        ]);

        $response->assertStatus(200);
    }

    public function test_closing_registration_through_admin_settings_updates_public_registration_status_after_cache_warm(): void
    {
        DB::table('tenant_registration_policies')->updateOrInsert(
            ['tenant_id' => $this->testTenantId],
            [
                'registration_mode' => 'open',
                'verification_level' => 'none',
                'post_verification' => 'activate',
                'fallback_mode' => 'none',
                'require_email_verify' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.registration_mode'],
            [
                'setting_value' => 'open',
                'setting_type' => 'string',
                'updated_at' => now(),
            ]
        );
        app(\App\Services\TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);

        $this->apiGet('/v2/auth/registration-info')
            ->assertStatus(200)
            ->assertJsonPath('data.can_register', true);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $this->apiPut('/v2/admin/settings', [
            'registration_mode' => 'closed',
        ])->assertStatus(200);

        $this->apiGet('/v2/auth/registration-info')
            ->assertStatus(200)
            ->assertJsonPath('data.can_register', false)
            ->assertJsonPath('data.is_closed', true)
            ->assertJsonPath('data.registration_mode', 'closed');
    }
}
