<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ExchangeRatingService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * ExchangeRatingServiceTest — tests for exchange rating submission, retrieval, and checks.
 */
class ExchangeRatingServiceTest extends TestCase
{
    private ExchangeRatingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExchangeRatingService();
        TenantContext::setById(1);
    }

    // =========================================================================
    // submitRating
    // =========================================================================

    public function testSubmitRatingRejectsInvalidRatingBelow1(): void
    {
        $result = $this->service->submitRating(1, 1, 0);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('between 1 and 5', $result['error']);
    }

    public function testSubmitRatingRejectsInvalidRatingAbove5(): void
    {
        $result = $this->service->submitRating(1, 1, 6);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('between 1 and 5', $result['error']);
    }

    public function testSubmitRatingRejectsNonExistentExchange(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null);

        $result = $this->service->submitRating(999999, 1, 5);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Exchange not found', $result['error']);
    }

    public function testSubmitRatingRejectsIncompleteExchange(): void
    {
        $exchange = (object) [
            'id' => 1,
            'requester_id' => 10,
            'provider_id' => 20,
            'status' => 'pending',
        ];

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn($exchange);

        $result = $this->service->submitRating(1, 10, 4);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('completed before rating', $result['error']);
    }

    public function testSubmitRatingRejectsNonParticipant(): void
    {
        $exchange = (object) [
            'id' => 1,
            'requester_id' => 10,
            'provider_id' => 20,
            'status' => 'completed',
        ];

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn($exchange);

        $result = $this->service->submitRating(1, 99, 4);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not a participant', $result['error']);
    }

    public function testSubmitRatingRejectsDuplicateRating(): void
    {
        $exchange = (object) [
            'id' => 1,
            'requester_id' => 10,
            'provider_id' => 20,
            'status' => 'completed',
        ];

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn($exchange);

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn((object) ['id' => 42]);

        $result = $this->service->submitRating(1, 10, 5);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already rated', $result['error']);
    }

    public function testSubmitRatingSucceedsForRequester(): void
    {
        $exchange = (object) [
            'id' => 1,
            'requester_id' => 10,
            'provider_id' => 20,
            'status' => 'completed',
        ];

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn($exchange);

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null); // no existing rating

        DB::shouldReceive('insert')
            ->once()
            ->andReturn(true);

        $result = $this->service->submitRating(1, 10, 5, 'Great service!');
        $this->assertTrue($result['success']);
    }

    public function testSubmitRatingSucceedsForProvider(): void
    {
        $exchange = (object) [
            'id' => 1,
            'requester_id' => 10,
            'provider_id' => 20,
            'status' => 'completed',
        ];

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn($exchange);

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null);

        DB::shouldReceive('insert')
            ->once()
            ->andReturn(true);

        $result = $this->service->submitRating(1, 20, 4);
        $this->assertTrue($result['success']);
    }

    // =========================================================================
    // getRatingsForExchange
    // =========================================================================

    public function testGetRatingsForExchangeReturnsArray(): void
    {
        $rows = [
            (object) [
                'id' => 1,
                'exchange_id' => 5,
                'rater_id' => 10,
                'rated_id' => 20,
                'rating' => 5,
                'comment' => 'Excellent',
                'role' => 'requester',
                'created_at' => '2026-01-01 12:00:00',
                'rater_first_name' => 'John',
                'rater_last_name' => 'Doe',
                'rater_username' => 'johndoe',
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->andReturn($rows);

        $ratings = $this->service->getRatingsForExchange(5);
        $this->assertIsArray($ratings);
        $this->assertCount(1, $ratings);
        $this->assertEquals(5, $ratings[0]['rating']);
        $this->assertEquals('Excellent', $ratings[0]['comment']);
    }

    public function testGetRatingsForExchangeReturnsEmptyForNoRatings(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->andReturn([]);

        $ratings = $this->service->getRatingsForExchange(999);
        $this->assertIsArray($ratings);
        $this->assertEmpty($ratings);
    }

    // =========================================================================
    // hasRated
    // =========================================================================

    public function testHasRatedReturnsTrueWhenRatingExists(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn((object) ['id' => 1]);

        $this->assertTrue($this->service->hasRated(1, 10));
    }

    public function testHasRatedReturnsFalseWhenNoRating(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null);

        $this->assertFalse($this->service->hasRated(1, 10));
    }

    // =========================================================================
    // getUserRating
    // =========================================================================

    public function testGetUserRatingReturnsAggregateData(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn((object) ['average' => '4.50', 'count' => '3']);

        $result = $this->service->getUserRating(10);
        $this->assertArrayHasKey('average', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertEquals(4.5, $result['average']);
        $this->assertEquals(3, $result['count']);
    }

    public function testGetUserRatingReturnsZerosWhenNoRatings(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn((object) ['average' => null, 'count' => '0']);

        $result = $this->service->getUserRating(999);
        $this->assertEquals(0.0, $result['average']);
        $this->assertEquals(0, $result['count']);
    }
}
