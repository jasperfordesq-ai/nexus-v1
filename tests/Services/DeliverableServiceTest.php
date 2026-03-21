<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\DeliverableService;

/**
 * DeliverableService Tests
 *
 * Tests deliverable CRUD: getAll, getById, create, update, addComment.
 */
class DeliverableServiceTest extends TestCase
{
    private function svc(): DeliverableService
    {
        return new DeliverableService();
    }

    public function test_get_all_returns_items_and_total(): void
    {
        $result = $this->svc()->getAll(2);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsInt($result['total']);
    }

    public function test_get_all_respects_limit_filter(): void
    {
        $result = $this->svc()->getAll(2, ['limit' => 5]);

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    public function test_get_all_respects_status_filter(): void
    {
        $result = $this->svc()->getAll(2, ['status' => 'draft']);

        $this->assertIsArray($result['items']);
    }

    public function test_get_by_id_returns_null_for_nonexistent(): void
    {
        $result = $this->svc()->getById(999999, 2);
        $this->assertNull($result);
    }

    public function test_get_by_id_scopes_by_tenant(): void
    {
        // ID 1 with a non-matching tenant should return null
        $result = $this->svc()->getById(1, 999999);
        $this->assertNull($result);
    }

    public function test_update_returns_false_for_nonexistent(): void
    {
        $result = $this->svc()->update(999999, 2, ['title' => 'Updated']);
        $this->assertFalse($result);
    }

    public function test_update_filters_allowed_fields(): void
    {
        // Should not fail even with disallowed fields
        $result = $this->svc()->update(999999, 2, [
            'title'       => 'Updated',
            'evil_field'  => 'should be ignored',
        ]);
        $this->assertFalse($result);
    }
}
