<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\MarketplaceOffer;
use App\Services\MarketplaceOfferService;
use Mockery;
use Tests\Laravel\TestCase;

class MarketplaceOfferServiceTest extends TestCase
{
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
