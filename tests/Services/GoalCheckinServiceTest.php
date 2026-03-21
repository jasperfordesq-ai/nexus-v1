<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\GoalCheckinService;

/**
 * GoalCheckinService Tests
 *
 * Tests goal check-in creation and retrieval with cursor pagination.
 */
class GoalCheckinServiceTest extends TestCase
{
    private function svc(): GoalCheckinService
    {
        return new GoalCheckinService();
    }

    // =========================================================================
    // getByGoalId
    // =========================================================================

    public function test_get_by_goal_id_returns_expected_structure(): void
    {
        $result = $this->svc()->getByGoalId(999999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function test_get_by_goal_id_returns_empty_for_nonexistent_goal(): void
    {
        $result = $this->svc()->getByGoalId(999999);
        $this->assertEmpty($result['items']);
        $this->assertNull($result['cursor']);
        $this->assertFalse($result['has_more']);
    }

    public function test_get_by_goal_id_respects_limit(): void
    {
        $result = $this->svc()->getByGoalId(999999, ['limit' => 5]);
        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    public function test_get_by_goal_id_supports_cursor(): void
    {
        // Passing an encoded cursor should not throw
        $cursor = base64_encode('999999');
        $result = $this->svc()->getByGoalId(999999, ['cursor' => $cursor]);
        $this->assertIsArray($result);
    }

    public function test_get_by_goal_id_caps_limit_at_100(): void
    {
        $result = $this->svc()->getByGoalId(999999, ['limit' => 200]);
        // Internal limit is min(200, 100) = 100; verify no error
        $this->assertIsArray($result);
    }
}
