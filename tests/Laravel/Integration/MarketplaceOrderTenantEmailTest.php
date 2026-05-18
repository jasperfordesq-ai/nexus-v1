<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\MarketplaceOffer;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\MarketplaceOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MarketplaceOrderTenantEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_from_offer_uses_offer_tenant_not_ambient_context_for_order_and_emails(): void
    {
        $marketplaceTenantId = 999;
        $seller = User::factory()->forTenant($marketplaceTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'email' => 'marketplace-seller-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $buyer = User::factory()->forTenant($marketplaceTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'email' => 'marketplace-buyer-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);

        $listingId = $this->createListing($marketplaceTenantId, (int) $seller->id);
        $offerId = $this->createAcceptedOffer($marketplaceTenantId, $listingId, (int) $buyer->id, (int) $seller->id);
        $offer = MarketplaceOffer::withoutGlobalScopes()->findOrFail($offerId);

        $mailer = new class extends EmailDispatchService {
            public array $calls = [];

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');

                return true;
            }
        };
        app()->instance(EmailDispatchService::class, $mailer);

        TenantContext::setById(2);

        $order = MarketplaceOrderService::createFromOffer($offer);

        $this->assertSame($marketplaceTenantId, (int) $order->tenant_id);
        $this->assertSame($marketplaceTenantId, (int) DB::table('marketplace_orders')->where('id', $order->id)->value('tenant_id'));
        $this->assertSame('sold', DB::table('marketplace_listings')->where('id', $listingId)->value('status'));
        $this->assertCount(2, $mailer->calls);
        $this->assertSame($marketplaceTenantId, $mailer->calls[0]['options']['tenant_id']);
        $this->assertSame($marketplaceTenantId, $mailer->calls[1]['options']['tenant_id']);
        $this->assertSame('marketplace_order', $mailer->calls[0]['options']['category']);
        $this->assertSame('marketplace_order', $mailer->calls[1]['options']['category']);
        $this->assertSame(2, DB::table('notifications')->where('type', 'marketplace_order')->where('tenant_id', $marketplaceTenantId)->count());
        $this->assertSame(2, TenantContext::getId());
    }

    private function createListing(int $tenantId, int $sellerId): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $sellerId,
            'title' => 'Tenant-bound marketplace item',
            'description' => 'A listing used to verify marketplace tenant routing.',
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

    private function createAcceptedOffer(int $tenantId, int $listingId, int $buyerId, int $sellerId): int
    {
        return (int) DB::table('marketplace_offers')->insertGetId([
            'tenant_id' => $tenantId,
            'marketplace_listing_id' => $listingId,
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'amount' => 10.00,
            'currency' => 'EUR',
            'status' => 'accepted',
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
