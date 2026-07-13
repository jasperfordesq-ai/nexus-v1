<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Models\MarketplaceOrder;
use App\Models\User;
use App\Services\MarketplaceTimeCreditSettlementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Laravel\TestCase;

class MarketplaceTimeCreditSettlementServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function makeOrder(float $buyerBalance = 10.0, float $sellerBalance = 1.0): array
    {
        $buyer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'balance' => $buyerBalance,
        ]);
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'balance' => $sellerBalance,
        ]);
        $orderId = (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_number' => 'MKT-TC-' . strtoupper(uniqid('', true)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'marketplace_listing_id' => null,
            'quantity' => 1,
            'unit_price' => 3.0,
            'total_price' => 0.0,
            'currency' => 'HRS',
            'time_credits_used' => 3.0,
            'status' => 'pending_payment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            $buyer,
            $seller,
            MarketplaceOrder::withoutGlobalScopes()->findOrFail($orderId),
        ];
    }

    public function test_settlement_and_refund_are_each_idempotent(): void
    {
        [$buyer, $seller, $order] = $this->makeOrder();
        $service = app(MarketplaceTimeCreditSettlementService::class);

        $settled = $service->settle($order);
        $settledAgain = $service->settle($order->fresh());

        $this->assertSame('paid', $settled->status);
        $this->assertSame($settled->wallet_transaction_id, $settledAgain->wallet_transaction_id);
        $this->assertSame(7.0, (float) $buyer->fresh()->balance);
        $this->assertSame(4.0, (float) $seller->fresh()->balance);
        $this->assertSame(1, DB::table('transactions')
            ->where('tenant_id', $this->testTenantId)
            ->where('transaction_type', 'marketplace_purchase')
            ->where('sender_id', $buyer->id)
            ->where('receiver_id', $seller->id)
            ->count());

        $refunded = $service->refund($settled, 'buyer request');
        $refundedAgain = $service->refund($settled->fresh(), 'retry');

        $this->assertSame('refunded', $refunded->status);
        $this->assertSame(
            $refunded->wallet_refund_transaction_id,
            $refundedAgain->wallet_refund_transaction_id,
        );
        $this->assertSame(10.0, (float) $buyer->fresh()->balance);
        $this->assertSame(1.0, (float) $seller->fresh()->balance);
        $this->assertSame(1, DB::table('transactions')
            ->where('tenant_id', $this->testTenantId)
            ->where('transaction_type', 'marketplace_refund')
            ->where('sender_id', $seller->id)
            ->where('receiver_id', $buyer->id)
            ->count());
    }

    public function test_insufficient_balance_rolls_back_without_a_ledger_entry(): void
    {
        [$buyer, $seller, $order] = $this->makeOrder(2.0, 0.0);

        try {
            app(MarketplaceTimeCreditSettlementService::class)->settle($order);
            $this->fail('Settlement must fail when the buyer cannot cover the amount.');
        } catch (RuntimeException) {
            $this->assertSame(2.0, (float) $buyer->fresh()->balance);
            $this->assertSame(0.0, (float) $seller->fresh()->balance);
        }

        $this->assertDatabaseHas('marketplace_orders', [
            'id' => $order->id,
            'status' => 'pending_payment',
            'wallet_transaction_id' => null,
        ]);
        $this->assertDatabaseMissing('transactions', [
            'tenant_id' => $this->testTenantId,
            'transaction_type' => 'marketplace_purchase',
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
        ]);
    }
}
