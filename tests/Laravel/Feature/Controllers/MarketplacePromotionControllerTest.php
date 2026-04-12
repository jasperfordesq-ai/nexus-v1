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
 * Smoke tests for MarketplacePromotionController.
 * Money paths — smoke scope only, deeper tests deferred.
 */
class MarketplacePromotionControllerTest extends TestCase
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

    public function test_products_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/promotions/products');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_promote_requires_auth(): void
    {
        $response = $this->apiPost('/v2/marketplace/listings/1/promote', []);
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_myPromotions_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/promotions/mine');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_products_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/promotions/products');
        $this->assertLessThan(500, $response->status());
    }

    public function test_myPromotions_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/promotions/mine');
        $this->assertLessThan(500, $response->status());
    }
}
