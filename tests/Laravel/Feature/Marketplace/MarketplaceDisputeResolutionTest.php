<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\MarketplaceDisputeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class MarketplaceDisputeResolutionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
    }

    public function test_seller_resolution_restores_the_pre_dispute_order_status(): void
    {
        [$buyer, $seller] = $this->participants();
        $orderId = $this->createOrder((int) $buyer->id, (int) $seller->id, [
            'status' => 'disputed',
        ]);
        $disputeId = $this->createDispute($orderId, (int) $buyer->id, 'shipped');

        $resolved = app(MarketplaceDisputeService::class)->resolve($disputeId, 1, [
            'resolution' => 'seller',
            'resolution_notes' => 'Tracking confirms delivery is in progress.',
        ]);

        $this->assertSame('resolved_seller', $resolved->status);
        $this->assertDatabaseHas('marketplace_orders', [
            'id' => $orderId,
            'tenant_id' => $this->testTenantId,
            'status' => 'shipped',
        ]);
    }

    public function test_free_order_buyer_resolution_refunds_once_and_restores_inventory(): void
    {
        [$buyer, $seller] = $this->participants();
        $listingId = $this->createListing((int) $seller->id, 4, 'sold');
        $orderId = $this->createOrder((int) $buyer->id, (int) $seller->id, [
            'marketplace_listing_id' => $listingId,
            'status' => 'disputed',
            'total_price' => 0,
            'unit_price' => 0,
        ]);
        $disputeId = $this->createDispute($orderId, (int) $buyer->id, 'paid');

        $service = app(MarketplaceDisputeService::class);
        $service->resolve($disputeId, 1, [
            'resolution' => 'buyer',
            'resolution_notes' => 'The free item was unavailable.',
        ]);

        $this->assertDatabaseHas('marketplace_orders', ['id' => $orderId, 'status' => 'refunded']);
        $this->assertDatabaseHas('marketplace_listings', [
            'id' => $listingId,
            'inventory_count' => 5,
            'status' => 'active',
        ]);
    }

    public function test_time_credit_buyer_resolution_writes_an_idempotent_reversal_ledger_entry(): void
    {
        [$buyer, $seller] = $this->participants([
            'buyer_balance' => 0,
            'seller_balance' => 10,
        ]);
        $originalTransactionId = DB::table('transactions')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'amount' => 10,
            'description' => 'Original marketplace purchase',
            'transaction_type' => 'marketplace_purchase',
            'status' => 'completed',
            'deleted_for_sender' => false,
            'deleted_for_receiver' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $this->createOrder((int) $buyer->id, (int) $seller->id, [
            'status' => 'disputed',
            'total_price' => 0,
            'unit_price' => 0,
            'time_credits_used' => 10,
            'wallet_transaction_id' => $originalTransactionId,
        ]);
        $disputeId = $this->createDispute($orderId, (int) $buyer->id, 'paid');

        app(MarketplaceDisputeService::class)->resolve($disputeId, 1, [
            'resolution' => 'buyer',
            'resolution_notes' => 'The service was not provided.',
        ]);

        $order = DB::table('marketplace_orders')->where('id', $orderId)->first();
        $this->assertSame('refunded', $order->status);
        $this->assertNotNull($order->wallet_refund_transaction_id);
        $this->assertDatabaseHas('transactions', [
            'id' => $order->wallet_refund_transaction_id,
            'tenant_id' => $this->testTenantId,
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'amount' => 10.00,
            'transaction_type' => 'marketplace_refund',
            'status' => 'completed',
        ]);
        $this->assertSame(10.0, (float) $buyer->fresh()->balance);
        $this->assertSame(0.0, (float) $seller->fresh()->balance);
    }

    /** @return array{0:User,1:User} */
    private function participants(array $balances = []): array
    {
        return [
            User::factory()->forTenant($this->testTenantId)->create([
                'balance' => $balances['buyer_balance'] ?? 0,
                'preferred_language' => 'en',
            ]),
            User::factory()->forTenant($this->testTenantId)->create([
                'balance' => $balances['seller_balance'] ?? 0,
                'preferred_language' => 'en',
            ]),
        ];
    }

    private function createListing(int $sellerId, int $inventory, string $status): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $sellerId,
            'title' => 'Dispute test listing',
            'description' => 'A listing used to verify dispute resolution.',
            'price_type' => 'free',
            'price_currency' => 'EUR',
            'inventory_count' => $inventory,
            'status' => $status,
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(int $buyerId, int $sellerId, array $overrides): int
    {
        return (int) DB::table('marketplace_orders')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'order_number' => 'DIS-' . uniqid(),
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'marketplace_listing_id' => null,
            'quantity' => 1,
            'unit_price' => 10,
            'total_price' => 10,
            'currency' => 'EUR',
            'time_credits_used' => 0,
            'status' => 'disputed',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createDispute(int $orderId, int $openedBy, string $priorStatus): int
    {
        return (int) DB::table('marketplace_disputes')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'opened_by' => $openedBy,
            'reason' => 'not_received',
            'description' => 'The order was not received.',
            'status' => 'open',
            'prior_order_status' => $priorStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
