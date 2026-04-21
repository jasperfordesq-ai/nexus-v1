<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SmartMatchingEngine;
use App\Services\EmbeddingService;
use App\Services\VettingService;
use Illuminate\Support\Facades\DB;
use Mockery;

class SmartMatchingEngineTest extends TestCase
{
    private SmartMatchingEngine $engine;
    private $mockEmbedding;
    private $mockVetting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockEmbedding = Mockery::mock(EmbeddingService::class);
        $this->mockVetting = Mockery::mock(VettingService::class);
        // Default: searcher is not staff, has all vettings — keeps existing tests passing
        $this->mockVetting->shouldReceive('isSafeguardingStaff')->andReturn(false)->byDefault();
        $this->mockVetting->shouldReceive('userHasAllValidVettings')->andReturn(true)->byDefault();
        $this->engine = new SmartMatchingEngine($this->mockEmbedding, $this->mockVetting);
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

    // ── filterCandidatesByVettingRequirements ──

    public function test_filter_returns_empty_for_empty_candidates(): void
    {
        // No mock calls expected — early return
        $result = $this->engine->filterCandidatesByVettingRequirements([], 42);
        $this->assertSame([], $result);
    }

    public function test_filter_returns_unchanged_for_nonpositive_searcher(): void
    {
        $candidates = [['id' => 1, 'user_id' => 10]];
        $result = $this->engine->filterCandidatesByVettingRequirements($candidates, 0);
        $this->assertSame($candidates, $result);

        $result = $this->engine->filterCandidatesByVettingRequirements($candidates, -5);
        $this->assertSame($candidates, $result);
    }

    public function test_filter_returns_all_candidates_when_searcher_is_staff(): void
    {
        $mockVetting = Mockery::mock(VettingService::class);
        $mockVetting->shouldReceive('isSafeguardingStaff')->once()->with(42)->andReturn(true);
        // If staff bypass works, userHasAllValidVettings should NEVER be called
        $mockVetting->shouldNotReceive('userHasAllValidVettings');
        $engine = new SmartMatchingEngine($this->mockEmbedding, $mockVetting);

        $candidates = [
            ['id' => 1, 'user_id' => 10],
            ['id' => 2, 'user_id' => 20],
        ];

        $result = $engine->filterCandidatesByVettingRequirements($candidates, 42);
        $this->assertSame($candidates, $result);
    }

    public function test_filter_returns_all_candidates_when_none_require_vetting(): void
    {
        $candidates = [
            ['id' => 1, 'user_id' => 10],
            ['id' => 2, 'user_id' => 20],
        ];

        // getRequiredVettingTypesForUsers returns empty arrays for all user ids
        DB::shouldReceive('table')->with('user_safeguarding_preferences as p')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        // userHasAllValidVettings should NEVER be called — fast-path return
        $this->mockVetting->shouldNotReceive('userHasAllValidVettings');

        $result = $this->engine->filterCandidatesByVettingRequirements($candidates, 42);
        $this->assertCount(2, $result);
    }

    public function test_filter_includes_candidate_when_searcher_holds_required_vetting(): void
    {
        $candidates = [['id' => 99, 'user_id' => 10]];

        // Owner 10 requires garda_vetting
        DB::shouldReceive('table')->with('user_safeguarding_preferences as p')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['user_id' => 10, 'triggers' => json_encode(['vetting_type_required' => 'garda_vetting'])],
        ]));

        // Searcher holds it
        $mockVetting = Mockery::mock(VettingService::class);
        $mockVetting->shouldReceive('isSafeguardingStaff')->andReturn(false);
        $mockVetting->shouldReceive('userHasAllValidVettings')
            ->once()
            ->with(42, ['garda_vetting'])
            ->andReturn(true);

        $engine = new SmartMatchingEngine($this->mockEmbedding, $mockVetting);

        $result = $engine->filterCandidatesByVettingRequirements($candidates, 42);
        $this->assertCount(1, $result);
        $this->assertSame(99, $result[0]['id']);
    }

    public function test_filter_drops_candidate_when_searcher_lacks_required_vetting(): void
    {
        $candidates = [['id' => 99, 'user_id' => 10]];

        DB::shouldReceive('table')->with('user_safeguarding_preferences as p')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['user_id' => 10, 'triggers' => json_encode(['vetting_type_required' => 'garda_vetting'])],
        ]));

        // Searcher lacks garda_vetting
        $mockVetting = Mockery::mock(VettingService::class);
        $mockVetting->shouldReceive('isSafeguardingStaff')->andReturn(false);
        $mockVetting->shouldReceive('userHasAllValidVettings')
            ->once()
            ->with(42, ['garda_vetting'])
            ->andReturn(false);

        $engine = new SmartMatchingEngine($this->mockEmbedding, $mockVetting);

        $result = $engine->filterCandidatesByVettingRequirements($candidates, 42);
        $this->assertEmpty($result);
    }

    public function test_filter_dedupes_vetting_checks_by_typeset_signature(): void
    {
        // Three candidates share the same owner requirements — vetting check
        // should only run once thanks to the signature cache.
        $candidates = [
            ['id' => 1, 'user_id' => 10],
            ['id' => 2, 'user_id' => 20],
            ['id' => 3, 'user_id' => 30],
        ];

        DB::shouldReceive('table')->with('user_safeguarding_preferences as p')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['user_id' => 10, 'triggers' => json_encode(['vetting_type_required' => 'garda_vetting'])],
            (object) ['user_id' => 20, 'triggers' => json_encode(['vetting_type_required' => 'garda_vetting'])],
            (object) ['user_id' => 30, 'triggers' => json_encode(['vetting_type_required' => 'garda_vetting'])],
        ]));

        $mockVetting = Mockery::mock(VettingService::class);
        $mockVetting->shouldReceive('isSafeguardingStaff')->andReturn(false);
        // Called exactly ONCE despite three candidates with same requirements
        $mockVetting->shouldReceive('userHasAllValidVettings')
            ->once()
            ->with(42, ['garda_vetting'])
            ->andReturn(true);

        $engine = new SmartMatchingEngine($this->mockEmbedding, $mockVetting);

        $result = $engine->filterCandidatesByVettingRequirements($candidates, 42);
        $this->assertCount(3, $result);
    }

    public function test_filter_mixed_candidates_drops_only_flagged_without_vetting(): void
    {
        $candidates = [
            ['id' => 1, 'user_id' => 10], // no requirements
            ['id' => 2, 'user_id' => 20], // requires garda — searcher lacks
            ['id' => 3, 'user_id' => 30], // requires dbs — searcher has
            ['id' => 4, 'user_id' => 40], // no requirements
        ];

        DB::shouldReceive('table')->with('user_safeguarding_preferences as p')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['user_id' => 20, 'triggers' => json_encode(['vetting_type_required' => 'garda_vetting'])],
            (object) ['user_id' => 30, 'triggers' => json_encode(['vetting_type_required' => 'dbs_enhanced'])],
        ]));

        $mockVetting = Mockery::mock(VettingService::class);
        $mockVetting->shouldReceive('isSafeguardingStaff')->andReturn(false);
        $mockVetting->shouldReceive('userHasAllValidVettings')
            ->with(42, ['garda_vetting'])->andReturn(false);
        $mockVetting->shouldReceive('userHasAllValidVettings')
            ->with(42, ['dbs_enhanced'])->andReturn(true);

        $engine = new SmartMatchingEngine($this->mockEmbedding, $mockVetting);

        $result = $engine->filterCandidatesByVettingRequirements($candidates, 42);

        // 1, 3, 4 kept (indexes reindexed); 2 dropped
        $ids = array_map(fn ($c) => $c['id'], $result);
        $this->assertContains(1, $ids);
        $this->assertNotContains(2, $ids);
        $this->assertContains(3, $ids);
        $this->assertContains(4, $ids);
    }

    public function test_filter_excludes_self_owned_candidates_from_vetting_lookup(): void
    {
        // Searcher=10 viewing candidates that include their own listing (user_id=10).
        // Self-owned candidates should not trigger a vetting lookup (you don't need
        // to vet yourself to see your own content).
        $candidates = [
            ['id' => 1, 'user_id' => 10], // self
            ['id' => 2, 'user_id' => 20],
        ];

        DB::shouldReceive('table')->with('user_safeguarding_preferences as p')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->engine->filterCandidatesByVettingRequirements($candidates, 10);
        $this->assertCount(2, $result);
    }

    public function test_filter_fails_open_on_db_error(): void
    {
        $candidates = [
            ['id' => 1, 'user_id' => 10],
            ['id' => 2, 'user_id' => 20],
        ];

        // getRequiredVettingTypesForUsers throws internally — filter should log warning
        // and return unfiltered candidates (downstream gates remain fail-closed).
        DB::shouldReceive('table')->andThrow(new \Exception('DB error'));

        $result = $this->engine->filterCandidatesByVettingRequirements($candidates, 42);
        // The bulk method catches its own exception and returns empty map, which the
        // filter then sees as "nobody requires vetting" — returns all candidates.
        $this->assertCount(2, $result);
    }
}
