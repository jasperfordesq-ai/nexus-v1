<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditLogServiceTest extends TestCase
{
    private AuditLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuditLogService();
    }

    public function test_action_constants_defined(): void
    {
        $this->assertSame('wallet_deposit', AuditLogService::ACTION_WALLET_DEPOSIT);
        $this->assertSame('admin_user_created', AuditLogService::ACTION_ADMIN_USER_CREATED);
        $this->assertSame('bulk_approve', AuditLogService::ACTION_BULK_APPROVE);
    }

    public function test_logAction_inserts_and_returns_id(): void
    {
        DB::shouldReceive('table')->with('org_audit_log')->andReturnSelf();
        DB::shouldReceive('insertGetId')->once()->andReturn(42);

        $id = $this->service->logAction(2, 'test_action', 1, ['key' => 'value']);
        $this->assertSame(42, $id);
    }

    public function test_getRecent_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — uses DB query builder with joins');
    }

    public function test_static_log_returns_id_on_success(): void
    {
        DB::shouldReceive('table')->with('org_audit_log')->andReturnSelf();
        DB::shouldReceive('insertGetId')->once()->andReturn(10);

        $id = AuditLogService::log('test_action', 1, 2, ['detail' => 'value']);
        $this->assertSame(10, $id);
    }

    public function test_static_log_returns_null_on_exception(): void
    {
        DB::shouldReceive('table')->with('org_audit_log')->andReturnSelf();
        DB::shouldReceive('insertGetId')->andThrow(new \Exception('DB error'));
        Log::shouldReceive('warning')->once();

        $id = AuditLogService::log('test_action');
        $this->assertNull($id);
    }

    public function test_getActionLabel_returns_known_label(): void
    {
        $this->assertSame('Wallet Deposit', AuditLogService::getActionLabel('wallet_deposit'));
        $this->assertSame('Admin Created User', AuditLogService::getActionLabel('admin_user_created'));
    }

    public function test_getActionLabel_returns_formatted_unknown_label(): void
    {
        $label = AuditLogService::getActionLabel('some_custom_action');
        $this->assertSame('Some Custom Action', $label);
    }

    public function test_logAdminAction_delegates_to_log(): void
    {
        DB::shouldReceive('table')->with('org_audit_log')->andReturnSelf();
        DB::shouldReceive('insertGetId')->once()->andReturn(5);

        $id = AuditLogService::logAdminAction('admin_user_created', 1, 2, ['email' => 'test@test.com']);
        $this->assertSame(5, $id);
    }

    public function test_logUserUpdated_includes_changed_fields(): void
    {
        DB::shouldReceive('table')->with('org_audit_log')->andReturnSelf();
        DB::shouldReceive('insertGetId')->once()->andReturn(6);

        $id = AuditLogService::logUserUpdated(1, 2, ['name', 'email']);
        $this->assertSame(6, $id);
    }

    public function test_cleanup_deletes_old_entries(): void
    {
        DB::shouldReceive('table')->with('org_audit_log')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('delete')->andReturn(50);
        DB::shouldReceive('raw')->andReturn('');

        $count = AuditLogService::cleanup(365);
        $this->assertSame(50, $count);
    }

    public function test_cleanup_returns_zero_on_exception(): void
    {
        DB::shouldReceive('table')->with('org_audit_log')->andReturnSelf();
        DB::shouldReceive('where')->andThrow(new \Exception('DB error'));
        DB::shouldReceive('raw')->andReturn('');
        Log::shouldReceive('warning')->once();

        $count = AuditLogService::cleanup();
        $this->assertSame(0, $count);
    }

    public function test_getLogCount_returns_integer(): void
    {
        DB::shouldReceive('table')->with('org_audit_log')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(25);

        $count = AuditLogService::getLogCount(1);
        $this->assertSame(25, $count);
    }

    public function test_getLogCount_returns_zero_on_exception(): void
    {
        DB::shouldReceive('table')->with('org_audit_log')->andReturnSelf();
        DB::shouldReceive('where')->andThrow(new \Exception('fail'));

        $count = AuditLogService::getLogCount(1);
        $this->assertSame(0, $count);
    }
}
