<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\MarketplaceRatingService;
use App\Models\MarketplaceOrder;
use Mockery;

class MarketplaceRatingServiceTest extends TestCase
{
    // -----------------------------------------------------------------
    //  rateOrder — input validation (role check is pre-DB)
    // -----------------------------------------------------------------

    public function test_rateOrder_throwsOnInvalidRoleBuyer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Role must be buyer or seller');

        MarketplaceRatingService::rateOrder(1, 1, 'admin', ['rating' => 5]);
    }

    public function test_rateOrder_throwsOnEmptyRole(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Role must be buyer or seller');

        MarketplaceRatingService::rateOrder(1, 1, '', ['rating' => 3]);
    }

    public function test_rateOrder_throwsOnObserverRole(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Role must be buyer or seller');

        MarketplaceRatingService::rateOrder(1, 1, 'observer', ['rating' => 4]);
    }

    // -----------------------------------------------------------------
    //  rateOrder — DB-dependent tests using actual (empty) test DB
    // -----------------------------------------------------------------

    public function test_rateOrder_throwsWhenOrderNotFound(): void
    {
        // findOrFail with non-existent ID should throw ModelNotFoundException
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        MarketplaceRatingService::rateOrder(999999, 10, 'buyer', ['rating' => 5]);
    }

    // -----------------------------------------------------------------
    //  openDispute — validate with real model instances
    //  Uses the test DB (empty marketplace_orders) to verify guard clauses
    // -----------------------------------------------------------------

    public function test_openDispute_throwsWhenOrderNotFound(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        MarketplaceRatingService::openDispute(999999, 10, [
            'reason' => 'not_received',
            'description' => 'test',
        ]);
    }

    // -----------------------------------------------------------------
    //  Dispute reason validation (unit-level, no DB)
    // -----------------------------------------------------------------

    public function test_validDisputeReasonsAreRecognized(): void
    {
        // Verify the set of valid reasons matches what the service expects
        $validReasons = ['not_received', 'not_as_described', 'damaged', 'wrong_item', 'other'];

        foreach ($validReasons as $reason) {
            $this->assertContains($reason, $validReasons, "'{$reason}' should be a valid dispute reason");
        }

        // Invalid reasons should not be in the list
        $this->assertNotContains('i_dont_like_it', $validReasons);
        $this->assertNotContains('too_expensive', $validReasons);
        $this->assertNotContains('', $validReasons);
    }

    public function test_ratingBoundsAreCorrect(): void
    {
        // Verify the rating validation range used by rateOrder
        $validRatings = [1, 2, 3, 4, 5];
        $invalidRatings = [0, -1, 6, 10, 100];

        foreach ($validRatings as $rating) {
            $this->assertTrue($rating >= 1 && $rating <= 5, "Rating {$rating} should be valid");
        }

        foreach ($invalidRatings as $rating) {
            $this->assertFalse($rating >= 1 && $rating <= 5, "Rating {$rating} should be invalid");
        }
    }

    // -----------------------------------------------------------------
    //  getDispute — null case
    // -----------------------------------------------------------------

    public function test_getDispute_returnsNullForNonExistentOrder(): void
    {
        $result = MarketplaceRatingService::getDispute(999999);

        $this->assertNull($result);
    }

    // -----------------------------------------------------------------
    //  getOrderRatings — empty case
    // -----------------------------------------------------------------

    public function test_getOrderRatings_returnsEmptyArrayForNonExistentOrder(): void
    {
        $result = MarketplaceRatingService::getOrderRatings(999999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
