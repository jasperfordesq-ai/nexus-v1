<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\MarketplaceInventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MarketplaceInventoryNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_low_stock_bell_only_sends_when_inventory_crosses_threshold(): void
    {
        $tenantId = $this->testTenantId;
        TenantContext::setById($tenantId);

        $seller = User::factory()->forTenant($tenantId)->create([
            'preferred_language' => 'en',
        ]);
        $listingId = $this->createListing($tenantId, (int) $seller->id, 4, 3);

        MarketplaceInventoryService::decrementForOrder($listingId, 1);
        MarketplaceInventoryService::decrementForOrder($listingId, 1);

        $this->assertSame(1, DB::table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $seller->id)
            ->where('type', 'marketplace_low_stock')
            ->count());
    }

    public function test_low_stock_bell_uses_listing_tenant_not_ambient_context(): void
    {
        $listingTenantId = 999;
        $seller = User::factory()->forTenant($listingTenantId)->create([
            'preferred_language' => 'en',
        ]);
        $listingId = $this->createListing($listingTenantId, (int) $seller->id, 2, 1);

        TenantContext::setById($this->testTenantId);
        MarketplaceInventoryService::decrementForOrder($listingId, 1);

        $this->assertSame(1, DB::table('notifications')
            ->where('tenant_id', $listingTenantId)
            ->where('user_id', $seller->id)
            ->where('type', 'marketplace_low_stock')
            ->count());
        $this->assertSame($this->testTenantId, TenantContext::getId());
    }

    private function createListing(int $tenantId, int $sellerId, int $inventoryCount, int $lowStockThreshold): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $sellerId,
            'title' => 'Low stock audit item',
            'description' => 'A listing used to verify inventory notification reliability.',
            'price' => 10.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'inventory_count' => $inventoryCount,
            'low_stock_threshold' => $lowStockThreshold,
            'is_oversold_protected' => 1,
            'shipping_available' => 0,
            'local_pickup' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
