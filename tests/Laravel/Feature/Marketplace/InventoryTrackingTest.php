<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Services\MarketplaceInventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * AG46 — Inventory tracking unit tests.
 */
class InventoryTrackingTest extends TestCase
{
    use DatabaseTransactions;

    private function ensureSchema(): bool
    {
        return Schema::hasTable('marketplace_listings')
            && Schema::hasColumn('marketplace_listings', 'inventory_count');
    }

    private function createListing(int $count = 5, bool $oversoldProtected = true, ?int $threshold = null): int
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $id = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Inv Widget',
            'description' => 'Inventory test',
            'price' => 10.00,
            'price_currency' => 'EUR',
            'status' => 'active',
            'inventory_count' => $count,
            'low_stock_threshold' => $threshold ?? 2,
            'is_oversold_protected' => $oversoldProtected ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The User factory above fires observers that reset TenantContext to
        // tenant 1. Re-pin to the test tenant so the TenantScope on
        // MarketplaceListing::find() (used throughout these tests to verify
        // results) resolves against tenant 2 — otherwise it returns null.
        TenantContext::setById($this->testTenantId);

        return $id;
    }

    /**
     * Load a listing for verification.
     *
     * The inventory service's own writes call MarketplaceListing::save(),
     * whose model observer resets TenantContext to tenant 1. Re-pin to the
     * test tenant before each scoped read so MarketplaceListing::find()
     * resolves against tenant 2 instead of returning null.
     */
    private function findListing(int $id): ?MarketplaceListing
    {
        TenantContext::setById($this->testTenantId);

        return MarketplaceListing::find($id);
    }

    public function test_decrement_reduces_inventory(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Inventory columns not present.');
        }

        $listingId = $this->createListing(3);
        MarketplaceInventoryService::decrementForOrder($listingId, 1);

        $listing = $this->findListing($listingId);
        $this->assertSame(2, (int) $listing->inventory_count);
        $this->assertSame('active', $listing->status);
    }

    public function test_decrement_to_zero_marks_sold_out(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Inventory columns not present.');
        }

        $listingId = $this->createListing(1);
        MarketplaceInventoryService::decrementForOrder($listingId, 1);

        $listing = $this->findListing($listingId);
        $this->assertSame(0, (int) $listing->inventory_count);
        $this->assertSame('sold', $listing->status);
    }

    public function test_oversold_protection_rejects_decrement_when_zero(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Inventory columns not present.');
        }

        $listingId = $this->createListing(0, true);

        $threw = false;
        try {
            MarketplaceInventoryService::decrementForOrder($listingId, 1);
        } catch (\DomainException $e) {
            $threw = true;
            $this->assertSame('OUT_OF_STOCK', $e->getMessage());
        }
        $this->assertTrue($threw);
    }

    public function test_unlimited_inventory_never_decrements(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Inventory columns not present.');
        }

        $listingId = $this->createListing(0, true);
        DB::table('marketplace_listings')->where('id', $listingId)->update(['inventory_count' => null]);

        // Should not throw — unlimited stock is null
        MarketplaceInventoryService::decrementForOrder($listingId, 5);
        $this->assertNull($this->findListing($listingId)->inventory_count);
    }

    public function test_increment_for_cancellation_restocks(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Inventory columns not present.');
        }

        $listingId = $this->createListing(0);
        DB::table('marketplace_listings')->where('id', $listingId)->update(['status' => 'sold']);

        MarketplaceInventoryService::incrementForCancellation($listingId, 2);

        $listing = $this->findListing($listingId);
        $this->assertSame(2, (int) $listing->inventory_count);
        $this->assertSame('active', $listing->status);
    }

    public function test_manual_restock_flips_status_active(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Inventory columns not present.');
        }

        $listingId = $this->createListing(0);
        DB::table('marketplace_listings')->where('id', $listingId)->update(['status' => 'sold']);

        $listing = $this->findListing($listingId);
        MarketplaceInventoryService::updateInventory($listing, [
            'inventory_count' => 3,
        ]);

        $fresh = $this->findListing($listingId);
        $this->assertSame(3, (int) $fresh->inventory_count);
        $this->assertSame('active', $fresh->status);
    }
}
