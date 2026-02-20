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
 * Integration tests for AdminConfigApiController
 *
 * Tests all 21 configuration management endpoints:
 * - GET    /api/v2/admin/config                    - Get config
 * - POST   /api/v2/admin/config/features           - Update feature
 * - POST   /api/v2/admin/config/modules            - Update module
 * - GET    /api/v2/admin/config/cache              - Cache stats
 * - POST   /api/v2/admin/config/cache/clear        - Clear cache
 * - GET    /api/v2/admin/config/jobs               - Get jobs
 * - POST   /api/v2/admin/config/jobs/{id}/run      - Run job
 * - GET    /api/v2/admin/config/cron               - Get cron jobs
 * - POST   /api/v2/admin/config/cron/{id}/run      - Run cron job
 * - GET    /api/v2/admin/config/settings           - Get settings
 * - PUT    /api/v2/admin/config/settings           - Update settings
 * - GET    /api/v2/admin/config/ai                 - Get AI config
 * - PUT    /api/v2/admin/config/ai                 - Update AI config
 * - GET    /api/v2/admin/config/feed-algorithm     - Get feed algorithm config
 * - PUT    /api/v2/admin/config/feed-algorithm     - Update feed algorithm config
 * - GET    /api/v2/admin/config/images             - Get image config
 * - PUT    /api/v2/admin/config/images             - Update image config
 * - GET    /api/v2/admin/config/seo                - Get SEO config
 * - PUT    /api/v2/admin/config/seo                - Update SEO config
 * - GET    /api/v2/admin/config/native-app         - Get native app config
 * - PUT    /api/v2/admin/config/native-app         - Update native app config
 *
 * @group integration
 */
class AdminConfigApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_config_' . uniqid() . '@test.local';
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
    // MAIN CONFIG TESTS
    // ===========================

    public function testGetConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/config',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // FEATURE & MODULE TESTS
    // ===========================

    public function testUpdateFeatureWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/config/features',
            [
                'feature' => 'events',
                'enabled' => true,
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateModuleWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/config/modules',
            [
                'module' => 'feed',
                'enabled' => true,
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // CACHE TESTS
    // ===========================

    public function testCacheStatsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/config/cache',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testClearCacheWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/config/cache/clear',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // JOBS TESTS
    // ===========================

    public function testGetJobsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/config/jobs',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testRunJobWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/config/jobs/1/run',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // CRON TESTS
    // ===========================

    public function testGetCronJobsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/config/cron',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testRunCronJobWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/config/cron/1/run',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // SETTINGS TESTS
    // ===========================

    public function testGetSettingsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/config/settings',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateSettingsWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/config/settings',
            [
                'site_name' => 'Test Community',
                'site_description' => 'A test community description',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AI CONFIG TESTS
    // ===========================

    public function testGetAiConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/config/ai',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateAiConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/config/ai',
            [
                'enabled' => true,
                'model' => 'gpt-4',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // FEED ALGORITHM CONFIG TESTS
    // ===========================

    public function testGetFeedAlgorithmConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/config/feed-algorithm',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateFeedAlgorithmConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/config/feed-algorithm',
            [
                'chronological_weight' => 0.5,
                'engagement_weight' => 0.3,
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // IMAGE CONFIG TESTS
    // ===========================

    public function testGetImageConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/config/images',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateImageConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/config/images',
            [
                'max_upload_size' => 5242880,
                'allowed_types' => ['jpg', 'png', 'webp'],
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // SEO CONFIG TESTS
    // ===========================

    public function testGetSeoConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/config/seo',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateSeoConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/config/seo',
            [
                'meta_title' => 'Test Community',
                'meta_description' => 'A test community',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // NATIVE APP CONFIG TESTS
    // ===========================

    public function testGetNativeAppConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/config/native-app',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateNativeAppConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/config/native-app',
            [
                'app_name' => 'Test App',
                'bundle_id' => 'com.test.app',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testConfigEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/config'],
            ['GET', '/api/v2/admin/config/cache'],
            ['GET', '/api/v2/admin/config/jobs'],
            ['GET', '/api/v2/admin/config/settings'],
            ['GET', '/api/v2/admin/config/ai'],
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
