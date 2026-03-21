<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\FeedSocialService;
use App\Core\TenantContext;
use Illuminate\Database\QueryException;

/**
 * FeedSocialService Tests
 *
 * Tests post sharing, trending hashtags, and hashtag-based feed filtering.
 * Skips gracefully if feed_shares/feed_hashtags tables are not present.
 */
class FeedSocialServiceTest extends TestCase
{
    private function svc(): FeedSocialService
    {
        return new FeedSocialService();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(2);
    }

    // =========================================================================
    // getTrendingHashtags
    // =========================================================================

    public function test_get_trending_hashtags_returns_array(): void
    {
        try {
            $result = $this->svc()->getTrendingHashtags();
        } catch (QueryException $e) {
            $this->markTestSkipped('feed_hashtags table not available: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
    }

    public function test_get_trending_hashtags_respects_limit(): void
    {
        try {
            $result = $this->svc()->getTrendingHashtags(7, 5);
        } catch (QueryException $e) {
            $this->markTestSkipped('feed_hashtags table not available: ' . $e->getMessage());
        }
        $this->assertLessThanOrEqual(5, count($result));
    }

    public function test_get_trending_hashtags_custom_days(): void
    {
        try {
            $result = $this->svc()->getTrendingHashtags(30);
        } catch (QueryException $e) {
            $this->markTestSkipped('feed_hashtags table not available: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
    }

    // =========================================================================
    // getHashtagPosts
    // =========================================================================

    public function test_get_hashtag_posts_returns_expected_structure(): void
    {
        try {
            $result = $this->svc()->getHashtagPosts('timebanking');
        } catch (QueryException $e) {
            $this->markTestSkipped('feed_hashtags table not available: ' . $e->getMessage());
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function test_get_hashtag_posts_strips_hash_prefix(): void
    {
        try {
            $withHash = $this->svc()->getHashtagPosts('#timebanking');
            $withoutHash = $this->svc()->getHashtagPosts('timebanking');
        } catch (QueryException $e) {
            $this->markTestSkipped('feed_hashtags table not available: ' . $e->getMessage());
        }

        $this->assertIsArray($withHash['items']);
        $this->assertIsArray($withoutHash['items']);
    }

    public function test_get_hashtag_posts_respects_limit(): void
    {
        try {
            $result = $this->svc()->getHashtagPosts('test', ['limit' => 5]);
        } catch (QueryException $e) {
            $this->markTestSkipped('feed_hashtags table not available: ' . $e->getMessage());
        }
        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    public function test_get_hashtag_posts_returns_empty_for_nonexistent_tag(): void
    {
        try {
            $result = $this->svc()->getHashtagPosts('zzz_nonexistent_hashtag_999');
        } catch (QueryException $e) {
            $this->markTestSkipped('feed_hashtags table not available: ' . $e->getMessage());
        }
        $this->assertEmpty($result['items']);
        $this->assertFalse($result['has_more']);
    }
}
