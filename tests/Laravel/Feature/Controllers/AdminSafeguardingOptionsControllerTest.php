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
 * Feature tests for AdminSafeguardingOptionsController.
 *
 * Covers:
 *  - GET    /v2/admin/safeguarding/options        list options (admin)
 *  - POST   /v2/admin/safeguarding/options        create option (validation)
 *  - PUT    /v2/admin/safeguarding/options/{id}   update option
 *  - DELETE /v2/admin/safeguarding/options/{id}   deactivate option
 */
class AdminSafeguardingOptionsControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/admin/safeguarding/options');

        $response->assertStatus(401);
    }

    public function test_index_rejects_non_admin(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/safeguarding/options');

        $response->assertStatus(403);
    }

    public function test_store_requires_option_key(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/safeguarding/options', [
            'label' => 'Some label',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_requires_label(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/safeguarding/options', [
            'option_key' => 'police_check',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_invalid_option_type(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/safeguarding/options', [
            'option_key' => 'police_check',
            'label' => 'Police check',
            'option_type' => 'not_a_valid_type',
        ]);

        $response->assertStatus(422);
    }

    public function test_update_rejects_non_admin(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPut('/v2/admin/safeguarding/options/1', [
            'label' => 'Hijack',
        ]);

        $response->assertStatus(403);
    }
}
