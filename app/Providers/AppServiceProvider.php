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
use App\Services\AuthService;
use App\Services\RegistrationService;
use App\Services\TokenService;
use App\Services\PushNotificationService;
use App\Services\ImageUploadService;
use App\Services\CookieConsentService;
use App\Services\LegalDocumentService;
use App\Services\IdeationChallengeService;
use App\Services\JobVacancyService;
use App\Services\SkillTaxonomyService;
use App\Services\MemberAvailabilityService;
use App\Services\EndorsementService;
use App\Services\MemberActivityService;
use App\Services\SubAccountService;
use App\Services\KnowledgeBaseService;
use App\Services\MetricsService;
use App\Services\FeedSidebarService;
use App\Services\FeedSocialService;
use App\Services\GroupRecommendationService;
use App\Services\ExchangeService;
use App\Services\ContentModerationService;
use App\Services\EmailService;
use App\Services\CronJobService;
use App\Services\AdminSettingsService;
use App\Services\AdminListingsService;
use App\Services\AdminUsersService;
use App\Services\OrgWalletService;
use App\Services\DeliverableService;
use App\Services\BrokerService;
use App\Services\FederationService;
use App\Services\ChallengeService;
use App\Services\BadgeService;
use App\Services\AiChatService;
use App\Services\RealtimeService;
use App\Services\SeoService;
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

        // --- Batch 3 services (20) ---

        $this->app->singleton(AuthService::class, function ($app) {
            return new AuthService(new User());
        });

        $this->app->singleton(RegistrationService::class, function ($app) {
            return new RegistrationService(new User());
        });

        $this->app->singleton(TokenService::class, function ($app) {
            return new TokenService(new User());
        });

        $this->app->singleton(PushNotificationService::class, function ($app) {
            return new PushNotificationService();
        });

        $this->app->singleton(ImageUploadService::class, function ($app) {
            return new ImageUploadService();
        });

        $this->app->singleton(CookieConsentService::class, function ($app) {
            return new CookieConsentService();
        });

        $this->app->singleton(LegalDocumentService::class, function ($app) {
            return new LegalDocumentService();
        });

        $this->app->singleton(IdeationChallengeService::class, function ($app) {
            return new IdeationChallengeService();
        });

        $this->app->singleton(JobVacancyService::class, function ($app) {
            return new JobVacancyService();
        });

        $this->app->singleton(SkillTaxonomyService::class, function ($app) {
            return new SkillTaxonomyService();
        });

        $this->app->singleton(MemberAvailabilityService::class, function ($app) {
            return new MemberAvailabilityService();
        });

        $this->app->singleton(EndorsementService::class, function ($app) {
            return new EndorsementService();
        });

        $this->app->singleton(MemberActivityService::class, function ($app) {
            return new MemberActivityService();
        });

        $this->app->singleton(SubAccountService::class, function ($app) {
            return new SubAccountService();
        });

        $this->app->singleton(KnowledgeBaseService::class, function ($app) {
            return new KnowledgeBaseService();
        });

        $this->app->singleton(MetricsService::class, function ($app) {
            return new MetricsService();
        });

        $this->app->singleton(FeedSidebarService::class, function ($app) {
            return new FeedSidebarService();
        });

        $this->app->singleton(FeedSocialService::class, function ($app) {
            return new FeedSocialService();
        });

        $this->app->singleton(GroupRecommendationService::class, function ($app) {
            return new GroupRecommendationService();
        });

        $this->app->singleton(ExchangeService::class, function ($app) {
            return new ExchangeService();
        });

        // --- Batch 4 services (15) — Admin/Specialized ---

        $this->app->singleton(ContentModerationService::class, function ($app) {
            return new ContentModerationService();
        });

        $this->app->singleton(EmailService::class, function ($app) {
            return new EmailService();
        });

        $this->app->singleton(CronJobService::class, function ($app) {
            return new CronJobService();
        });

        $this->app->singleton(AdminSettingsService::class, function ($app) {
            return new AdminSettingsService();
        });

        $this->app->singleton(AdminListingsService::class, function ($app) {
            return new AdminListingsService();
        });

        $this->app->singleton(AdminUsersService::class, function ($app) {
            return new AdminUsersService(new User());
        });

        $this->app->singleton(OrgWalletService::class, function ($app) {
            return new OrgWalletService();
        });

        $this->app->singleton(DeliverableService::class, function ($app) {
            return new DeliverableService();
        });

        $this->app->singleton(BrokerService::class, function ($app) {
            return new BrokerService();
        });

        $this->app->singleton(FederationService::class, function ($app) {
            return new FederationService();
        });

        $this->app->singleton(ChallengeService::class, function ($app) {
            return new ChallengeService();
        });

        $this->app->singleton(BadgeService::class, function ($app) {
            return new BadgeService(new UserBadge());
        });

        $this->app->singleton(AiChatService::class, function ($app) {
            return new AiChatService();
        });

        $this->app->singleton(RealtimeService::class, function ($app) {
            return new RealtimeService();
        });

        $this->app->singleton(SeoService::class, function ($app) {
            return new SeoService();
        });
    }

    public function boot(): void
    {
        //
    }
}
