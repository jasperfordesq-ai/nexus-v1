<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\GoalProgressService;
use Illuminate\Database\QueryException;

/**
 * GoalProgressService Tests
 *
 * Tests goal progress history retrieval and summary aggregation.
 * Skips gracefully if goal_progress_history table is not present.
 */
class GoalProgressServiceTest extends TestCase
{
    private function svc(): GoalProgressService
    {
        return new GoalProgressService();
    }

    // =========================================================================
    // getProgressHistory
    // =========================================================================

    public function test_get_progress_history_returns_expected_structure(): void
    {
        try {
            $result = $this->svc()->getProgressHistory(999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('goal_progress_history table not available: ' . $e->getMessage());
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function test_get_progress_history_returns_empty_for_nonexistent_goal(): void
    {
        try {
            $result = $this->svc()->getProgressHistory(999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('goal_progress_history table not available: ' . $e->getMessage());
        }
        $this->assertEmpty($result['items']);
        $this->assertNull($result['cursor']);
        $this->assertFalse($result['has_more']);
    }

    public function test_get_progress_history_respects_limit(): void
    {
        try {
            $result = $this->svc()->getProgressHistory(999999, ['limit' => 5]);
        } catch (QueryException $e) {
            $this->markTestSkipped('goal_progress_history table not available: ' . $e->getMessage());
        }
        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    public function test_get_progress_history_supports_cursor(): void
    {
        $cursor = base64_encode('999999');
        try {
            $result = $this->svc()->getProgressHistory(999999, ['cursor' => $cursor]);
        } catch (QueryException $e) {
            $this->markTestSkipped('goal_progress_history table not available: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
    }

    public function test_get_progress_history_supports_event_type_filter(): void
    {
        try {
            $result = $this->svc()->getProgressHistory(999999, ['event_type' => 'checkin']);
        } catch (QueryException $e) {
            $this->markTestSkipped('goal_progress_history table not available: ' . $e->getMessage());
        }
        $this->assertIsArray($result['items']);
    }

    // =========================================================================
    // getSummary
    // =========================================================================

    public function test_get_summary_returns_expected_keys(): void
    {
        try {
            $result = $this->svc()->getSummary(999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('goal_progress_history table not available: ' . $e->getMessage());
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_events', $result);
        $this->assertArrayHasKey('events_by_type', $result);
    }

    public function test_get_summary_returns_zero_for_nonexistent_goal(): void
    {
        try {
            $result = $this->svc()->getSummary(999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('goal_progress_history table not available: ' . $e->getMessage());
        }
        $this->assertSame(0, $result['total_events']);
        $this->assertEmpty($result['events_by_type']);
    }

    public function test_get_summary_total_events_is_int(): void
    {
        try {
            $result = $this->svc()->getSummary(999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('goal_progress_history table not available: ' . $e->getMessage());
        }
        $this->assertIsInt($result['total_events']);
    }

    public function test_get_summary_events_by_type_is_array(): void
    {
        try {
            $result = $this->svc()->getSummary(999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('goal_progress_history table not available: ' . $e->getMessage());
        }
        $this->assertIsArray($result['events_by_type']);
    }
}
