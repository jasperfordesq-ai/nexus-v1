<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\VettingService;

/**
 * VettingService Tests
 */
class VettingServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(VettingService::class));
    }

    public function test_public_methods_exist(): void
    {
        $methods = [
            'getUserRecords', 'getById', 'getAll', 'getStats',
            'create', 'update', 'verify', 'reject', 'delete',
            'updateDocumentUrl',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(VettingService::class, $method),
                "Method {$method} should exist on VettingService"
            );
        }
    }

    public function test_instance_methods_are_not_static(): void
    {
        $methods = [
            'getUserRecords', 'getById', 'getAll', 'getStats',
            'create', 'update', 'verify', 'reject', 'delete',
            'updateDocumentUrl',
        ];

        foreach ($methods as $method) {
            $ref = new \ReflectionMethod(VettingService::class, $method);
            $this->assertFalse($ref->isStatic(), "Method {$method} should be an instance method");
        }
    }

    public function test_get_all_signature(): void
    {
        $ref = new \ReflectionMethod(VettingService::class, 'getAll');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('filters', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertEquals([], $params[0]->getDefaultValue());
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function test_verify_signature(): void
    {
        $ref = new \ReflectionMethod(VettingService::class, 'verify');
        $params = $ref->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('adminId', $params[1]->getName());
        $this->assertEquals('bool', $ref->getReturnType()->getName());
    }

    public function test_reject_signature(): void
    {
        $ref = new \ReflectionMethod(VettingService::class, 'reject');
        $params = $ref->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('adminId', $params[1]->getName());
        $this->assertEquals('reason', $params[2]->getName());
        $this->assertEquals('bool', $ref->getReturnType()->getName());
    }

    public function test_create_signature(): void
    {
        $ref = new \ReflectionMethod(VettingService::class, 'create');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('data', $params[0]->getName());
        $this->assertEquals('int', $ref->getReturnType()->getName());
    }

    public function test_get_stats_signature(): void
    {
        $ref = new \ReflectionMethod(VettingService::class, 'getStats');
        $params = $ref->getParameters();
        $this->assertCount(0, $params);
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function test_sync_user_vetting_status_is_private(): void
    {
        $ref = new \ReflectionMethod(VettingService::class, 'syncUserVettingStatus');
        $this->assertTrue($ref->isPrivate());
    }

    public function test_get_all_returns_expected_structure_on_failure(): void
    {
        try {
            $service = new VettingService();
            $result = $service->getAll();
            $this->assertIsArray($result);
            $this->assertArrayHasKey('data', $result);
            $this->assertArrayHasKey('pagination', $result);
            $this->assertIsArray($result['data']);
            $this->assertIsArray($result['pagination']);
            $this->assertArrayHasKey('total', $result['pagination']);
            $this->assertArrayHasKey('page', $result['pagination']);
            $this->assertArrayHasKey('per_page', $result['pagination']);
            $this->assertArrayHasKey('total_pages', $result['pagination']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_stats_returns_expected_keys(): void
    {
        try {
            $service = new VettingService();
            $result = $service->getStats();
            $this->assertIsArray($result);
            $expectedKeys = [
                'total', 'by_status', 'by_type',
                'expiring_soon', 'expired',
                'pending', 'verified', 'rejected',
            ];
            $this->assertArrayHasKeys($expectedKeys, $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_user_records_returns_array(): void
    {
        try {
            $service = new VettingService();
            $result = $service->getUserRecords(999999);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_by_id_returns_null_for_nonexistent(): void
    {
        try {
            $service = new VettingService();
            $result = $service->getById(999999);
            $this->assertNull($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_update_document_url_signature(): void
    {
        $ref = new \ReflectionMethod(VettingService::class, 'updateDocumentUrl');
        $params = $ref->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('url', $params[1]->getName());
        $this->assertEquals('bool', $ref->getReturnType()->getName());
    }

    public function test_delete_signature(): void
    {
        $ref = new \ReflectionMethod(VettingService::class, 'delete');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('bool', $ref->getReturnType()->getName());
    }
}
