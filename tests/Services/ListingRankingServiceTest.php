<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ListingRankingService;
use App\Core\TenantContext;

/**
 * ListingRankingService Tests
 */
class ListingRankingServiceTest extends TestCase
{
    private ListingRankingService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new ListingRankingService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ListingRankingService::class, $this->service);
    }

    public function test_get_config_returns_expected_keys(): void
    {
        $config = $this->service->getConfig();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('relevance_category_match', $config);
        $this->assertArrayHasKey('relevance_search_boost', $config);
        $this->assertArrayHasKey('freshness_full_days', $config);
        $this->assertArrayHasKey('freshness_half_life_days', $config);
        $this->assertArrayHasKey('freshness_minimum', $config);
        $this->assertArrayHasKey('engagement_view_weight', $config);
        $this->assertArrayHasKey('engagement_inquiry_weight', $config);
        $this->assertArrayHasKey('engagement_save_weight', $config);
        $this->assertArrayHasKey('engagement_minimum', $config);
        $this->assertArrayHasKey('quality_min_description', $config);
        $this->assertArrayHasKey('quality_image_boost', $config);
        $this->assertArrayHasKey('quality_location_boost', $config);
        $this->assertArrayHasKey('quality_verified_boost', $config);
        $this->assertArrayHasKey('reciprocity_enabled', $config);
        $this->assertArrayHasKey('reciprocity_match_boost', $config);
        $this->assertArrayHasKey('reciprocity_mutual_boost', $config);
        $this->assertArrayHasKey('geo_enabled', $config);
        $this->assertArrayHasKey('geo_full_radius_km', $config);
        $this->assertArrayHasKey('geo_decay_per_km', $config);
    }

    public function test_get_config_default_values(): void
    {
        $config = $this->service->getConfig();
        $this->assertSame(1.5, $config['relevance_category_match']);
        $this->assertSame(2.0, $config['relevance_search_boost']);
        $this->assertSame(7, $config['freshness_full_days']);
        $this->assertSame(30, $config['freshness_half_life_days']);
        $this->assertSame(0.3, $config['freshness_minimum']);
    }

    public function test_is_enabled_returns_bool(): void
    {
        $result = $this->service->isEnabled();
        $this->assertIsBool($result);
    }

    public function test_clear_cache_resets_config(): void
    {
        // First call loads config
        $this->service->getConfig();
        // Clear cache
        $this->service->clearCache();
        // Config should be reloadable
        $config = $this->service->getConfig();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
    }

    public function test_rank_listings_empty_array(): void
    {
        $result = $this->service->rankListings([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_rank_listings_preserves_listing_data(): void
    {
        $listings = [
            [
                'id' => 1,
                'title' => 'Test Listing A',
                'description' => 'A good description that is long enough for quality scoring in the ranking algorithm',
                'type' => 'offer',
                'category_id' => 1,
                'image_url' => 'test.jpg',
                'location' => 'Dublin',
                'view_count' => 10,
                'contact_count' => 2,
                'save_count' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => null,
                'user_id' => 1,
            ],
            [
                'id' => 2,
                'title' => 'Test Listing B',
                'description' => 'Short',
                'type' => 'request',
                'category_id' => 2,
                'image_url' => null,
                'location' => null,
                'view_count' => 0,
                'contact_count' => 0,
                'save_count' => 0,
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
                'updated_at' => null,
                'user_id' => 2,
            ],
        ];

        $result = $this->service->rankListings($listings);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        // Each listing should have _match_rank and _score_breakdown
        foreach ($result as $listing) {
            $this->assertArrayHasKey('_match_rank', $listing);
            $this->assertArrayHasKey('_score_breakdown', $listing);
            $this->assertIsFloat($listing['_match_rank']);
            $this->assertIsArray($listing['_score_breakdown']);
        }
    }

    public function test_rank_listings_higher_quality_scores_better(): void
    {
        $listings = [
            [
                'id' => 1,
                'title' => 'High Quality',
                'description' => str_repeat('Quality description content. ', 10),
                'type' => 'offer',
                'category_id' => 1,
                'image_url' => 'image.jpg',
                'location' => 'City',
                'view_count' => 50,
                'contact_count' => 10,
                'save_count' => 5,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => null,
                'user_id' => 1,
            ],
            [
                'id' => 2,
                'title' => 'Low Quality',
                'description' => 'Short',
                'type' => 'offer',
                'category_id' => 1,
                'image_url' => null,
                'location' => null,
                'view_count' => 0,
                'contact_count' => 0,
                'save_count' => 0,
                'created_at' => date('Y-m-d H:i:s', strtotime('-90 days')),
                'updated_at' => null,
                'user_id' => 2,
            ],
        ];

        $result = $this->service->rankListings($listings);
        // First result should be the higher quality listing
        $this->assertSame(1, $result[0]['id']);
        $this->assertGreaterThan($result[1]['_match_rank'], $result[0]['_match_rank']);
    }

    public function test_rank_listings_score_breakdown_has_all_factors(): void
    {
        $listings = [
            [
                'id' => 1,
                'title' => 'Test',
                'description' => 'Test description',
                'type' => 'offer',
                'category_id' => 1,
                'image_url' => null,
                'location' => null,
                'view_count' => 0,
                'contact_count' => 0,
                'save_count' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => null,
                'user_id' => 1,
            ],
        ];

        $result = $this->service->rankListings($listings);
        $breakdown = $result[0]['_score_breakdown'];

        $this->assertArrayHasKey('relevance', $breakdown);
        $this->assertArrayHasKey('freshness', $breakdown);
        $this->assertArrayHasKey('engagement', $breakdown);
        $this->assertArrayHasKey('proximity', $breakdown);
        $this->assertArrayHasKey('quality', $breakdown);
        $this->assertArrayHasKey('reciprocity', $breakdown);
    }

    public function test_build_ranked_query_returns_sql_and_params(): void
    {
        $result = $this->service->buildRankedQuery();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sql', $result);
        $this->assertArrayHasKey('params', $result);
        $this->assertIsString($result['sql']);
        $this->assertIsArray($result['params']);
        $this->assertStringContainsString('SELECT', $result['sql']);
        $this->assertStringContainsString('tenant_id', $result['sql']);
    }

    public function test_build_ranked_query_with_filters(): void
    {
        $result = $this->service->buildRankedQuery(1, [
            'type' => 'offer',
            'category_id' => 5,
            'search' => 'gardening',
            'limit' => 10,
        ]);
        $this->assertIsArray($result);
        $this->assertStringContainsString('l.type', $result['sql']);
        $this->assertStringContainsString('l.category_id', $result['sql']);
        $this->assertStringContainsString('LIKE', $result['sql']);
        $this->assertStringContainsString('LIMIT', $result['sql']);
    }
}
