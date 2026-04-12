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
 * Smoke tests for MarketplaceOrderController.
 * Money paths — smoke scope only, deeper tests deferred.
 */
class MarketplaceOrderControllerTest extends TestCase
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
        $response = $this->apiPost('/v2/marketplace/orders', []);
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_purchases_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/orders/purchases');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_sales_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/orders/sales');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_show_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/orders/1');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_purchases_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/orders/purchases');
        $this->assertLessThan(500, $response->status());
    }

    public function test_sales_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/orders/sales');
        $this->assertLessThan(500, $response->status());
    }
}
