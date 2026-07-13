<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\MarketplacePickupSlotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class MarketplacePickupSlotControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['marketplace' => true]),
        ]);
        TenantContext::setById($this->testTenantId);
    }

    public function test_reserve_keeps_machine_code_but_returns_translated_message(): void
    {
        $buyer = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        Sanctum::actingAs($buyer, ['*']);

        $response = $this->apiPost('/v2/marketplace/orders/99999999/pickup-reservation', [
            'slot_id' => 99999999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'ORDER_NOT_FOUND');
        $response->assertJsonPath('errors.0.message', __('api.marketplace_pickup_order_not_found'));
        $this->assertNotSame('ORDER_NOT_FOUND', $response->json('errors.0.message'));
    }

    public function test_scan_keeps_machine_code_but_returns_translated_message(): void
    {
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        Sanctum::actingAs($seller, ['*']);

        $response = $this->apiPost('/v2/marketplace/seller/pickup-scan', [
            'qr_code' => 'missing-pickup-code',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'QR_NOT_FOUND');
        $response->assertJsonPath('errors.0.message', __('api.marketplace_pickup_qr_not_found'));
        $this->assertNotSame('QR_NOT_FOUND', $response->json('errors.0.message'));
    }

    public function test_destroy_with_booking_returns_translated_conflict_message(): void
    {
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $buyer = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $profileId = (int) DB::table('marketplace_seller_profiles')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'seller_type' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Pickup controller fixture',
            'description' => 'Pickup controller translation fixture.',
            'price_type' => 'free',
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_number' => 'MKT-PICKUP-CONTROLLER-' . uniqid(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'marketplace_listing_id' => $listingId,
            'quantity' => 1,
            'unit_price' => 0,
            'total_price' => 0,
            'currency' => 'EUR',
            'shipping_method' => 'pickup',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $slot = MarketplacePickupSlotService::create($profileId, [
            'slot_start' => now()->addDay()->toDateTimeString(),
            'slot_end' => now()->addDay()->addHour()->toDateTimeString(),
            'capacity' => 2,
        ]);
        MarketplacePickupSlotService::reserve((int) $slot->id, $orderId, (int) $buyer->id);
        Sanctum::actingAs($seller, ['*']);

        $response = $this->apiDelete("/v2/marketplace/seller/pickup-slots/{$slot->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('errors.0.code', 'SLOT_HAS_RESERVATIONS');
        $response->assertJsonPath('errors.0.message', __('api.marketplace_pickup_slot_has_reservations'));
        $this->assertNotSame('SLOT_HAS_RESERVATIONS', $response->json('errors.0.message'));
        $this->assertDatabaseHas('marketplace_pickup_slots', ['id' => $slot->id]);
    }
}
