<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the accessible (GOV.UK) marketplace advanced (faceted)
 * search. No search term is used so MarketplaceListingService::getAll takes the
 * SQL path (Meilisearch is skipped), keeping the test hermetic.
 */
class MarketplaceAdvancedSearchParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
        foreach (['HTTP_X_TENANT_ID', 'HTTP_X_TENANT_SLUG', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
            unset($_SERVER[$k]);
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['marketplace'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function seedItem(int $userId, string $title, array $overrides = []): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId(array_merge([
            'tenant_id'         => $this->testTenantId,
            'user_id'           => $userId,
            'title'             => $title,
            'description'       => 'A seeded marketplace listing for advanced-search tests.',
            'price'             => 50.00,
            'price_type'        => 'fixed',
            'condition'         => 'good',
            'delivery_method'   => 'pickup',
            'seller_type'       => 'private',
            'status'            => 'active',
            'moderation_status' => 'approved',
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $overrides));
    }

    public function test_advanced_search_form_renders(): void
    {
        $this->authenticatedUser();
        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/search");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.marketplace_advanced.title'));
        $res->assertSee(__('govuk_alpha_commerce.marketplace_advanced.condition_label'));
        $res->assertSee('name="condition[]"', false);
        $res->assertSee('name="price_min"', false);
    }

    public function test_condition_filter_excludes_non_matching(): void
    {
        $owner = $this->authenticatedUser();
        $this->seedItem($owner->id, 'Brand New Phone', ['condition' => 'new']);
        $this->seedItem($owner->id, 'Worn Out Boots', ['condition' => 'poor']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/search?condition[]=new");
        $res->assertOk();
        $res->assertSee('Brand New Phone');
        $res->assertDontSee('Worn Out Boots');
    }

    public function test_price_range_filter(): void
    {
        $owner = $this->authenticatedUser();
        $this->seedItem($owner->id, 'Cheap Mug', ['price' => 5.00]);
        $this->seedItem($owner->id, 'Pricey Laptop', ['price' => 900.00]);

        // price_min triggers the SQL (faceted) path.
        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/search?price_min=100");
        $res->assertOk();
        $res->assertSee('Pricey Laptop');
        $res->assertDontSee('Cheap Mug');
    }
}
