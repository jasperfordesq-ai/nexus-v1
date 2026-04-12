<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminOnboardingConfigController.
 *
 * Covers:
 *  - GET  /v2/admin/config/onboarding           get config (admin)
 *  - PUT  /v2/admin/config/onboarding           update config (admin)
 *  - GET  /v2/admin/config/onboarding/presets   list presets
 *  - POST /v2/admin/config/onboarding/apply-preset  apply preset
 */
class AdminOnboardingConfigControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_config_requires_auth(): void
    {
        $response = $this->apiGet('/v2/admin/config/onboarding');

        $response->assertStatus(401);
    }

    public function test_get_config_rejects_non_admin(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/config/onboarding');

        $response->assertStatus(403);
    }

    public function test_apply_preset_rejects_non_admin(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/config/onboarding/apply-preset', [
            'preset' => 'ireland',
        ]);

        $response->assertStatus(403);
    }

    public function test_presets_returns_list_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/config/onboarding/presets');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_update_config_rejects_non_admin(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPut('/v2/admin/config/onboarding', [
            'enabled' => true,
        ]);

        $response->assertStatus(403);
    }
}
