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
        $mockQuery->shouldReceive('withFederated')->andReturnSelf();
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('find')->with(999)->andReturnNull();
        $this->mockReview->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getById(999);
        $this->assertNull($result);
    }

    // ── getStats ──

    public function test_getStats_returns_zero_for_user_without_reviews(): void
    {
        // getStats() builds a base query (newQuery->withFederated->where->where)
        // then runs two aggregate passes:
        //   1) selectRaw('COUNT(*)..AVG(rating)..')->first()
        //   2) selectRaw('rating, COUNT(*)..')->groupBy('rating')->get()
        // For a user with no reviews, the aggregate row is empty and the
        // distribution collection is empty.
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('withFederated')->andReturnSelf();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('selectRaw')->andReturnSelf();
        $mockQuery->shouldReceive('groupBy')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturn((object) ['total' => 0, 'average' => null]);
        $mockQuery->shouldReceive('get')->andReturn(collect());
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
        // delete() is a soft-delete: it sets $review->status = 'hidden' (which on
        // an Eloquent model routes through setAttribute) and calls save().
        // Expect exactly that attribute write — the previous `$review->status =
        // 'approved'` line tripped an unexpected setAttribute('status','approved')
        // call on the mock and asserted nothing useful.
        // 'rejected' (not 'hidden') — reviews.status is enum('pending',
        // 'approved','rejected'); 'hidden' threw on every reviewer-delete.
        $review = Mockery::mock(Review::class);
        $review->shouldReceive('setAttribute')->once()->with('status', 'rejected');
        // delete() also stamps deleted_by_author_at to distinguish an
        // author-delete from a moderator-reject.
        $review->shouldReceive('setAttribute')->once()->with('deleted_by_author_at', Mockery::type(\Illuminate\Support\Carbon::class));
        $review->shouldReceive('save')->once()->andReturn(true);

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('find')->with(1)->andReturn($review);
        $this->mockReview->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->delete(1);
        $this->assertTrue($result);
    }
}
