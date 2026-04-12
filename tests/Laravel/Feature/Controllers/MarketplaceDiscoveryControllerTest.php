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
 * Smoke tests for MarketplaceDiscoveryController.
 */
class MarketplaceDiscoveryControllerTest extends TestCase
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

    public function test_listSavedSearches_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/saved-searches');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_listCollections_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/collections');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_storeSavedSearch_requires_auth(): void
    {
        $response = $this->apiPost('/v2/marketplace/saved-searches', []);
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_listSavedSearches_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/saved-searches');
        $this->assertLessThan(500, $response->status());
    }

    public function test_listCollections_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/collections');
        $this->assertLessThan(500, $response->status());
    }
}
