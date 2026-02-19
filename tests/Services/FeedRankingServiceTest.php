<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\FeedRankingService;

class FeedRankingServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(FeedRankingService::class));
    }

    public function testEngagementWeightConstants(): void
    {
        $this->assertEquals(1, FeedRankingService::LIKE_WEIGHT);
        $this->assertEquals(5, FeedRankingService::COMMENT_WEIGHT);
        $this->assertEquals(8, FeedRankingService::SHARE_WEIGHT);
    }

    public function testShareWeightIsHigherThanCommentWeight(): void
    {
        $this->assertGreaterThan(
            FeedRankingService::COMMENT_WEIGHT,
            FeedRankingService::SHARE_WEIGHT,
            'Shares should be weighted higher than comments'
        );
    }

    public function testCommentWeightIsHigherThanLikeWeight(): void
    {
        $this->assertGreaterThan(
            FeedRankingService::LIKE_WEIGHT,
            FeedRankingService::COMMENT_WEIGHT,
            'Comments should be weighted higher than likes'
        );
    }

    public function testVitalityConstants(): void
    {
        $this->assertEquals(7, FeedRankingService::VITALITY_FULL_THRESHOLD);
        $this->assertEquals(30, FeedRankingService::VITALITY_DECAY_THRESHOLD);
        $this->assertEquals(0.5, FeedRankingService::VITALITY_MINIMUM);
    }

    public function testGeoDecayConstants(): void
    {
        $this->assertEquals(10, FeedRankingService::GEO_FULL_SCORE_RADIUS);
        $this->assertEquals(0.10, FeedRankingService::GEO_DECAY_PER_INTERVAL);
        $this->assertEquals(10, FeedRankingService::GEO_DECAY_INTERVAL);
        $this->assertEquals(0.1, FeedRankingService::GEO_MINIMUM_SCORE);
    }

    public function testFreshnessConstants(): void
    {
        $this->assertEquals(24, FeedRankingService::FRESHNESS_FULL_HOURS);
        $this->assertEquals(72, FeedRankingService::FRESHNESS_HALF_LIFE_HOURS);
        $this->assertEquals(0.3, FeedRankingService::FRESHNESS_MINIMUM);
    }

    public function testContentQualityConstants(): void
    {
        $this->assertTrue(FeedRankingService::QUALITY_ENABLED);
        $this->assertEquals(1.3, FeedRankingService::QUALITY_IMAGE_BOOST);
        $this->assertEquals(1.1, FeedRankingService::QUALITY_LINK_BOOST);
        $this->assertEquals(1.4, FeedRankingService::QUALITY_VIDEO_BOOST);
        $this->assertEquals(50, FeedRankingService::QUALITY_LENGTH_MIN);
    }

    public function testNegativeSignalConstants(): void
    {
        $this->assertEquals(0.0, FeedRankingService::HIDE_PENALTY);
        $this->assertEquals(0.1, FeedRankingService::MUTE_PENALTY);
        $this->assertEquals(0.0, FeedRankingService::BLOCK_PENALTY);
        $this->assertEquals(0.15, FeedRankingService::REPORT_PENALTY_PER);
    }

    public function testDiversityConstants(): void
    {
        $this->assertTrue(FeedRankingService::DIVERSITY_ENABLED);
        $this->assertEquals(2, FeedRankingService::DIVERSITY_MAX_CONSECUTIVE);
        $this->assertEquals(0.5, FeedRankingService::DIVERSITY_PENALTY);
    }

    public function testDefaultScore(): void
    {
        $this->assertEquals(1.0, FeedRankingService::DEFAULT_SCORE);
    }

    public function testVideoBoostIsHigherThanImageBoost(): void
    {
        $this->assertGreaterThan(
            FeedRankingService::QUALITY_IMAGE_BOOST,
            FeedRankingService::QUALITY_VIDEO_BOOST,
            'Video boost should be higher than image boost'
        );
    }
}
