<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Listeners;

use App\Events\ListingCreated;
use App\Listeners\UpdateFeedOnListingCreated;
use App\Models\FeedActivity;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class UpdateFeedOnListingCreatedTest extends TestCase
{
    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(UpdateFeedOnListingCreated::class))
        );
    }

    public function test_handle_creates_feed_activity(): void
    {
        $listing = new Listing();
        $listing->id = 100;
        $listing->title = 'Guitar Lessons';
        $listing->description = 'Learn guitar basics';
        $listing->image_url = 'https://example.com/img.jpg';

        $user = new User();
        $user->id = 42;

        $event = new ListingCreated($listing, $user, 2);

        $feedMock = Mockery::mock('alias:' . FeedActivity::class);
        $feedMock->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['tenant_id'] === 2
                    && $data['source_type'] === 'listing'
                    && $data['source_id'] === 100
                    && $data['user_id'] === 42
                    && $data['title'] === 'Guitar Lessons'
                    && $data['is_visible'] === true;
            }));

        $listener = new UpdateFeedOnListingCreated();
        $listener->handle($event);
    }

    public function test_handle_uses_default_title_when_listing_title_is_null(): void
    {
        $listing = new Listing();
        $listing->id = 100;
        $listing->title = null;
        $listing->description = null;

        $user = new User();
        $user->id = 42;

        $event = new ListingCreated($listing, $user, 2);

        $feedMock = Mockery::mock('alias:' . FeedActivity::class);
        $feedMock->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['title'] === 'New Listing';
            }));

        $listener = new UpdateFeedOnListingCreated();
        $listener->handle($event);
    }

    public function test_handle_catches_exceptions_and_logs_error(): void
    {
        $listing = new Listing();
        $listing->id = 100;

        $user = new User();
        $user->id = 42;

        $event = new ListingCreated($listing, $user, 2);

        $feedMock = Mockery::mock('alias:' . FeedActivity::class);
        $feedMock->shouldReceive('create')
            ->andThrow(new \RuntimeException('DB error'));

        Log::shouldReceive('error')
            ->once()
            ->with('UpdateFeedOnListingCreated listener failed', Mockery::type('array'));

        $listener = new UpdateFeedOnListingCreated();
        $listener->handle($event);
    }
}
