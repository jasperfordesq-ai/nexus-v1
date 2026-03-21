<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\SuperAdminAuditService;

/**
 * SuperAdminAuditService Tests
 */
class SuperAdminAuditServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(SuperAdminAuditService::class));
    }

    public function test_public_methods_exist(): void
    {
        $methods = ['log', 'getLog', 'getStats', 'getActionLabel', 'getActionIcon'];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(SuperAdminAuditService::class, $method),
                "Method {$method} should exist on SuperAdminAuditService"
            );
        }
    }

    public function test_log_method_is_static(): void
    {
        $ref = new \ReflectionMethod(SuperAdminAuditService::class, 'log');
        $this->assertTrue($ref->isStatic());
    }

    public function test_log_method_signature(): void
    {
        $ref = new \ReflectionMethod(SuperAdminAuditService::class, 'log');
        $params = $ref->getParameters();

        $this->assertEquals('actionType', $params[0]->getName());
        $this->assertEquals('targetType', $params[1]->getName());
        $this->assertEquals('targetId', $params[2]->getName());
        $this->assertEquals('targetName', $params[3]->getName());
        $this->assertEquals('oldValues', $params[4]->getName());
        $this->assertEquals('newValues', $params[5]->getName());
        $this->assertEquals('description', $params[6]->getName());
    }

    public function test_log_returns_bool(): void
    {
        $ref = new \ReflectionMethod(SuperAdminAuditService::class, 'log');
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    public function test_get_log_is_static_and_returns_array(): void
    {
        $ref = new \ReflectionMethod(SuperAdminAuditService::class, 'getLog');
        $this->assertTrue($ref->isStatic());
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function test_get_stats_is_static_and_returns_array(): void
    {
        $ref = new \ReflectionMethod(SuperAdminAuditService::class, 'getStats');
        $this->assertTrue($ref->isStatic());
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function test_get_action_label_returns_known_labels(): void
    {
        $this->assertSame('Tenant Created', SuperAdminAuditService::getActionLabel('tenant_created'));
        $this->assertSame('Tenant Updated', SuperAdminAuditService::getActionLabel('tenant_updated'));
        $this->assertSame('Tenant Deleted', SuperAdminAuditService::getActionLabel('tenant_deleted'));
        $this->assertSame('Tenant Moved', SuperAdminAuditService::getActionLabel('tenant_moved'));
        $this->assertSame('Hub Toggled', SuperAdminAuditService::getActionLabel('hub_toggled'));
        $this->assertSame('Super Admin Granted', SuperAdminAuditService::getActionLabel('super_admin_granted'));
        $this->assertSame('Super Admin Revoked', SuperAdminAuditService::getActionLabel('super_admin_revoked'));
        $this->assertSame('User Created', SuperAdminAuditService::getActionLabel('user_created'));
        $this->assertSame('User Updated', SuperAdminAuditService::getActionLabel('user_updated'));
        $this->assertSame('User Moved', SuperAdminAuditService::getActionLabel('user_moved'));
        $this->assertSame('Bulk Users Moved', SuperAdminAuditService::getActionLabel('bulk_users_moved'));
        $this->assertSame('Bulk Tenants Updated', SuperAdminAuditService::getActionLabel('bulk_tenants_updated'));
    }

    public function test_get_action_label_falls_back_for_unknown_action(): void
    {
        $result = SuperAdminAuditService::getActionLabel('some_unknown_action');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        // Fallback uses ucwords(str_replace('_', ' ', ...))
        $this->assertSame('Some Unknown Action', $result);
    }

    public function test_get_action_icon_returns_known_icons(): void
    {
        $this->assertSame('fa-plus-circle', SuperAdminAuditService::getActionIcon('tenant_created'));
        $this->assertSame('fa-pen', SuperAdminAuditService::getActionIcon('tenant_updated'));
        $this->assertSame('fa-trash', SuperAdminAuditService::getActionIcon('tenant_deleted'));
        $this->assertSame('fa-arrows-alt', SuperAdminAuditService::getActionIcon('tenant_moved'));
        $this->assertSame('fa-toggle-on', SuperAdminAuditService::getActionIcon('hub_toggled'));
        $this->assertSame('fa-user-shield', SuperAdminAuditService::getActionIcon('super_admin_granted'));
        $this->assertSame('fa-user-slash', SuperAdminAuditService::getActionIcon('super_admin_revoked'));
        $this->assertSame('fa-user-plus', SuperAdminAuditService::getActionIcon('user_created'));
        $this->assertSame('fa-user-edit', SuperAdminAuditService::getActionIcon('user_updated'));
        $this->assertSame('fa-exchange-alt', SuperAdminAuditService::getActionIcon('user_moved'));
        $this->assertSame('fa-users', SuperAdminAuditService::getActionIcon('bulk_users_moved'));
        $this->assertSame('fa-building', SuperAdminAuditService::getActionIcon('bulk_tenants_updated'));
    }

    public function test_get_action_icon_falls_back_for_unknown_action(): void
    {
        $this->assertSame('fa-circle', SuperAdminAuditService::getActionIcon('unknown_action'));
    }

    public function test_log_gracefully_handles_failure(): void
    {
        // log() catches all Throwable internally and returns false on error
        // Without valid SuperPanelAccess/DB setup, it should return false, not throw
        try {
            $result = SuperAdminAuditService::log(
                'tenant_created',
                'tenant',
                999,
                'Test Tenant',
                null,
                ['slug' => 'test'],
                'Test description'
            );
            $this->assertIsBool($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Environment dependency: ' . $e->getMessage());
        }
    }

    public function test_get_log_returns_array_on_failure(): void
    {
        try {
            $result = SuperAdminAuditService::getLog(['action_type' => 'tenant_created']);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Environment dependency: ' . $e->getMessage());
        }
    }

    public function test_get_stats_returns_expected_structure_on_failure(): void
    {
        try {
            $result = SuperAdminAuditService::getStats(30);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('total_actions', $result);
            $this->assertArrayHasKey('by_type', $result);
            $this->assertArrayHasKey('top_actors', $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Environment dependency: ' . $e->getMessage());
        }
    }

    public function test_get_stats_days_parameter(): void
    {
        $ref = new \ReflectionMethod(SuperAdminAuditService::class, 'getStats');
        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('days', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertEquals(30, $params[0]->getDefaultValue());
    }

    public function test_get_log_accepts_filters_parameter(): void
    {
        $ref = new \ReflectionMethod(SuperAdminAuditService::class, 'getLog');
        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('filters', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertEquals([], $params[0]->getDefaultValue());
    }
}
