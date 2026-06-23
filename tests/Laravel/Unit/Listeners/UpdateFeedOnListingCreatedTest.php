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
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class UpdateFeedOnListingCreatedTest extends TestCase
{
    private $feedMock;

    protected function setUp(): void
    {
        // App\Models\FeedActivity may already be autoloaded by app boot or an
        // earlier test in the combined run, so the alias mock MUST be created
        // before parent::setUp() and tolerate the class already existing.
        // shouldIgnoreMissing() makes boot-time/static calls no-ops; per-test
        // expectations are layered on the shared instance in each test.
        $this->feedMock = Mockery::mock('alias:' . FeedActivity::class)->shouldIgnoreMissing();
        parent::setUp();
    }

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

        $feedMock = $this->feedMock;
        // The listener calls FeedActivity::firstOrCreate($keys, $values) for
        // idempotency on the (tenant_id, source_type, source_id) unique key.
        $feedMock->shouldReceive('firstOrCreate')
            ->once()
            ->with(
                Mockery::on(function ($keys) {
                    return $keys['tenant_id'] === 2
                        && $keys['source_type'] === 'listing'
                        && $keys['source_id'] === 100;
                }),
                Mockery::on(function ($values) {
                    return $values['user_id'] === 42
                        && $values['title'] === 'Guitar Lessons'
                        && $values['is_visible'] === true;
                })
            );

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

        $feedMock = $this->feedMock;
        $feedMock->shouldReceive('firstOrCreate')
            ->once()
            ->with(
                Mockery::type('array'),
                Mockery::on(function ($values) {
                    return $values['title'] === 'New Listing';
                })
            );

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

        $feedMock = $this->feedMock;
        $feedMock->shouldReceive('firstOrCreate')
            ->andThrow(new \RuntimeException('DB error'));

        Log::shouldReceive('error')
            ->once()
            ->with('UpdateFeedOnListingCreated listener failed', Mockery::type('array'));

        $listener = new UpdateFeedOnListingCreated();
        $listener->handle($event);
    }
}
