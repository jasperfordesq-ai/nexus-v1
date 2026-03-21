<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ReviewService;
use App\Models\Review;
use Mockery;

class ReviewServiceTest extends TestCase
{
    private ReviewService $service;
    private $mockReview;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockReview = Mockery::mock(Review::class);
        $this->service = new ReviewService($this->mockReview);
    }

    // ── getById ──

    public function test_getById_returns_null_when_not_found(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('find')->with(999)->andReturnNull();
        $this->mockReview->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getById(999);
        $this->assertNull($result);
    }

    // ── getStats ──

    public function test_getStats_returns_zero_for_user_without_reviews(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('count')->andReturn(0);
        $mockQuery->shouldReceive('avg')->andReturn(null);
        $this->mockReview->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getStats(999);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['average']);
        $this->assertArrayHasKey('distribution', $result);
        $this->assertArrayHasKey('positive', $result);
        $this->assertArrayHasKey('negative', $result);
    }

    // ── create ──

    public function test_create_throws_for_self_review(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot review yourself');

        $this->service->create(5, [
            'receiver_id' => 5,
            'rating' => 5,
        ]);
    }

    // ── delete ──

    public function test_delete_returns_false_when_not_found(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('find')->with(999)->andReturnNull();
        $this->mockReview->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->delete(999);
        $this->assertFalse($result);
    }

    public function test_delete_sets_status_to_hidden(): void
    {
        $review = Mockery::mock(Review::class);
        $review->shouldReceive('setAttribute')->with('status', 'hidden');
        $review->status = 'approved';
        $review->shouldReceive('save')->once()->andReturn(true);

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('find')->with(1)->andReturn($review);
        $this->mockReview->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->delete(1);
        $this->assertTrue($result);
    }
}
