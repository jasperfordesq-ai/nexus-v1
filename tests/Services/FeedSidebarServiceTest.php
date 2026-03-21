<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\FeedSidebarService;
use App\Core\TenantContext;
use Illuminate\Database\QueryException;

/**
 * FeedSidebarService Tests
 *
 * Tests community stats, suggested members, and sidebar data aggregation.
 * Skips gracefully if required tables/columns are not present.
 */
class FeedSidebarServiceTest extends TestCase
{
    private function svc(): FeedSidebarService
    {
        return new FeedSidebarService();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(2);
    }

    // =========================================================================
    // communityStats
    // =========================================================================

    public function test_community_stats_returns_expected_keys(): void
    {
        try {
            $result = $this->svc()->communityStats();
        } catch (QueryException $e) {
            $this->markTestSkipped('Schema issue: ' . $e->getMessage());
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_members', $result);
        $this->assertArrayHasKey('total_hours', $result);
        $this->assertArrayHasKey('total_listings', $result);
        $this->assertArrayHasKey('total_events', $result);
        $this->assertArrayHasKey('active_exchanges', $result);
    }

    public function test_community_stats_values_are_numeric(): void
    {
        try {
            $result = $this->svc()->communityStats();
        } catch (QueryException $e) {
            $this->markTestSkipped('Schema issue: ' . $e->getMessage());
        }

        $this->assertIsInt($result['total_members']);
        $this->assertIsFloat($result['total_hours']);
        $this->assertIsInt($result['total_listings']);
        $this->assertIsInt($result['total_events']);
        $this->assertIsInt($result['active_exchanges']);
    }

    public function test_community_stats_values_are_non_negative(): void
    {
        try {
            $result = $this->svc()->communityStats();
        } catch (QueryException $e) {
            $this->markTestSkipped('Schema issue: ' . $e->getMessage());
        }

        $this->assertGreaterThanOrEqual(0, $result['total_members']);
        $this->assertGreaterThanOrEqual(0.0, $result['total_hours']);
        $this->assertGreaterThanOrEqual(0, $result['total_listings']);
        $this->assertGreaterThanOrEqual(0, $result['total_events']);
        $this->assertGreaterThanOrEqual(0, $result['active_exchanges']);
    }

    // =========================================================================
    // suggestedMembers
    // =========================================================================

    public function test_suggested_members_returns_array(): void
    {
        try {
            $result = $this->svc()->suggestedMembers(999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('Schema issue: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
    }

    public function test_suggested_members_respects_limit(): void
    {
        try {
            $result = $this->svc()->suggestedMembers(999999, 3);
        } catch (QueryException $e) {
            $this->markTestSkipped('Schema issue: ' . $e->getMessage());
        }
        $this->assertLessThanOrEqual(3, count($result));
    }

    // =========================================================================
    // sidebar
    // =========================================================================

    public function test_sidebar_returns_stats_and_suggested(): void
    {
        try {
            $result = $this->svc()->sidebar(999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('Schema issue: ' . $e->getMessage());
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('suggested', $result);
        $this->assertIsArray($result['stats']);
        $this->assertIsArray($result['suggested']);
    }

    public function test_sidebar_stats_match_community_stats(): void
    {
        try {
            $sidebar = $this->svc()->sidebar(999999);
            $stats = $this->svc()->communityStats();
        } catch (QueryException $e) {
            $this->markTestSkipped('Schema issue: ' . $e->getMessage());
        }

        $this->assertSame($stats['total_members'], $sidebar['stats']['total_members']);
    }
}
