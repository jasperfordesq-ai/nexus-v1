<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\FeedRankingService;
use Tests\Laravel\TestCase;

/**
 * Ranking service contract tests.
 *
 * The original monolithic App\Services\RankingService (a thin DI wrapper around
 * the now-deleted legacy \Nexus\Services\RankingService) was removed during the
 * Laravel migration and split into dedicated services: FeedRankingService,
 * ListingRankingService, MemberRankingService, PollRankingService and
 * SmartGroupRankingService. FeedRankingService is the canonical EdgeRank
 * implementation, so these tests assert its current public contract.
 */
class RankingServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(FeedRankingService::class));
    }

    public function testSignalWeightConstants(): void
    {
        $this->assertSame(1, FeedRankingService::LIKE_WEIGHT);
        $this->assertSame(5, FeedRankingService::COMMENT_WEIGHT);
        $this->assertSame(8, FeedRankingService::SHARE_WEIGHT);
    }

    public function testGetConfigMethodExists(): void
    {
        $this->assertTrue(method_exists(FeedRankingService::class, 'getConfig'));
    }

    public function testGetConfigIsStatic(): void
    {
        $ref = new \ReflectionMethod(FeedRankingService::class, 'getConfig');
        $this->assertTrue($ref->isStatic());
    }

    public function testGetConfigReturnsDefaultsArray(): void
    {
        FeedRankingService::clearStaticCache();

        $config = FeedRankingService::getConfig();

        $this->assertIsArray($config);
        $this->assertTrue($config['enabled']);
        // validateConfigArray() normalises weights to float, so compare loosely.
        $this->assertEquals(FeedRankingService::LIKE_WEIGHT, $config['like_weight']);
    }
}
