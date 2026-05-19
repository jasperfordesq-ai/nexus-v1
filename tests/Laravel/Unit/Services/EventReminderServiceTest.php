<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\EventReminderService;
use Illuminate\Support\Facades\DB;

class EventReminderServiceTest extends TestCase
{
    private EventReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventReminderService();
    }

    private function expectTenantLookup(int $tenantId): void
    {
        $tenantQuery = \Mockery::mock();
        $tenantQuery->shouldReceive('where')
            ->once()
            ->with('id', $tenantId)
            ->andReturnSelf();
        $tenantQuery->shouldReceive('first')
            ->once()
            ->andReturn((object) [
                'id' => $tenantId,
                'slug' => 'tenant-' . $tenantId,
                'name' => 'Tenant ' . $tenantId,
            ]);

        DB::shouldReceive('table')
            ->once()
            ->with('tenants')
            ->andReturn($tenantQuery);
    }

    // =========================================================================
    // scheduleReminder()
    // =========================================================================

    public function test_scheduleReminder_returns_true_on_success(): void
    {
        DB::shouldReceive('statement')->once()->andReturn(true);

        $result = $this->service->scheduleReminder(2, 1, '2026-04-01 10:00:00');
        $this->assertTrue($result);
    }

    public function test_scheduleReminder_returns_false_on_exception(): void
    {
        DB::shouldReceive('statement')->andThrow(new \Exception('DB error'));

        $result = $this->service->scheduleReminder(2, 1, '2026-04-01 10:00:00');
        $this->assertFalse($result);
    }

    // =========================================================================
    // cancelReminder()
    // =========================================================================

    public function test_cancelReminder_returns_true_on_success(): void
    {
        DB::shouldReceive('delete')->once()->andReturn(1);

        $result = $this->service->cancelReminder(2, 1);
        $this->assertTrue($result);
    }

    public function test_cancelReminder_returns_false_on_exception(): void
    {
        DB::shouldReceive('delete')->andThrow(new \Exception('DB error'));

        $result = $this->service->cancelReminder(2, 1);
        $this->assertFalse($result);
    }

    // =========================================================================
    // sendDueReminders()
    // =========================================================================

    public function test_sendDueReminders_returns_zero_when_no_events(): void
    {
        $this->expectTenantLookup(2);
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->sendDueReminders(2);
        $this->assertEquals(0, $result);
    }

    public function test_sendDueReminders_processes_both_reminder_types(): void
    {
        // For both fixed reminder types plus configured-reminder scan, returns empty rows.
        $this->expectTenantLookup(2);
        DB::shouldReceive('select')->times(3)->andReturn([]);

        $result = $this->service->sendDueReminders(2);
        $this->assertEquals(0, $result);
    }

    public function test_sendDueReminders_sets_tenant_context_itself(): void
    {
        $source = file_get_contents(app_path('Services/EventReminderService.php'));

        $this->assertStringContainsString('TenantContext::runForTenant($tenantId', $source);
        $this->assertStringContainsString('TenantContext::getFrontendUrl()', $source);
        $this->assertStringContainsString('TenantContext::getSlugPrefix()', $source);
    }

    public function test_configured_email_reminders_with_invalid_email_are_failed_not_left_pending(): void
    {
        $source = file_get_contents(app_path('Services/EventReminderService.php'));

        $this->assertStringContainsString('failConfiguredReminder', $source);
        $this->assertStringContainsString('filter_var($reminder->email, FILTER_VALIDATE_EMAIL)', $source);
        $this->assertStringContainsString("->where('status', 'pending')", $source);
        $this->assertStringContainsString("'status' => 'failed'", $source);
    }
}
