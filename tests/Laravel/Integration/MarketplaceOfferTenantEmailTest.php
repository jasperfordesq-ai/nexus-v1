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
use App\Services\MarketplaceOfferService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MarketplaceOfferTenantEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_offer_uses_listing_tenant_not_ambient_context_for_email_and_bell(): void
    {
        $tenantId = 999;
        [$seller, $buyer, $listingId] = $this->createSellerBuyerAndListing($tenantId);
        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        TenantContext::setById(2);

        $offer = MarketplaceOfferService::create((int) $buyer->id, $listingId, [
            'amount' => 12.50,
            'currency' => 'EUR',
            'message' => 'Would love to buy this.',
        ]);

        $this->assertSame($tenantId, (int) $offer->tenant_id);
        $this->assertSame(1, DB::table('marketplace_listings')->where('id', $listingId)->value('contacts_count'));
        $this->assertCount(1, $mailer->calls);
        $this->assertSame($seller->email, $mailer->calls[0]['to']);
        $this->assertSame('marketplace_offer', $mailer->calls[0]['options']['category']);
        $this->assertSame($tenantId, $mailer->calls[0]['options']['tenant_id']);
        $this->assertSame(1, DB::table('notifications')->where('tenant_id', $tenantId)->where('user_id', $seller->id)->where('type', 'marketplace_offer')->count());
        $this->assertSame(2, TenantContext::getId());
    }

    public function test_create_offer_rejects_buyer_from_different_tenant_before_email_and_bell(): void
    {
        $tenantId = 999;
        [$seller, , $listingId] = $this->createSellerBuyerAndListing($tenantId);
        $crossTenantBuyer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'email' => 'offer-cross-buyer-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        TenantContext::setById($this->testTenantId);

        try {
            MarketplaceOfferService::create((int) $crossTenantBuyer->id, $listingId, [
                'amount' => 12.50,
                'currency' => 'EUR',
                'message' => 'Wrong tenant offer attempt.',
            ]);
            $this->fail('Cross-tenant marketplace offer was not rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(__('api_controllers_2.marketplace_offer.buyer_tenant_mismatch'), $e->getMessage());
        }

        $this->assertCount(0, $mailer->calls);
        $this->assertSame(0, DB::table('marketplace_offers')->where('marketplace_listing_id', $listingId)->count());
        $this->assertSame(0, DB::table('notifications')->where('tenant_id', $tenantId)->where('user_id', $seller->id)->count());
        $this->assertSame($this->testTenantId, TenantContext::getId());
    }

    public function test_accept_offer_uses_offer_tenant_not_ambient_context_for_email_bell_and_other_offers(): void
    {
        $tenantId = 999;
        [$seller, $buyer, $listingId] = $this->createSellerBuyerAndListing($tenantId);
        $otherBuyer = User::factory()->forTenant($tenantId)->create([
            'email' => 'offer-other-buyer-' . uniqid('', true) . '@example.test',
        ]);
        $offerId = $this->createOffer($tenantId, $listingId, (int) $buyer->id, (int) $seller->id, 'pending');
        $otherOfferId = $this->createOffer($tenantId, $listingId, (int) $otherBuyer->id, (int) $seller->id, 'countered');
        $offer = MarketplaceOffer::withoutGlobalScopes()->findOrFail($offerId);
        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        TenantContext::setById(2);

        MarketplaceOfferService::accept($offer, (int) $seller->id);

        $this->assertSame('reserved', DB::table('marketplace_listings')->where('id', $listingId)->value('status'));
        $this->assertSame('accepted', DB::table('marketplace_offers')->where('id', $offerId)->value('status'));
        $this->assertSame('declined', DB::table('marketplace_offers')->where('id', $otherOfferId)->value('status'));
        $this->assertCount(1, $mailer->calls);
        $this->assertSame($buyer->email, $mailer->calls[0]['to']);
        $this->assertSame('marketplace_offer', $mailer->calls[0]['options']['category']);
        $this->assertSame($tenantId, $mailer->calls[0]['options']['tenant_id']);
        $this->assertSame(1, DB::table('notifications')->where('tenant_id', $tenantId)->where('user_id', $buyer->id)->where('type', 'marketplace_offer')->count());
        $this->assertSame(2, TenantContext::getId());
    }

    public function test_marketplace_payment_webhook_resolves_unconfirmed_order_and_restores_tenant_context(): void
    {
        $source = file_get_contents(app_path('Services/MarketplacePaymentService.php'));

        $this->assertStringContainsString("->where('payment_intent_id', \$piId)", $source);
        $this->assertStringContainsString('$previousTenantId = TenantContext::currentId();', $source);
        $this->assertStringContainsString('TenantContext::reset();', $source);
    }

    /**
     * @return array{0:User,1:User,2:int}
     */
    private function createSellerBuyerAndListing(int $tenantId): array
    {
        $seller = User::factory()->forTenant($tenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'email' => 'offer-seller-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $buyer = User::factory()->forTenant($tenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'email' => 'offer-buyer-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);

        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $seller->id,
            'title' => 'Tenant-bound offer item',
            'description' => 'A listing used to verify marketplace offer tenant routing.',
            'price' => 20.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'contacts_count' => 0,
            'shipping_available' => 0,
            'local_pickup' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$seller, $buyer, $listingId];
    }

    private function createOffer(int $tenantId, int $listingId, int $buyerId, int $sellerId, string $status): int
    {
        return (int) DB::table('marketplace_offers')->insertGetId([
            'tenant_id' => $tenantId,
            'marketplace_listing_id' => $listingId,
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'amount' => 20.00,
            'currency' => 'EUR',
            'status' => $status,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fakeMailer(): EmailDispatchService
    {
        return new class extends EmailDispatchService {
            public array $calls = [];

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');

                return true;
            }
        };
    }
}
