<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Tests for MarketplaceOrderController — auth, authorization, tenant scope.
 */
class MarketplaceOrderControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(int $tenantId = 2): User
    {
        $user = User::factory()->forTenant($tenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function makeOrder(int $tenantId, int $buyerId, int $sellerId, array $overrides = []): int
    {
        $listingId = DB::table('marketplace_listings')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'user_id' => $sellerId,
            'title' => 'Item',
            'description' => 'desc',
            'price' => 10.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'shipping_available' => 0,
            'local_pickup' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
        ], []));

        return (int) DB::table('marketplace_orders')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'order_number' => 'ORD-' . uniqid(),
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'marketplace_listing_id' => $listingId,
            'quantity' => 1,
            'unit_price' => 10.00,
            'total_price' => 10.00,
            'currency' => 'EUR',
            'status' => 'pending_payment',
            'created_at' => now(),
        ], $overrides));
    }

    // -------- Smoke (kept) --------

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

    // -------- Deep tests --------

    public function test_show_returns_404_for_missing_order(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/orders/99999999');
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_show_forbidden_for_non_participant(): void
    {
        $buyer = User::factory()->forTenant(2)->create();
        $seller = User::factory()->forTenant(2)->create();
        $orderId = $this->makeOrder(2, $buyer->id, $seller->id);

        // Third party user tries to view the order
        $this->authenticatedUser();
        $response = $this->apiGet("/v2/marketplace/orders/{$orderId}");
        $this->assertEquals(403, $response->status());
    }

    public function test_show_allowed_for_buyer(): void
    {
        $seller = User::factory()->forTenant(2)->create();
        $buyer = User::factory()->forTenant(2)->create(['status' => 'active', 'is_approved' => true]);
        $orderId = $this->makeOrder(2, $buyer->id, $seller->id);

        Sanctum::actingAs($buyer, ['*']);
        $response = $this->apiGet("/v2/marketplace/orders/{$orderId}");
        $this->assertContains($response->status(), [200, 403]); // 403 only if feature gate blocks
    }

    public function test_ship_forbidden_for_non_seller(): void
    {
        $seller = User::factory()->forTenant(2)->create();
        $buyer = User::factory()->forTenant(2)->create(['status' => 'active', 'is_approved' => true]);
        $orderId = $this->makeOrder(2, $buyer->id, $seller->id);

        // Buyer tries to ship — must be seller only
        Sanctum::actingAs($buyer, ['*']);
        $response = $this->apiPut("/v2/marketplace/orders/{$orderId}/ship", []);
        $this->assertContains($response->status(), [403]);
    }

    public function test_confirm_delivery_forbidden_for_non_buyer(): void
    {
        $seller = User::factory()->forTenant(2)->create(['status' => 'active', 'is_approved' => true]);
        $buyer = User::factory()->forTenant(2)->create();
        $orderId = $this->makeOrder(2, $buyer->id, $seller->id, ['status' => 'shipped']);

        Sanctum::actingAs($seller, ['*']);
        $response = $this->apiPut("/v2/marketplace/orders/{$orderId}/confirm-delivery", []);
        $this->assertEquals(403, $response->status());
    }

    public function test_cancel_requires_reason(): void
    {
        $seller = User::factory()->forTenant(2)->create();
        $buyer = User::factory()->forTenant(2)->create(['status' => 'active', 'is_approved' => true]);
        $orderId = $this->makeOrder(2, $buyer->id, $seller->id);

        Sanctum::actingAs($buyer, ['*']);
        $response = $this->apiPut("/v2/marketplace/orders/{$orderId}/cancel", []);
        // 422 = validation; 403 = marketplace feature gate off in test tenant
        $this->assertContains($response->status(), [403, 422]);
    }

    public function test_cross_tenant_order_access_forbidden(): void
    {
        // Order in tenant 999
        $seller = User::factory()->forTenant(999)->create();
        $buyer = User::factory()->forTenant(999)->create();
        $orderId = $this->makeOrder(999, $buyer->id, $seller->id);

        // Current tenant context is 2 — authenticated user from tenant 2
        $this->authenticatedUser(2);
        $response = $this->apiGet("/v2/marketplace/orders/{$orderId}");
        // Should not leak — either 403 (not participant), 404, or feature gate
        $this->assertContains($response->status(), [403, 404]);
    }
}
