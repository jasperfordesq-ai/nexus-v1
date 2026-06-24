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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

class MarketplaceOfferTest extends TestCase
{
    use DatabaseTransactions;

    // Unique tenant id for this file to avoid cross-test contamination
    private const TENANT_ID = 99763;
    private const OTHER_TENANT_ID = 99767;

    private int $buyerId;
    private int $sellerId;
    private int $listingId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Seed our custom tenant and switch context to it.
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'             => 'Test Tenant 99763',
                'slug'             => 'test-99763',
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
                'name'             => 'Other Tenant 99767',
                'slug'             => 'test-99767',
                'domain'           => null,
                'is_active'        => true,
                'depth'            => 0,
                'allows_subtenants' => false,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);

        $this->buyerId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Buyer Offer Test',
            'email'      => 'buyer.off99763-' . uniqid() . '@example.com',
            'password'   => 'x',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sellerId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Seller Offer Test',
            'email'      => 'seller.off99763-' . uniqid() . '@example.com',
            'password'   => 'x',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->sellerId,
            'title'             => 'Offer Test Listing',
            'description'       => 'desc',
            'price'             => '50.00',
            'price_type'        => 'negotiable',
            'status'            => 'active',
            'moderation_status' => 'approved',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    // ------------------------------------------------------------------
    // Table / trait / cast meta
    // ------------------------------------------------------------------

    public function test_table_name_is_marketplace_offers(): void
    {
        $model = new MarketplaceOffer();
        $this->assertEquals('marketplace_offers', $model->getTable());
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(MarketplaceOffer::class)
        );
    }

    public function test_casts_include_expected_fields(): void
    {
        $casts = (new MarketplaceOffer())->getCasts();
        $this->assertEquals('float',    $casts['amount']);
        $this->assertEquals('float',    $casts['counter_amount']);
        $this->assertEquals('datetime', $casts['expires_at']);
        $this->assertEquals('datetime', $casts['accepted_at']);
        $this->assertEquals('integer',  $casts['buyer_id']);
        $this->assertEquals('integer',  $casts['seller_id']);
    }

    // ------------------------------------------------------------------
    // Relationship return types
    // ------------------------------------------------------------------

    public function test_listing_relationship_returns_belongs_to(): void
    {
        $model = new MarketplaceOffer();
        $this->assertInstanceOf(BelongsTo::class, $model->listing());
        $this->assertEquals('marketplace_listing_id', $model->listing()->getForeignKeyName());
    }

    public function test_buyer_relationship_returns_belongs_to(): void
    {
        $model = new MarketplaceOffer();
        $this->assertInstanceOf(BelongsTo::class, $model->buyer());
        $this->assertEquals('buyer_id', $model->buyer()->getForeignKeyName());
    }

    public function test_seller_relationship_returns_belongs_to(): void
    {
        $model = new MarketplaceOffer();
        $this->assertInstanceOf(BelongsTo::class, $model->seller());
        $this->assertEquals('seller_id', $model->seller()->getForeignKeyName());
    }

    // ------------------------------------------------------------------
    // scopePending — only 'pending' status
    // ------------------------------------------------------------------

    public function test_scope_pending_returns_only_pending_offers(): void
    {
        DB::table('marketplace_offers')->insert([
            [
                'tenant_id'                => self::TENANT_ID,
                'marketplace_listing_id'   => $this->listingId,
                'buyer_id'                 => $this->buyerId,
                'seller_id'                => $this->sellerId,
                'amount'                   => '40.00',
                'status'                   => 'pending',
                'created_at'               => now(),
                'updated_at'               => now(),
            ],
            [
                'tenant_id'                => self::TENANT_ID,
                'marketplace_listing_id'   => $this->listingId,
                'buyer_id'                 => $this->buyerId,
                'seller_id'                => $this->sellerId,
                'amount'                   => '35.00',
                'status'                   => 'accepted',
                'created_at'               => now(),
                'updated_at'               => now(),
            ],
            [
                'tenant_id'                => self::TENANT_ID,
                'marketplace_listing_id'   => $this->listingId,
                'buyer_id'                 => $this->buyerId,
                'seller_id'                => $this->sellerId,
                'amount'                   => '30.00',
                'status'                   => 'declined',
                'created_at'               => now(),
                'updated_at'               => now(),
            ],
        ]);

        $results = MarketplaceOffer::pending()->get();
        $this->assertCount(1, $results);
        $this->assertEquals('pending', $results->first()->status);
        $this->assertEquals(40.0, $results->first()->amount);
    }

    public function test_scope_pending_returns_empty_when_no_pending_offers(): void
    {
        DB::table('marketplace_offers')->insert([
            'tenant_id'              => self::TENANT_ID,
            'marketplace_listing_id' => $this->listingId,
            'buyer_id'               => $this->buyerId,
            'seller_id'              => $this->sellerId,
            'amount'                 => '45.00',
            'status'                 => 'expired',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $results = MarketplaceOffer::pending()->get();
        $this->assertCount(0, $results);
    }

    // ------------------------------------------------------------------
    // scopeActive — includes 'pending' and 'countered' only
    // ------------------------------------------------------------------

    public function test_scope_active_includes_pending_and_countered(): void
    {
        // Insert individually to avoid column-count mismatch in batch inserts
        DB::table('marketplace_offers')->insert([
            'tenant_id'              => self::TENANT_ID,
            'marketplace_listing_id' => $this->listingId,
            'buyer_id'               => $this->buyerId,
            'seller_id'              => $this->sellerId,
            'amount'                 => '40.00',
            'counter_amount'         => null,
            'status'                 => 'pending',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
        DB::table('marketplace_offers')->insert([
            'tenant_id'              => self::TENANT_ID,
            'marketplace_listing_id' => $this->listingId,
            'buyer_id'               => $this->buyerId,
            'seller_id'              => $this->sellerId,
            'amount'                 => '38.00',
            'counter_amount'         => '42.00',
            'status'                 => 'countered',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $results = MarketplaceOffer::active()->get();
        $this->assertCount(2, $results);
        $statuses = $results->pluck('status')->sort()->values()->all();
        $this->assertEquals(['countered', 'pending'], $statuses);
    }

    public function test_scope_active_excludes_accepted_declined_expired_withdrawn(): void
    {
        $terminalStatuses = ['accepted', 'declined', 'expired', 'withdrawn'];
        foreach ($terminalStatuses as $i => $status) {
            DB::table('marketplace_offers')->insert([
                'tenant_id'              => self::TENANT_ID,
                'marketplace_listing_id' => $this->listingId,
                'buyer_id'               => $this->buyerId,
                'seller_id'              => $this->sellerId,
                'amount'                 => '10.00',
                'status'                 => $status,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }

        $results = MarketplaceOffer::active()->get();
        $this->assertCount(0, $results);
    }

    public function test_scope_active_mixed_returns_only_open_offers(): void
    {
        // Insert individually to avoid column-count mismatch in batch inserts
        DB::table('marketplace_offers')->insert([  // Should appear
            'tenant_id'              => self::TENANT_ID,
            'marketplace_listing_id' => $this->listingId,
            'buyer_id'               => $this->buyerId,
            'seller_id'              => $this->sellerId,
            'amount'                 => '45.00',
            'counter_amount'         => null,
            'status'                 => 'pending',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
        DB::table('marketplace_offers')->insert([  // Should NOT appear
            'tenant_id'              => self::TENANT_ID,
            'marketplace_listing_id' => $this->listingId,
            'buyer_id'               => $this->buyerId,
            'seller_id'              => $this->sellerId,
            'amount'                 => '44.00',
            'counter_amount'         => null,
            'status'                 => 'accepted',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
        DB::table('marketplace_offers')->insert([  // Should appear
            'tenant_id'              => self::TENANT_ID,
            'marketplace_listing_id' => $this->listingId,
            'buyer_id'               => $this->buyerId,
            'seller_id'              => $this->sellerId,
            'amount'                 => '43.00',
            'counter_amount'         => '47.00',
            'status'                 => 'countered',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
        DB::table('marketplace_offers')->insert([  // Should NOT appear
            'tenant_id'              => self::TENANT_ID,
            'marketplace_listing_id' => $this->listingId,
            'buyer_id'               => $this->buyerId,
            'seller_id'              => $this->sellerId,
            'amount'                 => '40.00',
            'counter_amount'         => null,
            'status'                 => 'withdrawn',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $results = MarketplaceOffer::active()->get();
        $this->assertCount(2, $results);
        foreach ($results as $offer) {
            $this->assertContains($offer->status, ['pending', 'countered']);
        }
    }

    // ------------------------------------------------------------------
    // Tenant isolation — global scope excludes other tenants' offers
    // ------------------------------------------------------------------

    public function test_tenant_scope_excludes_other_tenant_offers(): void
    {
        // This tenant's offer
        DB::table('marketplace_offers')->insert([
            'tenant_id'              => self::TENANT_ID,
            'marketplace_listing_id' => $this->listingId,
            'buyer_id'               => $this->buyerId,
            'seller_id'              => $this->sellerId,
            'amount'                 => '50.00',
            'status'                 => 'pending',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // Another tenant's offer — inserted bypassing global scope via DB::table()
        DB::table('marketplace_offers')->insert([
            'tenant_id'              => self::OTHER_TENANT_ID,
            'marketplace_listing_id' => $this->listingId,  // same listing, different tenant
            'buyer_id'               => $this->buyerId,
            'seller_id'              => $this->sellerId,
            'amount'                 => '99.00',
            'status'                 => 'pending',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // TenantContext is set to TENANT_ID (99763) — must only see own offer
        $results = MarketplaceOffer::all();

        $this->assertCount(1, $results);
        $this->assertEquals(50.0, $results->first()->amount);
    }

    // ------------------------------------------------------------------
    // Amount cast: stored and retrieved as float
    // ------------------------------------------------------------------

    public function test_amount_and_counter_amount_cast_to_float(): void
    {
        $offerId = DB::table('marketplace_offers')->insertGetId([
            'tenant_id'              => self::TENANT_ID,
            'marketplace_listing_id' => $this->listingId,
            'buyer_id'               => $this->buyerId,
            'seller_id'              => $this->sellerId,
            'amount'                 => '37.50',
            'counter_amount'         => '42.75',
            'status'                 => 'countered',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $offer = MarketplaceOffer::find($offerId);

        $this->assertIsFloat($offer->amount);
        $this->assertIsFloat($offer->counter_amount);
        $this->assertEquals(37.50, $offer->amount);
        $this->assertEquals(42.75, $offer->counter_amount);
    }

    // ------------------------------------------------------------------
    // Combined scope: pending() + active() are consistent for 'pending' rows
    // ------------------------------------------------------------------

    public function test_pending_scope_is_subset_of_active_scope(): void
    {
        DB::table('marketplace_offers')->insert([
            'tenant_id'              => self::TENANT_ID,
            'marketplace_listing_id' => $this->listingId,
            'buyer_id'               => $this->buyerId,
            'seller_id'              => $this->sellerId,
            'amount'                 => '30.00',
            'counter_amount'         => null,
            'status'                 => 'pending',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
        DB::table('marketplace_offers')->insert([
            'tenant_id'              => self::TENANT_ID,
            'marketplace_listing_id' => $this->listingId,
            'buyer_id'               => $this->buyerId,
            'seller_id'              => $this->sellerId,
            'amount'                 => '28.00',
            'counter_amount'         => '32.00',
            'status'                 => 'countered',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $pendingIds = MarketplaceOffer::pending()->pluck('id');
        $activeIds  = MarketplaceOffer::active()->pluck('id');

        // Every pending offer must also appear in the active scope
        foreach ($pendingIds as $id) {
            $this->assertContains($id, $activeIds->all());
        }

        // Active scope has more (includes countered) than pending scope
        $this->assertGreaterThan($pendingIds->count(), $activeIds->count());
    }
}
