<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Core\TenantContext;
use App\Models\Concerns\HasTenantScope;
use App\Models\MarketplaceListing;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

class MarketplaceListingTest extends TestCase
{
    use DatabaseTransactions;

    // Unique tenant id for this file to avoid cross-test contamination
    private const TENANT_ID = 99762;
    private const OTHER_TENANT_ID = 99768;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Seed our custom tenant and switch context to it.
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'             => 'Test Tenant 99762',
                'slug'             => 'test-99762',
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
                'name'             => 'Other Tenant 99768',
                'slug'             => 'test-99768',
                'domain'           => null,
                'is_active'        => true,
                'depth'            => 0,
                'allows_subtenants' => false,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);

        $this->userId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Seller Listing Test',
            'email'      => 'seller.lst99762-' . uniqid() . '@example.com',
            'password'   => 'x',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------
    // Table / trait / cast meta
    // ------------------------------------------------------------------

    public function test_table_name_is_marketplace_listings(): void
    {
        $model = new MarketplaceListing();
        $this->assertEquals('marketplace_listings', $model->getTable());
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(MarketplaceListing::class)
        );
    }

    public function test_casts_include_expected_fields(): void
    {
        $casts = (new MarketplaceListing())->getCasts();
        $this->assertEquals('decimal:2', $casts['price']);
        $this->assertEquals('decimal:2', $casts['time_credit_price']);
        $this->assertEquals('float',     $casts['latitude']);
        $this->assertEquals('float',     $casts['longitude']);
        $this->assertEquals('boolean',   $casts['shipping_available']);
        $this->assertEquals('boolean',   $casts['local_pickup']);
        $this->assertEquals('datetime',  $casts['expires_at']);
        $this->assertEquals('datetime',  $casts['renewed_at']);
        $this->assertEquals('integer',   $casts['quantity']);
        $this->assertEquals('integer',   $casts['renewal_count']);
        $this->assertEquals('array',     $casts['template_data']);
    }

    // ------------------------------------------------------------------
    // Relationship return types
    // ------------------------------------------------------------------

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new MarketplaceListing();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_category_relationship_returns_belongs_to(): void
    {
        $model = new MarketplaceListing();
        $this->assertInstanceOf(BelongsTo::class, $model->category());
    }

    public function test_images_relationship_returns_has_many(): void
    {
        $model = new MarketplaceListing();
        $this->assertInstanceOf(HasMany::class, $model->images());
    }

    public function test_offers_relationship_returns_has_many(): void
    {
        $model = new MarketplaceListing();
        $this->assertInstanceOf(HasMany::class, $model->offers());
    }

    // ------------------------------------------------------------------
    // scopeActive — requires status='active' AND moderation_status='approved'
    // ------------------------------------------------------------------

    public function test_scope_active_includes_active_approved_listing(): void
    {
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'Active Approved Listing',
            'description'       => 'desc',
            'price_type'        => 'fixed',
            'status'            => 'active',
            'moderation_status' => 'approved',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $results = MarketplaceListing::active()->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Active Approved Listing', $results->first()->title);
    }

    public function test_scope_active_excludes_draft_status(): void
    {
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'Draft Listing',
            'description'       => 'desc',
            'price_type'        => 'fixed',
            'status'            => 'draft',
            'moderation_status' => 'approved',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $results = MarketplaceListing::active()->get();
        $this->assertCount(0, $results);
    }

    public function test_scope_active_excludes_pending_moderation(): void
    {
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'Pending Moderation Listing',
            'description'       => 'desc',
            'price_type'        => 'fixed',
            'status'            => 'active',
            'moderation_status' => 'pending',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $results = MarketplaceListing::active()->get();
        $this->assertCount(0, $results);
    }

    public function test_scope_active_excludes_rejected_moderation(): void
    {
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'Rejected Listing',
            'description'       => 'desc',
            'price_type'        => 'fixed',
            'status'            => 'active',
            'moderation_status' => 'rejected',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $results = MarketplaceListing::active()->get();
        $this->assertCount(0, $results);
    }

    public function test_scope_active_with_mixed_rows_returns_only_qualifying(): void
    {
        // Two active+approved, one active+pending, one draft+approved
        DB::table('marketplace_listings')->insert([
            [
                'tenant_id'         => self::TENANT_ID,
                'user_id'           => $this->userId,
                'title'             => 'Good 1',
                'description'       => 'desc',
                'price_type'        => 'fixed',
                'status'            => 'active',
                'moderation_status' => 'approved',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'tenant_id'         => self::TENANT_ID,
                'user_id'           => $this->userId,
                'title'             => 'Good 2',
                'description'       => 'desc',
                'price_type'        => 'free',
                'status'            => 'active',
                'moderation_status' => 'approved',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'tenant_id'         => self::TENANT_ID,
                'user_id'           => $this->userId,
                'title'             => 'Active but pending mod',
                'description'       => 'desc',
                'price_type'        => 'fixed',
                'status'            => 'active',
                'moderation_status' => 'pending',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'tenant_id'         => self::TENANT_ID,
                'user_id'           => $this->userId,
                'title'             => 'Draft approved',
                'description'       => 'desc',
                'price_type'        => 'fixed',
                'status'            => 'draft',
                'moderation_status' => 'approved',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
        ]);

        $results = MarketplaceListing::active()->get();
        $this->assertCount(2, $results);
        $titles = $results->pluck('title')->all();
        $this->assertContains('Good 1', $titles);
        $this->assertContains('Good 2', $titles);
    }

    // ------------------------------------------------------------------
    // scopeFree — filters by price_type='free'
    // ------------------------------------------------------------------

    public function test_scope_free_returns_only_free_listings(): void
    {
        // Insert individually to avoid column-count mismatch across rows with different optional columns
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'Free Item',
            'description'       => 'desc',
            'price_type'        => 'free',
            'price'             => null,
            'status'            => 'active',
            'moderation_status' => 'approved',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'Fixed Price Item',
            'description'       => 'desc',
            'price_type'        => 'fixed',
            'price'             => '5.00',
            'status'            => 'active',
            'moderation_status' => 'approved',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'Negotiable Item',
            'description'       => 'desc',
            'price_type'        => 'negotiable',
            'price'             => null,
            'status'            => 'active',
            'moderation_status' => 'approved',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $results = MarketplaceListing::free()->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Free Item', $results->first()->title);
        $this->assertEquals('free', $results->first()->price_type);
    }

    public function test_scope_free_returns_empty_when_no_free_listings(): void
    {
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'Paid Only',
            'description'       => 'desc',
            'price_type'        => 'fixed',
            'price'             => '100.00',
            'status'            => 'active',
            'moderation_status' => 'approved',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $results = MarketplaceListing::free()->get();
        $this->assertCount(0, $results);
    }

    // ------------------------------------------------------------------
    // scopeNearby — haversine radius filter
    // ------------------------------------------------------------------

    public function test_scope_nearby_returns_listing_within_radius(): void
    {
        // Dublin city centre approx 53.3498, -6.2603
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'Near Dublin',
            'description'       => 'desc',
            'price_type'        => 'fixed',
            'status'            => 'active',
            'moderation_status' => 'approved',
            'latitude'          => '53.3498',
            'longitude'         => '-6.2603',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Search from same point with 1 km radius — must include the row
        $results = MarketplaceListing::nearby(53.3498, -6.2603, 1.0)->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Near Dublin', $results->first()->title);
    }

    public function test_scope_nearby_excludes_listing_outside_radius(): void
    {
        // Cork city centre approx 51.8985, -8.4756 — ~260 km from Dublin
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'Cork Listing',
            'description'       => 'desc',
            'price_type'        => 'fixed',
            'status'            => 'active',
            'moderation_status' => 'approved',
            'latitude'          => '51.8985',
            'longitude'         => '-8.4756',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Search from Dublin with 10 km radius — must NOT include Cork
        $results = MarketplaceListing::nearby(53.3498, -6.2603, 10.0)->get();
        $this->assertCount(0, $results);
    }

    public function test_scope_nearby_excludes_listing_with_null_coordinates(): void
    {
        // Listing has no coordinates — should be excluded regardless of radius
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'No Coordinates Listing',
            'description'       => 'desc',
            'price_type'        => 'fixed',
            'status'            => 'active',
            'moderation_status' => 'approved',
            'latitude'          => null,
            'longitude'         => null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $results = MarketplaceListing::nearby(53.3498, -6.2603, 10000.0)->get();
        $this->assertCount(0, $results);
    }

    // ------------------------------------------------------------------
    // Tenant isolation — global scope must exclude other tenants' rows
    // ------------------------------------------------------------------

    public function test_tenant_scope_excludes_other_tenant_listings(): void
    {
        // This tenant's listing
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'My Tenant Listing',
            'description'       => 'desc',
            'price_type'        => 'fixed',
            'status'            => 'active',
            'moderation_status' => 'approved',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Different tenant's listing inserted directly (bypasses global scope)
        DB::table('marketplace_listings')->insert([
            'tenant_id'         => self::OTHER_TENANT_ID,
            'user_id'           => $this->userId,
            'title'             => 'Other Tenant Listing',
            'description'       => 'desc',
            'price_type'        => 'free',
            'status'            => 'active',
            'moderation_status' => 'approved',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // TenantContext is set to TENANT_ID (99762) — should only see our listing
        $results = MarketplaceListing::all();

        $this->assertCount(1, $results);
        $this->assertEquals('My Tenant Listing', $results->first()->title);
    }
}
