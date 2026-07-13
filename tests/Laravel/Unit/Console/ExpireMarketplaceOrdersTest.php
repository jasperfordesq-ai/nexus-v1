<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Models\User;
use App\Services\MarketplaceSellerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class ExpireMarketplaceOrdersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_expired_unpaid_order_is_cancelled_and_inventory_is_released_once(): void
    {
        $buyer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Reserved expiring item',
            'description' => 'Checkout expiry regression fixture.',
            'price' => 12.0,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'inventory_count' => 0,
            'status' => 'sold',
            'moderation_status' => 'approved',
            'seller_type' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_number' => 'MKT-EXPIRE-' . strtoupper(uniqid('', true)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'marketplace_listing_id' => $listingId,
            'quantity' => 1,
            'unit_price' => 12.0,
            'total_price' => 12.0,
            'currency' => 'EUR',
            'status' => 'pending_payment',
            'payment_expires_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('marketplace:expire-pending-orders', ['--limit' => 10])
            ->expectsOutputToContain('expired=1')
            ->assertSuccessful();

        $this->assertDatabaseHas('marketplace_orders', [
            'id' => $orderId,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('marketplace_listings', [
            'id' => $listingId,
            'inventory_count' => 1,
        ]);

        $this->artisan('marketplace:expire-pending-orders', ['--limit' => 10])
            ->expectsOutputToContain('expired=0')
            ->assertSuccessful();
        $this->assertSame(1, (int) DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->value('inventory_count'));
    }

    public function test_elapsed_delivery_window_completes_order_and_seller_stats_once(): void
    {
        $buyer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $profileId = (int) DB::table('marketplace_seller_profiles')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'seller_type' => 'private',
            'total_sales' => 0,
            'total_revenue' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Delivered item',
            'description' => 'Auto-completion regression fixture.',
            'price' => 18.0,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'status' => 'sold',
            'moderation_status' => 'approved',
            'seller_type' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_number' => 'MKT-COMPLETE-' . strtoupper(uniqid('', true)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'marketplace_listing_id' => $listingId,
            'quantity' => 1,
            'unit_price' => 18.0,
            'total_price' => 18.0,
            'currency' => 'EUR',
            'status' => 'delivered',
            'auto_complete_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('marketplace:complete-orders', ['--limit' => 10])
            ->expectsOutput('1')
            ->assertSuccessful();
        $this->artisan('marketplace:complete-orders', ['--limit' => 10])
            ->expectsOutput('0')
            ->assertSuccessful();

        $this->assertDatabaseHas('marketplace_orders', [
            'id' => $orderId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('marketplace_seller_profiles', [
            'id' => $profileId,
            'total_sales' => 1,
        ]);
        $this->assertSame(0.0, (float) DB::table('marketplace_seller_profiles')
            ->where('id', $profileId)
            ->value('total_revenue'), 'The legacy scalar must not mix currencies.');
        $stats = MarketplaceSellerService::getDashboardStats((int) $seller->id);
        $this->assertSame(18.0, $stats['total_revenue']);
        $this->assertSame('EUR', $stats['revenue_currency']);
        $this->assertSame([
            ['currency' => 'EUR', 'total' => 18.0],
        ], $stats['revenue_by_currency']);
    }
}
