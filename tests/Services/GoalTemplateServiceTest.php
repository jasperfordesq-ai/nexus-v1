<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\GoalTemplateService;
use App\Models\GoalTemplate;

/**
 * GoalTemplateService Tests
 *
 * Tests template listing, categories, retrieval, creation, and goal-from-template.
 */
class GoalTemplateServiceTest extends TestCase
{
    private function svc(): GoalTemplateService
    {
        return new GoalTemplateService(new GoalTemplate());
    }

    // =========================================================================
    // getAll
    // =========================================================================

    public function test_get_all_returns_expected_structure(): void
    {
        $result = $this->svc()->getAll();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function test_get_all_respects_limit(): void
    {
        $result = $this->svc()->getAll(['limit' => 5]);
        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    public function test_get_all_supports_category_filter(): void
    {
        $result = $this->svc()->getAll(['category' => 'health']);
        $this->assertIsArray($result['items']);
    }

    public function test_get_all_supports_cursor_pagination(): void
    {
        $cursor = base64_encode('999999');
        $result = $this->svc()->getAll(['cursor' => $cursor]);
        $this->assertIsArray($result);
    }

    // =========================================================================
    // getCategories
    // =========================================================================

    public function test_get_categories_returns_array(): void
    {
        $result = $this->svc()->getCategories();
        $this->assertIsArray($result);
    }

    // =========================================================================
    // getById
    // =========================================================================

    public function test_get_by_id_returns_null_for_nonexistent(): void
    {
        $result = $this->svc()->getById(999999);
        $this->assertNull($result);
    }

    public function test_get_by_id_returns_model_or_null(): void
    {
        $result = $this->svc()->getById(999999);
        $this->assertTrue($result === null || $result instanceof GoalTemplate);
    }

    // =========================================================================
    // createGoalFromTemplate
    // =========================================================================

    public function test_create_goal_from_template_returns_null_for_nonexistent_template(): void
    {
        $result = $this->svc()->createGoalFromTemplate(999999, 1);
        $this->assertNull($result);
    }
}
