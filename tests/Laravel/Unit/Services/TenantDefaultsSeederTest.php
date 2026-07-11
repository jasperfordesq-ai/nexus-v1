<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\TenantDefaultsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * TenantDefaultsSeederTest
 *
 * Uses a fresh high-range tenant ID (99300) inserted inside the transaction
 * so it is rolled back automatically by DatabaseTransactions.  Every method
 * is idempotent; we verify both the initial seed and that calling twice
 * produces no duplicates.
 */
class TenantDefaultsSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99300;

    protected function setUp(): void
    {
        parent::setUp();

        // Insert the test tenant so FK constraints on categories / attributes /
        // menus are satisfied.  DatabaseTransactions rolls this back after the test.
        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Seeder Tenant ' . self::TENANT_ID,
            'slug'       => 'test-seeder-' . self::TENANT_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
    }

    // ── seedCategories ────────────────────────────────────────────────────────

    public function test_seed_categories_inserts_eight_rows(): void
    {
        TenantDefaultsSeeder::seedCategories(self::TENANT_ID);

        $count = DB::table('categories')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame(8, $count, 'Expected exactly 8 default categories');
    }

    public function test_seed_categories_sets_is_active_true(): void
    {
        TenantDefaultsSeeder::seedCategories(self::TENANT_ID);

        $inactive = DB::table('categories')
            ->where('tenant_id', self::TENANT_ID)
            ->where('is_active', 0)
            ->count();

        $this->assertSame(0, $inactive, 'All seeded categories must be active');
    }

    public function test_seed_categories_generates_correct_slugs(): void
    {
        TenantDefaultsSeeder::seedCategories(self::TENANT_ID);

        // "Home & Garden" → "home---garden" after the preg_replace; double-check
        // the slug is lowercase and contains no uppercase letters.
        $row = DB::table('categories')
            ->where('tenant_id', self::TENANT_ID)
            ->where('name', 'Technology')
            ->first();

        $this->assertNotNull($row, '"Technology" category must exist');
        $this->assertSame('technology', $row->slug);
    }

    public function test_seed_categories_is_idempotent(): void
    {
        TenantDefaultsSeeder::seedCategories(self::TENANT_ID);
        TenantDefaultsSeeder::seedCategories(self::TENANT_ID); // second call

        $count = DB::table('categories')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame(8, $count, 'Running seedCategories twice must not duplicate rows');
    }

    // ── seedAttributes ────────────────────────────────────────────────────────

    public function test_seed_attributes_inserts_eight_rows(): void
    {
        TenantDefaultsSeeder::seedAttributes(self::TENANT_ID);

        $count = DB::table('attributes')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame(8, $count, 'Expected exactly 8 default attributes');
    }

    public function test_seed_attributes_includes_correct_target_types(): void
    {
        TenantDefaultsSeeder::seedAttributes(self::TENANT_ID);

        $offerCount = DB::table('attributes')
            ->where('tenant_id', self::TENANT_ID)
            ->where('target_type', 'offer')
            ->count();

        $requestCount = DB::table('attributes')
            ->where('tenant_id', self::TENANT_ID)
            ->where('target_type', 'request')
            ->count();

        $anyCount = DB::table('attributes')
            ->where('tenant_id', self::TENANT_ID)
            ->where('target_type', 'any')
            ->count();

        $this->assertSame(3, $offerCount, '3 offer-side attributes expected');
        $this->assertSame(2, $requestCount, '2 request-side attributes expected');
        $this->assertSame(3, $anyCount, '3 any-type attributes expected');
    }

    public function test_seed_attributes_does_not_offer_self_declared_vetting_signals(): void
    {
        TenantDefaultsSeeder::seedAttributes(self::TENANT_ID);

        $bgChecked = DB::table('attributes')
            ->where('tenant_id', self::TENANT_ID)
            ->where('name', 'Background Checked')
            ->count();

        $gardaVetted = DB::table('attributes')
            ->where('tenant_id', self::TENANT_ID)
            ->where('name', 'Garda Vetted')
            ->count();

        $this->assertSame(0, $bgChecked, '"Background Checked" must not be self-selectable');
        $this->assertSame(0, $gardaVetted, 'Irish-specific "Garda Vetted" must NOT be seeded');
    }

    public function test_seed_attributes_is_idempotent(): void
    {
        TenantDefaultsSeeder::seedAttributes(self::TENANT_ID);
        TenantDefaultsSeeder::seedAttributes(self::TENANT_ID);

        $count = DB::table('attributes')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame(8, $count, 'Running seedAttributes twice must not duplicate rows');
    }

    // ── seedMenus ─────────────────────────────────────────────────────────────

    public function test_seed_menus_creates_main_and_footer_menus(): void
    {
        TenantDefaultsSeeder::seedMenus(self::TENANT_ID);

        $mainExists = DB::table('menus')
            ->where('tenant_id', self::TENANT_ID)
            ->where('slug', 'main-nav')
            ->exists();

        $footerExists = DB::table('menus')
            ->where('tenant_id', self::TENANT_ID)
            ->where('slug', 'footer-nav')
            ->exists();

        $this->assertTrue($mainExists, 'main-nav menu must be seeded');
        $this->assertTrue($footerExists, 'footer-nav menu must be seeded');
    }

    public function test_seed_menus_main_nav_has_correct_items(): void
    {
        TenantDefaultsSeeder::seedMenus(self::TENANT_ID);

        $menuId = DB::table('menus')
            ->where('tenant_id', self::TENANT_ID)
            ->where('slug', 'main-nav')
            ->value('id');

        $this->assertNotNull($menuId);

        // Should have Home, Explore, Community (dropdown), About, Dashboard
        // plus 3 children of Community (Groups, Members, Events) = 8 items total.
        $itemCount = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->count();

        $this->assertSame(8, $itemCount, 'main-nav must have 8 menu items (including Community children)');
    }

    public function test_seed_menus_footer_nav_has_four_items(): void
    {
        TenantDefaultsSeeder::seedMenus(self::TENANT_ID);

        $footerMenuId = DB::table('menus')
            ->where('tenant_id', self::TENANT_ID)
            ->where('slug', 'footer-nav')
            ->value('id');

        $this->assertNotNull($footerMenuId);

        $footerItemCount = DB::table('menu_items')
            ->where('menu_id', $footerMenuId)
            ->count();

        $this->assertSame(4, $footerItemCount, 'footer-nav must have 4 items');
    }

    public function test_seed_menus_community_dropdown_has_parent_id(): void
    {
        TenantDefaultsSeeder::seedMenus(self::TENANT_ID);

        $menuId = DB::table('menus')
            ->where('tenant_id', self::TENANT_ID)
            ->where('slug', 'main-nav')
            ->value('id');

        // Community dropdown item itself has parent_id = null
        $communityItem = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('label', 'Community')
            ->first();

        $this->assertNotNull($communityItem, '"Community" dropdown must exist');
        $this->assertNull($communityItem->parent_id, 'Community dropdown should have no parent');

        // Its children (Groups, Members, Events) must reference it
        $children = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('parent_id', $communityItem->id)
            ->count();

        $this->assertSame(3, $children, 'Community dropdown must have 3 children');
    }

    public function test_seed_menus_is_idempotent(): void
    {
        TenantDefaultsSeeder::seedMenus(self::TENANT_ID);
        TenantDefaultsSeeder::seedMenus(self::TENANT_ID); // second call

        $menuCount = DB::table('menus')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame(2, $menuCount, 'Running seedMenus twice must not duplicate menus');
    }

    public function test_seed_menus_dashboard_requires_auth(): void
    {
        TenantDefaultsSeeder::seedMenus(self::TENANT_ID);

        $menuId = DB::table('menus')
            ->where('tenant_id', self::TENANT_ID)
            ->where('slug', 'main-nav')
            ->value('id');

        $dashboard = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('label', 'Dashboard')
            ->first();

        $this->assertNotNull($dashboard, 'Dashboard menu item must exist');
        $rules = json_decode($dashboard->visibility_rules, true);
        $this->assertTrue($rules['requires_auth'] ?? false, 'Dashboard must have requires_auth=true');
    }
}
