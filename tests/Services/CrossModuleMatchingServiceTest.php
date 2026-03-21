<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\CrossModuleMatchingService;
use App\Services\SmartMatchingEngine;
use App\Services\MatchLearningService;
use App\Services\EmbeddingService;
use App\Core\Database;
use App\Core\TenantContext;

/**
 * CrossModuleMatchingService Tests
 *
 * Tests aggregated matching across listings, groups, volunteering, and events.
 * Uses real DB data with test users and validates the response structure,
 * module filtering, score ordering, and limit enforcement.
 */
class CrossModuleMatchingServiceTest extends TestCase
{
    private static int $testTenantId = 1;
    private static ?int $testUserId = null;
    private static bool $dbAvailable = false;

    private CrossModuleMatchingService $service;

    public static function setUpBeforeClass(): void
    {
        try {
            TenantContext::setById(self::$testTenantId);
        } catch (\Throwable $e) {
            return;
        }

        try {
            $timestamp = time() . rand(1000, 9999);

            Database::query(
                "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, skills, bio, is_approved, status, created_at)
                 VALUES (?, ?, ?, 'Cross', 'Module', 'Cross Module', 'gardening cooking teaching', 'I enjoy helping others', 1, 'active', NOW())",
                [self::$testTenantId, "cross_module_{$timestamp}@test.com", "cross_module_{$timestamp}"]
            );
            self::$testUserId = (int) Database::getInstance()->lastInsertId();

            self::$dbAvailable = true;
        } catch (\Throwable $e) {
            error_log("CrossModuleMatchingServiceTest setup failed: " . $e->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$dbAvailable) {
            return;
        }

        try {
            if (self::$testUserId) {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            }
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available for integration test');
        }

        TenantContext::setById(self::$testTenantId);

        $smartMatchingEngine = new SmartMatchingEngine(new EmbeddingService());
        $matchLearningService = new MatchLearningService();
        $this->service = new CrossModuleMatchingService($smartMatchingEngine, $matchLearningService);
    }

    // ==========================================
    // getAllMatches — Basic Structure
    // ==========================================

    public function testGetAllMatchesReturnsExpectedStructure(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('matches', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertIsArray($result['matches']);
        $this->assertIsArray($result['meta']);
    }

    public function testGetAllMatchesMetaStructure(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId);

        $meta = $result['meta'];
        $this->assertArrayHasKey('total', $meta);
        $this->assertArrayHasKey('modules', $meta);
        $this->assertArrayHasKey('min_score', $meta);
        $this->assertIsInt($meta['total']);
        $this->assertIsArray($meta['modules']);
    }

    public function testGetAllMatchesDefaultModulesIncludeAll(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId);

        $meta = $result['meta'];
        $this->assertContains('listings', $meta['modules']);
        $this->assertContains('groups', $meta['modules']);
        $this->assertContains('volunteering', $meta['modules']);
        $this->assertContains('events', $meta['modules']);
    }

    // ==========================================
    // getAllMatches — Options
    // ==========================================

    public function testGetAllMatchesWithCustomLimit(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId, ['limit' => 5]);

        $this->assertLessThanOrEqual(5, count($result['matches']));
    }

    public function testGetAllMatchesWithHighMinScore(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId, ['min_score' => 95]);

        foreach ($result['matches'] as $match) {
            $this->assertGreaterThanOrEqual(95, $match['match_score'],
                'All matches should be above min_score threshold');
        }
    }

    public function testGetAllMatchesWithSingleModule(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId, [
            'modules' => ['listings'],
        ]);

        $meta = $result['meta'];
        $this->assertEquals(['listings'], $meta['modules']);

        foreach ($result['matches'] as $match) {
            $this->assertEquals('listing', $match['module'],
                'Only listing matches should be returned when module filter is listings-only');
        }
    }

    public function testGetAllMatchesWithGroupsOnly(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId, [
            'modules' => ['groups'],
        ]);

        foreach ($result['matches'] as $match) {
            $this->assertEquals('group', $match['module']);
        }
    }

    public function testGetAllMatchesWithEventsOnly(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId, [
            'modules' => ['events'],
        ]);

        foreach ($result['matches'] as $match) {
            $this->assertEquals('event', $match['module']);
        }
    }

    public function testGetAllMatchesWithVolunteeringOnly(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId, [
            'modules' => ['volunteering'],
        ]);

        foreach ($result['matches'] as $match) {
            $this->assertEquals('volunteering', $match['module']);
        }
    }

    // ==========================================
    // getAllMatches — Ordering
    // ==========================================

    public function testGetAllMatchesSortedByScoreDescending(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId);

        $scores = array_map(fn($m) => $m['match_score'], $result['matches']);

        for ($i = 1; $i < count($scores); $i++) {
            $this->assertGreaterThanOrEqual(
                $scores[$i],
                $scores[$i - 1],
                'Matches should be sorted by score descending'
            );
        }
    }

    // ==========================================
    // getAllMatches — Debug Mode
    // ==========================================

    public function testGetAllMatchesWithoutDebugExcludesBreakdown(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId, ['debug' => false]);

        foreach ($result['matches'] as $match) {
            $this->assertArrayNotHasKey('match_breakdown', $match,
                'match_breakdown should be stripped when debug is false');
        }
    }

    public function testGetAllMatchesWithDebugMode(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId, ['debug' => true]);

        // If there are listing matches with breakdowns, they should be present
        $this->assertIsArray($result['matches']);
    }

    // ==========================================
    // getAllMatches — Match Item Structure
    // ==========================================

    public function testListingMatchItemStructure(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId, [
            'modules' => ['listings'],
            'min_score' => 1,
        ]);

        foreach ($result['matches'] as $match) {
            $this->assertArrayHasKey('module', $match);
            $this->assertArrayHasKey('match_score', $match);
            $this->assertArrayHasKey('match_type', $match);
            $this->assertArrayHasKey('match_reasons', $match);
            $this->assertArrayHasKey('title', $match);

            $this->assertEquals('listing', $match['module']);
            $this->assertIsFloat($match['match_score']);
            $this->assertIsArray($match['match_reasons']);
            break; // Only check first
        }
    }

    public function testGroupMatchItemStructure(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId, [
            'modules' => ['groups'],
            'min_score' => 1,
        ]);

        foreach ($result['matches'] as $match) {
            $this->assertEquals('group', $match['module']);
            $this->assertArrayHasKey('group_id', $match);
            $this->assertArrayHasKey('title', $match);
            $this->assertArrayHasKey('member_count', $match);
            $this->assertArrayHasKey('visibility', $match);
            $this->assertEquals('group_recommendation', $match['match_type']);
            break; // Only check first
        }
    }

    // ==========================================
    // getAllMatches — Edge Cases
    // ==========================================

    public function testGetAllMatchesForNonExistentUser(): void
    {
        $result = $this->service->getAllMatches(999999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('matches', $result);
        $this->assertIsArray($result['matches']);
    }

    public function testGetAllMatchesWithEmptyModules(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId, [
            'modules' => [],
        ]);

        $this->assertEmpty($result['matches']);
        $this->assertEquals(0, $result['meta']['total']);
    }

    public function testGetAllMatchesScoresAreBounded(): void
    {
        $result = $this->service->getAllMatches(self::$testUserId, ['min_score' => 0]);

        foreach ($result['matches'] as $match) {
            $this->assertGreaterThanOrEqual(0, $match['match_score']);
            $this->assertLessThanOrEqual(100, $match['match_score']);
        }
    }
}
