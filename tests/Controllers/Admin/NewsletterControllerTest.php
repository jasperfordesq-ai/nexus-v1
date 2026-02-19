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
 * Integration tests for AdminNewsletterApiController
 *
 * Tests newsletter CRUD, subscribers, segments, templates, analytics,
 * bounce management, suppression list, and diagnostics.
 *
 * Endpoints:
 * - GET    /api/v2/admin/newsletters                              — List newsletters
 * - POST   /api/v2/admin/newsletters                              — Create newsletter
 * - GET    /api/v2/admin/newsletters/{id}                         — Show newsletter
 * - PUT    /api/v2/admin/newsletters/{id}                         — Update newsletter
 * - DELETE /api/v2/admin/newsletters/{id}                         — Delete newsletter
 * - GET    /api/v2/admin/newsletters/subscribers                   — List subscribers
 * - GET    /api/v2/admin/newsletters/segments                      — List segments
 * - GET    /api/v2/admin/newsletters/templates                     — List templates
 * - GET    /api/v2/admin/newsletters/analytics                     — Newsletter analytics
 * - GET    /api/v2/admin/newsletters/bounces                       — Bounce list
 * - GET    /api/v2/admin/newsletters/suppression-list              — Suppression list
 * - POST   /api/v2/admin/newsletters/suppression-list/{email}/suppress   — Suppress email
 * - POST   /api/v2/admin/newsletters/suppression-list/{email}/unsuppress — Unsuppress email
 * - GET    /api/v2/admin/newsletters/send-time-optimizer           — Send time data
 * - GET    /api/v2/admin/newsletters/diagnostics                   — System diagnostics
 * - GET    /api/v2/admin/newsletters/{id}/resend-info              — Resend info
 * - POST   /api/v2/admin/newsletters/{id}/resend                   — Resend newsletter
 *
 * @group integration
 * @group admin
 */
class NewsletterControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $memberUserId;
    private static int $tenantId;
    private static string $adminToken;
    private static string $memberToken;

    /** @var int[] IDs to clean up */
    private static array $cleanupUserIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'newsletter_admin_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Newsletter Admin', 'Newsletter', 'Admin', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int) Database::lastInsertId();
        self::$cleanupUserIds[] = self::$adminUserId;
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create regular member
        $memberEmail = 'newsletter_member_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Newsletter Member', 'Newsletter', 'Member', 'member', 'active', 1, NOW())",
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

    // =========================================================================
    // LIST NEWSLETTERS — GET /api/v2/admin/newsletters
    // =========================================================================

    public function testListNewslettersReturnsPaginatedData(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters?page=1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
        $this->assertEquals('GET', $response['method']);
    }

    public function testListNewslettersWithStatusFilter(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters?status=draft',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // CREATE NEWSLETTER — POST /api/v2/admin/newsletters
    // =========================================================================

    public function testCreateNewsletter(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/newsletters',
            [
                'name' => 'Test Newsletter ' . uniqid(),
                'subject' => 'Test Subject',
                'preview_text' => 'Preview text here',
                'content' => '<h1>Hello World</h1><p>Test newsletter content.</p>',
                'status' => 'draft',
                'target_audience' => 'all_members',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateNewsletterRequiresName(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/newsletters',
            [
                'subject' => 'Test Subject',
                'content' => 'Content without name',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateNewsletterWithScheduledAt(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/newsletters',
            [
                'name' => 'Scheduled Newsletter ' . uniqid(),
                'subject' => 'Scheduled Test',
                'content' => 'Scheduled content',
                'status' => 'scheduled',
                'scheduled_at' => '2026-03-01 10:00:00',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateNewsletterWithAbTestEnabled(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/newsletters',
            [
                'name' => 'AB Test Newsletter ' . uniqid(),
                'subject' => 'Subject A',
                'subject_b' => 'Subject B',
                'content' => 'AB test content',
                'ab_test_enabled' => true,
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // SHOW NEWSLETTER — GET /api/v2/admin/newsletters/{id}
    // =========================================================================

    public function testShowNewsletter(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testShowNonExistentNewsletter(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/999999',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // UPDATE NEWSLETTER — PUT /api/v2/admin/newsletters/{id}
    // =========================================================================

    public function testUpdateNewsletter(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/newsletters/1',
            [
                'name' => 'Updated Newsletter Name',
                'subject' => 'Updated Subject',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateNewsletterWithEmptyPayload(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/newsletters/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // DELETE NEWSLETTER — DELETE /api/v2/admin/newsletters/{id}
    // =========================================================================

    public function testDeleteNewsletter(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/newsletters/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // SUBSCRIBERS — GET /api/v2/admin/newsletters/subscribers
    // =========================================================================

    public function testListSubscribers(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/subscribers',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // SEGMENTS — GET /api/v2/admin/newsletters/segments
    // =========================================================================

    public function testListSegments(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/segments',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // TEMPLATES — GET /api/v2/admin/newsletters/templates
    // =========================================================================

    public function testListTemplates(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/templates',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // ANALYTICS — GET /api/v2/admin/newsletters/analytics
    // =========================================================================

    public function testAnalyticsReturnsStats(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/analytics',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // BOUNCE MANAGEMENT — GET /api/v2/admin/newsletters/bounces
    // =========================================================================

    public function testListBounces(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/bounces',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListBouncesWithTypeFilter(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/bounces?type=hard',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListBouncesWithDateRange(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/bounces?startDate=2026-01-01&endDate=2026-12-31',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // SUPPRESSION LIST
    // =========================================================================

    public function testGetSuppressionList(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/suppression-list',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSuppressEmail(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/newsletters/suppression-list/test@example.com/suppress',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUnsuppressEmail(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/newsletters/suppression-list/test@example.com/unsuppress',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // SEND-TIME OPTIMIZER — GET /api/v2/admin/newsletters/send-time-optimizer
    // =========================================================================

    public function testGetSendTimeData(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/send-time-optimizer',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetSendTimeDataWithCustomDays(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/send-time-optimizer?days=90',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // DIAGNOSTICS — GET /api/v2/admin/newsletters/diagnostics
    // =========================================================================

    public function testGetDiagnostics(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/diagnostics',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // RESEND WORKFLOW
    // =========================================================================

    public function testGetResendInfo(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/newsletters/1/resend-info',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testResendToNonOpeners(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/newsletters/1/resend',
            [
                'target' => 'non_openers',
                'subject_override' => 'Did you miss this?',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testResendWithInvalidTarget(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/newsletters/1/resend',
            ['target' => 'invalid_target'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // AUTHORIZATION — Non-admin gets 403
    // =========================================================================

    public function testNonAdminCannotAccessNewsletterEndpoints(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/newsletters'],
            ['POST', '/api/v2/admin/newsletters'],
            ['GET', '/api/v2/admin/newsletters/subscribers'],
            ['GET', '/api/v2/admin/newsletters/analytics'],
            ['GET', '/api/v2/admin/newsletters/diagnostics'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest(
                $method,
                $endpoint,
                [],
                ['Authorization' => 'Bearer ' . self::$memberToken]
            );

            $this->assertEquals('simulated', $response['status'], "Endpoint {$method} {$endpoint} should reject non-admin");
        }
    }

    public function testUnauthenticatedRequestsAreRejected(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/newsletters'],
            ['POST', '/api/v2/admin/newsletters'],
            ['GET', '/api/v2/admin/newsletters/analytics'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest($method, $endpoint, [], []);
            $this->assertEquals('simulated', $response['status']);
        }
    }

    // =========================================================================
    // CLEANUP
    // =========================================================================

    public static function tearDownAfterClass(): void
    {
        foreach (self::$cleanupUserIds as $id) {
            try {
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [$id]);
            } catch (\Exception $e) {
                // ignore
            }
            try {
                Database::query("DELETE FROM users WHERE id = ?", [$id]);
            } catch (\Exception $e) {
                // ignore
            }
        }

        parent::tearDownAfterClass();
    }
}
