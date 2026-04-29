<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use App\Models\MarketplacePickupReservation;
use App\Models\MarketplacePickupSlot;
use App\Models\User;
use App\Services\MarketplacePickupSlotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * AG45 — Click-and-collect pickup slot reservation tests.
 */
class PickupSlotReservationTest extends TestCase
{
    use DatabaseTransactions;

    private function ensureSchema(): bool
    {
        return Schema::hasTable('marketplace_pickup_slots')
            && Schema::hasTable('marketplace_pickup_reservations')
            && Schema::hasTable('marketplace_orders')
            && Schema::hasTable('marketplace_listings')
            && Schema::hasTable('marketplace_seller_profiles');
    }

    private function createUser(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function createSellerProfile(int $userId): int
    {
        return (int) DB::table('marketplace_seller_profiles')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'display_name' => 'Test Seller',
            'seller_type' => 'business',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createListing(int $userId): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'title' => 'Pickup Widget',
            'description' => 'Pickup test',
            'price' => 5.00,
            'price_currency' => 'EUR',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(int $buyerId, int $sellerId, int $listingId): int
    {
        return (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'marketplace_listing_id' => $listingId,
            'order_number' => 'TEST-' . uniqid(),
            'subtotal' => 5.00,
            'total' => 5.00,
            'currency' => 'EUR',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSlot(int $sellerProfileId, int $capacity = 1): MarketplacePickupSlot
    {
        return MarketplacePickupSlotService::create($sellerProfileId, [
            'slot_start' => now()->addDay()->toDateTimeString(),
            'slot_end' => now()->addDay()->addHour()->toDateTimeString(),
            'capacity' => $capacity,
            'is_active' => true,
        ]);
    }

    public function test_capacity_enforced_atomically(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Pickup tables not present in this test DB.');
        }

        $seller = $this->createUser();
        $sellerProfileId = $this->createSellerProfile($seller->id);
        $listingId = $this->createListing($seller->id);

        $slot = $this->createSlot($sellerProfileId, 1);

        $buyer1 = $this->createUser();
        $buyer2 = $this->createUser();
        $order1 = $this->createOrder($buyer1->id, $seller->id, $listingId);
        $order2 = $this->createOrder($buyer2->id, $seller->id, $listingId);

        $r1 = MarketplacePickupSlotService::reserve($slot->id, $order1, $buyer1->id);
        $this->assertSame('reserved', $r1->status);

        $threw = false;
        try {
            MarketplacePickupSlotService::reserve($slot->id, $order2, $buyer2->id);
        } catch (\DomainException $e) {
            $threw = true;
            $this->assertSame('SLOT_FULL', $e->getMessage());
        }
        $this->assertTrue($threw, 'Second reservation must fail when slot is full');

        $slot->refresh();
        $this->assertSame(1, (int) $slot->booked_count);
    }

    public function test_qr_scan_marks_picked_up(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Pickup tables not present in this test DB.');
        }

        $seller = $this->createUser();
        $sellerProfileId = $this->createSellerProfile($seller->id);
        $listingId = $this->createListing($seller->id);
        $slot = $this->createSlot($sellerProfileId, 5);

        $buyer = $this->createUser();
        $orderId = $this->createOrder($buyer->id, $seller->id, $listingId);

        $reservation = MarketplacePickupSlotService::reserve($slot->id, $orderId, $buyer->id);
        $this->assertSame('reserved', $reservation->status);

        $scanned = MarketplacePickupSlotService::scanQr($reservation->qr_code, $seller->id);
        $this->assertSame('picked_up', $scanned->status);

        // Re-scanning the same QR should fail
        $threw = false;
        try {
            MarketplacePickupSlotService::scanQr($reservation->qr_code, $seller->id);
        } catch (\DomainException $e) {
            $threw = true;
            $this->assertSame('ALREADY_PICKED_UP', $e->getMessage());
        }
        $this->assertTrue($threw);
    }

    public function test_duplicate_reservation_for_same_order_rejected(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Pickup tables not present in this test DB.');
        }

        $seller = $this->createUser();
        $sellerProfileId = $this->createSellerProfile($seller->id);
        $listingId = $this->createListing($seller->id);
        $slot = $this->createSlot($sellerProfileId, 5);
        $buyer = $this->createUser();
        $orderId = $this->createOrder($buyer->id, $seller->id, $listingId);

        MarketplacePickupSlotService::reserve($slot->id, $orderId, $buyer->id);

        $threw = false;
        try {
            MarketplacePickupSlotService::reserve($slot->id, $orderId, $buyer->id);
        } catch (\DomainException $e) {
            $threw = true;
            $this->assertSame('DUPLICATE_RESERVATION', $e->getMessage());
        }
        $this->assertTrue($threw);
    }
}
