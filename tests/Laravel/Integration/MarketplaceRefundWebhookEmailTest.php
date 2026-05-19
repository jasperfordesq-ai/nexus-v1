<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\MarketplacePaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MarketplaceRefundWebhookEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_charge_refunded_webhook_notifies_buyer_and_seller_once(): void
    {
        $tenantId = $this->testTenantId;
        TenantContext::setById($tenantId);

        $buyer = User::factory()->forTenant($tenantId)->create([
            'email' => 'marketplace-refund-buyer@example.test',
            'preferred_language' => 'en',
        ]);
        $seller = User::factory()->forTenant($tenantId)->create([
            'email' => 'marketplace-refund-seller@example.test',
            'preferred_language' => 'en',
        ]);
        $listingId = $this->createListing($tenantId, (int) $seller->id);
        $orderId = $this->createOrder($tenantId, (int) $buyer->id, (int) $seller->id, $listingId);
        $this->createPayment($tenantId, $orderId);

        $mailer = new class extends EmailDispatchService {
            /** @var list<array{to:string,subject:string,body:string,options:array<string,mixed>}> */
            public array $calls = [];

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');
                DB::table('email_log')->insert([
                    'tenant_id' => $options['tenant_id'] ?? null,
                    'recipient_email' => $to,
                    'category' => $options['category'] ?? null,
                    'subject' => $subject,
                    'provider' => 'smtp',
                    'status' => 'sent',
                    'sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return true;
            }
        };
        app()->instance(EmailDispatchService::class, $mailer);

        $charge = (object) [
            'payment_intent' => 'pi_marketplace_refund_email',
            'amount_refunded' => 1000,
            'amount' => 1000,
        ];

        MarketplacePaymentService::handleWebhookEvent('charge.refunded', $charge);
        MarketplacePaymentService::handleWebhookEvent('charge.refunded', $charge);

        $this->assertCount(2, $mailer->calls);
        $recipients = array_column($mailer->calls, 'to');
        sort($recipients);
        $this->assertSame([
            'marketplace-refund-buyer@example.test',
            'marketplace-refund-seller@example.test',
        ], $recipients);
        $this->assertSame('marketplace_refund', $mailer->calls[0]['options']['category']);
        $this->assertSame($tenantId, $mailer->calls[0]['options']['tenant_id']);
        $this->assertSame(2, DB::table('notifications')->where('tenant_id', $tenantId)->where('type', 'marketplace_order')->count());
    }

    private function createListing(int $tenantId, int $sellerId): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $sellerId,
            'title' => 'Refund webhook item',
            'description' => 'A listing used to verify refund webhook email routing.',
            'price' => 10.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'shipping_available' => 0,
            'local_pickup' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(int $tenantId, int $buyerId, int $sellerId, int $listingId): int
    {
        return (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $tenantId,
            'order_number' => 'MRW-' . uniqid(),
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'marketplace_listing_id' => $listingId,
            'quantity' => 1,
            'unit_price' => 10.00,
            'total_price' => 10.00,
            'currency' => 'EUR',
            'status' => 'paid',
            'payment_intent_id' => 'pi_marketplace_refund_email',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPayment(int $tenantId, int $orderId): void
    {
        DB::table('marketplace_payments')->insert([
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'stripe_payment_intent_id' => 'pi_marketplace_refund_email',
            'amount' => 10.00,
            'currency' => 'EUR',
            'platform_fee' => 0.50,
            'seller_payout' => 9.50,
            'status' => 'succeeded',
            'payout_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
