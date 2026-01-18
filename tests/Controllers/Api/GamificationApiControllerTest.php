<?php

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

/**
 * Tests for GamificationApiController endpoints
 *
 * Tests gamification features including daily rewards, challenges,
 * collections, shop, badges, and achievements.
 */
class GamificationApiControllerTest extends ApiTestCase
{
    /**
     * Test POST /api/daily-reward/check
     */
    public function testCheckDailyReward(): void
    {
        $response = $this->post('/api/daily-reward/check');

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/daily-reward/check', $response['endpoint']);
    }

    /**
     * Test GET /api/daily-reward/status
     */
    public function testGetDailyRewardStatus(): void
    {
        $response = $this->get('/api/daily-reward/status');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/daily-reward/status', $response['endpoint']);
    }

    /**
     * Test GET /api/gamification/challenges
     */
    public function testGetChallenges(): void
    {
        $response = $this->get('/api/gamification/challenges', [
            'type' => 'active'
        ]);

        $this->assertEquals('/api/gamification/challenges', $response['endpoint']);
    }

    /**
     * Test GET /api/gamification/collections
     */
    public function testGetCollections(): void
    {
        $response = $this->get('/api/gamification/collections');

        $this->assertEquals('/api/gamification/collections', $response['endpoint']);
    }

    /**
     * Test GET /api/gamification/shop
     */
    public function testGetShopItems(): void
    {
        $response = $this->get('/api/gamification/shop', [
            'category' => 'all',
            'limit' => 20
        ]);

        $this->assertEquals('/api/gamification/shop', $response['endpoint']);
    }

    /**
     * Test POST /api/gamification/shop/purchase
     */
    public function testPurchaseShopItem(): void
    {
        $response = $this->post('/api/gamification/shop/purchase', [
            'item_id' => 1,
            'quantity' => 1
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('item_id', $response['data']);
    }

    /**
     * Test GET /api/gamification/summary
     */
    public function testGetGamificationSummary(): void
    {
        $response = $this->get('/api/gamification/summary');

        $this->assertEquals('/api/gamification/summary', $response['endpoint']);
    }

    /**
     * Test POST /api/gamification/showcase
     */
    public function testUpdateShowcase(): void
    {
        $response = $this->post('/api/gamification/showcase', [
            'badge_ids' => [1, 2, 3]
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('badge_ids', $response['data']);
    }

    /**
     * Test GET /api/gamification/showcased
     */
    public function testGetShowcasedBadges(): void
    {
        $response = $this->get('/api/gamification/showcased', [
            'user_id' => self::$testUserId
        ]);

        $this->assertEquals('/api/gamification/showcased', $response['endpoint']);
    }

    /**
     * Test GET /api/gamification/share
     */
    public function testShareAchievement(): void
    {
        $response = $this->get('/api/gamification/share', [
            'achievement_id' => 1,
            'platform' => 'facebook'
        ]);

        $this->assertEquals('/api/gamification/share', $response['endpoint']);
        $this->assertArrayHasKey('achievement_id', $response['data']);
    }

    /**
     * Test GET /api/gamification/seasons
     */
    public function testGetSeasons(): void
    {
        $response = $this->get('/api/gamification/seasons');

        $this->assertEquals('/api/gamification/seasons', $response['endpoint']);
    }

    /**
     * Test GET /api/gamification/seasons/current
     */
    public function testGetCurrentSeason(): void
    {
        $response = $this->get('/api/gamification/seasons/current');

        $this->assertEquals('/api/gamification/seasons/current', $response['endpoint']);
    }

    /**
     * Test POST /api/shop/purchase (alias)
     */
    public function testShopPurchaseAlias(): void
    {
        $response = $this->post('/api/shop/purchase', [
            'item_id' => 1
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('item_id', $response['data']);
    }
}
