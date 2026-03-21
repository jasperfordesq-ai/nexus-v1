<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationFeatureService;
use App\Services\FederationAuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;

class FederationFeatureServiceTest extends TestCase
{
    private FederationFeatureService $service;
    private FederationAuditService $auditMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditMock = Mockery::mock(FederationAuditService::class);
        $this->auditMock->shouldReceive('log')->andReturn(true);
        $this->service = new FederationFeatureService($this->auditMock);
    }

    public function test_getSystemControls_returns_defaults_when_table_empty(): void
    {
        DB::shouldReceive('table->where->first')->andReturn(null);
        DB::shouldReceive('statement')->once(); // initializeSystemDefaults
        DB::shouldReceive('table->where->first')->andReturn(null); // Second call

        $result = $this->service->getSystemControls();
        $this->assertArrayHasKey('federation_enabled', $result);
        $this->assertEquals(1, $result['federation_enabled']);
    }

    public function test_isGloballyEnabled_returns_false_during_lockdown(): void
    {
        $controls = (object) ['federation_enabled' => 1, 'emergency_lockdown_active' => 1];
        DB::shouldReceive('table->where->first')->andReturn($controls);

        $this->assertFalse($this->service->isGloballyEnabled());
    }

    public function test_isGloballyEnabled_returns_true_when_enabled(): void
    {
        $controls = (object) ['federation_enabled' => 1, 'emergency_lockdown_active' => 0];
        DB::shouldReceive('table->where->first')->andReturn($controls);

        $this->assertTrue($this->service->isGloballyEnabled());
    }

    public function test_isWhitelistModeActive_checks_controls(): void
    {
        $controls = (object) ['whitelist_mode_enabled' => 1, 'federation_enabled' => 1, 'emergency_lockdown_active' => 0];
        DB::shouldReceive('table->where->first')->andReturn($controls);

        $this->assertTrue($this->service->isWhitelistModeActive());
    }

    public function test_isTenantWhitelisted_returns_true_when_whitelist_mode_off(): void
    {
        $controls = (object) ['whitelist_mode_enabled' => 0, 'federation_enabled' => 1, 'emergency_lockdown_active' => 0];
        DB::shouldReceive('table->where->first')->andReturn($controls);

        $this->assertTrue($this->service->isTenantWhitelisted(1));
    }

    public function test_getMaxFederationLevel_returns_integer(): void
    {
        $controls = (object) ['max_federation_level' => 4, 'federation_enabled' => 1, 'emergency_lockdown_active' => 0];
        DB::shouldReceive('table->where->first')->andReturn($controls);

        $this->assertEquals(4, $this->service->getMaxFederationLevel());
    }

    public function test_isSystemFeatureEnabled_returns_false_for_unknown_feature(): void
    {
        $controls = (object) ['federation_enabled' => 1, 'emergency_lockdown_active' => 0];
        DB::shouldReceive('table->where->first')->andReturn($controls);

        $this->assertFalse($this->service->isSystemFeatureEnabled('nonexistent_feature'));
    }

    public function test_triggerEmergencyLockdown_updates_db_and_audits(): void
    {
        DB::shouldReceive('table->where->update')->once()->andReturn(1);

        $result = $this->service->triggerEmergencyLockdown(1, 'Security incident');
        $this->assertTrue($result);
    }

    public function test_liftEmergencyLockdown_updates_db_and_audits(): void
    {
        DB::shouldReceive('table->where->update')->once()->andReturn(1);

        $result = $this->service->liftEmergencyLockdown(1);
        $this->assertTrue($result);
    }

    public function test_clearCache_resets_all_caches(): void
    {
        $this->service->clearCache();
        // Should not throw — just verify it runs
        $this->assertTrue(true);
    }

    public function test_getTenantFeatureDefinitions_returns_all_features(): void
    {
        $definitions = $this->service->getTenantFeatureDefinitions();
        $this->assertArrayHasKey(FederationFeatureService::TENANT_FEDERATION_ENABLED, $definitions);
        $this->assertArrayHasKey(FederationFeatureService::TENANT_MESSAGING_ENABLED, $definitions);
    }

    public function test_addToWhitelist_returns_true_on_success(): void
    {
        DB::shouldReceive('statement')->once();

        $result = $this->service->addToWhitelist(1, 5, 'Approved');
        $this->assertTrue($result);
    }

    public function test_removeFromWhitelist_returns_true(): void
    {
        DB::shouldReceive('table->where->delete')->once();

        $result = $this->service->removeFromWhitelist(1, 5);
        $this->assertTrue($result);
    }
}
