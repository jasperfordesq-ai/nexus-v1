<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Smoke tests for MarketplaceListingController.
 */
class MarketplaceListingControllerTest extends TestCase
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

    private function enableMarketplaceFeature(): void
    {
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode(['marketplace' => true])]);

        TenantContext::setById($this->testTenantId);
    }

    private function createMarketplaceCategory(string $name = 'Repair Tools'): int
    {
        return DB::table('marketplace_categories')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMarketplaceListing(User $seller, int $categoryId, array $overrides = []): int
    {
        return DB::table('marketplace_listings')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'category_id' => $categoryId,
            'title' => 'Community repair kit',
            'tagline' => 'A practical kit for local repair sessions.',
            'description' => 'A public marketplace item for a community repair kit.',
            'price' => 25,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'time_credit_price' => 2,
            'condition' => 'good',
            'quantity' => 1,
            'location' => 'Remote or local',
            'latitude' => null,
            'longitude' => null,
            'shipping_available' => true,
            'local_pickup' => true,
            'delivery_method' => 'both',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'expires_at' => '2030-07-10 00:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function addMarketplaceImage(int $listingId, string $url, bool $primary = true, int $sortOrder = 0): void
    {
        DB::table('marketplace_images')->insert([
            'tenant_id' => $this->testTenantId,
            'marketplace_listing_id' => $listingId,
            'image_url' => $url,
            'thumbnail_url' => $url,
            'alt_text' => 'Community repair kit',
            'sort_order' => $sortOrder,
            'is_primary' => $primary,
            'created_at' => now(),
        ]);
    }

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/marketplace/listings', []);
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_savedListings_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/listings/saved');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_destroy_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/marketplace/listings/1');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->apiGet('/v2/marketplace/listings');
        $response->assertStatus(401);
    }

    public function test_index_keeps_marketplace_public_contract_opt_in(): void
    {
        $this->enableMarketplaceFeature();
        $this->authenticatedUser();
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'first_name' => 'Market',
            'last_name' => 'Seller',
            'status' => 'active',
            'is_approved' => true,
        ]);
        $categoryId = $this->createMarketplaceCategory();
        $listingId = $this->createMarketplaceListing($seller, $categoryId);
        $this->addMarketplaceImage($listingId, '/uploads/tenants/hour-timebank/marketplace/repair-kit.jpg');

        $defaultResponse = $this->apiGet('/v2/marketplace/listings?limit=1');
        $defaultResponse->assertOk();
        $this->assertArrayNotHasKey('public_contract', $defaultResponse->json('data.0'));

        $contractResponse = $this->apiGet('/v2/marketplace/listings?limit=1', [
            'X-Public-Contract' => '1',
        ]);
        $contractResponse->assertOk();
        $contractResponse->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'public_contract' => [
                        'id',
                        'slug',
                        'title',
                        'description',
                        'excerpt',
                        'primary_image' => ['url', 'alt_text'],
                        'gallery' => [['url', 'alt_text', 'sort_order']],
                        'category' => ['id', 'name', 'slug'],
                        'location' => ['label', 'latitude', 'longitude'],
                        'price' => ['amount', 'currency', 'price_type', 'time_credits'],
                        'seller' => ['id', 'display_name', 'avatar_url', 'is_verified', 'seller_type'],
                        'delivery' => ['method', 'shipping_available', 'local_pickup'],
                        'condition',
                        'quantity',
                        'expires_at',
                        'created_at',
                        'updated_at',
                        'status',
                    ],
                ],
            ],
        ]);

        $contract = $contractResponse->json('data.0.public_contract');
        $this->assertSame($listingId, $contract['id']);
        $this->assertSame((string) $listingId, $contract['slug']);
        $this->assertSame('Community repair kit', $contract['title']);
        $this->assertSame('A practical kit for local repair sessions.', $contract['excerpt']);
        $this->assertSame('/uploads/tenants/hour-timebank/marketplace/repair-kit.jpg', $contract['primary_image']['url']);
        $this->assertSame('Repair Tools', $contract['category']['name']);
        $this->assertSame('Remote or local', $contract['location']['label']);
        $this->assertEquals(25.0, $contract['price']['amount']);
        $this->assertSame('EUR', $contract['price']['currency']);
        $this->assertEquals(2.0, $contract['price']['time_credits']);
        $this->assertSame('Market Seller', $contract['seller']['display_name']);
        $this->assertSame('both', $contract['delivery']['method']);
        $this->assertTrue($contract['delivery']['shipping_available']);
        $this->assertTrue($contract['delivery']['local_pickup']);
        $this->assertSame('good', $contract['condition']);
        $this->assertSame(1, $contract['quantity']);
    }

    public function test_show_keeps_marketplace_public_contract_opt_in(): void
    {
        $this->enableMarketplaceFeature();
        $this->authenticatedUser();
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'first_name' => 'Detail',
            'last_name' => 'Seller',
            'status' => 'active',
            'is_approved' => true,
        ]);
        $categoryId = $this->createMarketplaceCategory('Shared Tools');
        $listingId = $this->createMarketplaceListing($seller, $categoryId, [
            'title' => 'Shared cordless drill',
            'tagline' => 'Borrow a drill for local projects.',
            'description' => 'A detailed public marketplace description for a cordless drill.',
            'price_type' => 'free',
            'price' => null,
            'time_credit_price' => null,
            'location' => 'Online',
        ]);
        $this->addMarketplaceImage($listingId, '/uploads/tenants/hour-timebank/marketplace/drill.jpg');

        $defaultResponse = $this->apiGet("/v2/marketplace/listings/{$listingId}");
        $defaultResponse->assertOk();
        $this->assertArrayNotHasKey('public_contract', $defaultResponse->json('data'));

        $contractResponse = $this->apiGet("/v2/marketplace/listings/{$listingId}", [
            'X-Public-Contract' => '1',
        ]);
        $contractResponse->assertOk();

        $contract = $contractResponse->json('data.public_contract');
        $this->assertSame($listingId, $contract['id']);
        $this->assertSame('Shared cordless drill', $contract['title']);
        $this->assertSame('Borrow a drill for local projects.', $contract['excerpt']);
        $this->assertSame('/uploads/tenants/hour-timebank/marketplace/drill.jpg', $contract['primary_image']['url']);
        $this->assertSame('Shared Tools', $contract['category']['name']);
        $this->assertSame('Online', $contract['location']['label']);
        $this->assertSame('free', $contract['price']['price_type']);
        $this->assertSame('Detail Seller', $contract['seller']['display_name']);
    }

    public function test_categories_public_smoke(): void
    {
        $response = $this->apiGet('/v2/marketplace/categories');
        $this->assertLessThan(500, $response->status());
    }

    public function test_category_listings_public_endpoint_filters_by_slug(): void
    {
        $this->enableMarketplaceFeature();
        $this->authenticatedUser();

        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        $toolsCategoryId = DB::table('marketplace_categories')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Repair Tools',
            'slug' => 'repair-tools',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booksCategoryId = DB::table('marketplace_categories')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Books',
            'slug' => 'books',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('marketplace_listings')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'user_id' => $seller->id,
                'category_id' => $toolsCategoryId,
                'title' => 'Community repair kit',
                'description' => 'A public listing for testing category-backed Next pages.',
                'price_type' => 'free',
                'condition' => 'good',
                'quantity' => 1,
                'delivery_method' => 'pickup',
                'seller_type' => 'private',
                'status' => 'active',
                'moderation_status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->testTenantId,
                'user_id' => $seller->id,
                'category_id' => $booksCategoryId,
                'title' => 'Neighbourhood cookbook',
                'description' => 'A different category listing that should not appear.',
                'price_type' => 'free',
                'condition' => 'good',
                'quantity' => 1,
                'delivery_method' => 'pickup',
                'seller_type' => 'private',
                'status' => 'active',
                'moderation_status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->apiGet('/v2/marketplace/categories/repair-tools/listings');

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->all();

        $this->assertContains('Community repair kit', $titles);
        $this->assertNotContains('Neighbourhood cookbook', $titles);
    }

    public function test_category_listings_unknown_slug_does_not_fall_back_to_all_listings(): void
    {
        $this->enableMarketplaceFeature();
        $this->authenticatedUser();

        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        $categoryId = DB::table('marketplace_categories')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Visible Category',
            'slug' => 'visible-category',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('marketplace_listings')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'category_id' => $categoryId,
            'title' => 'Visible marketplace listing',
            'description' => 'This listing must not leak into unknown category pages.',
            'price_type' => 'free',
            'condition' => 'good',
            'quantity' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiGet('/v2/marketplace/categories/missing-category/listings');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
        $response->assertJsonPath('meta.has_more', false);
    }

    public function test_savedListings_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/listings/saved');
        $this->assertLessThan(500, $response->status());
    }
}
