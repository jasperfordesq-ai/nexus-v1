<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Services\MarketplaceInventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * MarketplaceInventoryServiceTest
 *
 * Tests atomic stock decrement/increment, out-of-stock guards,
 * auto status flips (active↔sold), low-stock notifications,
 * and inventory update / restock fanout via saved-searches.
 *
 * Strategy:
 *  - Insert minimal marketplace_listings rows directly (no FK on users/categories needed
 *    provided we use valid tenant_id=2 and a real user_id from the test DB).
 *  - Use DatabaseTransactions so every test rolls back cleanly.
 *  - notifications table gets written as a side-effect for low-stock/restock tests.
 */
class MarketplaceInventoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    // A real user_id that exists in nexus_test for tenant 2 (inserted fresh per test).
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);

        // Insert a minimal user so FK on notifications is satisfiable.
        $uid = uniqid('inv_', true);
        $this->userId = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'Inv Test ' . $uid,
            'first_name'  => 'Inv',
            'last_name'   => 'Test',
            'email'       => 'inv.' . $uid . '@example.test',
            'status'      => 'active',
            'balance'     => 0,
            'role'        => 'member',
            'is_approved' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a minimal marketplace_listings row and return its ID.
     */
    private function insertListing(array $overrides = []): int
    {
        $defaults = [
            'tenant_id'            => self::TENANT_ID,
            'user_id'              => $this->userId,
            'title'                => 'Test Item ' . uniqid(),
            'description'          => 'desc',
            'status'               => 'active',
            'inventory_count'      => null,
            'low_stock_threshold'  => null,
            'is_oversold_protected'=> 1,
            'created_at'           => now(),
            'updated_at'           => now(),
        ];

        return DB::table('marketplace_listings')->insertGetId(array_merge($defaults, $overrides));
    }

    // ── decrementForOrder: unlimited stock (NULL) ─────────────────────────────

    public function test_decrement_for_order_returns_without_changing_null_inventory(): void
    {
        $listingId = $this->insertListing(['inventory_count' => null]);

        MarketplaceInventoryService::decrementForOrder($listingId, 1);

        $row = DB::table('marketplace_listings')->where('id', $listingId)->first();
        $this->assertNull($row->inventory_count, 'Unlimited inventory should remain NULL');
        $this->assertSame('active', $row->status, 'Status should not change for unlimited stock');
    }

    // ── decrementForOrder: normal decrement ───────────────────────────────────

    public function test_decrement_for_order_reduces_inventory_count(): void
    {
        $listingId = $this->insertListing(['inventory_count' => 10]);

        MarketplaceInventoryService::decrementForOrder($listingId, 3);

        $row = DB::table('marketplace_listings')->where('id', $listingId)->first();
        $this->assertSame(7, (int) $row->inventory_count);
        $this->assertSame('active', $row->status, 'Status should still be active with stock remaining');
    }

    // ── decrementForOrder: auto sold-out at zero ──────────────────────────────

    public function test_decrement_for_order_flips_status_to_sold_when_reaching_zero(): void
    {
        $listingId = $this->insertListing(['inventory_count' => 1, 'status' => 'active']);

        MarketplaceInventoryService::decrementForOrder($listingId, 1);

        $row = DB::table('marketplace_listings')->where('id', $listingId)->first();
        $this->assertSame(0, (int) $row->inventory_count);
        $this->assertSame('sold', $row->status, 'Status must flip to sold when inventory hits 0');
    }

    // ── decrementForOrder: out-of-stock guard ─────────────────────────────────

    public function test_decrement_for_order_throws_when_oversold_protected_and_no_stock(): void
    {
        $listingId = $this->insertListing([
            'inventory_count'       => 0,
            'is_oversold_protected' => 1,
            'status'                => 'sold',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('OUT_OF_STOCK');

        MarketplaceInventoryService::decrementForOrder($listingId, 1);
    }

    // ── decrementForOrder: oversell allowed when not protected ────────────────

    public function test_decrement_for_order_allows_negative_when_not_oversold_protected(): void
    {
        $listingId = $this->insertListing([
            'inventory_count'       => 0,
            'is_oversold_protected' => 0,
        ]);

        // Should not throw
        MarketplaceInventoryService::decrementForOrder($listingId, 1);

        $row = DB::table('marketplace_listings')->where('id', $listingId)->first();
        $this->assertSame(-1, (int) $row->inventory_count, 'Negative inventory allowed when oversell protection is off');
    }

    // ── decrementForOrder: non-existent listing ───────────────────────────────

    public function test_decrement_for_order_throws_for_non_existent_listing(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('LISTING_NOT_FOUND');

        MarketplaceInventoryService::decrementForOrder(999999999, 1);
    }

    // ── decrementForOrder: low-stock notification ─────────────────────────────

    public function test_decrement_creates_low_stock_notification_when_crossing_threshold(): void
    {
        // Start at 6, threshold=5 → decrement to 5 crosses the boundary
        $listingId = $this->insertListing([
            'inventory_count'     => 6,
            'low_stock_threshold' => 5,
            'status'              => 'active',
        ]);

        $beforeCount = DB::table('notifications')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $this->userId)
            ->where('type', 'marketplace_low_stock')
            ->count();

        MarketplaceInventoryService::decrementForOrder($listingId, 1);

        $afterCount = DB::table('notifications')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $this->userId)
            ->where('type', 'marketplace_low_stock')
            ->count();

        $this->assertGreaterThan($beforeCount, $afterCount, 'A low_stock notification should have been created');
    }

    // ── decrementForOrder: no low-stock notification when already below threshold

    public function test_decrement_does_not_duplicate_low_stock_notification_when_already_below(): void
    {
        // Start at 3, threshold=5 → already below, no notification expected
        $listingId = $this->insertListing([
            'inventory_count'     => 3,
            'low_stock_threshold' => 5,
            'status'              => 'active',
        ]);

        $beforeCount = DB::table('notifications')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $this->userId)
            ->where('type', 'marketplace_low_stock')
            ->count();

        MarketplaceInventoryService::decrementForOrder($listingId, 1);

        $afterCount = DB::table('notifications')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $this->userId)
            ->where('type', 'marketplace_low_stock')
            ->count();

        // Count should not have increased (previousCount=3 was already ≤ threshold=5)
        $this->assertSame($beforeCount, $afterCount, 'No additional notification when stock was already at/below threshold');
    }

    // ── incrementForCancellation: normal increment ────────────────────────────

    public function test_increment_for_cancellation_increases_inventory_count(): void
    {
        $listingId = $this->insertListing(['inventory_count' => 4, 'status' => 'active']);

        MarketplaceInventoryService::incrementForCancellation($listingId, 2);

        $row = DB::table('marketplace_listings')->where('id', $listingId)->first();
        $this->assertSame(6, (int) $row->inventory_count);
        $this->assertSame('active', $row->status);
    }

    // ── incrementForCancellation: restock sold → active ───────────────────────

    public function test_increment_for_cancellation_flips_sold_to_active_when_restocked(): void
    {
        $listingId = $this->insertListing(['inventory_count' => 0, 'status' => 'sold']);

        MarketplaceInventoryService::incrementForCancellation($listingId, 1);

        $row = DB::table('marketplace_listings')->where('id', $listingId)->first();
        $this->assertSame(1, (int) $row->inventory_count);
        $this->assertSame('active', $row->status, 'Should flip from sold → active on restock');
    }

    // ── incrementForCancellation: unlimited stock (NULL) is a no-op ──────────

    public function test_increment_for_cancellation_is_noop_for_unlimited_stock(): void
    {
        $listingId = $this->insertListing(['inventory_count' => null, 'status' => 'active']);

        MarketplaceInventoryService::incrementForCancellation($listingId, 5);

        $row = DB::table('marketplace_listings')->where('id', $listingId)->first();
        $this->assertNull($row->inventory_count, 'Null (unlimited) inventory must not change on cancellation');
    }

    // ── updateInventory: manual restock triggers restock fanout ───────────────

    public function test_update_inventory_triggers_restock_fanout_when_restocking_from_zero(): void
    {
        $listingId = $this->insertListing([
            'inventory_count' => 0,
            'status'          => 'sold',
            'title'           => 'RestockItem',
        ]);

        // Insert a matching saved search so the fanout has a recipient.
        $uid2 = uniqid('watcher_', true);
        $watcherId = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'Watcher ' . $uid2,
            'first_name'  => 'Watch',
            'last_name'   => 'User',
            'email'       => 'watcher.' . $uid2 . '@example.test',
            'status'      => 'active',
            'balance'     => 0,
            'role'        => 'member',
            'is_approved' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        DB::table('marketplace_saved_searches')->insert([
            'tenant_id'    => self::TENANT_ID,
            'user_id'      => $watcherId,
            'name'         => 'My search',
            'search_query' => 'RestockItem',
            'is_active'    => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $listing = MarketplaceListing::withoutGlobalScopes()->find($listingId);
        MarketplaceInventoryService::updateInventory($listing, ['inventory_count' => 5]);

        $row = DB::table('marketplace_listings')->where('id', $listingId)->first();
        $this->assertSame(5, (int) $row->inventory_count);
        $this->assertSame('active', $row->status, 'Status should flip to active on restock');

        // Watcher should have received a restocked notification.
        $notifExists = DB::table('notifications')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $watcherId)
            ->where('type', 'marketplace_restocked')
            ->exists();
        $this->assertTrue($notifExists, 'Restock fanout notification should be created for saved-search watchers');
    }

    // ── updateInventory: set to NULL (unlimited) ──────────────────────────────

    public function test_update_inventory_sets_unlimited_when_null_provided(): void
    {
        $listingId = $this->insertListing(['inventory_count' => 10]);
        $listing   = MarketplaceListing::withoutGlobalScopes()->find($listingId);

        $updated = MarketplaceInventoryService::updateInventory($listing, ['inventory_count' => null]);

        $this->assertNull($updated->inventory_count, 'Inventory should be null (unlimited) after update');
    }

    // ── updateInventory: negative count clamped to zero ───────────────────────

    public function test_update_inventory_clamps_negative_count_to_zero(): void
    {
        $listingId = $this->insertListing(['inventory_count' => 10]);
        $listing   = MarketplaceListing::withoutGlobalScopes()->find($listingId);

        $updated = MarketplaceInventoryService::updateInventory($listing, ['inventory_count' => -5]);

        $this->assertSame(0, (int) $updated->inventory_count, 'Negative inventory_count must be clamped to 0');
    }
}
