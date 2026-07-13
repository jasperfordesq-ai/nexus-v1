<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\MarketplaceSellerService;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class MarketplaceRevenueCurrencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seller_and_admin_totals_preserve_currency_boundaries(): void
    {
        TenantContext::setById($this->testTenantId);
        $seller = User::factory()->forTenant($this->testTenantId)->admin()->create(['status' => 'active']);
        $buyer = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Mixed currency revenue fixture',
            'description' => 'Currency totals must never be added together.',
            'price' => 10,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'shipping_available' => false,
            'local_pickup' => true,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'sold',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([['EUR', 10.00], ['USD', 20.00]] as $index => [$currency, $total]) {
            DB::table('marketplace_orders')->insert([
                'tenant_id' => $this->testTenantId,
                'order_number' => 'MIXED-' . $index,
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'marketplace_listing_id' => $listingId,
                'quantity' => 1,
                'unit_price' => $total,
                'total_price' => $total,
                'currency' => $currency,
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sellerStats = MarketplaceSellerService::getDashboardStats($seller->id);
        $this->assertNull($sellerStats['total_revenue']);
        $this->assertNull($sellerStats['revenue_currency']);
        $this->assertSame([
            ['currency' => 'EUR', 'total' => 10.0],
            ['currency' => 'USD', 'total' => 20.0],
        ], $sellerStats['revenue_by_currency']);

        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['marketplace'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode($features),
        ]);
        TenantContext::setById($this->testTenantId);
        Sanctum::actingAs($seller, ['*']);

        $response = $this->apiGet('/v2/admin/marketplace/dashboard');
        $response->assertOk();
        $response->assertJsonPath('data.revenue', null);
        $response->assertJsonPath('data.currency', null);
        $response->assertJsonPath('data.revenue_by_currency.0.currency', 'EUR');
        $response->assertJsonPath('data.revenue_by_currency.0.total', 10);
        $response->assertJsonPath('data.revenue_by_currency.1.currency', 'USD');
        $response->assertJsonPath('data.revenue_by_currency.1.total', 20);
    }
}
