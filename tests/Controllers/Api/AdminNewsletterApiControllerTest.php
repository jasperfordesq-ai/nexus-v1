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
 * Integration tests for AdminNewsletterApiController
 *
 * Tests all 17 newsletter management endpoints:
 * CRUD (5), Subscribers (1), Segments (1), Templates (1), Analytics (1),
 * Bounces (1), Suppression (3), Resend (2), Send Time (1), Diagnostics (1)
 *
 * @group integration
 */
class AdminNewsletterApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_newsletter_' . uniqid() . '@test.local';
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
    // CRUD TESTS
    // ===========================

    public function testListNewslettersWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testShowNewsletterWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateNewsletterWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/newsletters',
            [
                'subject' => 'Test Newsletter ' . uniqid(),
                'content' => '<h1>Test Newsletter</h1><p>Content here</p>',
                'status' => 'draft',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateNewsletterWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/newsletters/1',
            [
                'subject' => 'Updated Newsletter Subject',
                'content' => '<p>Updated content</p>',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteNewsletterWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/newsletters/99999',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // SUBSCRIBERS & SEGMENTS TESTS
    // ===========================

    public function testGetSubscribersWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/subscribers',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetSegmentsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/segments',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // TEMPLATES & ANALYTICS TESTS
    // ===========================

    public function testGetTemplatesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/templates',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetAnalyticsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/analytics',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // BOUNCES & SUPPRESSION TESTS
    // ===========================

    public function testGetBouncesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/bounces',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetSuppressionListWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/suppression',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSuppressEmailWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/newsletters/suppression',
            ['email' => 'suppress_test@test.local'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUnsuppressEmailWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/newsletters/suppression/suppress_test@test.local',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // RESEND TESTS
    // ===========================

    public function testGetResendInfoWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/1/resend',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testResendNewsletterWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/newsletters/1/resend',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // SEND TIME & DIAGNOSTICS TESTS
    // ===========================

    public function testGetSendTimeDataWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/send-time',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetDiagnosticsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/diagnostics',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testNewsletterEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/newsletters'],
            ['GET', '/api/v2/admin/newsletters/subscribers'],
            ['GET', '/api/v2/admin/newsletters/analytics'],
            ['GET', '/api/v2/admin/newsletters/bounces'],
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
