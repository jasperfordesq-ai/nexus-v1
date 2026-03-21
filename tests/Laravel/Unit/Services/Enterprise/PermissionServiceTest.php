<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Enterprise;

use Tests\Laravel\TestCase;
use App\Services\Enterprise\PermissionService;
use Illuminate\Support\Facades\DB;
use Mockery;

class PermissionServiceTest extends TestCase
{
    private PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear session cache
        $_SESSION = [];
        $this->service = new PermissionService();
    }

    public function test_canAll_returns_false_when_any_permission_denied(): void
    {
        // Mock isSuperAdmin to return false
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(null, false, false, false, false, false);

        $result = $this->service->canAll(999, ['users.delete', 'admin.access']);
        $this->assertFalse($result);
    }

    public function test_canAny_returns_false_when_all_denied(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(null, false, false, false, false, false);

        $result = $this->service->canAny(999, ['users.delete', 'admin.access']);
        $this->assertFalse($result);
    }

    public function test_clearUserPermissionCache_clears_session(): void
    {
        $_SESSION['perm_1_users.read'] = true;
        $_SESSION['perm_1_users.write'] = false;
        $_SESSION['other_key'] = 'preserved';

        DB::shouldReceive('statement')->andReturnSelf();

        $this->service->clearUserPermissionCache(1);

        $this->assertArrayNotHasKey('perm_1_users.read', $_SESSION);
        $this->assertArrayNotHasKey('perm_1_users.write', $_SESSION);
        $this->assertArrayHasKey('other_key', $_SESSION);
    }

    public function test_disableAudit_and_enableAudit(): void
    {
        $this->service->disableAudit();
        $this->service->enableAudit();
        $this->assertTrue(true); // No exception
    }

    public function test_result_constants(): void
    {
        $this->assertEquals('granted', PermissionService::RESULT_GRANTED);
        $this->assertEquals('denied', PermissionService::RESULT_DENIED);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }
}
