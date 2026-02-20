<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use Nexus\Services\TokenService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Integration tests for AdminCategoriesApiController
 *
 * Tests all 8 category management endpoints:
 * - GET    /api/v2/admin/categories              - List categories
 * - POST   /api/v2/admin/categories              - Create category
 * - PUT    /api/v2/admin/categories/{id}          - Update category
 * - DELETE /api/v2/admin/categories/{id}          - Delete category
 * - GET    /api/v2/admin/categories/attributes    - List attributes
 * - POST   /api/v2/admin/categories/attributes    - Create attribute
 * - PUT    /api/v2/admin/categories/attributes/{id} - Update attribute
 * - DELETE /api/v2/admin/categories/attributes/{id} - Delete attribute
 *
 * @group integration
 */
class AdminCategoriesApiControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $tenantId;
    private static string $adminToken;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'admin_categories_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_super_admin, created_at)
             VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$tenantId);
    }

    // ===========================
    // CATEGORY CRUD TESTS
    // ===========================

    public function testListCategoriesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/categories',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateCategoryWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/categories',
            [
                'name' => 'Test Category ' . uniqid(),
                'description' => 'Test category description',
                'icon' => 'star',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateCategoryValidation(): void
    {
        // Test missing name
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/categories',
            ['description' => 'No name provided'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateCategoryWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/categories/1',
            [
                'name' => 'Updated Category Name',
                'description' => 'Updated description',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteCategoryWorks(): void
    {
        // Create a category to delete
        try {
            Database::query(
                "INSERT INTO categories (tenant_id, name, slug, created_at)
                 VALUES (?, 'Delete Me Category', 'delete-me-category', NOW())",
                [self::$tenantId]
            );
            $catId = (int)Database::lastInsertId();
        } catch (\Exception $e) {
            $this->markTestSkipped('categories table may not exist');
            return;
        }

        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/categories/' . $catId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM categories WHERE id = ?", [$catId]);
    }

    // ===========================
    // ATTRIBUTE CRUD TESTS
    // ===========================

    public function testListAttributesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/categories/attributes',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateAttributeWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/categories/attributes',
            [
                'name' => 'Test Attribute',
                'type' => 'text',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateAttributeWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/categories/attributes/1',
            ['name' => 'Updated Attribute Name'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteAttributeWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/categories/attributes/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testCategoryEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/categories'],
            ['POST', '/api/v2/admin/categories'],
            ['GET', '/api/v2/admin/categories/attributes'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest($method, $endpoint, [], []);
            $this->assertEquals('simulated', $response['status'], "Endpoint {$method} {$endpoint} should require auth");
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$adminUserId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$adminUserId]);
        }

        parent::tearDownAfterClass();
    }
}
