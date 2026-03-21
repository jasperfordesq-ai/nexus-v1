<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\MemberVerificationBadgeService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MemberVerificationBadgeServiceTest extends TestCase
{
    private MemberVerificationBadgeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MemberVerificationBadgeService();
    }

    public function test_badge_types_constant(): void
    {
        $this->assertContains('email_verified', MemberVerificationBadgeService::BADGE_TYPES);
        $this->assertContains('admin_verified', MemberVerificationBadgeService::BADGE_TYPES);
        $this->assertCount(5, MemberVerificationBadgeService::BADGE_TYPES);
    }

    public function test_grantBadge_invalid_type_returns_null(): void
    {
        $result = $this->service->grantBadge(1, 'invalid_type', 5);
        $this->assertNull($result);
        $this->assertNotEmpty($this->service->getErrors());
        $this->assertSame('INVALID_TYPE', $this->service->getErrors()[0]['code']);
    }

    public function test_grantBadge_user_not_found_returns_null(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = $this->service->grantBadge(999, 'email_verified', 5);
        $this->assertNull($result);
        $this->assertSame('NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_grantBadge_already_active_returns_existing_id(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['id' => 1, 'first_name' => 'Test', 'last_name' => 'User']);

        DB::shouldReceive('table')->with('member_verification_badges')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['id' => 10, 'revoked_at' => null]);

        $result = $this->service->grantBadge(1, 'email_verified', 5);
        $this->assertSame(10, $result);
        $this->assertSame('ALREADY_GRANTED', $this->service->getErrors()[0]['code']);
    }

    public function test_revokeBadge_returns_true(): void
    {
        DB::shouldReceive('table')->with('member_verification_badges')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(1);

        $this->assertTrue($this->service->revokeBadge(1, 'email_verified', 5));
    }

    public function test_getUserBadges_returns_array(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['badge_type' => 'email_verified', 'granted_at' => now(), 'expires_at' => null, 'verified_by_name' => 'Admin'],
        ]));

        $result = $this->service->getUserBadges(1);
        $this->assertCount(1, $result);
        $this->assertSame('Email Verified', $result[0]['label']);
        $this->assertSame('mail-check', $result[0]['icon']);
    }

    public function test_getBatchUserBadges_empty_returns_empty(): void
    {
        $this->assertSame([], $this->service->getBatchUserBadges([]));
    }

    public function test_getBatchUserBadges_groups_by_user(): void
    {
        DB::shouldReceive('table')->with('member_verification_badges')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['user_id' => 1, 'badge_type' => 'email_verified'],
            (object) ['user_id' => 1, 'badge_type' => 'phone_verified'],
            (object) ['user_id' => 2, 'badge_type' => 'id_verified'],
        ]));

        $result = $this->service->getBatchUserBadges([1, 2]);
        $this->assertCount(2, $result[1]);
        $this->assertCount(1, $result[2]);
    }
}
