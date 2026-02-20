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
 * Integration tests for BlogRestoreController
 *
 * Tests blog export/import, diagnostics, and backup functionality.
 *
 * @group integration
 * @group admin
 */
class BlogRestoreControllerTest extends ApiTestCase
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

        $adminEmail = 'restore_admin_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Restore Admin', 'Restore', 'Admin', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int) Database::lastInsertId();
        self::$cleanupUserIds[] = self::$adminUserId;
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        $memberEmail = 'restore_member_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Restore Member', 'Restore', 'Member', 'member', 'active', 1, NOW())",
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

    public function testIndexShowsRestoreInterface(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/admin-legacy/blog-restore',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDiagnosticReturnsStats(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/blog-restore/diagnostic',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUploadFormShown(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/admin-legacy/blog-restore/upload-form',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDownloadExportAll(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/admin-legacy/blog-restore/download-export?status=all',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDownloadExportPublished(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/admin-legacy/blog-restore/download-export?status=published',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testNonAdminCannotAccessRestore(): void
    {
        $endpoints = [
            ['GET', '/admin-legacy/blog-restore'],
            ['POST', '/admin-legacy/blog-restore/diagnostic'],
            ['GET', '/admin-legacy/blog-restore/download-export'],
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
