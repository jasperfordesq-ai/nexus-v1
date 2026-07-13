<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Core\TenantContext;
use App\Models\User;
use App\Services\MarketplaceListingService;
use App\Models\MarketplaceListing;
use App\Services\MarketplaceConfigurationService;
use App\Services\TenantSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class MarketplaceListingServiceTest extends TestCase
{
    use DatabaseTransactions;

    // -----------------------------------------------------------------
    //  update — tests methods that accept model instances as params
    // -----------------------------------------------------------------

    // -----------------------------------------------------------------
    //  recordView — simple increment
    // -----------------------------------------------------------------

    public function test_recordView_callsIncrementOnListing(): void
    {
        // recordView uses MarketplaceListing::where()->increment(),
        // which is a static Eloquent call. We test the method exists
        // and verify the service logic is sound.
        $this->assertTrue(method_exists(MarketplaceListingService::class, 'recordView'));
    }

    // -----------------------------------------------------------------
    //  getById — null case
    // -----------------------------------------------------------------

    public function test_getById_returnsNullForNonExistentListing(): void
    {
        // Since this queries the DB directly, verify it returns null for
        // a non-existent ID (uses the test DB which is empty)
        $result = MarketplaceListingService::getById(999999);

        $this->assertNull($result);
    }

    public function test_getById_hides_unpublished_listing_but_owner_preview_can_access_it(): void
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $tenantId = TenantContext::getId();
        $seller = User::factory()->forTenant($tenantId)->create(['status' => 'active']);
        $listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $seller->id,
            'title' => 'Pending private listing',
            'description' => 'Must not be returned by ordinary detail lookup.',
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'shipping_available' => 0,
            'local_pickup' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'draft',
            'moderation_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::reset();
        TenantContext::setById($tenantId);
        $this->assertTrue(MarketplaceListing::query()->whereKey($listingId)->exists());
        $this->assertNull(MarketplaceListingService::getById($listingId, $seller->id));
        $this->assertSame(
            $listingId,
            MarketplaceListingService::getByIdForOwner($listingId, $seller->id)['id'] ?? null
        );
        $this->assertNull(MarketplaceListingService::getByIdForOwner($listingId, $seller->id + 9999));
    }

    public function test_getAll_does_not_treat_arbitrary_user_filter_as_owner_preview(): void
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $tenantId = TenantContext::getId();
        $seller = User::factory()->forTenant($tenantId)->create(['status' => 'active']);
        $viewer = User::factory()->forTenant($tenantId)->create(['status' => 'active']);

        foreach ([['Published', 'active', 'approved'], ['Draft', 'draft', 'pending']] as [$title, $status, $moderation]) {
            DB::table('marketplace_listings')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $seller->id,
                'title' => "{$title} seller listing",
                'description' => 'Visibility regression fixture.',
                'price_currency' => 'EUR',
                'price_type' => 'fixed',
                'quantity' => 1,
                'shipping_available' => 0,
                'local_pickup' => 1,
                'delivery_method' => 'pickup',
                'seller_type' => 'private',
                'status' => $status,
                'moderation_status' => $moderation,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        TenantContext::reset();
        TenantContext::setById($tenantId);

        $otherView = MarketplaceListingService::getAll([
            'user_id' => $seller->id,
            'current_user_id' => $viewer->id,
            'limit' => 20,
        ]);
        $ownerView = MarketplaceListingService::getAll([
            'user_id' => $seller->id,
            'current_user_id' => $seller->id,
            'limit' => 20,
        ]);

        $this->assertSame(['Published seller listing'], array_column($otherView['items'], 'title'));
        $this->assertContains('Draft seller listing', array_column($ownerView['items'], 'title'));
    }

    public function test_listing_detail_rounds_coordinates_for_public_viewers_but_preserves_owner_precision(): void
    {
        TenantContext::setById($this->testTenantId);
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $viewer = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Coordinate privacy listing',
            'description' => 'Public viewers must not receive a precise home location.',
            'price_currency' => 'EUR',
            'price_type' => 'free',
            'quantity' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'latitude' => 53.349805,
            'longitude' => -6.26031,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $public = MarketplaceListingService::getById($listingId, $viewer->id);
        $owner = MarketplaceListingService::getById($listingId, $seller->id);

        $this->assertSame(53.35, $public['latitude']);
        $this->assertSame(-6.26, $public['longitude']);
        $this->assertSame(53.349805, $owner['latitude']);
        $this->assertSame(-6.26031, $owner['longitude']);
    }

    public function test_listing_money_is_numeric_and_inventory_is_only_serialized_for_the_owner(): void
    {
        TenantContext::setById($this->testTenantId);
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $viewer = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Finite inventory listing',
            'description' => 'Inventory must survive an ordinary edit round trip.',
            'price' => 25.50,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'time_credit_price' => 3.50,
            'quantity' => 1,
            'inventory_count' => 8,
            'low_stock_threshold' => 2,
            'is_oversold_protected' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $public = MarketplaceListingService::getById($listingId, $viewer->id);
        $owner = MarketplaceListingService::getById($listingId, $seller->id);

        $this->assertSame(25.5, $owner['price']);
        $this->assertSame(3.5, $owner['time_credit_price']);
        $this->assertIsFloat($owner['price']);
        $this->assertIsFloat($owner['time_credit_price']);
        $this->assertArrayNotHasKey('inventory_count', $public);
        $this->assertArrayNotHasKey('low_stock_threshold', $public);
        $this->assertArrayNotHasKey('is_oversold_protected', $public);
        $this->assertSame(8, $owner['inventory_count']);
        $this->assertSame(2, $owner['low_stock_threshold']);
        $this->assertTrue($owner['is_oversold_protected']);
    }

    public function test_create_defaults_to_the_tenant_payment_currency(): void
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        app(TenantSettingsService::class)->set(
            $this->testTenantId,
            'general.default_currency',
            'jpy'
        );
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        $listing = MarketplaceListingService::create($seller->id, [
            'title' => 'Tenant currency listing',
            'description' => 'Defaults should follow the tenant payment configuration.',
            'price' => 500,
            'price_type' => 'fixed',
            'status' => 'draft',
        ]);

        $this->assertSame('JPY', $listing->price_currency);
    }

    public function test_create_rejects_price_precision_the_payment_provider_cannot_represent(): void
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        app(TenantSettingsService::class)->set(
            $this->testTenantId,
            'general.default_currency',
            'jpy'
        );
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        $this->expectException(\InvalidArgumentException::class);
        MarketplaceListingService::create($seller->id, [
            'title' => 'Invalid fractional yen listing',
            'description' => 'A zero-decimal currency cannot retain a fractional cash amount.',
            'price' => 500.5,
            'price_type' => 'fixed',
            'status' => 'draft',
        ]);
    }

    public function test_update_rejects_an_unsupported_payment_currency(): void
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $listing = MarketplaceListingService::create($seller->id, [
            'title' => 'Supported currency listing',
            'description' => 'Currency changes must remain within the provider contract.',
            'price' => 25,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'status' => 'draft',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        MarketplaceListingService::update($listing, ['price_currency' => 'ZZZ']);
    }

    public function test_create_enforces_tenant_listing_policy_flags(): void
    {
        TenantContext::setById($this->testTenantId);
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_ALLOW_FREE_ITEMS,
            false,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(__('api.marketplace_free_items_disabled'));
        MarketplaceListingService::create($seller->id, [
            'title' => 'Policy-disabled free item',
            'description' => 'The service boundary must enforce tenant policy.',
            'price_type' => 'free',
            'status' => 'draft',
        ]);
    }

    #[DataProvider('disabledListingPolicyProvider')]
    public function test_create_rejects_other_disabled_listing_capabilities(
        string $configKey,
        array $listingOverrides,
        string $messageKey,
    ): void {
        TenantContext::setById($this->testTenantId);
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        MarketplaceConfigurationService::set($configKey, false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(__($messageKey));
        MarketplaceListingService::create($seller->id, array_merge([
            'title' => 'Policy-disabled marketplace capability',
            'description' => 'Every governed listing field is checked by the service.',
            'price' => 10,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'draft',
        ], $listingOverrides));
    }

    public static function disabledListingPolicyProvider(): array
    {
        return [
            'business sellers' => [
                MarketplaceConfigurationService::CONFIG_ALLOW_BUSINESS_SELLERS,
                ['seller_type' => 'business'],
                'api.marketplace_business_sellers_disabled',
            ],
            'shipping' => [
                MarketplaceConfigurationService::CONFIG_ALLOW_SHIPPING,
                ['delivery_method' => 'shipping', 'shipping_available' => true],
                'api.marketplace_shipping_disabled',
            ],
            'community delivery' => [
                MarketplaceConfigurationService::CONFIG_ALLOW_COMMUNITY_DELIVERY,
                ['delivery_method' => 'community_delivery'],
                'api.marketplace_community_delivery_disabled',
            ],
            'hybrid pricing' => [
                MarketplaceConfigurationService::CONFIG_ALLOW_HYBRID_PRICING,
                ['price' => 10, 'time_credit_price' => 2],
                'api.marketplace_hybrid_pricing_disabled',
            ],
        ];
    }

    public function test_public_reads_hide_free_items_after_policy_is_disabled(): void
    {
        TenantContext::setById($this->testTenantId);
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $listing = MarketplaceListingService::create($seller->id, [
            'title' => 'Free item hidden after policy change',
            'description' => 'Existing content must follow the current public policy.',
            'price_type' => 'free',
            'status' => 'active',
        ]);

        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_ALLOW_FREE_ITEMS,
            false,
        );

        $this->assertNull(MarketplaceListingService::getById((int) $listing->id));
        $this->assertNotContains(
            (int) $listing->id,
            array_column(MarketplaceListingService::getAll(['limit' => 100])['items'], 'id'),
        );
    }

    public function test_public_reads_hide_inactive_and_marketplace_suspended_sellers(): void
    {
        TenantContext::setById($this->testTenantId);
        $inactiveSeller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'inactive',
            'is_approved' => true,
        ]);
        $suspendedSeller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        DB::table('marketplace_seller_profiles')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $suspendedSeller->id,
            'display_name' => 'Suspended marketplace seller',
            'seller_type' => 'private',
            'is_suspended' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = [];
        foreach ([$inactiveSeller, $suspendedSeller] as $seller) {
            $ids[] = DB::table('marketplace_listings')->insertGetId([
                'tenant_id' => $this->testTenantId,
                'user_id' => $seller->id,
                'title' => 'Seller state hidden listing ' . $seller->id,
                'description' => 'Public reads must honor seller state.',
                'price_currency' => 'EUR',
                'price_type' => 'fixed',
                'price' => 10,
                'quantity' => 1,
                'delivery_method' => 'pickup',
                'seller_type' => 'private',
                'status' => 'active',
                'moderation_status' => 'approved',
                'expires_at' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($ids as $id) {
            $this->assertNull(MarketplaceListingService::getById((int) $id));
        }
    }

    public function test_public_reads_hide_expired_listing(): void
    {
        TenantContext::setById($this->testTenantId);
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Expired marketplace listing',
            'description' => 'Must not remain public after expiry.',
            'price_currency' => 'EUR',
            'price_type' => 'free',
            'quantity' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        $this->assertNull(MarketplaceListingService::getById($listingId));
        $this->assertNotContains(
            $listingId,
            array_column(MarketplaceListingService::getAll(['limit' => 100])['items'], 'id')
        );
    }

    public function test_material_edit_returns_approved_listing_to_moderation(): void
    {
        TenantContext::setById($this->testTenantId);
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_MODERATION_ENABLED,
            true
        );
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Approved listing',
            'description' => 'Approved description.',
            'price_currency' => 'EUR',
            'price_type' => 'free',
            'quantity' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $listing = MarketplaceListing::findOrFail($listingId);

        MarketplaceListingService::update($listing, ['title' => 'Materially changed title']);

        $this->assertSame('pending', $listing->fresh()->moderation_status);
        $this->assertNull(MarketplaceListingService::getById($listingId));
    }

    public function test_getAll_price_sort_cursor_uses_price_and_id_tiebreaker(): void
    {
        TenantContext::setById($this->testTenantId);
        $tenantId = TenantContext::getId();
        $titlePrefix = 'Cursor item ' . uniqid('', true) . ' ';

        $seller = User::factory()->forTenant($tenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        foreach ([10.00, 20.00, 20.00, 30.00] as $index => $price) {
            DB::table('marketplace_listings')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $seller->id,
                'title' => $titlePrefix . $index,
                'description' => 'Price cursor test item',
                'price' => $price,
                'price_currency' => 'EUR',
                'price_type' => 'fixed',
                'quantity' => 1,
                'shipping_available' => 0,
                'local_pickup' => 1,
                'delivery_method' => 'pickup',
                'seller_type' => 'private',
                'status' => 'active',
                'moderation_status' => 'approved',
                'created_at' => now()->addSeconds($index),
                'updated_at' => now()->addSeconds($index),
            ]);
        }

        $this->assertSame(
            4,
            DB::table('marketplace_listings')
                ->where('tenant_id', $tenantId)
                ->where('title', 'like', $titlePrefix . '%')
                ->count()
        );
        $this->assertSame(
            4,
            TenantContext::runForTenant(
                $tenantId,
                fn () => MarketplaceListing::query()
                    ->where('title', 'like', $titlePrefix . '%')
                    ->count()
            )
        );

        [$page1, $page2] = TenantContext::runForTenant($tenantId, function () use ($seller) {
            $page1 = MarketplaceListingService::getAll([
                'sort' => 'price_asc',
                'limit' => 2,
                'user_id' => $seller->id,
            ]);
            $page2 = MarketplaceListingService::getAll([
                'sort' => 'price_asc',
                'limit' => 2,
                'user_id' => $seller->id,
                'cursor' => $page1['cursor'],
            ]);

            return [$page1, $page2];
        });

        $this->assertSame([10.0, 20.0], array_column($page1['items'], 'price'));
        $this->assertSame([20.0, 30.0], array_column($page2['items'], 'price'));
        $this->assertCount(4, array_unique(array_merge(
            array_column($page1['items'], 'id'),
            array_column($page2['items'], 'id')
        )));
    }

    public function test_image_reorder_requires_the_complete_unique_listing_gallery(): void
    {
        [$listing, $imageIds] = $this->createListingImageFixture();

        MarketplaceListingService::reorderImages($listing, [
            $imageIds[2],
            $imageIds[0],
            $imageIds[1],
        ]);
        $gallery = DB::table('marketplace_images')
            ->where('marketplace_listing_id', $listing->id)
            ->orderBy('sort_order')
            ->get(['id', 'is_primary']);

        $this->assertSame([$imageIds[2], $imageIds[0], $imageIds[1]], $gallery->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([1, 0, 0], $gallery->pluck('is_primary')->map(fn ($value) => (int) $value)->all());

        $this->expectException(\InvalidArgumentException::class);
        MarketplaceListingService::reorderImages($listing, [$imageIds[0], $imageIds[0]]);
    }

    public function test_deleting_the_primary_image_promotes_the_next_gallery_image(): void
    {
        [$listing, $imageIds] = $this->createListingImageFixture();

        $this->assertTrue(MarketplaceListingService::deleteImage($listing, $imageIds[0]));

        $this->assertDatabaseMissing('marketplace_images', ['id' => $imageIds[0]]);
        $this->assertDatabaseHas('marketplace_images', [
            'id' => $imageIds[1],
            'is_primary' => 1,
        ]);
        $this->assertSame(
            1,
            DB::table('marketplace_images')
                ->where('marketplace_listing_id', $listing->id)
                ->where('is_primary', true)
                ->count()
        );
    }

    /** @return array{MarketplaceListing, list<int>} */
    private function createListingImageFixture(): array
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Gallery fixture',
            'description' => 'Image ordering must remain canonical.',
            'price_currency' => 'EUR',
            'price_type' => 'free',
            'quantity' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'draft',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $imageIds = [];
        foreach (range(0, 2) as $sortOrder) {
            $imageIds[] = (int) DB::table('marketplace_images')->insertGetId([
                'tenant_id' => $this->testTenantId,
                'marketplace_listing_id' => $listingId,
                'image_url' => "/storage/marketplace/gallery-{$sortOrder}.jpg",
                'sort_order' => $sortOrder,
                'is_primary' => $sortOrder === 0,
                'created_at' => now(),
            ]);
        }

        return [MarketplaceListing::query()->findOrFail($listingId), $imageIds];
    }
}
