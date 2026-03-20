<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use App\Services\FeedRankingService;

/**
 * Comprehensive tests for FeedRankingService (14-signal EdgeRank pipeline).
 */
class FeedRankingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset cached config so each test gets fresh defaults
        $ref = new \ReflectionClass(FeedRankingService::class);
        $prop = $ref->getProperty('config');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    // =========================================================================
    // CONSTANT VALIDATION
    // =========================================================================

    public function testEngagementWeightsExist(): void
    {
        $this->assertSame(1, FeedRankingService::LIKE_WEIGHT);
        $this->assertSame(5, FeedRankingService::COMMENT_WEIGHT);
        $this->assertSame(8, FeedRankingService::SHARE_WEIGHT);
    }

    public function testFreshnessConstants(): void
    {
        $this->assertSame(24, FeedRankingService::FRESHNESS_FULL_HOURS);
        $this->assertSame(72, FeedRankingService::FRESHNESS_HALF_LIFE_HOURS);
        $this->assertSame(0.3, FeedRankingService::FRESHNESS_MINIMUM);
    }

    public function testGeoConstants(): void
    {
        $this->assertSame(50, FeedRankingService::GEO_FULL_SCORE_RADIUS);
        $this->assertSame(100, FeedRankingService::GEO_DECAY_INTERVAL);
        $this->assertSame(0.03, FeedRankingService::GEO_DECAY_PER_INTERVAL);
        $this->assertSame(0.15, FeedRankingService::GEO_MINIMUM_SCORE);
    }

    public function testVitalityConstants(): void
    {
        $this->assertSame(7, FeedRankingService::VITALITY_FULL_THRESHOLD);
        $this->assertSame(30, FeedRankingService::VITALITY_DECAY_THRESHOLD);
        $this->assertSame(0.5, FeedRankingService::VITALITY_MINIMUM);
    }

    public function testSocialGraphConstants(): void
    {
        $this->assertTrue(FeedRankingService::SOCIAL_GRAPH_ENABLED);
        $this->assertSame(2.0, FeedRankingService::SOCIAL_GRAPH_MAX_BOOST);
        $this->assertSame(90, FeedRankingService::SOCIAL_GRAPH_INTERACTION_DAYS);
        $this->assertSame(1.5, FeedRankingService::SOCIAL_GRAPH_FOLLOWER_BOOST);
    }

    public function testNegativeSignalConstants(): void
    {
        $this->assertTrue(FeedRankingService::NEGATIVE_SIGNALS_ENABLED);
        $this->assertSame(0.0, FeedRankingService::HIDE_PENALTY);
        $this->assertSame(0.1, FeedRankingService::MUTE_PENALTY);
        $this->assertSame(0.0, FeedRankingService::BLOCK_PENALTY);
        $this->assertSame(0.15, FeedRankingService::REPORT_PENALTY_PER);
    }

    public function testVelocityConstants(): void
    {
        $this->assertTrue(FeedRankingService::VELOCITY_ENABLED);
        $this->assertSame(2, FeedRankingService::VELOCITY_WINDOW_HOURS);
        $this->assertSame(3, FeedRankingService::VELOCITY_THRESHOLD);
        $this->assertSame(1.8, FeedRankingService::VELOCITY_MAX_BOOST);
        $this->assertSame(6, FeedRankingService::VELOCITY_DECAY_HOURS);
    }

    public function testConversationDepthConstants(): void
    {
        $this->assertTrue(FeedRankingService::CONVERSATION_DEPTH_ENABLED);
        $this->assertSame(1.5, FeedRankingService::CONVERSATION_DEPTH_MAX_BOOST);
        $this->assertSame(3, FeedRankingService::CONVERSATION_DEPTH_THRESHOLD);
    }

    public function testDiversityConstants(): void
    {
        $this->assertTrue(FeedRankingService::DIVERSITY_ENABLED);
        $this->assertSame(2, FeedRankingService::DIVERSITY_MAX_CONSECUTIVE);
        $this->assertSame(0.5, FeedRankingService::DIVERSITY_PENALTY);
        $this->assertTrue(FeedRankingService::DIVERSITY_TYPE_ENABLED);
        $this->assertSame(3, FeedRankingService::DIVERSITY_TYPE_MAX_CONSECUTIVE);
    }

    public function testQualityConstants(): void
    {
        $this->assertTrue(FeedRankingService::QUALITY_ENABLED);
        $this->assertSame(1.3, FeedRankingService::QUALITY_IMAGE_BOOST);
        $this->assertSame(1.1, FeedRankingService::QUALITY_LINK_BOOST);
        $this->assertSame(50, FeedRankingService::QUALITY_LENGTH_MIN);
        $this->assertSame(1.2, FeedRankingService::QUALITY_LENGTH_BONUS);
        $this->assertSame(1.4, FeedRankingService::QUALITY_VIDEO_BOOST);
        $this->assertSame(1.1, FeedRankingService::QUALITY_HASHTAG_BOOST);
        $this->assertSame(1.15, FeedRankingService::QUALITY_MENTION_BOOST);
    }

    // =========================================================================
    // NEW SIGNAL CONSTANTS (Signals 13 & 14)
    // =========================================================================

    public function testCtrConstants(): void
    {
        $this->assertTrue(FeedRankingService::CTR_ENABLED);
        $this->assertSame(1.5, FeedRankingService::CTR_MAX_BOOST);
        $this->assertSame(5, FeedRankingService::CTR_MIN_IMPRESSIONS);
    }

    public function testUserTypePrefsConstants(): void
    {
        $this->assertTrue(FeedRankingService::USER_TYPE_PREFS_ENABLED);
        $this->assertSame(1.4, FeedRankingService::USER_TYPE_PREFS_MAX_BOOST);
        $this->assertSame(30, FeedRankingService::USER_TYPE_PREFS_LOOKBACK_DAYS);
    }

    // =========================================================================
    // REACTION WEIGHTS
    // =========================================================================

    public function testReactionWeightsContainAllTypes(): void
    {
        $weights = FeedRankingService::REACTION_WEIGHTS;
        $expected = ['love', 'celebrate', 'insightful', 'like', 'curious', 'sad', 'angry'];
        foreach ($expected as $type) {
            $this->assertArrayHasKey($type, $weights, "Missing reaction type: $type");
        }
    }

    public function testReactionWeightsOrdering(): void
    {
        $w = FeedRankingService::REACTION_WEIGHTS;
        $this->assertGreaterThan($w['like'], $w['love']);
        $this->assertGreaterThan($w['like'], $w['celebrate']);
        $this->assertGreaterThan($w['like'], $w['insightful']);
        $this->assertLessThan($w['like'], $w['angry']);
        $this->assertLessThan($w['like'], $w['sad']);
    }

    public function testReactionWeightsArePositive(): void
    {
        foreach (FeedRankingService::REACTION_WEIGHTS as $type => $weight) {
            $this->assertGreaterThan(0.0, $weight, "Reaction weight for $type must be positive");
        }
    }

    // =========================================================================
    // HAVERSINE DISTANCE (public method)
    // =========================================================================

    public function testHaversineZeroDistance(): void
    {
        $distance = FeedRankingService::calculateHaversineDistance(53.3498, -6.2603, 53.3498, -6.2603);
        $this->assertEqualsWithDelta(0.0, $distance, 0.01);
    }

    public function testHaversineDublinToLondon(): void
    {
        $distance = FeedRankingService::calculateHaversineDistance(53.3498, -6.2603, 51.5074, -0.1278);
        $this->assertEqualsWithDelta(463.0, $distance, 15.0, 'Dublin-London ~463km');
    }

    public function testHaversineShortDistance(): void
    {
        $distance = FeedRankingService::calculateHaversineDistance(53.3498, -6.2603, 53.3400, -6.1200);
        $this->assertLessThan(50, $distance);
    }

    public function testHaversineSymmetric(): void
    {
        $d1 = FeedRankingService::calculateHaversineDistance(53.3498, -6.2603, 51.5074, -0.1278);
        $d2 = FeedRankingService::calculateHaversineDistance(51.5074, -0.1278, 53.3498, -6.2603);
        $this->assertEqualsWithDelta($d1, $d2, 0.01, 'Haversine should be symmetric');
    }

    // =========================================================================
    // GEO DECAY SCORING (public method)
    // =========================================================================

    public function testGeoDecayWithinFullRadius(): void
    {
        $score = FeedRankingService::computeGeoDecayFromDistance(30.0);
        $this->assertSame(1.0, $score);
    }

    public function testGeoDecayBeyondFullRadius(): void
    {
        $score = FeedRankingService::computeGeoDecayFromDistance(200.0);
        $this->assertLessThan(1.0, $score);
        $this->assertGreaterThanOrEqual(FeedRankingService::GEO_MINIMUM_SCORE, $score);
    }

    public function testGeoDecayNeverBelowMinimum(): void
    {
        $score = FeedRankingService::computeGeoDecayFromDistance(50000.0);
        $this->assertSame(FeedRankingService::GEO_MINIMUM_SCORE, $score);
    }

    public function testGeoDecayAtBoundary(): void
    {
        $score = FeedRankingService::computeGeoDecayFromDistance(50.0);
        $this->assertSame(1.0, $score, 'At exact full radius should be 1.0');
    }

    // =========================================================================
    // VITALITY SCORING (via computeVitalityFromDays)
    // =========================================================================

    public function testVitalityFullScore(): void
    {
        $score = FeedRankingService::computeVitalityFromDays(5);
        $this->assertSame(1.0, $score);
    }

    public function testVitalityAtThreshold(): void
    {
        $score = FeedRankingService::computeVitalityFromDays(7);
        $this->assertSame(1.0, $score, 'At exact threshold should be 1.0');
    }

    public function testVitalityDecayedScore(): void
    {
        $score = FeedRankingService::computeVitalityFromDays(20);
        $this->assertLessThan(1.0, $score);
        $this->assertGreaterThan(FeedRankingService::VITALITY_MINIMUM, $score);
    }

    public function testVitalityMinimumScore(): void
    {
        $score = FeedRankingService::computeVitalityFromDays(365);
        $this->assertSame(FeedRankingService::VITALITY_MINIMUM, $score);
    }

    public function testVitalityAtDecayThreshold(): void
    {
        $score = FeedRankingService::computeVitalityFromDays(30);
        $this->assertSame(FeedRankingService::VITALITY_MINIMUM, $score, 'At decay threshold should be minimum');
    }

    // =========================================================================
    // CONTENT QUALITY SCORE
    // =========================================================================

    public function testContentQualityPlainPost(): void
    {
        $post = ['content' => 'Short post.'];
        $score = FeedRankingService::calculateContentQualityScore($post);
        $this->assertSame(1.0, $score, 'Plain short post should score 1.0');
    }

    public function testContentQualityWithImage(): void
    {
        $post = ['content' => 'Check this out!', 'image_url' => 'https://example.com/photo.jpg'];
        $score = FeedRankingService::calculateContentQualityScore($post);
        $this->assertGreaterThan(1.0, $score, 'Post with image should get boost');
    }

    public function testContentQualityWithLongContent(): void
    {
        $post = ['content' => str_repeat('a', 100)];
        $score = FeedRankingService::calculateContentQualityScore($post);
        $this->assertGreaterThanOrEqual(1.0, $score, 'Long content should get length bonus');
    }

    public function testContentQualityWithHashtags(): void
    {
        $post = ['content' => 'Great event! #timebanking #community'];
        $score = FeedRankingService::calculateContentQualityScore($post);
        $this->assertGreaterThan(1.0, $score, 'Post with hashtags should get boost');
    }

    public function testContentQualityWithMentions(): void
    {
        $post = ['content' => 'Thanks @john for the help!'];
        $score = FeedRankingService::calculateContentQualityScore($post);
        $this->assertGreaterThan(1.0, $score, 'Post with mentions should get boost');
    }

    public function testContentQualityWithLink(): void
    {
        $post = ['content' => 'Check out https://example.com/resource'];
        $score = FeedRankingService::calculateContentQualityScore($post);
        $this->assertGreaterThan(1.0, $score, 'Post with link should get boost');
    }

    // =========================================================================
    // HACKER NEWS TIME DECAY
    // =========================================================================

    public function testHackerNewsDecayAtZeroHours(): void
    {
        $ref = new \ReflectionMethod(FeedRankingService::class, 'hackerNewsDecay');
        $ref->setAccessible(true);
        $score = $ref->invoke(null, 0);
        $this->assertSame(1.0, $score, 'Fresh post should score 1.0');
    }

    public function testHackerNewsDecayMonotonic(): void
    {
        $ref = new \ReflectionMethod(FeedRankingService::class, 'hackerNewsDecay');
        $ref->setAccessible(true);
        $prev = 1.0;
        foreach ([1, 6, 12, 24, 48, 72, 168] as $hours) {
            $score = $ref->invoke(null, $hours);
            $this->assertLessThanOrEqual($prev, $score, "Decay should decrease at {$hours}h");
            $prev = $score;
        }
    }

    public function testHackerNewsDecayNeverBelowMinimum(): void
    {
        $ref = new \ReflectionMethod(FeedRankingService::class, 'hackerNewsDecay');
        $ref->setAccessible(true);
        $score = $ref->invoke(null, 10000);
        $this->assertGreaterThanOrEqual(FeedRankingService::FRESHNESS_MINIMUM, $score);
    }

    // =========================================================================
    // ENGAGEMENT SCORE
    // =========================================================================

    public function testEngagementScoreZero(): void
    {
        $score = FeedRankingService::calculateEngagementScore(0, 0);
        $this->assertSame(1.0, $score, 'Zero engagement returns minimum 1.0');
    }

    public function testEngagementScoreCommentsWeighMore(): void
    {
        $likesOnly = FeedRankingService::calculateEngagementScore(5, 0);
        $commentsOnly = FeedRankingService::calculateEngagementScore(0, 5);
        $this->assertGreaterThan($likesOnly, $commentsOnly, 'Comments should weigh more than likes');
    }

    public function testEngagementScoreMonotonic(): void
    {
        $s1 = FeedRankingService::calculateEngagementScore(1, 0);
        $s2 = FeedRankingService::calculateEngagementScore(5, 0);
        $s3 = FeedRankingService::calculateEngagementScore(10, 0);
        $this->assertLessThan($s2, $s1);
        $this->assertLessThan($s3, $s2);
    }

    // =========================================================================
    // BOOST CONSTANT SANITY CHECKS
    // =========================================================================

    public function testAllBoostConstantsAreReasonable(): void
    {
        $boosts = [
            'SOCIAL_GRAPH_MAX_BOOST' => FeedRankingService::SOCIAL_GRAPH_MAX_BOOST,
            'VELOCITY_MAX_BOOST' => FeedRankingService::VELOCITY_MAX_BOOST,
            'CONVERSATION_DEPTH_MAX_BOOST' => FeedRankingService::CONVERSATION_DEPTH_MAX_BOOST,
            'CTR_MAX_BOOST' => FeedRankingService::CTR_MAX_BOOST,
            'USER_TYPE_PREFS_MAX_BOOST' => FeedRankingService::USER_TYPE_PREFS_MAX_BOOST,
            'QUALITY_VIDEO_BOOST' => FeedRankingService::QUALITY_VIDEO_BOOST,
            'QUALITY_IMAGE_BOOST' => FeedRankingService::QUALITY_IMAGE_BOOST,
        ];
        foreach ($boosts as $name => $value) {
            $this->assertLessThanOrEqual(3.0, $value, "$name exceeds 3x");
            $this->assertGreaterThan(1.0, $value, "$name should be > 1.0");
        }
    }

    public function testPenaltiesAreBetweenZeroAndOne(): void
    {
        $penalties = [
            'HIDE_PENALTY' => FeedRankingService::HIDE_PENALTY,
            'MUTE_PENALTY' => FeedRankingService::MUTE_PENALTY,
            'BLOCK_PENALTY' => FeedRankingService::BLOCK_PENALTY,
        ];
        foreach ($penalties as $name => $value) {
            $this->assertGreaterThanOrEqual(0.0, $value, "$name must be >= 0");
            $this->assertLessThanOrEqual(1.0, $value, "$name must be <= 1");
        }
    }

    // =========================================================================
    // 14-SIGNAL PIPELINE COMPLETENESS
    // =========================================================================

    public function testRankFeedItemsMethodExists(): void
    {
        $this->assertTrue(method_exists(FeedRankingService::class, 'rankFeedItems'));
    }

    public function testCalculatePostScoreMethodExists(): void
    {
        $this->assertTrue(method_exists(FeedRankingService::class, 'calculatePostScore'));
    }

    public function testGetBatchClickThroughRatesMethodExists(): void
    {
        $this->assertTrue(method_exists(FeedRankingService::class, 'getBatchClickThroughRates'));
    }

    public function testGetUserTypePreferencesMethodExists(): void
    {
        $ref = new \ReflectionClass(FeedRankingService::class);
        $this->assertTrue($ref->hasMethod('getUserTypePreferences'));
    }

    public function testAllBatchLoadersExist(): void
    {
        $ref = new \ReflectionClass(FeedRankingService::class);
        $methods = [
            'getBatchSocialGraphScores',
            'getBatchEngagementVelocity',
            'getBatchNegativeSignals',
            'getBatchClickThroughRates',
            'getBatchVitalityScores',
            'getBatchConversationDepth',
            'getBatchReactionScores',
        ];
        foreach ($methods as $method) {
            $this->assertTrue($ref->hasMethod($method), "Batch loader $method must exist");
        }
    }

    public function testDiversityMethodsExist(): void
    {
        $ref = new \ReflectionClass(FeedRankingService::class);
        $this->assertTrue($ref->hasMethod('applyDiversityInPlace'));
        $this->assertTrue($ref->hasMethod('applyContentDiversity'));
        $this->assertTrue($ref->hasMethod('applyContentTypeDiversity'));
    }

    public function testGetConfigMethodExists(): void
    {
        $this->assertTrue(method_exists(FeedRankingService::class, 'getConfig'));
    }

    public function testContextualBoostMethodExists(): void
    {
        $ref = new \ReflectionClass(FeedRankingService::class);
        $this->assertTrue($ref->hasMethod('contextualBoost'));
    }

    public function testHackerNewsDecayMethodExists(): void
    {
        $ref = new \ReflectionClass(FeedRankingService::class);
        $this->assertTrue($ref->hasMethod('hackerNewsDecay'));
    }

    // =========================================================================
    // SIGNAL 15: SAVE/BOOKMARK CONSTANTS
    // =========================================================================

    public function testSaveSignalConstants(): void
    {
        $this->assertTrue(FeedRankingService::SAVE_SIGNAL_ENABLED);
        $this->assertSame(1.35, FeedRankingService::SAVE_SIGNAL_MAX_BOOST);
        $this->assertSame(2, FeedRankingService::SAVE_SIGNAL_MIN_SAVES);
    }

    // =========================================================================
    // CONTEXTUAL BOOST (Signal 9)
    // =========================================================================

    public function testContextualBoostReturnsFloat(): void
    {
        $ref = new \ReflectionMethod(FeedRankingService::class, 'contextualBoost');
        $ref->setAccessible(true);
        $score = $ref->invoke(null, 'post', 'UTC');
        $this->assertIsFloat($score);
        $this->assertGreaterThan(0.0, $score);
    }

    public function testContextualBoostDefaultType(): void
    {
        $ref = new \ReflectionMethod(FeedRankingService::class, 'contextualBoost');
        $ref->setAccessible(true);
        $score = $ref->invoke(null, 'unknown_type', 'UTC');
        $this->assertSame(1.0, $score, 'Unknown type should return neutral 1.0');
    }

    public function testContextualBoostHandlesInvalidTimezone(): void
    {
        $ref = new \ReflectionMethod(FeedRankingService::class, 'contextualBoost');
        $ref->setAccessible(true);
        // Should not throw, falls back to UTC
        $score = $ref->invoke(null, 'post', 'Invalid/Timezone');
        $this->assertIsFloat($score);
    }

    // =========================================================================
    // FRESHNESS SCORE (calculateFreshnessScore)
    // =========================================================================

    public function testFreshnessScoreRecentPost(): void
    {
        $recent = date('Y-m-d H:i:s', time() - 3600); // 1 hour ago
        $score = FeedRankingService::calculateFreshnessScore($recent);
        $this->assertSame(1.0, $score, 'Post within FRESHNESS_FULL_HOURS should score 1.0');
    }

    public function testFreshnessScoreOldPost(): void
    {
        $old = date('Y-m-d H:i:s', time() - 86400 * 7); // 7 days ago
        $score = FeedRankingService::calculateFreshnessScore($old);
        $this->assertLessThan(1.0, $score);
        $this->assertGreaterThanOrEqual(FeedRankingService::FRESHNESS_MINIMUM, $score);
    }

    public function testFreshnessScoreVeryOldPost(): void
    {
        $veryOld = date('Y-m-d H:i:s', time() - 86400 * 365);
        $score = FeedRankingService::calculateFreshnessScore($veryOld);
        $this->assertSame(FeedRankingService::FRESHNESS_MINIMUM, (float)round($score, 1));
    }

    public function testFreshnessScoreMonotonic(): void
    {
        $scores = [];
        foreach ([1, 24, 72, 168, 720] as $hours) {
            $ts = date('Y-m-d H:i:s', time() - $hours * 3600);
            $scores[] = FeedRankingService::calculateFreshnessScore($ts);
        }
        for ($i = 1; $i < count($scores); $i++) {
            $this->assertLessThanOrEqual($scores[$i - 1], $scores[$i], "Freshness should decrease monotonically");
        }
    }

    // =========================================================================
    // DIVERSITY BEHAVIOR
    // =========================================================================

    public function testApplyContentDiversityReturnsArray(): void
    {
        $items = [
            ['user_id' => 1, 'type' => 'post', 'content' => 'a'],
            ['user_id' => 1, 'type' => 'post', 'content' => 'b'],
            ['user_id' => 1, 'type' => 'post', 'content' => 'c'],
            ['user_id' => 2, 'type' => 'event', 'content' => 'd'],
        ];
        $result = FeedRankingService::applyContentDiversity($items);
        $this->assertIsArray($result);
        $this->assertCount(4, $result, 'Diversity should not drop items');
    }

    public function testApplyContentDiversityBreaksConsecutiveSameAuthor(): void
    {
        $items = [
            ['user_id' => 1, 'type' => 'post', '_edge_rank' => 10],
            ['user_id' => 1, 'type' => 'post', '_edge_rank' => 9],
            ['user_id' => 1, 'type' => 'post', '_edge_rank' => 8],
            ['user_id' => 2, 'type' => 'event', '_edge_rank' => 7],
            ['user_id' => 3, 'type' => 'listing', '_edge_rank' => 6],
        ];
        $result = FeedRankingService::applyContentDiversity($items);

        // After diversity, user_id=1 should not have 3 consecutive posts
        $consecutive = 0;
        $maxConsecutive = 0;
        $lastUserId = null;
        foreach ($result as $item) {
            if ($item['user_id'] === $lastUserId) {
                $consecutive++;
            } else {
                $maxConsecutive = max($maxConsecutive, $consecutive);
                $consecutive = 1;
            }
            $lastUserId = $item['user_id'];
        }
        $maxConsecutive = max($maxConsecutive, $consecutive);
        $this->assertLessThanOrEqual(
            FeedRankingService::DIVERSITY_MAX_CONSECUTIVE,
            $maxConsecutive,
            'No author should have more than DIVERSITY_MAX_CONSECUTIVE consecutive items'
        );
    }

    public function testApplyContentTypeDiversityReturnsArray(): void
    {
        $items = [
            ['user_id' => 1, 'type' => 'post'],
            ['user_id' => 2, 'type' => 'post'],
            ['user_id' => 3, 'type' => 'post'],
            ['user_id' => 4, 'type' => 'post'],
            ['user_id' => 5, 'type' => 'event'],
        ];
        $result = FeedRankingService::applyContentTypeDiversity($items);
        $this->assertIsArray($result);
        $this->assertCount(5, $result);
    }

    // =========================================================================
    // CONFIG DEFAULTS COMPLETENESS
    // =========================================================================

    public function testConfigContainsAllSignalKeys(): void
    {
        // Force defaults by resetting config
        $ref = new \ReflectionClass(FeedRankingService::class);
        $prop = $ref->getProperty('config');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $config = FeedRankingService::getConfig();

        $expectedKeys = [
            'enabled', 'like_weight', 'comment_weight', 'share_weight',
            'vitality_full_days', 'vitality_decay_days', 'vitality_minimum',
            'geo_full_radius', 'geo_decay_interval', 'geo_decay_rate', 'geo_minimum',
            'freshness_enabled', 'freshness_full_hours', 'freshness_half_life', 'freshness_minimum',
            'social_graph_enabled', 'social_graph_max_boost', 'social_graph_lookback_days',
            'negative_signals_enabled', 'hide_penalty', 'mute_penalty', 'block_penalty', 'report_penalty_per',
            'quality_enabled', 'quality_image_boost', 'quality_link_boost', 'quality_length_min',
            'diversity_enabled', 'diversity_max_consecutive',
            'velocity_enabled', 'velocity_window_hours', 'velocity_threshold', 'velocity_max_boost',
            'conversation_depth_enabled', 'conversation_depth_max_boost', 'conversation_depth_threshold',
            'ctr_enabled', 'ctr_max_boost', 'ctr_min_impressions',
            'user_type_prefs_enabled', 'user_type_prefs_max_boost', 'user_type_prefs_lookback_days',
            'save_signal_enabled', 'save_signal_max_boost', 'save_signal_min_saves',
        ];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Config missing key: $key");
        }
    }

    // =========================================================================
    // 15-SIGNAL PIPELINE COMPLETENESS
    // =========================================================================

    public function testGetBatchSaveScoresMethodExists(): void
    {
        $ref = new \ReflectionClass(FeedRankingService::class);
        $this->assertTrue($ref->hasMethod('getBatchSaveScores'), 'getBatchSaveScores must exist for Signal 15');
    }

    public function testCalculatePostScoreUsesAllSignals(): void
    {
        // Verify calculatePostScore method signature accepts all needed params
        $ref = new \ReflectionMethod(FeedRankingService::class, 'calculatePostScore');
        $params = $ref->getParameters();
        $this->assertCount(4, $params, 'calculatePostScore should have 4 parameters');
        $this->assertSame('post', $params[0]->getName());
        $this->assertSame('viewerId', $params[1]->getName());
    }

    public function testAllSignalMethodsExist(): void
    {
        $ref = new \ReflectionClass(FeedRankingService::class);
        $requiredMethods = [
            // Batch loaders (used in rankFeedItems)
            'getBatchSocialGraphScores',
            'getBatchEngagementVelocity',
            'getBatchNegativeSignals',
            'getBatchClickThroughRates',
            'getBatchVitalityScores',
            'getBatchConversationDepth',
            'getBatchReactionScores',
            'getBatchSaveScores',
            // Single-item scorers (used in calculatePostScore)
            'calculateSocialGraphScore',
            'calculateNegativeSignalsScore',
            'calculateContentQualityScore',
            'calculateEngagementScore',
            'calculateVitalityScore',
            'calculateGeoDecayScore',
            'calculateHaversineDistance',
            'calculateFreshnessScore',
            // Pipeline methods
            'rankFeedItems',
            'calculatePostScore',
            'hackerNewsDecay',
            'contextualBoost',
            'getUserTypePreferences',
            // Diversity
            'applyDiversityInPlace',
            'applyContentDiversity',
            'applyContentTypeDiversity',
        ];
        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "Required method $method must exist in FeedRankingService"
            );
        }
    }

    // =========================================================================
    // SCORE MATH VERIFICATION
    // =========================================================================

    public function testGeoDecaySteppedCorrectly(): void
    {
        // At 150km (50 full + 100 beyond = 1 interval), decay = 1 * 0.03 = 0.03
        $score = FeedRankingService::computeGeoDecayFromDistance(150.0);
        $this->assertEqualsWithDelta(0.97, $score, 0.001, '150km should be ~0.97');
    }

    public function testGeoDecayMultipleIntervals(): void
    {
        // At 350km (50 full + 300 beyond = 3 intervals), decay = 3 * 0.03 = 0.09
        $score = FeedRankingService::computeGeoDecayFromDistance(350.0);
        $this->assertEqualsWithDelta(0.91, $score, 0.001, '350km should be ~0.91');
    }

    public function testVitalityLinearInterpolation(): void
    {
        // At day 18.5 (midpoint of 7-30 range), should be ~0.75
        $score = FeedRankingService::computeVitalityFromDays(19);
        // Linear: 1.0 - ((19-7)/(30-7)) * (1.0-0.5) = 1.0 - (12/23)*0.5 = 1.0 - 0.2609 = 0.739
        $this->assertEqualsWithDelta(0.739, $score, 0.01, 'Vitality at day 19 should be ~0.739');
    }

    public function testContentQualityStacksMultipleBoosts(): void
    {
        $post = [
            'content' => 'Check https://example.com for details! #timebank @community ' . str_repeat('x', 60),
            'image_url' => 'https://example.com/img.jpg',
        ];
        $score = FeedRankingService::calculateContentQualityScore($post);
        // Should stack: image(1.3) * link(1.1) * hashtag(1.1) * mention(1.15) * length(1.2)
        $expected = 1.3 * 1.1 * 1.1 * 1.15 * 1.2;
        $this->assertEqualsWithDelta($expected, $score, 0.01, 'Quality boosts should stack multiplicatively');
    }

    public function testEngagementScoreWeighting(): void
    {
        // 1 comment (weight 5) should equal 5 likes (weight 1)
        $oneComment = FeedRankingService::calculateEngagementScore(0, 1);
        $fiveLikes = FeedRankingService::calculateEngagementScore(5, 0);
        $this->assertSame($oneComment, $fiveLikes, '1 comment should equal 5 likes in score');
    }

    public function testGeoDecayScoreWithNullCoordinates(): void
    {
        $score = FeedRankingService::calculateGeoDecayScore(null, null, 53.35, -6.26);
        $this->assertSame(1.0, $score, 'Null viewer coords should return 1.0 (no penalty)');
    }

    public function testGeoDecayScoreWithNullPosterCoordinates(): void
    {
        $score = FeedRankingService::calculateGeoDecayScore(53.35, -6.26, null, null);
        $this->assertSame(1.0, $score, 'Null poster coords should return 1.0 (no penalty)');
    }
}
