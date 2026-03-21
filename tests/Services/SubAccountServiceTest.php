<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\SubAccountService;
use App\Services\MemberActivityService;
use App\Models\AccountRelationship;

class SubAccountServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SubAccountService::class));
    }

    public function testRelationshipTypesConstant(): void
    {
        $expected = ['family', 'guardian', 'carer', 'organization'];
        $this->assertEquals($expected, SubAccountService::RELATIONSHIP_TYPES);
    }

    public function testDefaultPermissionsConstant(): void
    {
        $perms = SubAccountService::DEFAULT_PERMISSIONS;
        $this->assertIsArray($perms);
        $this->assertArrayHasKey('can_view_activity', $perms);
        $this->assertArrayHasKey('can_manage_listings', $perms);
        $this->assertArrayHasKey('can_transact', $perms);
        $this->assertArrayHasKey('can_view_messages', $perms);
        $this->assertTrue($perms['can_view_activity']);
        $this->assertFalse($perms['can_manage_listings']);
        $this->assertFalse($perms['can_transact']);
        $this->assertFalse($perms['can_view_messages']);
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void
    {
        $service = $this->createService();
        $this->assertIsArray($service->getErrors());
        $this->assertEmpty($service->getErrors());
    }

    public function testRequestRelationshipRejectsSelfRelationship(): void
    {
        $service = $this->createService();
        $result = $service->requestRelationship(1, 1, 'family');
        $this->assertNull($result);

        $errors = $service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('SELF_RELATIONSHIP', $errors[0]['code']);
    }

    public function testRequestRelationshipRejectsInvalidType(): void
    {
        $service = $this->createService();
        $result = $service->requestRelationship(1, 2, 'invalid_type');
        $this->assertNull($result);

        $errors = $service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('INVALID_TYPE', $errors[0]['code']);
    }

    public function testAllPublicMethodsExist(): void
    {
        $methods = [
            'getErrors', 'getChildren', 'getChildAccounts', 'getParentAccounts',
            'requestRelationship', 'approve', 'approveRelationship',
            'revoke', 'revokeRelationship', 'updatePermissions',
            'hasPermission', 'getChildActivitySummary',
        ];
        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(SubAccountService::class, $method),
                "Method {$method} should exist"
            );
        }
    }

    public function testApproveRelationshipIsAliasForApprove(): void
    {
        // Both methods should exist and have same return type
        $ref1 = new \ReflectionMethod(SubAccountService::class, 'approve');
        $ref2 = new \ReflectionMethod(SubAccountService::class, 'approveRelationship');
        $this->assertEquals($ref1->getReturnType()->getName(), $ref2->getReturnType()->getName());
    }

    public function testRevokeRelationshipIsAliasForRevoke(): void
    {
        $ref1 = new \ReflectionMethod(SubAccountService::class, 'revoke');
        $ref2 = new \ReflectionMethod(SubAccountService::class, 'revokeRelationship');
        $this->assertEquals($ref1->getReturnType()->getName(), $ref2->getReturnType()->getName());
    }

    private function createService(): SubAccountService
    {
        $activityService = $this->createMock(MemberActivityService::class);
        return new SubAccountService(new AccountRelationship(), $activityService);
    }
}
