<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\MemberVerificationBadgeService;

class MemberVerificationBadgeServiceTest extends TestCase
{
    private MemberVerificationBadgeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MemberVerificationBadgeService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(MemberVerificationBadgeService::class));
    }

    public function testBadgeTypesConstant(): void
    {
        $this->assertIsArray(MemberVerificationBadgeService::BADGE_TYPES);
        $this->assertContains('email_verified', MemberVerificationBadgeService::BADGE_TYPES);
        $this->assertContains('phone_verified', MemberVerificationBadgeService::BADGE_TYPES);
        $this->assertContains('id_verified', MemberVerificationBadgeService::BADGE_TYPES);
        $this->assertContains('address_verified', MemberVerificationBadgeService::BADGE_TYPES);
        $this->assertContains('admin_verified', MemberVerificationBadgeService::BADGE_TYPES);
        $this->assertCount(5, MemberVerificationBadgeService::BADGE_TYPES);
    }

    public function testBadgeLabelsConstant(): void
    {
        $this->assertIsArray(MemberVerificationBadgeService::BADGE_LABELS);
        $this->assertEquals('Email Verified', MemberVerificationBadgeService::BADGE_LABELS['email_verified']);
        $this->assertEquals('Phone Verified', MemberVerificationBadgeService::BADGE_LABELS['phone_verified']);
        $this->assertEquals('ID Verified', MemberVerificationBadgeService::BADGE_LABELS['id_verified']);
        $this->assertEquals('Address Verified', MemberVerificationBadgeService::BADGE_LABELS['address_verified']);
        $this->assertEquals('Admin Verified', MemberVerificationBadgeService::BADGE_LABELS['admin_verified']);
    }

    public function testBadgeIconsConstant(): void
    {
        $this->assertIsArray(MemberVerificationBadgeService::BADGE_ICONS);
        $this->assertEquals('mail-check', MemberVerificationBadgeService::BADGE_ICONS['email_verified']);
        $this->assertEquals('phone-check', MemberVerificationBadgeService::BADGE_ICONS['phone_verified']);
        $this->assertEquals('shield-check', MemberVerificationBadgeService::BADGE_ICONS['id_verified']);
        $this->assertEquals('badge-check', MemberVerificationBadgeService::BADGE_ICONS['address_verified']);
        $this->assertEquals('user-check', MemberVerificationBadgeService::BADGE_ICONS['admin_verified']);
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void
    {
        $this->assertIsArray($this->service->getErrors());
        $this->assertEmpty($this->service->getErrors());
    }

    public function testGrantBadgeRejectsInvalidBadgeType(): void
    {
        $result = $this->service->grantBadge(1, 'invalid_type', 1);
        $this->assertNull($result);

        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('INVALID_TYPE', $errors[0]['code']);
    }

    public function testGetBatchUserBadgesReturnsEmptyForEmptyInput(): void
    {
        $result = $this->service->getBatchUserBadges([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testAllPublicMethodsExist(): void
    {
        $methods = ['grantBadge', 'revokeBadge', 'getUserBadges', 'getBatchUserBadges', 'getAdminBadgeList', 'getErrors'];
        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(MemberVerificationBadgeService::class, $method),
                "Method {$method} should exist"
            );
        }
    }
}
