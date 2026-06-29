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

    public function test_index_public_smoke(): void
    {
        // Public endpoint (outside auth middleware group)
        $response = $this->apiGet('/v2/marketplace/listings');
        $this->assertLessThan(500, $response->status());
    }

    public function test_categories_public_smoke(): void
    {
        $response = $this->apiGet('/v2/marketplace/categories');
        $this->assertLessThan(500, $response->status());
    }

    public function test_category_listings_public_endpoint_filters_by_slug(): void
    {
        $this->enableMarketplaceFeature();

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
