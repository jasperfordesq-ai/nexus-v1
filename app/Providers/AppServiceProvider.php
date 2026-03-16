<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Providers;

use App\Models\Connection;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\FeedPost;
use App\Models\Group;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Review;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ConnectionService;
use App\Services\EventService;
use App\Services\FeedService;
use App\Services\GroupService;
use App\Services\ListingService;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\ReviewService;
use App\Services\SearchService;
use App\Services\UserService;
use App\Services\WalletService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ListingService::class, function ($app) {
            return new ListingService(new Listing());
        });

        $this->app->singleton(UserService::class, function ($app) {
            return new UserService(new User());
        });

        $this->app->singleton(EventService::class, function ($app) {
            return new EventService(new Event(), new EventRsvp());
        });

        $this->app->singleton(GroupService::class, function ($app) {
            return new GroupService(new Group());
        });

        $this->app->singleton(MessageService::class, function ($app) {
            return new MessageService(new Message());
        });

        $this->app->singleton(WalletService::class, function ($app) {
            return new WalletService(new Transaction(), new User());
        });

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService(new Notification());
        });

        $this->app->singleton(ReviewService::class, function ($app) {
            return new ReviewService(new Review());
        });

        $this->app->singleton(SearchService::class, function ($app) {
            return new SearchService(new User(), new Listing(), new Event(), new Group());
        });

        $this->app->singleton(ConnectionService::class, function ($app) {
            return new ConnectionService(new Connection());
        });

        $this->app->singleton(FeedService::class, function ($app) {
            return new FeedService(new FeedPost());
        });
    }

    public function boot(): void
    {
        //
    }
}
