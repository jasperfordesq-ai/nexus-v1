<?php

declare(strict_types=1);

namespace Nexus\Tests\Enterprise;

use Nexus\Services\Enterprise\GdprService;
use Nexus\Tests\DatabaseTestCase;
use PDO;

/**
 * GDPR Service Tests
 *
 * Tests for the GDPR compliance service including data export,
 * deletion, consent management, and breach reporting.
 */
class GdprServiceTest extends DatabaseTestCase
{
    private GdprService $gdprService;

    protected function setUp(): void
    {
        parent::setUp();
        // Set session tenant_id for the service
        $_SESSION['tenant_id'] = 1;
        $this->gdprService = new GdprService();
    }

    /**
     * Test creating a data access request.
     */
    public function testCreateDataAccessRequest(): void
    {
        $userId = $this->createTestUser();

        $result = $this->gdprService->createRequest($userId, 'access');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);

        $this->assertDatabaseHas('gdpr_requests', [
            'user_id' => $userId,
            'request_type' => 'access',
            'status' => 'pending'
        ]);
    }

    /**
     * Test creating a data erasure request.
     */
    public function testCreateDataErasureRequest(): void
    {
        $userId = $this->createTestUser();

        $result = $this->gdprService->createRequest($userId, 'erasure');

        $this->assertIsArray($result);
        $this->assertDatabaseHas('gdpr_requests', [
            'user_id' => $userId,
            'request_type' => 'erasure',
            'status' => 'pending'
        ]);
    }

    /**
     * Test invalid request type throws exception.
     */
    public function testInvalidRequestTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->gdprService->createRequest(1, 'invalid_type');
    }

    /**
     * Test granting consent.
     */
    public function testGrantConsent(): void
    {
        $userId = $this->createTestUser();
        $consentTypeId = $this->createConsentType('marketing_emails');

        $result = $this->gdprService->grantConsent($userId, $consentTypeId, 'web', '127.0.0.1');

        $this->assertTrue($result);
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $userId,
            'consent_type_id' => $consentTypeId,
            'granted' => 1
        ]);
    }

    /**
     * Test withdrawing consent.
     */
    public function testWithdrawConsent(): void
    {
        $userId = $this->createTestUser();
        $consentTypeId = $this->createConsentType('marketing_emails');

        // First grant consent
        $this->gdprService->grantConsent($userId, $consentTypeId, 'web', '127.0.0.1');

        // Then withdraw
        $result = $this->gdprService->withdrawConsent($userId, $consentTypeId, '127.0.0.1');

        $this->assertTrue($result);

        // Check consent was withdrawn
        $consent = $this->getTestData('user_consents', [
            'user_id' => $userId,
            'consent_type_id' => $consentTypeId
        ]);

        $this->assertNotNull($consent[0]['withdrawn_at'] ?? null);
    }

    /**
     * Test checking user consent status.
     */
    public function testHasConsent(): void
    {
        $userId = $this->createTestUser();
        $consentTypeId = $this->createConsentType('analytics');

        // Initially no consent
        $this->assertFalse($this->gdprService->hasConsent($userId, 'analytics'));

        // Grant consent
        $this->gdprService->grantConsent($userId, $consentTypeId, 'web', '127.0.0.1');

        // Now should have consent
        $this->assertTrue($this->gdprService->hasConsent($userId, 'analytics'));
    }

    /**
     * Test getting user's consent history.
     */
    public function testGetConsentHistory(): void
    {
        $userId = $this->createTestUser();
        $consentTypeId = $this->createConsentType('notifications');

        $this->gdprService->grantConsent($userId, $consentTypeId, 'web', '127.0.0.1');
        $this->gdprService->withdrawConsent($userId, $consentTypeId, '127.0.0.1');
        $this->gdprService->grantConsent($userId, $consentTypeId, 'mobile', '192.168.1.1');

        $history = $this->gdprService->getConsentHistory($userId);

        $this->assertIsArray($history);
        $this->assertGreaterThanOrEqual(3, count($history));
    }

    /**
     * Test reporting a data breach.
     */
    public function testReportBreach(): void
    {
        $result = $this->gdprService->reportBreach([
            'title' => 'Test Security Incident',
            'description' => 'A test breach for unit testing purposes.',
            'severity' => 'low',
            'detected_at' => date('Y-m-d H:i:s'),
            'reported_by' => 1
        ]);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['breach_id']);

        $this->assertDatabaseHas('data_breach_log', [
            'title' => 'Test Security Incident',
            'severity' => 'low'
        ]);
    }

    /**
     * Test breach notification deadline calculation.
     */
    public function testBreachNotificationDeadline(): void
    {
        $detectedAt = date('Y-m-d H:i:s');
        $expectedDeadline = date('Y-m-d H:i:s', strtotime($detectedAt) + (72 * 3600));

        $result = $this->gdprService->reportBreach([
            'title' => 'Deadline Test',
            'description' => 'Testing deadline calculation.',
            'severity' => 'high',
            'detected_at' => $detectedAt,
            'reported_by' => 1
        ]);

        $breach = $this->getTestData('data_breach_log', ['id' => $result['breach_id']]);

        $this->assertNotEmpty($breach);
        $this->assertEquals($expectedDeadline, $breach[0]['notification_deadline']);
    }

    /**
     * Test audit log is created for GDPR actions.
     */
    public function testAuditLogCreation(): void
    {
        $userId = $this->createTestUser();

        $this->gdprService->createRequest($userId, 'access');

        $this->assertDatabaseHas('gdpr_audit_log', [
            'user_id' => $userId,
            'action' => 'access_requested'
        ]);
    }

    /**
     * Test processing a data access request.
     */
    public function testProcessDataAccess(): void
    {
        $userId = $this->createTestUser();

        $request = $this->gdprService->createRequest($userId, 'access');

        // Process the request
        $result = $this->gdprService->processRequest($request['id'], 1);

        $this->assertTrue($result['success']);

        // Check request status updated
        $this->assertDatabaseHas('gdpr_requests', [
            'id' => $request['id'],
            'status' => 'processing'
        ]);
    }

    /**
     * Test SLA tracking for requests.
     */
    public function testRequestSlaTracking(): void
    {
        $userId = $this->createTestUser();

        $result = $this->gdprService->createRequest($userId, 'access');

        $stats = $this->gdprService->getRequestStats();

        $this->assertArrayHasKey('pending_count', $stats);
        $this->assertArrayHasKey('overdue_count', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['pending_count']);
    }

    /**
     * Helper: Create a test user.
     */
    private function createTestUser(): int
    {
        return $this->insertTestData('users', [
            'username' => 'testuser_' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Helper: Create a consent type.
     */
    private function createConsentType(string $slug): int
    {
        return $this->insertTestData('consent_types', [
            'slug' => $slug,
            'name' => ucwords(str_replace('_', ' ', $slug)),
            'description' => 'Test consent type: ' . $slug,
            'required' => 0,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
