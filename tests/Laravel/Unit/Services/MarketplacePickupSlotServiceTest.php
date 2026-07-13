<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\MarketplacePickupSlot;
use App\Services\MarketplacePickupSlotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * MarketplacePickupSlotServiceTest
 *
 * Uses a fresh high-range tenant (99301) with two real users, a seller
 * profile, a listing, and an order so every service path exercises real DB
 * behaviour.  DatabaseTransactions rolls everything back after each test.
 *
 * Fixtures are inserted in setUp() so each test gets a clean slate.
 */
class MarketplacePickupSlotServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID  = 99301;
    private const SELLER_UID = 993011;
    private const BUYER_UID  = 993012;

    private int $sellerProfileId;
    private int $listingId;
    private int $orderId;

    protected function setUp(): void
    {
        parent::setUp();

        // Insert tenant row FIRST so TenantContext::setById() can find it.
        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Pickup Tenant ' . self::TENANT_ID,
            'slug'       => 'test-pickup-' . self::TENANT_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);

        // Seller user
        DB::table('users')->insertOrIgnore([
            'id'         => self::SELLER_UID,
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Pickup Seller',
            'email'      => 'seller-' . self::TENANT_ID . '@example.com',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Buyer user
        DB::table('users')->insertOrIgnore([
            'id'         => self::BUYER_UID,
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Pickup Buyer',
            'email'      => 'buyer-' . self::TENANT_ID . '@example.com',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seller profile
        $this->sellerProfileId = (int) DB::table('marketplace_seller_profiles')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'user_id'     => self::SELLER_UID,
            'seller_type' => 'private',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Listing owned by the seller
        $this->listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'user_id'     => self::SELLER_UID,
            'title'       => 'Test Widget',
            'description' => 'A widget for testing',
            'price'       => '5.00',
            'price_type'  => 'fixed',
            'status'      => 'active',
            'moderation_status' => 'approved',
            'expires_at' => now()->addWeek(),
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Order linking buyer → seller → listing
        $this->orderId = (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id'              => self::TENANT_ID,
            'order_number'           => 'TEST-ORD-' . self::TENANT_ID . '-' . uniqid(),
            'buyer_id'               => self::BUYER_UID,
            'seller_id'              => self::SELLER_UID,
            'marketplace_listing_id' => $this->listingId,
            'quantity'               => 1,
            'unit_price'             => '5.00',
            'total_price'            => '5.00',
            'shipping_method'        => 'pickup',
            'status'                 => 'paid',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    // ── create ────────────────────────────────────────────────────────────────

    public function test_create_persists_slot_with_correct_fields(): void
    {
        $start = now()->addHour()->format('Y-m-d H:i:s');
        $end   = now()->addHours(2)->format('Y-m-d H:i:s');

        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => $start,
            'slot_end'   => $end,
            'capacity'   => 5,
        ]);

        $this->assertNotNull($slot->id);
        $this->assertSame(self::TENANT_ID, (int) $slot->tenant_id);
        $this->assertSame($this->sellerProfileId, (int) $slot->seller_id);
        $this->assertSame(5, (int) $slot->capacity);
        $this->assertSame(0, (int) $slot->booked_count);
        $this->assertTrue((bool) $slot->is_active);
    }

    public function test_create_enforces_minimum_capacity_of_one(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addHour()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addHours(2)->format('Y-m-d H:i:s'),
            'capacity'   => 0, // invalid — should be clamped to 1
        ]);

        $this->assertSame(1, (int) $slot->capacity, 'Capacity 0 must be clamped to 1');
    }

    public function test_create_rejects_an_end_before_the_start(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('SLOT_END_BEFORE_START');

        MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'slot_end' => now()->addHour()->format('Y-m-d H:i:s'),
            'capacity' => 1,
        ]);
    }

    public function test_create_rejects_a_seller_profile_from_another_tenant(): void
    {
        $userId = (int) DB::table('users')->insertGetId([
            'tenant_id' => 999,
            'name' => 'Other tenant seller',
            'email' => 'other-tenant-slot-' . uniqid() . '@example.test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $profileId = (int) DB::table('marketplace_seller_profiles')->insertGetId([
            'tenant_id' => 999,
            'user_id' => $userId,
            'seller_type' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('SELLER_PROFILE_NOT_FOUND');
        MarketplacePickupSlotService::create($profileId, [
            'slot_start' => now()->addHour()->format('Y-m-d H:i:s'),
            'slot_end' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'capacity' => 1,
        ]);
    }

    // ── listForSeller ─────────────────────────────────────────────────────────

    public function test_list_for_seller_returns_slots_for_correct_seller(): void
    {
        MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 3,
        ]);

        $rows = MarketplacePickupSlotService::listForSeller($this->sellerProfileId);

        $this->assertCount(1, $rows);
        $this->assertSame($this->sellerProfileId, $rows[0]['seller_id']);
        $this->assertArrayHasKey('remaining', $rows[0]);
    }

    public function test_list_for_seller_date_filter_excludes_outside_window(): void
    {
        // Slot tomorrow
        MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 1,
        ]);
        // Slot next week
        MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addWeek()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addWeek()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 1,
        ]);

        // Only ask for slots in the next 3 days
        $rows = MarketplacePickupSlotService::listForSeller(
            $this->sellerProfileId,
            now()->toDateTimeString(),
            now()->addDays(3)->toDateTimeString()
        );

        $this->assertCount(1, $rows, 'Only the tomorrow slot should be in the 3-day window');
    }

    // ── listAvailableForListing ───────────────────────────────────────────────

    public function test_list_available_for_listing_returns_future_slots_with_capacity(): void
    {
        // Active slot with capacity
        MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 2,
            'is_active'  => true,
        ]);

        $available = MarketplacePickupSlotService::listAvailableForListing($this->listingId);

        $this->assertCount(1, $available);
        $this->assertSame(2, $available[0]['remaining']);
    }

    public function test_list_available_for_listing_hides_inactive_slots(): void
    {
        MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 2,
            'is_active'  => false,
        ]);

        $available = MarketplacePickupSlotService::listAvailableForListing($this->listingId);

        $this->assertCount(0, $available, 'Inactive slots must not appear in buyer listing');
    }

    public function test_list_available_for_listing_returns_empty_for_unknown_listing(): void
    {
        $result = MarketplacePickupSlotService::listAvailableForListing(999999999);

        $this->assertSame([], $result);
    }

    public function test_list_available_for_listing_enforces_public_listing_and_active_seller_boundary(): void
    {
        MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity' => 2,
        ]);

        foreach ([
            ['moderation_status' => 'pending'],
            ['status' => 'draft'],
            ['status' => 'active', 'moderation_status' => 'approved', 'expires_at' => now()->subMinute()],
            ['status' => 'active', 'moderation_status' => 'approved', 'expires_at' => now()->addDay(), 'delivery_method' => 'shipping'],
        ] as $listingState) {
            DB::table('marketplace_listings')->where('id', $this->listingId)->update($listingState);
            $this->assertSame([], MarketplacePickupSlotService::listAvailableForListing($this->listingId));
        }

        DB::table('marketplace_listings')->where('id', $this->listingId)->update([
            'status' => 'active',
            'moderation_status' => 'approved',
            'expires_at' => now()->addDay(),
            'delivery_method' => 'pickup',
        ]);
        DB::table('marketplace_seller_profiles')->where('id', $this->sellerProfileId)->update([
            'is_suspended' => true,
        ]);
        $this->assertSame([], MarketplacePickupSlotService::listAvailableForListing($this->listingId));

        DB::table('marketplace_seller_profiles')->where('id', $this->sellerProfileId)->update([
            'is_suspended' => false,
        ]);
        DB::table('users')->where('id', self::SELLER_UID)->update(['status' => 'inactive']);
        $this->assertSame([], MarketplacePickupSlotService::listAvailableForListing($this->listingId));
    }

    public function test_update_reloads_and_locks_current_booking_count_before_lowering_capacity(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity' => 2,
        ]);
        $staleSlot = MarketplacePickupSlot::withoutGlobalScopes()->findOrFail($slot->id);
        MarketplacePickupSlotService::reserve($slot->id, $this->orderId, self::BUYER_UID);

        try {
            MarketplacePickupSlotService::update($staleSlot, ['capacity' => 0]);
            $this->fail('A stale slot model must not overwrite the current booking count.');
        } catch (\DomainException $exception) {
            $this->assertSame('CAPACITY_BELOW_BOOKINGS', $exception->getMessage());
        }

        $this->assertSame(2, (int) $slot->fresh()->capacity);
        $this->assertSame(1, (int) $slot->fresh()->booked_count);
    }

    public function test_delete_rechecks_active_reservations_under_the_slot_lock(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity' => 2,
        ]);
        $staleSlot = MarketplacePickupSlot::withoutGlobalScopes()->findOrFail($slot->id);
        MarketplacePickupSlotService::reserve($slot->id, $this->orderId, self::BUYER_UID);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('SLOT_HAS_RESERVATIONS');
        try {
            MarketplacePickupSlotService::delete($staleSlot);
        } finally {
            $this->assertDatabaseHas('marketplace_pickup_slots', ['id' => $slot->id]);
        }
    }

    // ── reserve ───────────────────────────────────────────────────────────────

    public function test_reserve_creates_reservation_and_increments_booked_count(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 3,
        ]);

        $reservation = MarketplacePickupSlotService::reserve(
            $slot->id,
            $this->orderId,
            self::BUYER_UID
        );

        $this->assertSame('reserved', $reservation->status);
        $this->assertNotEmpty($reservation->qr_code);
        $this->assertSame($slot->id, (int) $reservation->slot_id);

        // Verify booked_count incremented in DB
        $slot->refresh();
        $this->assertSame(1, (int) $slot->booked_count);
    }

    public function test_reserve_throws_slot_full_when_at_capacity(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 1,
        ]);

        // First reservation fills it
        MarketplacePickupSlotService::reserve($slot->id, $this->orderId, self::BUYER_UID);

        // Build a second order for a different attempt
        $secondOrderId = (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id'              => self::TENANT_ID,
            'order_number'           => 'TEST-ORD2-' . self::TENANT_ID . '-' . uniqid(),
            'buyer_id'               => self::BUYER_UID,
            'seller_id'              => self::SELLER_UID,
            'marketplace_listing_id' => $this->listingId,
            'quantity'               => 1,
            'unit_price'             => '5.00',
            'total_price'            => '5.00',
            'shipping_method'        => 'pickup',
            'status'                 => 'paid',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('SLOT_FULL');

        MarketplacePickupSlotService::reserve($slot->id, $secondOrderId, self::BUYER_UID);
    }

    public function test_reserve_throws_duplicate_reservation_for_same_order(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 5,
        ]);

        MarketplacePickupSlotService::reserve($slot->id, $this->orderId, self::BUYER_UID);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('DUPLICATE_RESERVATION');

        MarketplacePickupSlotService::reserve($slot->id, $this->orderId, self::BUYER_UID);
    }

    public function test_reserve_throws_slot_inactive(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 3,
            'is_active'  => false,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('SLOT_INACTIVE');

        MarketplacePickupSlotService::reserve($slot->id, $this->orderId, self::BUYER_UID);
    }

    public function test_reserve_throws_slot_past_for_past_slots(): void
    {
        // Bypass service create so we can force a past timestamp directly
        $slotId = (int) DB::table('marketplace_pickup_slots')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'seller_id'   => $this->sellerProfileId,
            'slot_start'  => now()->subHour()->format('Y-m-d H:i:s'),
            'slot_end'    => now()->subMinutes(30)->format('Y-m-d H:i:s'),
            'capacity'    => 5,
            'booked_count' => 0,
            'is_active'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('SLOT_PAST');

        MarketplacePickupSlotService::reserve($slotId, $this->orderId, self::BUYER_UID);
    }

    public function test_reserve_rejects_a_slot_owned_by_another_seller(): void
    {
        $otherSeller = (int) DB::table('users')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'name' => 'Other pickup seller',
            'email' => 'other-pickup-' . uniqid() . '@example.test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherProfile = (int) DB::table('marketplace_seller_profiles')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id' => $otherSeller,
            'seller_type' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $slot = MarketplacePickupSlotService::create($otherProfile, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity' => 2,
        ]);

        try {
            MarketplacePickupSlotService::reserve($slot->id, $this->orderId, self::BUYER_UID);
            $this->fail('A buyer must not reserve another seller\'s pickup slot.');
        } catch (\DomainException $e) {
            $this->assertSame('SLOT_SELLER_MISMATCH', $e->getMessage());
        }

        $this->assertSame(0, (int) $slot->fresh()->booked_count);
        $this->assertDatabaseMissing('marketplace_pickup_reservations', [
            'order_id' => $this->orderId,
        ]);
    }

    // ── scanQr ───────────────────────────────────────────────────────────────

    public function test_reserve_rejects_an_order_not_configured_for_pickup(): void
    {
        DB::table('marketplace_orders')->where('id', $this->orderId)->update([
            'shipping_method' => 'shipping',
        ]);
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity' => 2,
        ]);

        try {
            MarketplacePickupSlotService::reserve($slot->id, $this->orderId, self::BUYER_UID);
            $this->fail('A shipping order must not reserve a pickup slot.');
        } catch (\DomainException $e) {
            $this->assertSame('ORDER_NOT_PICKUP_ELIGIBLE', $e->getMessage());
        }

        $this->assertSame(0, (int) $slot->fresh()->booked_count);
        $this->assertDatabaseMissing('marketplace_pickup_reservations', [
            'order_id' => $this->orderId,
        ]);
    }

    public function test_scan_qr_marks_reservation_picked_up(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 2,
        ]);

        $reservation = MarketplacePickupSlotService::reserve(
            $slot->id,
            $this->orderId,
            self::BUYER_UID
        );

        $updated = MarketplacePickupSlotService::scanQr($reservation->qr_code, self::SELLER_UID);

        $this->assertSame('picked_up', $updated->status);
        $this->assertNotNull($updated->picked_up_at);
    }

    public function test_scan_qr_throws_already_picked_up_on_second_scan(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 2,
        ]);

        $reservation = MarketplacePickupSlotService::reserve(
            $slot->id,
            $this->orderId,
            self::BUYER_UID
        );

        MarketplacePickupSlotService::scanQr($reservation->qr_code, self::SELLER_UID);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ALREADY_PICKED_UP');

        MarketplacePickupSlotService::scanQr($reservation->qr_code, self::SELLER_UID);
    }

    public function test_scan_qr_throws_not_for_seller_when_wrong_seller(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 2,
        ]);

        $reservation = MarketplacePickupSlotService::reserve(
            $slot->id,
            $this->orderId,
            self::BUYER_UID
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('NOT_FOR_SELLER');

        // The buyer tries to scan as seller — wrong user id
        MarketplacePickupSlotService::scanQr($reservation->qr_code, self::BUYER_UID);
    }

    public function test_scan_qr_throws_qr_not_found_for_unknown_code(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('QR_NOT_FOUND');

        MarketplacePickupSlotService::scanQr('totally-fake-qr-code-9999', self::SELLER_UID);
    }

    // ── update / delete ───────────────────────────────────────────────────────

    public function test_update_modifies_slot_fields(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 2,
            'is_active'  => true,
        ]);

        $updated = MarketplacePickupSlotService::update($slot, ['capacity' => 10, 'is_active' => false]);

        $this->assertSame(10, (int) $updated->capacity);
        $this->assertFalse((bool) $updated->is_active);
    }

    public function test_update_rejects_capacity_below_active_bookings(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity' => 2,
        ]);
        MarketplacePickupSlotService::reserve($slot->id, $this->orderId, self::BUYER_UID);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('CAPACITY_BELOW_BOOKINGS');
        MarketplacePickupSlotService::update($slot->fresh(), ['capacity' => 0]);
    }

    public function test_delete_rejects_slot_with_active_reservation(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity' => 2,
        ]);
        MarketplacePickupSlotService::reserve($slot->id, $this->orderId, self::BUYER_UID);

        try {
            MarketplacePickupSlotService::delete($slot);
            $this->fail('A slot with an active reservation must not be deleted.');
        } catch (\DomainException $e) {
            $this->assertSame('SLOT_HAS_RESERVATIONS', $e->getMessage());
        }

        $this->assertDatabaseHas('marketplace_pickup_slots', ['id' => $slot->id]);
    }

    public function test_delete_removes_slot_from_db(): void
    {
        $slot = MarketplacePickupSlotService::create($this->sellerProfileId, [
            'slot_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'slot_end'   => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'capacity'   => 1,
        ]);
        $slotId = $slot->id;

        MarketplacePickupSlotService::delete($slot);

        $exists = DB::table('marketplace_pickup_slots')->where('id', $slotId)->exists();
        $this->assertFalse($exists, 'Deleted slot must not exist in DB');
    }
}
