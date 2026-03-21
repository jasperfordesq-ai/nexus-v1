<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationAuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FederationAuditServiceTest extends TestCase
{
    // =========================================================================
    // log()
    // =========================================================================

    public function test_log_inserts_audit_record(): void
    {
        DB::shouldReceive('table->where->select->first')->andReturn(null);
        DB::shouldReceive('table->insert')->once()->andReturn(true);

        $result = FederationAuditService::log('test_action', 1, 2, null, ['key' => 'value']);
        $this->assertTrue($result);
    }

    public function test_log_critical_also_logs_to_laravel(): void
    {
        DB::shouldReceive('table->where->select->first')->andReturn(null);
        DB::shouldReceive('table->insert')->once();
        Log::shouldReceive('critical')->once();

        FederationAuditService::log('test_action', 1, 2, null, [], 'critical');
    }

    public function test_log_returns_false_on_db_error(): void
    {
        DB::shouldReceive('table->where->select->first')->andReturn(null);
        DB::shouldReceive('table->insert')->andThrow(new \Exception('DB error'));
        Log::shouldReceive('error')->once();

        $result = FederationAuditService::log('test_action');
        $this->assertFalse($result);
    }

    // =========================================================================
    // logSearch()
    // =========================================================================

    public function test_logSearch_delegates_to_log(): void
    {
        DB::shouldReceive('table->where->select->first')->andReturn(null);
        DB::shouldReceive('table->insert')->once();

        $result = FederationAuditService::logSearch('members', ['skill' => 'PHP'], 5, 1);
        $this->assertTrue($result);
    }

    // =========================================================================
    // getLog()
    // =========================================================================

    public function test_getLog_returns_empty_on_error(): void
    {
        DB::shouldReceive('table')->andThrow(new \Exception('error'));
        Log::shouldReceive('error')->once();

        $result = FederationAuditService::getLog();
        $this->assertEquals([], $result);
    }

    // =========================================================================
    // getStats()
    // =========================================================================

    public function test_getStats_returns_expected_structure(): void
    {
        DB::shouldReceive('table->where->count')->andReturn(100);
        DB::shouldReceive('table->select->where->groupBy->orderByDesc->get->toArray')->andReturn([]);
        DB::shouldReceive('table->select->where->groupBy->orderByDesc->limit->get->toArray')->andReturn([]);
        DB::shouldReceive('table->select->where->whereNotNull->whereNotNull->groupBy->orderByDesc->limit->get->toArray')
            ->andReturn([]);

        $this->markTestIncomplete('Complex DB mock chain; requires integration test');
    }

    // =========================================================================
    // Pure utility methods
    // =========================================================================

    public function test_getActionLabel_returns_known_labels(): void
    {
        $this->assertEquals('Partnership Approved', FederationAuditService::getActionLabel('partnership_approved'));
        $this->assertEquals('Federated Search', FederationAuditService::getActionLabel('federated_search'));
    }

    public function test_getActionLabel_returns_formatted_fallback_for_unknown(): void
    {
        $result = FederationAuditService::getActionLabel('custom_unknown_action');
        $this->assertEquals('Custom Unknown Action', $result);
    }

    public function test_getActionIcon_returns_icon_class(): void
    {
        $this->assertStringContainsString('fa-handshake', FederationAuditService::getActionIcon('partnership_requested'));
        $this->assertEquals('fa-circle', FederationAuditService::getActionIcon('unknown'));
    }

    public function test_getLevelBadge_returns_badge_class(): void
    {
        $this->assertEquals('badge-info', FederationAuditService::getLevelBadge('info'));
        $this->assertEquals('badge-danger', FederationAuditService::getLevelBadge('critical'));
        $this->assertEquals('badge-secondary', FederationAuditService::getLevelBadge('unknown'));
    }

    // =========================================================================
    // purgeOld()
    // =========================================================================

    public function test_purgeOld_returns_zero_on_error(): void
    {
        DB::shouldReceive('table->where->delete')->andThrow(new \Exception('error'));
        Log::shouldReceive('error')->once();

        $this->assertEquals(0, FederationAuditService::purgeOld());
    }
}
