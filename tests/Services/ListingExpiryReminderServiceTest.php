<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ListingExpiryReminderService;
use App\Core\TenantContext;

/**
 * ListingExpiryReminderService Tests
 */
class ListingExpiryReminderServiceTest extends TestCase
{
    private ListingExpiryReminderService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new ListingExpiryReminderService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ListingExpiryReminderService::class, $this->service);
    }

    public function test_send_due_reminders_returns_expected_structure(): void
    {
        $result = $this->service->sendDueReminders();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertIsInt($result['sent']);
        $this->assertIsInt($result['errors']);
    }

    public function test_send_due_reminders_non_negative_counts(): void
    {
        $result = $this->service->sendDueReminders();
        $this->assertGreaterThanOrEqual(0, $result['sent']);
        $this->assertGreaterThanOrEqual(0, $result['errors']);
    }

    public function test_cleanup_old_records_returns_int(): void
    {
        $result = $this->service->cleanupOldRecords();
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function test_days_before_expiry_constant(): void
    {
        // Verify the constant is accessible via reflection
        $reflection = new \ReflectionClass(ListingExpiryReminderService::class);
        $constant = $reflection->getConstant('DAYS_BEFORE_EXPIRY');
        $this->assertSame(3, $constant);
    }
}
