<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupInviteService;
use Illuminate\Support\Facades\DB;

class GroupInviteServiceTest extends TestCase
{
    private GroupInviteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupInviteService();
    }

    public function test_createLink_returns_null_when_user_cannot_invite(): void
    {
        // canInvite checks group_members joined with groups — mock the chain to return false
        DB::shouldReceive('table->join->where->where->where->where->exists')
            ->once()
            ->andReturn(false);

        $result = $this->service->createLink(1, 99);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('FORBIDDEN', $errors[0]['code']);
    }

    public function test_createLink_returns_invite_data_on_success(): void
    {
        // canInvite returns true
        DB::shouldReceive('table->join->where->where->where->where->exists')
            ->once()
            ->andReturn(true);

        // insertGetId for the invite
        DB::shouldReceive('table->insertGetId')
            ->once()
            ->andReturn(42);

        $result = $this->service->createLink(1, 10);

        $this->assertIsArray($result);
        $this->assertEquals(42, $result['id']);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('invite_url', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertEquals(40, strlen($result['token']));
    }

    public function test_acceptInvite_returns_null_when_token_not_found(): void
    {
        DB::shouldReceive('table->where->where->where->first')
            ->once()
            ->andReturn(null);

        $result = $this->service->acceptInvite('invalid-token', 5);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('NOT_FOUND', $errors[0]['code']);
    }

    public function test_revokeInvite_returns_true_on_success(): void
    {
        DB::shouldReceive('table->where->where->where->update')
            ->once()
            ->andReturn(1);

        $result = $this->service->revokeInvite(10, 5);

        $this->assertTrue($result);
        $this->assertEmpty($this->service->getErrors());
    }

    public function test_revokeInvite_returns_false_when_invite_not_found(): void
    {
        DB::shouldReceive('table->where->where->where->update')
            ->once()
            ->andReturn(0);

        $result = $this->service->revokeInvite(999, 5);

        $this->assertFalse($result);
        $errors = $this->service->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('NOT_FOUND', $errors[0]['code']);
    }

    public function test_sendEmailInvites_returns_empty_when_user_cannot_invite(): void
    {
        DB::shouldReceive('table->join->where->where->where->where->exists')
            ->once()
            ->andReturn(false);

        $result = $this->service->sendEmailInvites(1, 99, ['test@example.com']);

        $this->assertEmpty($result);
        $errors = $this->service->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('FORBIDDEN', $errors[0]['code']);
    }

    public function test_status_constants_are_defined(): void
    {
        $this->assertEquals('pending', GroupInviteService::STATUS_PENDING);
        $this->assertEquals('accepted', GroupInviteService::STATUS_ACCEPTED);
        $this->assertEquals('expired', GroupInviteService::STATUS_EXPIRED);
        $this->assertEquals('revoked', GroupInviteService::STATUS_REVOKED);
    }
}
