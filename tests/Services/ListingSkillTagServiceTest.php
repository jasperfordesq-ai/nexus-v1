<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ListingSkillTagService;
use App\Core\TenantContext;

/**
 * ListingSkillTagService Tests
 */
class ListingSkillTagServiceTest extends TestCase
{
    private ListingSkillTagService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new ListingSkillTagService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ListingSkillTagService::class, $this->service);
    }

    public function test_set_tags_returns_false_for_nonexistent_listing(): void
    {
        $result = $this->service->setTags(999999, ['gardening', 'cooking']);
        $this->assertFalse($result);
    }

    public function test_get_tags_returns_array(): void
    {
        $result = $this->service->getTags(999999);
        $this->assertIsArray($result);
    }

    public function test_get_tags_returns_empty_for_nonexistent_listing(): void
    {
        $result = $this->service->getTags(999999);
        $this->assertEmpty($result);
    }

    public function test_add_tag_empty_string_returns_false(): void
    {
        $result = $this->service->addTag(999999, '');
        $this->assertFalse($result);
    }

    public function test_add_tag_whitespace_only_returns_false(): void
    {
        $result = $this->service->addTag(999999, '   ');
        $this->assertFalse($result);
    }

    public function test_remove_tag_does_not_throw(): void
    {
        // Should not throw even for nonexistent listing/tag
        $this->service->removeTag(999999, 'nonexistent-tag');
        $this->assertTrue(true);
    }

    public function test_find_listings_by_tags_empty_array_returns_empty(): void
    {
        $result = $this->service->findListingsByTags([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_find_listings_by_tags_returns_array(): void
    {
        $result = $this->service->findListingsByTags(['gardening', 'cooking']);
        $this->assertIsArray($result);
    }

    public function test_find_listings_by_tags_respects_limit(): void
    {
        $result = $this->service->findListingsByTags(['test'], 3);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(3, count($result));
    }

    public function test_find_listings_by_tags_with_invalid_tags(): void
    {
        // Tags with only special characters should normalize to empty and return empty
        $result = $this->service->findListingsByTags(['!!!', '@@@', '###']);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_popular_tags_returns_array(): void
    {
        $result = $this->service->getPopularTags();
        $this->assertIsArray($result);
    }

    public function test_get_popular_tags_respects_limit(): void
    {
        $result = $this->service->getPopularTags(5);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));
    }

    public function test_get_popular_tags_item_structure(): void
    {
        $result = $this->service->getPopularTags();
        foreach ($result as $item) {
            $this->assertArrayHasKey('tag', $item);
            $this->assertArrayHasKey('count', $item);
            $this->assertIsString($item['tag']);
            $this->assertIsInt($item['count']);
        }
    }

    public function test_autocomplete_tags_short_prefix_returns_empty(): void
    {
        $result = $this->service->autocompleteTags('a');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_autocomplete_tags_returns_array(): void
    {
        $result = $this->service->autocompleteTags('ga', 5);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));
    }

    public function test_max_tags_per_listing_constant(): void
    {
        $reflection = new \ReflectionClass(ListingSkillTagService::class);
        $this->assertSame(10, $reflection->getConstant('MAX_TAGS_PER_LISTING'));
    }
}
