<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use App\Services\MarketplaceConfigurationService;

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

    public function test_promote_own_listing_returns_created_response(): void
    {
        $user = $this->authenticatedUser();
        $this->enableMarketplacePromotions();

        $listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Promotion test listing',
            'description' => 'A listing used to verify promotion creation.',
            'price' => 10,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'condition' => 'good',
            'quantity' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiPost("/v2/marketplace/listings/{$listingId}/promote", [
            'promotion_type' => 'bump',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.promotion_type', 'bump');
        $this->assertDatabaseHas('marketplace_promotions', [
            'tenant_id' => $this->testTenantId,
            'marketplace_listing_id' => $listingId,
            'user_id' => $user->id,
            'promotion_type' => 'bump',
            'is_active' => 1,
        ]);
    }

    private function enableMarketplacePromotions(): void
    {
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode(['marketplace' => true])]);
        TenantContext::setById($this->testTenantId);

        MarketplaceConfigurationService::set(MarketplaceConfigurationService::CONFIG_PROMOTIONS_ENABLED, true);
    }
}
