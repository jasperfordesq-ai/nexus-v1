<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Exceptions\SafeguardingPolicyException;
use App\Models\MarketplaceOffer;
use App\Models\User;
use App\Services\MarketplaceOfferService;
use App\Services\SafeguardingInteractionPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

class MarketplaceOfferServiceTest extends TestCase
{
    use DatabaseTransactions;

    // ── accept / decline / counter: seller ownership guard ───────────

    public function test_accept_rejects_when_caller_is_not_the_seller(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->seller_id = 10;
        $offer->status = 'pending';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You are not the seller');

        MarketplaceOfferService::accept($offer, 999);
    }

    public function test_decline_rejects_when_caller_is_not_the_seller(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->seller_id = 10;
        $offer->status = 'pending';

        $this->expectException(\InvalidArgumentException::class);
        MarketplaceOfferService::decline($offer, 999);
    }

    public function test_counter_rejects_when_caller_is_not_the_seller(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->seller_id = 10;
        $offer->status = 'pending';

        $this->expectException(\InvalidArgumentException::class);
        MarketplaceOfferService::counter($offer, 999, ['amount' => 50]);
    }

    public function test_create_denial_writes_no_offer_or_contact_increment(): void
    {
        $seller = User::factory()->forTenant($this->testTenantId)->create();
        $buyer = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);

        $listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Protected listing',
            'description' => 'Safeguarding policy test',
            'price' => 10,
            'price_currency' => 'GBP',
            'price_type' => 'fixed',
            'quantity' => 1,
            'status' => 'active',
            'moderation_status' => 'approved',
            'contacts_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with((int) $buyer->id, (int) $seller->id, $this->testTenantId, 'marketplace_offer')
            ->andThrow(new SafeguardingPolicyException('VETTING_REQUIRED', 'Vetting required'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        try {
            MarketplaceOfferService::create((int) $buyer->id, (int) $listingId, ['amount' => 9]);
            $this->fail('Expected safeguarding denial');
        } catch (SafeguardingPolicyException $e) {
            $this->assertSame('VETTING_REQUIRED', $e->reasonCode);
        }

        $this->assertDatabaseMissing('marketplace_offers', [
            'tenant_id' => $this->testTenantId,
            'marketplace_listing_id' => $listingId,
            'buyer_id' => $buyer->id,
        ]);
        $this->assertSame(0, (int) DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->value('contacts_count'));
    }

    public function test_counter_denial_leaves_offer_unchanged(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->tenant_id = $this->testTenantId;
        $offer->seller_id = 10;
        $offer->buyer_id = 5;
        $offer->status = 'pending';
        $offer->expires_at = null;
        $offer->shouldNotReceive('save');

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with(10, 5, $this->testTenantId, 'marketplace_counter_offer')
            ->andThrow(new SafeguardingPolicyException('VETTING_REQUIRED', 'Vetting required'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $this->expectException(SafeguardingPolicyException::class);
        MarketplaceOfferService::counter($offer, 10, [
            'amount' => 8,
            'message' => 'Must not persist',
        ]);
    }

    // ── actionable-state guard ───────────────────────────────────────

    public function test_accept_rejects_when_offer_already_accepted(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->seller_id = 10;
        $offer->status = 'accepted';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot act on an offer with status 'accepted'");

        MarketplaceOfferService::accept($offer, 10);
    }

    // ── withdraw: buyer-only ─────────────────────────────────────────

    public function test_withdraw_rejects_when_caller_is_not_the_buyer(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->buyer_id = 5;
        $offer->status = 'pending';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only the buyer can withdraw');

        MarketplaceOfferService::withdraw($offer, 999);
    }

    // ── acceptCounter: requires 'countered' status ───────────────────

    public function test_acceptCounter_rejects_when_caller_is_not_the_buyer(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->buyer_id = 5;
        $offer->status = 'countered';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only the buyer can accept a counter-offer');

        MarketplaceOfferService::acceptCounter($offer, 999);
    }

    public function test_acceptCounter_rejects_when_offer_not_in_countered_status(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->buyer_id = 5;
        $offer->status = 'pending';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('has not been countered');

        MarketplaceOfferService::acceptCounter($offer, 5);
    }
}
