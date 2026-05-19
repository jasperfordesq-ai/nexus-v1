<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Models\User;
use App\Services\MarketplaceRatingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MarketplaceRatingTenantScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dispute_lookup_rejects_order_from_another_tenant(): void
    {
        $foreignTenantId = $this->createTenant();
        $buyer = User::factory()->forTenant($foreignTenantId)->create();
        $seller = User::factory()->forTenant($foreignTenantId)->create();
        $orderId = $this->createOrder($foreignTenantId, (int) $buyer->id, (int) $seller->id, 'paid');

        $this->expectException(ModelNotFoundException::class);

        MarketplaceRatingService::openDispute($orderId, (int) $buyer->id, [
            'reason' => 'not_received',
            'description' => 'Package did not arrive.',
        ], $this->testTenantId);
    }

    public function test_rating_lookup_rejects_order_from_another_tenant(): void
    {
        $foreignTenantId = $this->createTenant();
        $buyer = User::factory()->forTenant($foreignTenantId)->create();
        $seller = User::factory()->forTenant($foreignTenantId)->create();
        $orderId = $this->createOrder($foreignTenantId, (int) $buyer->id, (int) $seller->id, 'completed');

        $this->expectException(ModelNotFoundException::class);

        MarketplaceRatingService::rateOrder($orderId, (int) $buyer->id, 'buyer', ['rating' => 5], $this->testTenantId);
    }

    private function createTenant(): int
    {
        $slug = 'marketplace-rating-scope-' . uniqid();

        return (int) DB::table('tenants')->insertGetId([
            'name' => 'Marketplace Rating Scope Tenant',
            'slug' => $slug,
            'domain' => $slug . '.example.test',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(int $tenantId, int $buyerId, int $sellerId, string $status): int
    {
        return (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $tenantId,
            'order_number' => 'MRS-' . uniqid(),
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'marketplace_listing_id' => null,
            'quantity' => 1,
            'unit_price' => 10.00,
            'total_price' => 10.00,
            'currency' => 'EUR',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
