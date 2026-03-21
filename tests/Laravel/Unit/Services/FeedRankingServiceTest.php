<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FeedRankingService;

class FeedRankingServiceTest extends TestCase
{
    // FeedRankingService has many public constants — verify them
    public function test_signal_constants_are_defined(): void
    {
        $this->assertEquals(1, FeedRankingService::LIKE_WEIGHT);
        $this->assertEquals(5, FeedRankingService::COMMENT_WEIGHT);
        $this->assertEquals(8, FeedRankingService::SHARE_WEIGHT);
    }

    public function test_freshness_constants_are_defined(): void
    {
        $this->assertEquals(24, FeedRankingService::FRESHNESS_FULL_HOURS);
        $this->assertEquals(72, FeedRankingService::FRESHNESS_HALF_LIFE_HOURS);
        $this->assertEqualsWithDelta(0.3, FeedRankingService::FRESHNESS_MINIMUM, 0.001);
    }

    public function test_geo_constants_are_defined(): void
    {
        $this->assertEquals(50, FeedRankingService::GEO_FULL_SCORE_RADIUS);
        $this->assertEquals(100, FeedRankingService::GEO_DECAY_INTERVAL);
        $this->assertEqualsWithDelta(0.15, FeedRankingService::GEO_MINIMUM_SCORE, 0.001);
    }

    public function test_social_graph_constants_are_defined(): void
    {
        $this->assertTrue(FeedRankingService::SOCIAL_GRAPH_ENABLED);
        $this->assertEquals(2.0, FeedRankingService::SOCIAL_GRAPH_MAX_BOOST);
        $this->assertEquals(90, FeedRankingService::SOCIAL_GRAPH_INTERACTION_DAYS);
    }

    public function test_negative_signals_constants_are_defined(): void
    {
        $this->assertTrue(FeedRankingService::NEGATIVE_SIGNALS_ENABLED);
        $this->assertEquals(0.0, FeedRankingService::HIDE_PENALTY);
        $this->assertEqualsWithDelta(0.1, FeedRankingService::MUTE_PENALTY, 0.001);
    }

    public function test_velocity_constants_are_defined(): void
    {
        $this->assertTrue(FeedRankingService::VELOCITY_ENABLED);
        $this->assertEquals(2, FeedRankingService::VELOCITY_WINDOW_HOURS);
        $this->assertEquals(3, FeedRankingService::VELOCITY_THRESHOLD);
    }

    public function test_diversity_constants_are_defined(): void
    {
        $this->assertTrue(FeedRankingService::DIVERSITY_ENABLED);
        $this->assertEquals(2, FeedRankingService::DIVERSITY_MAX_CONSECUTIVE);
    }

    // The actual ranking algorithm requires complex DB state — integration test needed
    public function test_ranking_pipeline_requires_integration_test(): void
    {
        $this->markTestIncomplete('FeedRankingService 15-signal pipeline requires integration test with feed_activity data');
    }
}
