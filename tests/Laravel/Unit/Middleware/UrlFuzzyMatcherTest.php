<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Middleware\UrlFuzzyMatcher;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Tests for UrlFuzzyMatcher middleware.
 *
 * Tests pattern-based URL corrections and the Levenshtein distance utility.
 * Database-backed suggestions require seeded content and are tested
 * via the findSuggestion method.
 */
class UrlFuzzyMatcherTest extends TestCase
{
    use DatabaseTransactions;

    public function test_findSuggestion_corrects_singular_listing_to_plural(): void
    {
        $result = UrlFuzzyMatcher::findSuggestion('/listing/123');

        $this->assertEquals('/listings/123', $result);
    }

    public function test_findSuggestion_corrects_singular_group_to_plural(): void
    {
        $result = UrlFuzzyMatcher::findSuggestion('/group/5');

        $this->assertEquals('/groups/5', $result);
    }

    public function test_findSuggestion_corrects_blog_to_news(): void
    {
        $result = UrlFuzzyMatcher::findSuggestion('/blog/my-post');

        $this->assertEquals('/news/my-post', $result);
    }

    public function test_findSuggestion_corrects_forum_to_discussions(): void
    {
        $result = UrlFuzzyMatcher::findSuggestion('/forum/topic-1');

        $this->assertEquals('/discussions/topic-1', $result);
    }

    public function test_findSuggestion_removes_trailing_slash(): void
    {
        $result = UrlFuzzyMatcher::findSuggestion('/listings/');

        $this->assertEquals('/listings', $result);
    }

    public function test_findSuggestion_corrects_volunteer_typo(): void
    {
        $result = UrlFuzzyMatcher::findSuggestion('/voluntaer-work');

        $this->assertEquals('/volunteer-work', $result);
    }

    public function test_findSuggestion_corrects_gardening_typo(): void
    {
        $result = UrlFuzzyMatcher::findSuggestion('/gardaning-help');

        $this->assertEquals('/gardening-help', $result);
    }

    public function test_findSuggestion_returns_null_for_no_match(): void
    {
        // A URL that doesn't match any pattern or DB content
        $result = UrlFuzzyMatcher::findSuggestion('/completely-unknown-page-xyz');

        $this->assertNull($result);
    }

    public function test_findSuggestion_returns_null_when_no_correction_needed(): void
    {
        // URL that already matches patterns (no correction changes it)
        // and has no DB match
        $result = UrlFuzzyMatcher::findSuggestion('/members/profile');

        $this->assertNull($result);
    }

    public function test_calculateDistance_returns_zero_for_identical_strings(): void
    {
        $this->assertEquals(0, UrlFuzzyMatcher::calculateDistance('hello', 'hello'));
    }

    public function test_calculateDistance_returns_zero_for_case_insensitive_match(): void
    {
        $this->assertEquals(0, UrlFuzzyMatcher::calculateDistance('Hello', 'hello'));
    }

    public function test_calculateDistance_returns_correct_distance(): void
    {
        // "kitten" -> "sitting" = distance 3
        $this->assertEquals(3, UrlFuzzyMatcher::calculateDistance('kitten', 'sitting'));
    }

    public function test_calculateDistance_handles_empty_strings(): void
    {
        $this->assertEquals(5, UrlFuzzyMatcher::calculateDistance('', 'hello'));
        $this->assertEquals(5, UrlFuzzyMatcher::calculateDistance('hello', ''));
        $this->assertEquals(0, UrlFuzzyMatcher::calculateDistance('', ''));
    }

    public function test_findSuggestion_handles_empty_url(): void
    {
        $result = UrlFuzzyMatcher::findSuggestion('/');

        $this->assertNull($result);
    }

    public function test_findSuggestion_multiple_corrections_applied(): void
    {
        // /listing/ -> /listings/ (singular to plural) -> /listings (trailing slash)
        $result = UrlFuzzyMatcher::findSuggestion('/listing/');

        // Should apply both corrections
        $this->assertNotNull($result);
        $this->assertStringStartsWith('/listings', $result);
    }
}
