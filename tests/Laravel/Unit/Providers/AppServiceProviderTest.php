<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Providers;

use App\Services\ListingService;
use App\Services\UserService;
use App\Services\EventService;
use App\Services\GroupService;
use App\Services\MessageService;
use App\Services\WalletService;
use App\Services\NotificationService;
use App\Services\ReviewService;
use App\Services\SearchService;
use App\Services\ConnectionService;
use App\Services\FeedService;
use App\Services\GamificationService;
use App\Services\EmailService;
use App\Services\AuthService;
use App\Services\TokenService;
use Tests\Laravel\TestCase;

class AppServiceProviderTest extends TestCase
{
    /**
     * @dataProvider singletonServicesProvider
     */
    public function test_service_is_registered_as_singleton(string $serviceClass): void
    {
        $instance1 = $this->app->make($serviceClass);
        $instance2 = $this->app->make($serviceClass);

        $this->assertSame($instance1, $instance2, "{$serviceClass} should be registered as a singleton");
    }

    public static function singletonServicesProvider(): array
    {
        return [
            'ListingService' => [ListingService::class],
            'UserService' => [UserService::class],
            'EventService' => [EventService::class],
            'GroupService' => [GroupService::class],
            'MessageService' => [MessageService::class],
            'WalletService' => [WalletService::class],
            'NotificationService' => [NotificationService::class],
            'ReviewService' => [ReviewService::class],
            'SearchService' => [SearchService::class],
            'ConnectionService' => [ConnectionService::class],
            'FeedService' => [FeedService::class],
            'GamificationService' => [GamificationService::class],
            'EmailService' => [EmailService::class],
            'AuthService' => [AuthService::class],
            'TokenService' => [TokenService::class],
        ];
    }

    public function test_listing_service_is_resolvable(): void
    {
        $service = $this->app->make(ListingService::class);
        $this->assertInstanceOf(ListingService::class, $service);
    }

    public function test_user_service_is_resolvable(): void
    {
        $service = $this->app->make(UserService::class);
        $this->assertInstanceOf(UserService::class, $service);
    }

    public function test_gamification_service_is_resolvable(): void
    {
        $service = $this->app->make(GamificationService::class);
        $this->assertInstanceOf(GamificationService::class, $service);
    }
}
