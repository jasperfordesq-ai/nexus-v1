<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Providers;

use App\Events\ConnectionRequested;
use App\Events\JobVacancyCreated;
use App\Events\ListingCreated;
use App\Events\MessageSent;
use App\Events\ReviewCreated;
use App\Events\SafeguardingFlaggedEvent;
use App\Events\TransactionCompleted;
use App\Events\UserRegistered;
use App\Listeners\CopyMessageForBrokerReview;
use App\Listeners\NotifyConnectionRequest;
use App\Listeners\NotifyJobAlertSubscribers;
use App\Listeners\NotifyMessageReceived;
use App\Listeners\NotifySafeguardingStaff;
use App\Listeners\NotifyTransactionCompleted;
use App\Listeners\PushListingToFederatedPartners;
use App\Listeners\PushMessageToFederatedPartner;
use App\Listeners\PushReviewToFederatedPartner;
use App\Listeners\PushTransactionToFederatedPartner;
use App\Listeners\SendWelcomeNotification;
use App\Listeners\UpdateFeedOnListingCreated;
use App\Listeners\UpdateWalletBalance;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * EventServiceProvider
 *
 * Maps domain events to their listeners.  During the Laravel migration each
 * listener delegates to the corresponding legacy service; once the services
 * are fully ported the legacy references can be removed.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event-to-listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserRegistered::class => [
            SendWelcomeNotification::class,
        ],

        ListingCreated::class => [
            UpdateFeedOnListingCreated::class,
            PushListingToFederatedPartners::class,
        ],

        TransactionCompleted::class => [
            UpdateWalletBalance::class,
            NotifyTransactionCompleted::class,
            PushTransactionToFederatedPartner::class,
        ],

        ConnectionRequested::class => [
            NotifyConnectionRequest::class,
        ],

        MessageSent::class => [
            NotifyMessageReceived::class,
            CopyMessageForBrokerReview::class,
            PushMessageToFederatedPartner::class,
        ],

        JobVacancyCreated::class => [
            NotifyJobAlertSubscribers::class,
        ],

        SafeguardingFlaggedEvent::class => [
            NotifySafeguardingStaff::class,
        ],

        ReviewCreated::class => [
            PushReviewToFederatedPartner::class,
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
