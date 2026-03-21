<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SuperAdminAuditService;
use Illuminate\Support\Facades\DB;

class SuperAdminAuditServiceTest extends TestCase
{
    // ── getActionLabel ──

    public function test_getActionLabel_returns_known_labels(): void
    {
        $this->assertEquals('Tenant Created', SuperAdminAuditService::getActionLabel('tenant_created'));
        $this->assertEquals('Tenant Updated', SuperAdminAuditService::getActionLabel('tenant_updated'));
        $this->assertEquals('Super Admin Granted', SuperAdminAuditService::getActionLabel('super_admin_granted'));
    }

    public function test_getActionLabel_formats_unknown_types(): void
    {
        $this->assertEquals('Custom Action', SuperAdminAuditService::getActionLabel('custom_action'));
    }

    // ── getActionIcon ──

    public function test_getActionIcon_returns_known_icons(): void
    {
        $this->assertEquals('fa-plus-circle', SuperAdminAuditService::getActionIcon('tenant_created'));
        $this->assertEquals('fa-trash', SuperAdminAuditService::getActionIcon('tenant_deleted'));
    }

    public function test_getActionIcon_returns_default_for_unknown(): void
    {
        $this->assertEquals('fa-circle', SuperAdminAuditService::getActionIcon('unknown_action'));
    }

    // ── log ──

    public function test_log_returns_false_on_exception(): void
    {
        DB::shouldReceive('selectOne')->andThrow(new \RuntimeException('fail'));
        DB::shouldReceive('table->insert')->andThrow(new \RuntimeException('fail'));

        $result = SuperAdminAuditService::log('test_action', 'test_target');
        $this->assertFalse($result);
    }

    // ── getLog ──

    public function test_getLog_returns_array(): void
    {
        $result = SuperAdminAuditService::getLog();
        $this->assertIsArray($result);
    }

    public function test_getLog_returns_empty_on_error(): void
    {
        DB::shouldReceive('table->orderByDesc')->andThrow(new \RuntimeException('fail'));

        $result = SuperAdminAuditService::getLog();
        $this->assertEquals([], $result);
    }

    // ── getStats ──

    public function test_getStats_returns_expected_keys(): void
    {
        $result = SuperAdminAuditService::getStats();
        $this->assertArrayHasKey('total_actions', $result);
        $this->assertArrayHasKey('by_type', $result);
        $this->assertArrayHasKey('top_actors', $result);
    }

    public function test_getStats_returns_defaults_on_error(): void
    {
        DB::shouldReceive('table->where')->andThrow(new \RuntimeException('fail'));

        $result = SuperAdminAuditService::getStats();
        $this->assertEquals(0, $result['total_actions']);
        $this->assertEquals([], $result['by_type']);
        $this->assertEquals([], $result['top_actors']);
    }
}
