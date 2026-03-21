<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Providers;

use App\Events\ConnectionRequested;
use App\Events\ListingCreated;
use App\Events\MessageSent;
use App\Events\TransactionCompleted;
use App\Events\UserRegistered;
use App\Listeners\NotifyConnectionRequest;
use App\Listeners\NotifyMessageReceived;
use App\Listeners\SendWelcomeNotification;
use App\Listeners\UpdateFeedOnListingCreated;
use App\Listeners\UpdateWalletBalance;
use App\Providers\EventServiceProvider;
use Tests\Laravel\TestCase;

class EventServiceProviderTest extends TestCase
{
    public function test_user_registered_maps_to_send_welcome_notification(): void
    {
        $provider = new EventServiceProvider($this->app);
        $listen = $this->getListenProperty($provider);

        $this->assertArrayHasKey(UserRegistered::class, $listen);
        $this->assertContains(SendWelcomeNotification::class, $listen[UserRegistered::class]);
    }

    public function test_listing_created_maps_to_update_feed(): void
    {
        $provider = new EventServiceProvider($this->app);
        $listen = $this->getListenProperty($provider);

        $this->assertArrayHasKey(ListingCreated::class, $listen);
        $this->assertContains(UpdateFeedOnListingCreated::class, $listen[ListingCreated::class]);
    }

    public function test_transaction_completed_maps_to_update_wallet_balance(): void
    {
        $provider = new EventServiceProvider($this->app);
        $listen = $this->getListenProperty($provider);

        $this->assertArrayHasKey(TransactionCompleted::class, $listen);
        $this->assertContains(UpdateWalletBalance::class, $listen[TransactionCompleted::class]);
    }

    public function test_connection_requested_maps_to_notify_connection_request(): void
    {
        $provider = new EventServiceProvider($this->app);
        $listen = $this->getListenProperty($provider);

        $this->assertArrayHasKey(ConnectionRequested::class, $listen);
        $this->assertContains(NotifyConnectionRequest::class, $listen[ConnectionRequested::class]);
    }

    public function test_message_sent_maps_to_notify_message_received(): void
    {
        $provider = new EventServiceProvider($this->app);
        $listen = $this->getListenProperty($provider);

        $this->assertArrayHasKey(MessageSent::class, $listen);
        $this->assertContains(NotifyMessageReceived::class, $listen[MessageSent::class]);
    }

    public function test_event_discovery_is_disabled(): void
    {
        $provider = new EventServiceProvider($this->app);

        $this->assertFalse($provider->shouldDiscoverEvents());
    }

    public function test_all_five_events_are_mapped(): void
    {
        $provider = new EventServiceProvider($this->app);
        $listen = $this->getListenProperty($provider);

        $this->assertCount(5, $listen);
    }

    /**
     * Access the protected $listen property via reflection.
     */
    private function getListenProperty(EventServiceProvider $provider): array
    {
        $reflection = new \ReflectionProperty($provider, 'listen');
        $reflection->setAccessible(true);
        return $reflection->getValue($provider);
    }
}
