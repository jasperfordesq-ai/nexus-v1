<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SmartMatchingEngine;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\DB;
use Mockery;

class SmartMatchingEngineTest extends TestCase
{
    private SmartMatchingEngine $engine;
    private $mockEmbedding;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockEmbedding = Mockery::mock(EmbeddingService::class);
        $this->engine = new SmartMatchingEngine($this->mockEmbedding);
    }

    // ── getConfig ──

    public function test_getConfig_returns_defaults(): void
    {
        DB::shouldReceive('table->where->value')->andReturnNull();

        $config = $this->engine->getConfig();
        $this->assertTrue($config['enabled']);
        $this->assertEquals(50, $config['max_distance_km']);
        $this->assertEquals(40, $config['min_match_score']);
        $this->assertEquals(80, $config['hot_match_threshold']);
        $this->assertArrayHasKey('weights', $config);
        $this->assertArrayHasKey('proximity', $config);
    }

    public function test_getConfig_caches_result(): void
    {
        DB::shouldReceive('table->where->value')->once()->andReturnNull();

        $this->engine->getConfig();
        $this->engine->getConfig(); // should not call DB again
    }

    // ── clearCache ──

    public function test_clearCache_resets_internal_state(): void
    {
        DB::shouldReceive('table->where->value')->andReturnNull();
        $this->engine->getConfig();
        $this->engine->clearCache();

        // After clearing, next call should query DB again
        DB::shouldReceive('table->where->value')->once()->andReturnNull();
        $this->engine->getConfig();
    }

    // ── extractKeywords ──

    public function test_extractKeywords_removes_stop_words(): void
    {
        $keywords = $this->engine->extractKeywords('I am looking for help with gardening');
        $this->assertNotContains('looking', $keywords);
        $this->assertNotContains('for', $keywords);
        $this->assertContains('garden', $keywords); // stemmed from gardening
    }

    public function test_extractKeywords_stems_words(): void
    {
        $keywords = $this->engine->extractKeywords('cooking and cleaning services');
        $this->assertContains('cook', $keywords);
        $this->assertContains('clean', $keywords);
        $this->assertContains('servic', $keywords);
    }

    public function test_extractKeywords_includes_short_domain_terms(): void
    {
        $keywords = $this->engine->extractKeywords('AI and ML development');
        $this->assertContains('ai', $keywords);
        $this->assertContains('ml', $keywords);
    }

    public function test_extractKeywords_returns_unique_values(): void
    {
        $keywords = $this->engine->extractKeywords('test test test testing');
        $this->assertCount(count(array_unique($keywords)), $keywords);
    }

    // ── calculateMatchScore ──

    public function test_calculateMatchScore_returns_expected_structure(): void
    {
        DB::shouldReceive('table->where->value')->andReturnNull();
        DB::shouldReceive('select')->andReturn([]);
        DB::shouldReceive('table->where->where->select->first')->andReturnNull();
        DB::shouldReceive('selectOne')->andReturnNull();

        $userData = [
            'latitude' => 53.35, 'longitude' => -6.26, 'skills' => 'gardening',
            'skills_weighted' => [], 'skills_proficiency_keys' => null,
        ];
        $userListings = [['type' => 'offer', 'category_id' => 1, 'title' => 'Gardening', 'description' => '']];
        $myListing = ['type' => 'offer', 'category_id' => 1, 'title' => 'Gardening', 'description' => ''];
        $candidate = [
            'id' => 10, 'user_id' => 2, 'category_id' => 1, 'title' => 'Need gardening help',
            'description' => 'Looking for someone to help with garden', 'created_at' => now()->toDateTimeString(),
            'latitude' => 53.36, 'longitude' => -6.25, 'image_url' => null,
            'category_name' => 'Gardening', 'author_rating' => null,
        ];

        $result = $this->engine->calculateMatchScore($userData, $userListings, $myListing, $candidate);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('reasons', $result);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertArrayHasKey('distance', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertIsFloat($result['score']);
        $this->assertIsArray($result['reasons']);
    }

    // ── findMatchesForUser ──

    public function test_findMatchesForUser_returns_empty_for_nonexistent_user(): void
    {
        DB::shouldReceive('table->where->value')->andReturnNull();
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->engine->findMatchesForUser(0);
        $this->assertIsArray($result);
    }
}
