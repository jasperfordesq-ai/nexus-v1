<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\GoalReminderService;
use App\Core\TenantContext;

/**
 * GoalReminderService Tests
 *
 * Tests goal reminder CRUD: getReminder, setReminder, deleteReminder.
 */
class GoalReminderServiceTest extends TestCase
{
    private function svc(): GoalReminderService
    {
        return new GoalReminderService();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(2);
    }

    // =========================================================================
    // getReminder
    // =========================================================================

    public function test_get_reminder_returns_null_for_nonexistent(): void
    {
        $result = $this->svc()->getReminder(999999, 999999);
        $this->assertNull($result);
    }

    public function test_get_reminder_returns_nullable_array(): void
    {
        $result = $this->svc()->getReminder(999999, 999999);
        $this->assertTrue($result === null || is_array($result));
    }

    // =========================================================================
    // deleteReminder
    // =========================================================================

    public function test_delete_reminder_returns_false_for_nonexistent(): void
    {
        $result = $this->svc()->deleteReminder(999999, 999999);
        $this->assertFalse($result, 'Deleting nonexistent reminder should return false');
    }

    public function test_delete_reminder_returns_bool(): void
    {
        $result = $this->svc()->deleteReminder(999999, 999999);
        $this->assertIsBool($result);
    }
}
