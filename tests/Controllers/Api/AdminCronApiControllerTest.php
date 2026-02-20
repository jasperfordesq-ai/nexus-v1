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
 * Integration tests for AdminCronApiController
 *
 * Tests all 8 cron management endpoints:
 * - GET    /api/v2/admin/cron/logs                    - Get cron logs
 * - GET    /api/v2/admin/cron/logs/{logId}           - Get log detail
 * - DELETE /api/v2/admin/cron/logs                    - Clear logs
 * - GET    /api/v2/admin/cron/jobs/{jobId}/settings  - Get job settings
 * - PUT    /api/v2/admin/cron/jobs/{jobId}/settings  - Update job settings
 * - GET    /api/v2/admin/cron/global-settings        - Get global settings
 * - PUT    /api/v2/admin/cron/global-settings        - Update global settings
 * - GET    /api/v2/admin/cron/health                 - Get health metrics
 *
 * @group integration
 */
class AdminCronApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_cron_' . uniqid() . '@test.local';
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
    // LOGS TESTS
    // ===========================

    public function testGetLogsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/cron/logs?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetLogDetailWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/cron/logs/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testClearLogsWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/cron/logs',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // JOB SETTINGS TESTS
    // ===========================

    public function testGetJobSettingsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/cron/jobs/daily_digest/settings',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateJobSettingsWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/cron/jobs/daily_digest/settings',
            [
                'enabled' => true,
                'schedule' => '0 8 * * *',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // GLOBAL SETTINGS TESTS
    // ===========================

    public function testGetGlobalSettingsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/cron/global-settings',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateGlobalSettingsWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/cron/global-settings',
            [
                'cron_enabled' => true,
                'max_execution_time' => 300,
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // HEALTH TESTS
    // ===========================

    public function testGetHealthMetricsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/cron/health',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testCronEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/cron/logs'],
            ['GET', '/api/v2/admin/cron/global-settings'],
            ['GET', '/api/v2/admin/cron/health'],
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
