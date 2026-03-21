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
 * Feature tests for CookieConsentController — cookie consent management.
 */
class CookieConsentControllerTest extends TestCase
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
    //  GET /cookie-consent
    // ------------------------------------------------------------------

    public function test_show_requires_auth(): void
    {
        $response = $this->apiGet('/cookie-consent');

        $response->assertStatus(401);
    }

    public function test_show_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/cookie-consent');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /cookie-consent
    // ------------------------------------------------------------------

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/cookie-consent', [
            'analytics' => true,
            'marketing' => false,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_saves_consent(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/cookie-consent', [
            'analytics' => true,
            'marketing' => false,
            'functional' => true,
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }

    // ------------------------------------------------------------------
    //  GET /cookie-consent/inventory
    // ------------------------------------------------------------------

    public function test_inventory_requires_auth(): void
    {
        $response = $this->apiGet('/cookie-consent/inventory');

        $response->assertStatus(401);
    }

    public function test_inventory_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/cookie-consent/inventory');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /cookie-consent/check/{category}
    // ------------------------------------------------------------------

    public function test_check_requires_auth(): void
    {
        $response = $this->apiGet('/cookie-consent/check/analytics');

        $response->assertStatus(401);
    }
}
