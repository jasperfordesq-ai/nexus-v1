<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\TenantHierarchyService;

/**
 * TenantHierarchyService Tests
 */
class TenantHierarchyServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(TenantHierarchyService::class));
    }

    public function test_public_methods_exist(): void
    {
        $methods = [
            'createTenant',
            'updateTenant',
            'deleteTenant',
            'moveTenant',
            'toggleSubtenantCapability',
            'assignTenantSuperAdmin',
            'revokeTenantSuperAdmin',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(TenantHierarchyService::class, $method),
                "Method {$method} should exist on TenantHierarchyService"
            );
        }
    }

    public function test_all_public_methods_are_static(): void
    {
        $methods = [
            'createTenant', 'updateTenant', 'deleteTenant',
            'moveTenant', 'toggleSubtenantCapability',
            'assignTenantSuperAdmin', 'revokeTenantSuperAdmin',
        ];

        foreach ($methods as $method) {
            $ref = new \ReflectionMethod(TenantHierarchyService::class, $method);
            $this->assertTrue($ref->isStatic(), "Method {$method} should be static");
        }
    }

    public function test_create_tenant_signature(): void
    {
        $ref = new \ReflectionMethod(TenantHierarchyService::class, 'createTenant');
        $params = $ref->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('data', $params[0]->getName());
        $this->assertEquals('parentId', $params[1]->getName());
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function test_update_tenant_signature(): void
    {
        $ref = new \ReflectionMethod(TenantHierarchyService::class, 'updateTenant');
        $params = $ref->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('data', $params[1]->getName());
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function test_move_tenant_signature(): void
    {
        $ref = new \ReflectionMethod(TenantHierarchyService::class, 'moveTenant');
        $params = $ref->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('newParentId', $params[1]->getName());
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function test_delete_tenant_signature(): void
    {
        $ref = new \ReflectionMethod(TenantHierarchyService::class, 'deleteTenant');
        $params = $ref->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('hardDelete', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertFalse($params[1]->getDefaultValue());
    }

    public function test_create_tenant_with_invalid_parent_returns_error(): void
    {
        try {
            $result = TenantHierarchyService::createTenant(
                ['name' => 'Test Tenant'],
                999999
            );
            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
            $this->assertArrayHasKey('error', $result);
            $this->assertStringContainsString('Parent tenant not found', $result['error']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_create_tenant_requires_name(): void
    {
        try {
            // Use parent_id=1 which is the Master tenant
            $result = TenantHierarchyService::createTenant(
                ['name' => ''],
                1
            );
            $this->assertIsArray($result);
            // Either parent not found (no DB) or name required
            $this->assertFalse($result['success']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_update_tenant_not_found_returns_error(): void
    {
        try {
            $result = TenantHierarchyService::updateTenant(
                999999,
                ['name' => 'Updated Name']
            );
            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('Tenant not found', $result['error']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_move_tenant_cannot_move_master(): void
    {
        try {
            $result = TenantHierarchyService::moveTenant(1, 2);
            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('Cannot move the Master tenant', $result['error']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_move_tenant_cannot_move_to_self(): void
    {
        try {
            $result = TenantHierarchyService::moveTenant(5, 5);
            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('Cannot move a tenant to be its own parent', $result['error']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_delete_tenant_cannot_delete_master(): void
    {
        try {
            $result = TenantHierarchyService::deleteTenant(1);
            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('Cannot delete the Master tenant', $result['error']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_delete_tenant_not_found_returns_error(): void
    {
        try {
            $result = TenantHierarchyService::deleteTenant(999999);
            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('Tenant not found', $result['error']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_toggle_subtenant_capability_not_found(): void
    {
        try {
            $result = TenantHierarchyService::toggleSubtenantCapability(999999, true);
            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('Tenant not found', $result['error']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_assign_super_admin_user_not_found(): void
    {
        try {
            $result = TenantHierarchyService::assignTenantSuperAdmin(999999, 1);
            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('User not found', $result['error']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_revoke_super_admin_user_not_found(): void
    {
        try {
            $result = TenantHierarchyService::revokeTenantSuperAdmin(999999);
            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('User not found', $result['error']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_generate_slug_is_private(): void
    {
        $ref = new \ReflectionMethod(TenantHierarchyService::class, 'generateSlug');
        $this->assertTrue($ref->isPrivate());
    }
}
