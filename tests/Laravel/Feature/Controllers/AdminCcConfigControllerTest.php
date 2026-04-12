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
 * Feature tests for AdminCcConfigController.
 *
 * Covers admin gating and validation for Credit Commons node configuration:
 *   GET /v2/admin/federation/cc-config
 *   PUT /v2/admin/federation/cc-config
 */
class AdminCcConfigControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------------------------
    //  Auth / admin gating
    // ------------------------------------------------------------------

    public function test_show_requires_auth(): void
    {
        $response = $this->apiGet('/v2/admin/federation/cc-config');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_show_rejects_non_admin(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->create());

        $response = $this->apiGet('/v2/admin/federation/cc-config');

        $response->assertStatus(403);
    }

    public function test_update_requires_auth(): void
    {
        $response = $this->apiPut('/v2/admin/federation/cc-config', ['node_slug' => 'test-node']);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_update_rejects_non_admin(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->create());

        $response = $this->apiPut('/v2/admin/federation/cc-config', ['node_slug' => 'test-node']);

        $response->assertStatus(403);
    }

    // ------------------------------------------------------------------
    //  Validation — node_slug / exchange_rate / validated_window
    // ------------------------------------------------------------------

    public function test_update_rejects_invalid_node_slug(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());

        $response = $this->apiPut('/v2/admin/federation/cc-config', [
            'node_slug' => 'INVALID UPPER!',
        ]);

        $response->assertStatus(422);
    }

    public function test_update_rejects_exchange_rate_out_of_range(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());

        $response = $this->apiPut('/v2/admin/federation/cc-config', [
            'exchange_rate' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_update_rejects_invalid_validated_window(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());

        $response = $this->apiPut('/v2/admin/federation/cc-config', [
            'validated_window' => 5, // below 30-second minimum
        ]);

        $response->assertStatus(422);
    }
}
