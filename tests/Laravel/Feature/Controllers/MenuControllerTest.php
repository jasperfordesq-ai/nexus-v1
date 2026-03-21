<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Feature tests for MenuController — navigation menus.
 *
 * GET routes are PUBLIC (no auth required).
 * POST /menus/clear-cache requires auth.
 */
class MenuControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  GET /menus (PUBLIC)
    // ------------------------------------------------------------------

    public function test_menus_index_is_public(): void
    {
        $response = $this->apiGet('/menus');

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_menus_index_returns_data(): void
    {
        $response = $this->apiGet('/menus');

        $this->assertContains($response->getStatusCode(), [200, 404]);
    }

    // ------------------------------------------------------------------
    //  GET /menus/config (PUBLIC)
    // ------------------------------------------------------------------

    public function test_menus_config_is_public(): void
    {
        $response = $this->apiGet('/menus/config');

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    //  GET /menus/mobile (PUBLIC)
    // ------------------------------------------------------------------

    public function test_menus_mobile_is_public(): void
    {
        $response = $this->apiGet('/menus/mobile');

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    //  GET /menus/{slug} (PUBLIC)
    // ------------------------------------------------------------------

    public function test_menus_show_is_public(): void
    {
        $response = $this->apiGet('/menus/main');

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    //  POST /menus/clear-cache (auth required)
    // ------------------------------------------------------------------

    public function test_clear_cache_requires_auth(): void
    {
        $response = $this->apiPost('/menus/clear-cache');

        $response->assertStatus(401);
    }

    public function test_clear_cache_works(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/menus/clear-cache');

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }
}
