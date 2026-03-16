<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Providers;

use App\Models\Category;
use App\Models\Connection;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\FeedPost;
use App\Models\Goal;
use App\Models\Group;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Newsletter;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Poll;
use App\Models\Post;
use App\Models\ResourceItem;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\VolApplication;
use App\Models\VolOpportunity;
use App\Services\AdminAnalyticsService;
use App\Services\AuditLogService;
use App\Services\BlogService;
use App\Services\CategoryService;
use App\Services\CommentService;
use App\Services\ConnectionService;
use App\Services\EventService;
use App\Services\FeedService;
use App\Services\GamificationService;
use App\Services\GoalService;
use App\Services\GroupService;
use App\Services\HelpService;
use App\Services\ListingService;
use App\Services\MemberRankingService;
use App\Services\MessageService;
use App\Services\NewsletterService;
use App\Services\NotificationService;
use App\Services\PageService;
use App\Services\PollService;
use App\Services\ResourceService;
use App\Services\ReviewService;
use App\Services\SearchService;
use App\Services\TenantService;
use App\Services\VolunteerService;
use App\Services\UserService;
use App\Services\WalletService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // --- Existing services ---

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

        // --- New services ---

        $this->app->singleton(GoalService::class, function ($app) {
            return new GoalService(new Goal());
        });

        $this->app->singleton(PollService::class, function ($app) {
            return new PollService(new Poll());
        });

        $this->app->singleton(CommentService::class, function ($app) {
            return new CommentService();
        });

        $this->app->singleton(BlogService::class, function ($app) {
            return new BlogService(new Post(), new Category());
        });

        $this->app->singleton(VolunteerService::class, function ($app) {
            return new VolunteerService(new VolOpportunity(), new VolApplication());
        });

        $this->app->singleton(GamificationService::class, function ($app) {
            return new GamificationService(new User(), new UserBadge());
        });

        $this->app->singleton(CategoryService::class, function ($app) {
            return new CategoryService(new Category());
        });

        $this->app->singleton(PageService::class, function ($app) {
            return new PageService(new Page());
        });

        $this->app->singleton(ResourceService::class, function ($app) {
            return new ResourceService(new ResourceItem());
        });

        $this->app->singleton(HelpService::class, function ($app) {
            return new HelpService();
        });

        $this->app->singleton(NewsletterService::class, function ($app) {
            return new NewsletterService(new Newsletter());
        });

        $this->app->singleton(TenantService::class, function ($app) {
            return new TenantService(new Tenant());
        });

        $this->app->singleton(AuditLogService::class, function ($app) {
            return new AuditLogService();
        });

        $this->app->singleton(AdminAnalyticsService::class, function ($app) {
            return new AdminAnalyticsService(new User(), new Transaction());
        });

        $this->app->singleton(MemberRankingService::class, function ($app) {
            return new MemberRankingService(new User());
        });
    }

    public function boot(): void
    {
        //
    }
}
