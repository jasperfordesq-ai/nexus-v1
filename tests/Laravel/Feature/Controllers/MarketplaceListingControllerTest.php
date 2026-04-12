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
 * Smoke tests for MarketplaceListingController.
 */
class MarketplaceListingControllerTest extends TestCase
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

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/marketplace/listings', []);
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_savedListings_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/listings/saved');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_destroy_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/marketplace/listings/1');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_index_public_smoke(): void
    {
        // Public endpoint (outside auth middleware group)
        $response = $this->apiGet('/v2/marketplace/listings');
        $this->assertLessThan(500, $response->status());
    }

    public function test_categories_public_smoke(): void
    {
        $response = $this->apiGet('/v2/marketplace/categories');
        $this->assertLessThan(500, $response->status());
    }

    public function test_savedListings_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/listings/saved');
        $this->assertLessThan(500, $response->status());
    }
}
