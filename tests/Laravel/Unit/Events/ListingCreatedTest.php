<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Events;

use App\Events\ListingCreated;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tests\Laravel\TestCase;

class ListingCreatedTest extends TestCase
{
    public function test_instantiation_stores_properties(): void
    {
        $listing = new Listing();
        $user = new User();
        $user->id = 5;
        $tenantId = 3;

        $event = new ListingCreated($listing, $user, $tenantId);

        $this->assertSame($listing, $event->listing);
        $this->assertSame($user, $event->user);
        $this->assertSame(3, $event->tenantId);
    }

    public function test_implements_should_broadcast(): void
    {
        $this->assertTrue(
            in_array(ShouldBroadcast::class, class_implements(ListingCreated::class))
        );
    }

    public function test_broadcast_on_returns_public_tenant_feed_channel(): void
    {
        $listing = new Listing();
        $user = new User();
        $user->id = 1;

        $event = new ListingCreated($listing, $user, 7);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertEquals('tenant.7.feed', $channels[0]->name);
    }

    public function test_broadcast_as_returns_correct_name(): void
    {
        $listing = new Listing();
        $user = new User();
        $user->id = 1;

        $event = new ListingCreated($listing, $user, 1);

        $this->assertEquals('listing.created', $event->broadcastAs());
    }
}
