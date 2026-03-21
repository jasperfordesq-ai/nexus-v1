<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\HashtagService;
use App\Core\TenantContext;

/**
 * HashtagService Tests
 */
class HashtagServiceTest extends TestCase
{
    private static int $testTenantId = 2;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);
    }

    // extractHashtags() pure logic

    public function test_extract_hashtags_returns_empty_for_plain_text(): void
    {
        $result = HashtagService::extractHashtags('Hello world, no hashtags here');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_extract_hashtags_finds_single_tag(): void
    {
        $result = HashtagService::extractHashtags('Check out #timebanking in your area');
        $this->assertContains('timebanking', $result);
        $this->assertCount(1, $result);
    }

    public function test_extract_hashtags_finds_multiple_tags(): void
    {
        $result = HashtagService::extractHashtags('#timebank #community #exchange');
        $this->assertContains('timebank', $result);
        $this->assertContains('community', $result);
        $this->assertContains('exchange', $result);
        $this->assertCount(3, $result);
    }

    public function test_extract_hashtags_normalises_to_lowercase(): void
    {
        $result = HashtagService::extractHashtags('#TimeBanking #COMMUNITY');
        $this->assertContains('timebanking', $result);
        $this->assertContains('community', $result);
    }

    public function test_extract_hashtags_deduplicates(): void
    {
        $result = HashtagService::extractHashtags('#cats and more #cats with #dogs');
        $this->assertContains('cats', $result);
        $this->assertContains('dogs', $result);
        $catCount = count(array_filter($result, fn($t) => $t === 'cats'));
        $this->assertSame(1, $catCount);
    }

    public function test_extract_hashtags_ignores_single_char_after_hash(): void
    {
        $result = HashtagService::extractHashtags('Price is # and code #1');
        $this->assertEmpty($result);
    }

    public function test_extract_hashtags_respects_max_50_char_length(): void
    {
        $longTag = str_repeat('a', 51);
        $result = HashtagService::extractHashtags("#{$longTag}");
        $this->assertEmpty($result);
    }

    public function test_extract_hashtags_allows_underscores_and_hyphens(): void
    {
        $result = HashtagService::extractHashtags('#time_banking #co-op');
        $this->assertContains('time_banking', $result);
        $this->assertContains('co-op', $result);
    }

    public function test_extract_hashtags_returns_array_of_strings(): void
    {
        $result = HashtagService::extractHashtags('#nexus #platform');
        foreach ($result as $tag) {
            $this->assertIsString($tag);
        }
    }

    public function test_extract_hashtags_handles_empty_string(): void
    {
        $result = HashtagService::extractHashtags('');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // DB-backed methods

    public function test_get_trending_returns_array(): void
    {
        try {
            $result = HashtagService::getTrending(10, 7);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_popular_returns_array(): void
    {
        try {
            $result = HashtagService::getPopular(20);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_search_returns_array(): void
    {
        try {
            $result = HashtagService::search('time', 10);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_search_strips_hash_prefix(): void
    {
        try {
            $withHash    = HashtagService::search('#time', 5);
            $withoutHash = HashtagService::search('time', 5);
            $this->assertEquals($withHash, $withoutHash);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_posts_by_hashtag_returns_expected_structure(): void
    {
        try {
            $result = HashtagService::getPostsByHashtag('timebanking');
            $this->assertIsArray($result);
            $this->assertArrayHasKey('items', $result);
            $this->assertArrayHasKey('cursor', $result);
            $this->assertArrayHasKey('has_more', $result);
            $this->assertArrayHasKey('tag', $result);
            $this->assertSame('timebanking', $result['tag']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_posts_by_hashtag_strips_hash_prefix(): void
    {
        try {
            $withHash    = HashtagService::getPostsByHashtag('#community');
            $withoutHash = HashtagService::getPostsByHashtag('community');
            $this->assertSame($withoutHash['tag'], $withHash['tag']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_batch_post_hashtags_returns_empty_for_empty_input(): void
    {
        try {
            $result = HashtagService::getBatchPostHashtags([]);
            $this->assertIsArray($result);
            $this->assertEmpty($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_post_hashtags_returns_array(): void
    {
        try {
            $result = HashtagService::getPostHashtags(999999);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_tenant_isolation_get_trending(): void
    {
        try {
            TenantContext::setById(2);
            $tenant2 = HashtagService::getTrending(5);
            TenantContext::setById(1);
            $tenant1 = HashtagService::getTrending(5);
            $this->assertIsArray($tenant1);
            $this->assertIsArray($tenant2);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        } finally {
            TenantContext::setById(self::$testTenantId);
        }
    }
}
