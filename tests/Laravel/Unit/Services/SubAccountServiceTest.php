<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SubAccountService;
use App\Services\MemberActivityService;
use App\Models\AccountRelationship;
use App\Models\User;
use Mockery;

class SubAccountServiceTest extends TestCase
{
    private SubAccountService $service;
    private $mockRelationship;
    private $mockActivity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRelationship = Mockery::mock(AccountRelationship::class);
        $this->mockActivity = Mockery::mock(MemberActivityService::class);
        $this->service = new SubAccountService($this->mockRelationship, $this->mockActivity);
    }

    // ── constants ──

    public function test_relationship_types_constant(): void
    {
        $this->assertEquals(['family', 'guardian', 'carer', 'organization'], SubAccountService::RELATIONSHIP_TYPES);
    }

    public function test_default_permissions_constant(): void
    {
        $perms = SubAccountService::DEFAULT_PERMISSIONS;
        $this->assertTrue($perms['can_view_activity']);
        $this->assertFalse($perms['can_manage_listings']);
        $this->assertFalse($perms['can_transact']);
        $this->assertFalse($perms['can_view_messages']);
    }

    // ── requestRelationship ──

    public function test_requestRelationship_rejects_self(): void
    {
        $result = $this->service->requestRelationship(1, 1);
        $this->assertNull($result);
        $this->assertEquals('SELF_RELATIONSHIP', $this->service->getErrors()[0]['code']);
    }

    public function test_requestRelationship_rejects_invalid_type(): void
    {
        $result = $this->service->requestRelationship(1, 2, 'invalid');
        $this->assertNull($result);
        $this->assertEquals('INVALID_TYPE', $this->service->getErrors()[0]['code']);
    }

    public function test_requestRelationship_fails_when_user_not_found(): void
    {
        // User::query()->where('id', X)->first() returns null
        $result = $this->service->requestRelationship(9999, 9998, 'family');
        $this->assertNull($result);
    }

    // ── approve ──

    public function test_approve_returns_false_when_not_found(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('update')->andReturn(0);
        $this->mockRelationship->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->approve(999, 1);
        $this->assertFalse($result);
    }

    // ── revoke ──

    public function test_revoke_returns_false_when_not_found(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('update')->andReturn(0);
        $this->mockRelationship->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->revoke(999, 1);
        $this->assertFalse($result);
    }

    // ── hasPermission ──

    public function test_hasPermission_returns_false_when_no_relationship(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturnNull();
        $this->mockRelationship->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->hasPermission(1, 2, 'can_view_activity');
        $this->assertFalse($result);
    }

    // ── updatePermissions ──

    public function test_updatePermissions_fails_when_not_found(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturnNull();
        $this->mockRelationship->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->updatePermissions(1, 999, ['can_transact' => true]);
        $this->assertFalse($result);
        $this->assertEquals('NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    // ── getChildActivitySummary ──

    public function test_getChildActivitySummary_returns_null_without_permission(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturnNull();
        $this->mockRelationship->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getChildActivitySummary(1, 2);
        $this->assertNull($result);
    }

    // ── getErrors ──

    public function test_getErrors_initially_empty(): void
    {
        $this->assertEquals([], $this->service->getErrors());
    }
}
