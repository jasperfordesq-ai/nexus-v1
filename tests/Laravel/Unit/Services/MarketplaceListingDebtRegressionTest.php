<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Services\MarketplaceListingService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MarketplaceListingDebtRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::setById($this->testTenantId);
        $this->seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    public function test_repeated_save_and_unsave_requests_do_not_inflate_or_underflow_counter(): void
    {
        $buyer = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $listing = $this->listing('Idempotent bookmark', 53.35, -6.26);

        MarketplaceListingService::saveListing($buyer->id, $listing->id);
        MarketplaceListingService::saveListing($buyer->id, $listing->id);

        $this->assertSame(1, $listing->fresh()->saves_count);
        $this->assertSame(1, DB::table('marketplace_saved_listings')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $buyer->id)
            ->where('marketplace_listing_id', $listing->id)
            ->count());

        MarketplaceListingService::unsaveListing($buyer->id, $listing->id);
        MarketplaceListingService::unsaveListing($buyer->id, $listing->id);

        $this->assertSame(0, $listing->fresh()->saves_count);
    }

    public function test_nearby_search_prefilters_coordinates_and_handles_antimeridian(): void
    {
        $near = $this->listing('Across the antimeridian', 0.0, 179.9);
        $far = $this->listing('Far from the antimeridian', 0.0, 170.0);
        $queries = [];

        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'marketplace_listings')) {
                $queries[] = strtolower($query->sql);
            }
        });

        $items = MarketplaceListingService::getNearby(0.0, -179.9, 30.0, 20);
        $ids = array_column($items, 'id');

        $this->assertContains($near->id, $ids);
        $this->assertNotContains($far->id, $ids);
        $this->assertTrue(collect($queries)->contains(
            static fn (string $sql): bool => str_contains($sql, '`latitude` between')
                && str_contains($sql, '`longitude` >=')
                && str_contains($sql, '`longitude` <=')
        ), 'Expected the exact-distance query to include an index-friendly bounding box.');
    }

    public function test_price_and_featured_ordering_avoid_expression_filesorts(): void
    {
        $this->listing('Indexed price ordering', 53.35, -6.26, 12.50);
        $queries = [];

        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'from `marketplace_listings`')) {
                $queries[] = strtolower($query->sql);
            }
        });

        MarketplaceListingService::getAll([
            'user_id' => $this->seller->id,
            'current_user_id' => $this->seller->id,
            'sort' => 'price_asc',
            'featured_first' => true,
            'limit' => 20,
        ]);

        $browseSql = collect($queries)->first(
            static fn (string $sql): bool => str_contains($sql, 'order by')
        );
        $this->assertIsString($browseSql);
        $this->assertStringContainsString('`promoted_until` desc', $browseSql);
        $this->assertStringContainsString('`price` asc', $browseSql);
        $this->assertStringNotContainsString('coalesce(', $browseSql);
        $this->assertStringNotContainsString('promoted_until >', $browseSql);
    }

    public function test_price_cursor_preserves_native_null_order_without_coalesce(): void
    {
        $this->listing('Free item A', 53.35, -6.26, null);
        $this->listing('Free item B', 53.35, -6.26, null);
        $this->listing('Ten euro item', 53.35, -6.26, 10.0);
        $this->listing('Twenty euro item', 53.35, -6.26, 20.0);
        $prices = [];
        $cursor = null;

        do {
            $page = MarketplaceListingService::getAll([
                'user_id' => $this->seller->id,
                'current_user_id' => $this->seller->id,
                'sort' => 'price_asc',
                'cursor' => $cursor,
                'limit' => 1,
            ]);
            if ($page['items'] !== []) {
                $prices[] = $page['items'][0]['price'];
            }
            $cursor = $page['cursor'];
        } while ($cursor !== null);

        $this->assertSame([null, null, 10.0, 20.0], $prices);
    }

    public function test_featured_first_cursor_carries_promotion_order_without_skips_or_duplicates(): void
    {
        $promotionA = now()->addDays(3)->startOfSecond();
        $promotionB = now()->addDay()->startOfSecond();
        $listings = [
            $this->listing('Featured A1', 53.35, -6.26, 10.0),
            $this->listing('Featured A2', 53.35, -6.26, 20.0),
            $this->listing('Featured B', 53.35, -6.26, 5.0),
            $this->listing('Unfeatured A', 53.35, -6.26, 1.0),
            $this->listing('Unfeatured B', 53.35, -6.26, 2.0),
        ];
        $listings[0]->update(['promoted_until' => $promotionA]);
        $listings[1]->update(['promoted_until' => $promotionA]);
        $listings[2]->update(['promoted_until' => $promotionB]);

        $expected = array_column(MarketplaceListingService::getAll([
            'user_id' => $this->seller->id,
            'current_user_id' => $this->seller->id,
            'featured_first' => true,
            'sort' => 'price_asc',
            'limit' => 100,
        ])['items'], 'id');

        $actual = [];
        $cursor = null;
        do {
            $page = MarketplaceListingService::getAll([
                'user_id' => $this->seller->id,
                'current_user_id' => $this->seller->id,
                'featured_first' => true,
                'sort' => 'price_asc',
                'limit' => 1,
                'cursor' => $cursor,
            ]);
            if ($page['items'] !== []) {
                $actual[] = $page['items'][0]['id'];
            }
            $cursor = $page['cursor'];
        } while ($cursor !== null);

        $this->assertSame($expected, $actual);
        $this->assertCount(count($actual), array_unique($actual));
    }

    private function listing(
        string $title,
        float $latitude,
        float $longitude,
        ?float $price = 0.0
    ): MarketplaceListing {
        $id = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $this->seller->id,
            'title' => $title,
            'description' => 'Marketplace performance regression fixture.',
            'price' => $price,
            'price_currency' => 'EUR',
            'price_type' => $price !== null && $price > 0 ? 'fixed' : 'free',
            'quantity' => 1,
            'location' => 'Test location',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'shipping_available' => false,
            'local_pickup' => true,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'saves_count' => 0,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return MarketplaceListing::findOrFail($id);
    }
}
