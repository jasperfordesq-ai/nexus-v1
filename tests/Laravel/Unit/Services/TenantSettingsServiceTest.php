<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\TenantSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TenantSettingsServiceTest extends TestCase
{
    private TenantSettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TenantSettingsService();
    }

    public function test_get_returns_default_when_key_not_found(): void
    {
        Cache::shouldReceive('remember')->andReturn([]);

        $result = $this->service->get(2, 'nonexistent_key', 'default_val');

        $this->assertEquals('default_val', $result);
    }

    public function test_getBool_returns_default_when_null(): void
    {
        Cache::shouldReceive('remember')->andReturn([]);

        $result = $this->service->getBool(2, 'nonexistent', true);

        $this->assertTrue($result);
    }

    public function test_getBool_converts_string_true(): void
    {
        Cache::shouldReceive('remember')->andReturn(['my_flag' => 'true']);

        $result = $this->service->getBool(2, 'my_flag', false);

        $this->assertTrue($result);
    }

    public function test_getBool_converts_string_false(): void
    {
        Cache::shouldReceive('remember')->andReturn(['my_flag' => 'false']);

        $result = $this->service->getBool(2, 'my_flag', true);

        $this->assertFalse($result);
    }

    public function test_isRegistrationOpen_returns_true_for_open(): void
    {
        Cache::shouldReceive('remember')->andReturn(['registration_mode' => 'open']);

        $this->assertTrue($this->service->isRegistrationOpen(2));
    }

    public function test_isRegistrationOpen_returns_false_for_closed(): void
    {
        Cache::shouldReceive('remember')->andReturn(['registration_mode' => 'closed']);

        $this->assertFalse($this->service->isRegistrationOpen(2));
    }

    public function test_checkLoginGates_returns_null_for_admin(): void
    {
        $user = ['role' => 'admin', 'tenant_id' => 2];

        $this->assertNull($this->service->checkLoginGates($user));
    }

    public function test_checkLoginGates_returns_null_for_super_admin(): void
    {
        $user = ['role' => 'member', 'is_super_admin' => true, 'tenant_id' => 2];

        $this->assertNull($this->service->checkLoginGates($user));
    }

    public function test_checkLoginGates_returns_null_for_tenant_super_admin(): void
    {
        $user = ['role' => 'member', 'is_tenant_super_admin' => true, 'tenant_id' => 2];

        $this->assertNull($this->service->checkLoginGates($user));
    }

    public function test_checkLoginGates_blocks_pending_verification(): void
    {
        $user = ['role' => 'member', 'tenant_id' => 2, 'verification_status' => 'pending'];

        $result = $this->service->checkLoginGates($user);

        $this->assertNotNull($result);
        $this->assertEquals('AUTH_PENDING_VERIFICATION', $result['code']);
    }

    public function test_checkLoginGates_blocks_failed_verification(): void
    {
        $user = ['role' => 'member', 'tenant_id' => 2, 'verification_status' => 'failed'];

        $result = $this->service->checkLoginGates($user);

        $this->assertNotNull($result);
        $this->assertEquals('AUTH_VERIFICATION_FAILED', $result['code']);
    }

    public function test_clearCacheForTenant_calls_cache_forget(): void
    {
        Cache::shouldReceive('forget')->once()->with('tenant_settings:2');

        $this->service->clearCacheForTenant(2);
    }

    public function test_set_inserts_new_setting(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);
        DB::shouldReceive('insert')->once();
        Cache::shouldReceive('forget')->once();

        $this->service->set(2, 'test_key', 'test_value');
    }

    public function test_set_updates_existing_setting(): void
    {
        DB::shouldReceive('selectOne')->andReturn((object) ['id' => 1]);
        DB::shouldReceive('update')->once();
        Cache::shouldReceive('forget')->once();

        $this->service->set(2, 'test_key', 'new_value');
    }
}
