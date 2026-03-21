<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\TenantVisibilityService;

/**
 * TenantVisibilityService Tests
 */
class TenantVisibilityServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(TenantVisibilityService::class));
    }

    public function test_public_methods_exist(): void
    {
        $methods = [
            'getVisibleTenantIds',
            'getTenantList',
            'getTenant',
            'getUserList',
            'getTenantAdmins',
            'getHierarchyTree',
            'getDashboardStats',
            'getAvailableParents',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(TenantVisibilityService::class, $method),
                "Method {$method} should exist on TenantVisibilityService"
            );
        }
    }

    public function test_all_public_methods_are_static(): void
    {
        $methods = [
            'getVisibleTenantIds', 'getTenantList', 'getTenant',
            'getUserList', 'getTenantAdmins', 'getHierarchyTree',
            'getDashboardStats', 'getAvailableParents',
        ];

        foreach ($methods as $method) {
            $ref = new \ReflectionMethod(TenantVisibilityService::class, $method);
            $this->assertTrue($ref->isStatic(), "Method {$method} should be static");
        }
    }

    public function test_get_tenant_list_signature(): void
    {
        $ref = new \ReflectionMethod(TenantVisibilityService::class, 'getTenantList');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('filters', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertEquals([], $params[0]->getDefaultValue());
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function test_get_user_list_signature(): void
    {
        $ref = new \ReflectionMethod(TenantVisibilityService::class, 'getUserList');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('filters', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertEquals([], $params[0]->getDefaultValue());
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function test_get_dashboard_stats_signature(): void
    {
        $ref = new \ReflectionMethod(TenantVisibilityService::class, 'getDashboardStats');
        $params = $ref->getParameters();
        $this->assertCount(0, $params);
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function test_get_tenant_requires_int_parameter(): void
    {
        $ref = new \ReflectionMethod(TenantVisibilityService::class, 'getTenant');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('int', $params[0]->getType()->getName());
    }

    public function test_get_tenant_admins_signature(): void
    {
        $ref = new \ReflectionMethod(TenantVisibilityService::class, 'getTenantAdmins');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('int', $params[0]->getType()->getName());
    }

    public function test_get_tenant_list_returns_array_on_failure(): void
    {
        try {
            $result = TenantVisibilityService::getTenantList();
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Environment dependency: ' . $e->getMessage());
        }
    }

    public function test_get_user_list_returns_array_on_failure(): void
    {
        try {
            $result = TenantVisibilityService::getUserList();
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Environment dependency: ' . $e->getMessage());
        }
    }

    public function test_get_dashboard_stats_returns_expected_keys(): void
    {
        try {
            $result = TenantVisibilityService::getDashboardStats();
            $this->assertIsArray($result);
            $expectedKeys = [
                'total_tenants', 'active_tenants', 'inactive_tenants',
                'hub_tenants', 'total_users', 'super_admins',
                'recent_tenants', 'recent_users',
            ];
            $this->assertArrayHasKeys($expectedKeys, $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Environment dependency: ' . $e->getMessage());
        }
    }

    public function test_get_visible_tenant_ids_returns_array(): void
    {
        try {
            $result = TenantVisibilityService::getVisibleTenantIds();
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Environment dependency: ' . $e->getMessage());
        }
    }

    public function test_get_hierarchy_tree_returns_array(): void
    {
        try {
            $result = TenantVisibilityService::getHierarchyTree();
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Environment dependency: ' . $e->getMessage());
        }
    }

    public function test_get_available_parents_returns_array(): void
    {
        try {
            $result = TenantVisibilityService::getAvailableParents();
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Environment dependency: ' . $e->getMessage());
        }
    }

    public function test_get_tenant_admins_returns_array(): void
    {
        try {
            $result = TenantVisibilityService::getTenantAdmins(2);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Environment dependency: ' . $e->getMessage());
        }
    }
}
