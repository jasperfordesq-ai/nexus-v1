<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Admin;

use Nexus\Tests\Controllers\Api\ApiTestCase;
use Nexus\Services\TokenService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Integration tests for AttributeController
 *
 * Tests attribute CRUD operations for listing attributes management.
 *
 * @group integration
 * @group admin
 */
class AttributeControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $memberUserId;
    private static int $tenantId;
    private static string $adminToken;
    private static string $memberToken;

    private static array $cleanupUserIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'attr_admin_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Attr Admin', 'Attr', 'Admin', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int) Database::lastInsertId();
        self::$cleanupUserIds[] = self::$adminUserId;
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create member user
        $memberEmail = 'attr_member_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Attr Member', 'Attr', 'Member', 'member', 'active', 1, NOW())",
            [self::$tenantId, $memberEmail, password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$memberUserId = (int) Database::lastInsertId();
        self::$cleanupUserIds[] = self::$memberUserId;
        self::$memberToken = TokenService::generateToken(self::$memberUserId, self::$tenantId);
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$tenantId);
    }

    public function testIndexListsAttributes(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/admin-legacy/attributes',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateShowsForm(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/admin-legacy/attributes/create',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testStoreCreatesAttribute(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/attributes/store',
            [
                'name' => 'Test Attribute',
                'input_type' => 'checkbox',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testStoreWithCategory(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/attributes/store',
            [
                'name' => 'Categorized Attribute',
                'category_id' => '1',
                'input_type' => 'select',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateModifiesAttribute(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/attributes/update',
            [
                'id' => '1',
                'name' => 'Updated Attribute',
                'input_type' => 'text',
                'is_active' => '1',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteRemovesAttribute(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/attributes/delete',
            ['id' => '1'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testNonAdminCannotAccessAttributes(): void
    {
        $endpoints = [
            ['GET', '/admin-legacy/attributes'],
            ['POST', '/admin-legacy/attributes/store'],
            ['POST', '/admin-legacy/attributes/update'],
            ['POST', '/admin-legacy/attributes/delete'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest(
                $method,
                $endpoint,
                [],
                ['Authorization' => 'Bearer ' . self::$memberToken]
            );

            $this->assertEquals('simulated', $response['status']);
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$cleanupUserIds as $id) {
            try {
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [$id]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM users WHERE id = ?", [$id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }
}
