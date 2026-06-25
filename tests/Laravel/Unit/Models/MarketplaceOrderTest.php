<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Core\TenantContext;
use App\Models\Concerns\HasTenantScope;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Models\MarketplaceOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

class MarketplaceOrderTest extends TestCase
{
    use DatabaseTransactions;

    // Unique tenant id for this file to avoid cross-test contamination
    private const TENANT_ID = 99761;
    private const OTHER_TENANT_ID = 99769;

    private int $userId1;
    private int $userId2;
    private int $listingId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Seed our custom tenant and switch context to it.
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'             => 'Test Tenant 99761',
                'slug'             => 'test-99761',
                'domain'           => null,
                'is_active'        => true,
                'depth'            => 0,
                'allows_subtenants' => false,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        DB::table('tenants')->updateOrInsert(
            ['id' => self::OTHER_TENANT_ID],
            [
                'name'             => 'Other Tenant 99769',
                'slug'             => 'test-99769',
                'domain'           => null,
                'is_active'        => true,
                'depth'            => 0,
                'allows_subtenants' => false,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);

        $this->userId1 = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Buyer One',
            'email'      => 'buyer1.ord99761-' . uniqid() . '@example.com',
            'password'   => 'x',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->userId2 = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Seller One',
            'email'      => 'seller1.ord99761-' . uniqid() . '@example.com',
            'password'   => 'x',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed a listing (needed for FK on marketplace_orders.marketplace_listing_id).
        $this->listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId2,
            'title'             => 'Test Listing for Orders',
            'description'       => 'desc',
            'price'             => '10.00',
            'price_type'        => 'fixed',
            'status'            => 'active',
            'moderation_status' => 'approved',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    // ------------------------------------------------------------------
    // Table / trait / cast meta
    // ------------------------------------------------------------------

    public function test_table_name_is_marketplace_orders(): void
    {
        $model = new MarketplaceOrder();
        $this->assertEquals('marketplace_orders', $model->getTable());
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(MarketplaceOrder::class)
        );
    }

    public function test_casts_include_decimal_and_datetime_fields(): void
    {
        $casts = (new MarketplaceOrder())->getCasts();
        $this->assertEquals('decimal:2', $casts['unit_price']);
        $this->assertEquals('decimal:2', $casts['total_price']);
        $this->assertEquals('decimal:2', $casts['shipping_cost']);
        $this->assertEquals('float',     $casts['time_credits_used']);
        $this->assertEquals('datetime',  $casts['escrow_released_at']);
        $this->assertEquals('datetime',  $casts['buyer_confirmed_at']);
        $this->assertEquals('datetime',  $casts['seller_confirmed_at']);
        $this->assertEquals('datetime',  $casts['cancelled_at']);
        $this->assertEquals('integer',   $casts['quantity']);
        $this->assertEquals('array',     $casts['delivery_address']);
    }

    // ------------------------------------------------------------------
    // Relationship return types
    // ------------------------------------------------------------------

    public function test_buyer_relationship_returns_belongs_to(): void
    {
        $model = new MarketplaceOrder();
        $this->assertInstanceOf(BelongsTo::class, $model->buyer());
        $this->assertEquals('buyer_id', $model->buyer()->getForeignKeyName());
    }

    public function test_seller_relationship_returns_belongs_to(): void
    {
        $model = new MarketplaceOrder();
        $this->assertInstanceOf(BelongsTo::class, $model->seller());
        $this->assertEquals('seller_id', $model->seller()->getForeignKeyName());
    }

    public function test_listing_relationship_returns_belongs_to(): void
    {
        $model = new MarketplaceOrder();
        $this->assertInstanceOf(BelongsTo::class, $model->listing());
    }

    public function test_ratings_relationship_returns_has_many(): void
    {
        $model = new MarketplaceOrder();
        $this->assertInstanceOf(HasMany::class, $model->ratings());
    }

    public function test_dispute_relationship_returns_has_one(): void
    {
        $model = new MarketplaceOrder();
        $this->assertInstanceOf(HasOne::class, $model->dispute());
    }

    // ------------------------------------------------------------------
    // scopeForBuyer — filters to the given buyer_id only
    // ------------------------------------------------------------------

    public function test_scope_for_buyer_returns_only_buyer_rows(): void
    {
        // Row for buyer1
        DB::table('marketplace_orders')->insert([
            'tenant_id'                => self::TENANT_ID,
            'order_number'             => 'ORD-B1-001',
            'buyer_id'                 => $this->userId1,
            'seller_id'                => $this->userId2,
            'marketplace_listing_id'   => $this->listingId,
            'quantity'                 => 1,
            'unit_price'               => '10.00',
            'total_price'              => '10.00',
            'status'                   => 'paid',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        // Row for buyer2 (seller acting as buyer here)
        DB::table('marketplace_orders')->insert([
            'tenant_id'                => self::TENANT_ID,
            'order_number'             => 'ORD-B2-001',
            'buyer_id'                 => $this->userId2,
            'seller_id'                => $this->userId1,
            'marketplace_listing_id'   => $this->listingId,
            'quantity'                 => 1,
            'unit_price'               => '5.00',
            'total_price'              => '5.00',
            'status'                   => 'paid',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $results = MarketplaceOrder::forBuyer($this->userId1)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($this->userId1, $results->first()->buyer_id);
    }

    public function test_scope_for_buyer_excludes_other_buyers(): void
    {
        DB::table('marketplace_orders')->insert([
            'tenant_id'                => self::TENANT_ID,
            'order_number'             => 'ORD-B2-002',
            'buyer_id'                 => $this->userId2,
            'seller_id'                => $this->userId1,
            'marketplace_listing_id'   => $this->listingId,
            'quantity'                 => 1,
            'unit_price'               => '5.00',
            'total_price'              => '5.00',
            'status'                   => 'pending_payment',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $results = MarketplaceOrder::forBuyer($this->userId1)->get();
        $this->assertCount(0, $results);
    }

    // ------------------------------------------------------------------
    // scopeForSeller — filters to the given seller_id only
    // ------------------------------------------------------------------

    public function test_scope_for_seller_returns_only_seller_rows(): void
    {
        // Order where userId2 is seller
        DB::table('marketplace_orders')->insert([
            'tenant_id'                => self::TENANT_ID,
            'order_number'             => 'ORD-S1-001',
            'buyer_id'                 => $this->userId1,
            'seller_id'                => $this->userId2,
            'marketplace_listing_id'   => $this->listingId,
            'quantity'                 => 1,
            'unit_price'               => '20.00',
            'total_price'              => '20.00',
            'status'                   => 'shipped',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        // Order where userId1 is seller
        DB::table('marketplace_orders')->insert([
            'tenant_id'                => self::TENANT_ID,
            'order_number'             => 'ORD-S2-001',
            'buyer_id'                 => $this->userId2,
            'seller_id'                => $this->userId1,
            'marketplace_listing_id'   => $this->listingId,
            'quantity'                 => 1,
            'unit_price'               => '15.00',
            'total_price'              => '15.00',
            'status'                   => 'delivered',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $results = MarketplaceOrder::forSeller($this->userId2)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($this->userId2, $results->first()->seller_id);
    }

    // ------------------------------------------------------------------
    // scopeActive — excludes cancelled and refunded statuses
    // ------------------------------------------------------------------

    public function test_scope_active_includes_non_terminal_statuses(): void
    {
        $activeStatuses = ['pending_payment', 'paid', 'shipped', 'delivered', 'completed', 'disputed'];

        foreach ($activeStatuses as $i => $status) {
            DB::table('marketplace_orders')->insert([
                'tenant_id'              => self::TENANT_ID,
                'order_number'           => "ORD-ACT-{$i}",
                'buyer_id'               => $this->userId1,
                'seller_id'              => $this->userId2,
                'marketplace_listing_id' => $this->listingId,
                'quantity'               => 1,
                'unit_price'             => '10.00',
                'total_price'            => '10.00',
                'status'                 => $status,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }

        $results = MarketplaceOrder::active()->get();
        $this->assertCount(count($activeStatuses), $results);
    }

    public function test_scope_active_excludes_cancelled_and_refunded(): void
    {
        // Only cancelled and refunded — should return 0
        DB::table('marketplace_orders')->insert([
            'tenant_id'              => self::TENANT_ID,
            'order_number'           => 'ORD-CANCELLED-001',
            'buyer_id'               => $this->userId1,
            'seller_id'              => $this->userId2,
            'marketplace_listing_id' => $this->listingId,
            'quantity'               => 1,
            'unit_price'             => '10.00',
            'total_price'            => '10.00',
            'status'                 => 'cancelled',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        DB::table('marketplace_orders')->insert([
            'tenant_id'              => self::TENANT_ID,
            'order_number'           => 'ORD-REFUNDED-001',
            'buyer_id'               => $this->userId1,
            'seller_id'              => $this->userId2,
            'marketplace_listing_id' => $this->listingId,
            'quantity'               => 1,
            'unit_price'             => '10.00',
            'total_price'            => '10.00',
            'status'                 => 'refunded',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $results = MarketplaceOrder::active()->get();
        $this->assertCount(0, $results);
    }

    public function test_scope_active_mixed_includes_only_non_terminal(): void
    {
        // One paid (should be included), one cancelled (excluded)
        DB::table('marketplace_orders')->insert([
            'tenant_id'              => self::TENANT_ID,
            'order_number'           => 'ORD-MIX-PAID',
            'buyer_id'               => $this->userId1,
            'seller_id'              => $this->userId2,
            'marketplace_listing_id' => $this->listingId,
            'quantity'               => 1,
            'unit_price'             => '10.00',
            'total_price'            => '10.00',
            'status'                 => 'paid',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        DB::table('marketplace_orders')->insert([
            'tenant_id'              => self::TENANT_ID,
            'order_number'           => 'ORD-MIX-CANCELLED',
            'buyer_id'               => $this->userId1,
            'seller_id'              => $this->userId2,
            'marketplace_listing_id' => $this->listingId,
            'quantity'               => 1,
            'unit_price'             => '10.00',
            'total_price'            => '10.00',
            'status'                 => 'cancelled',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $results = MarketplaceOrder::active()->get();
        $this->assertCount(1, $results);
        $this->assertEquals('paid', $results->first()->status);
    }

    // ------------------------------------------------------------------
    // Tenant isolation — global scope must exclude other tenants' rows
    // ------------------------------------------------------------------

    public function test_tenant_scope_excludes_other_tenant_rows(): void
    {
        // Row belonging to this tenant (99761)
        DB::table('marketplace_orders')->insert([
            'tenant_id'              => self::TENANT_ID,
            'order_number'           => 'ORD-MINE-001',
            'buyer_id'               => $this->userId1,
            'seller_id'              => $this->userId2,
            'marketplace_listing_id' => $this->listingId,
            'quantity'               => 1,
            'unit_price'             => '10.00',
            'total_price'            => '10.00',
            'status'                 => 'paid',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // Row belonging to a different tenant — inserted raw to bypass global scope
        DB::table('marketplace_orders')->insert([
            'tenant_id'              => self::OTHER_TENANT_ID,
            'order_number'           => 'ORD-OTHER-001',
            'buyer_id'               => $this->userId1,
            'seller_id'              => $this->userId2,
            'marketplace_listing_id' => $this->listingId,
            'quantity'               => 1,
            'unit_price'             => '99.00',
            'total_price'            => '99.00',
            'status'                 => 'paid',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // TenantContext is already set to TENANT_ID (99761) by setUp()
        $results = MarketplaceOrder::all();

        $this->assertCount(1, $results);
        $this->assertEquals('ORD-MINE-001', $results->first()->order_number);
    }

    // ------------------------------------------------------------------
    // Casts: delivery_address is cast to array
    // ------------------------------------------------------------------

    public function test_delivery_address_cast_to_array(): void
    {
        $orderId = DB::table('marketplace_orders')->insertGetId([
            'tenant_id'              => self::TENANT_ID,
            'order_number'           => 'ORD-CAST-001',
            'buyer_id'               => $this->userId1,
            'seller_id'              => $this->userId2,
            'marketplace_listing_id' => $this->listingId,
            'quantity'               => 1,
            'unit_price'             => '10.00',
            'total_price'            => '10.00',
            'status'                 => 'paid',
            'delivery_address'       => json_encode(['street' => '1 Main St', 'city' => 'Dublin']),
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $order = MarketplaceOrder::find($orderId);
        $this->assertIsArray($order->delivery_address);
        $this->assertEquals('1 Main St', $order->delivery_address['street']);
        $this->assertEquals('Dublin', $order->delivery_address['city']);
    }

    // ------------------------------------------------------------------
    // Precision: total_price stored and retrieved correctly (decimal:2)
    // ------------------------------------------------------------------

    public function test_total_price_precision_decimal_2(): void
    {
        $orderId = DB::table('marketplace_orders')->insertGetId([
            'tenant_id'              => self::TENANT_ID,
            'order_number'           => 'ORD-PREC-001',
            'buyer_id'               => $this->userId1,
            'seller_id'              => $this->userId2,
            'marketplace_listing_id' => $this->listingId,
            'quantity'               => 3,
            'unit_price'             => '7.33',
            'total_price'            => '21.99',
            'status'                 => 'pending_payment',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $order = MarketplaceOrder::find($orderId);
        // Cast is decimal:2 — Eloquent returns string representation like '21.99'
        $this->assertEquals('21.99', $order->total_price);
        $this->assertEquals('7.33', $order->unit_price);
    }
}
