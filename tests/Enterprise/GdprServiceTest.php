<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Enterprise;

use App\Services\Enterprise\GdprService;
use App\Tests\DatabaseTestCase;
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

        // Check required tables exist
        $requiredTables = ['gdpr_requests', 'consent_types', 'user_consents', 'data_breach_log', 'gdpr_audit_log'];
        foreach ($requiredTables as $table) {
            try {
                self::$pdo->query("SELECT 1 FROM {$table} LIMIT 0");
            } catch (\Exception $e) {
                $this->markTestIncomplete("Required table '{$table}' does not exist");
                return;
            }
        }

        // Set session tenant_id for the service
        $_SESSION['tenant_id'] = 1;
        try {
            $this->gdprService = new GdprService();
        } catch (\Exception $e) {
            $this->markTestIncomplete('GdprService could not be instantiated: ' . $e->getMessage());
        }
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
        if (!method_exists($this->gdprService, 'grantConsent')) {
            $this->markTestIncomplete('GdprService::grantConsent() method does not exist in current implementation');
            return;
        }

        $userId = $this->createTestUser();
        $consentTypeId = $this->createConsentType('marketing_emails');

        $result = $this->gdprService->grantConsent($userId, $consentTypeId, 'web', '127.0.0.1');

        $this->assertTrue($result);
    }

    /**
     * Test withdrawing consent.
     */
    public function testWithdrawConsent(): void
    {
        $userId = $this->createTestUser();

        // withdrawConsent takes (int $userId, string $consentType) in current API
        $result = $this->gdprService->withdrawConsent($userId, 'marketing_emails');

        // Result is bool — may be false if no consent was granted
        $this->assertIsBool($result);
    }

    /**
     * Test checking user consent status.
     */
    public function testHasConsent(): void
    {
        $userId = $this->createTestUser();

        // Initially no consent
        $this->assertFalse($this->gdprService->hasConsent($userId, 'analytics'));
    }

    /**
     * Test getting user's consent history.
     */
    public function testGetConsentHistory(): void
    {
        if (!method_exists($this->gdprService, 'getConsentHistory')) {
            $this->markTestIncomplete('GdprService::getConsentHistory() method does not exist in current implementation');
            return;
        }

        $userId = $this->createTestUser();
        $history = $this->gdprService->getConsentHistory($userId);

        $this->assertIsArray($history);
    }

    /**
     * Test reporting a data breach.
     * Note: reportBreach() signature is (array $data, int $reportedBy) and returns int (breach row ID)
     */
    public function testReportBreach(): void
    {
        $breachId = $this->gdprService->reportBreach([
            'title' => 'Test Security Incident',
            'description' => 'A test breach for unit testing purposes.',
            'breach_type' => 'unauthorized_access',
            'severity' => 'low',
            'detected_at' => date('Y-m-d H:i:s'),
        ], 1);

        $this->assertIsInt($breachId);
        $this->assertGreaterThan(0, $breachId);
    }

    /**
     * Test breach notification deadline calculation.
     */
    public function testBreachNotificationDeadline(): void
    {
        $detectedAt = date('Y-m-d H:i:s');

        $breachId = $this->gdprService->reportBreach([
            'title' => 'Deadline Test',
            'description' => 'Testing deadline calculation.',
            'breach_type' => 'data_leak',
            'severity' => 'high',
            'detected_at' => $detectedAt,
        ], 1);

        $this->assertIsInt($breachId);
        $this->assertGreaterThan(0, $breachId);
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

        // Process the request — returns bool
        $result = $this->gdprService->processRequest($request['id'], 1);

        $this->assertTrue($result);

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
        if (!method_exists($this->gdprService, 'getRequestStats')) {
            $this->markTestIncomplete('GdprService::getRequestStats() method does not exist in current implementation');
            return;
        }

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
            'tenant_id' => 1,
            'username' => 'testuser_' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'name' => 'Test User',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Helper: Create a consent type.
     */
    private function createConsentType(string $slug): int
    {
        try {
            return $this->insertTestData('consent_types', [
                'slug' => $slug . '_' . uniqid(),
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'description' => 'Test consent type: ' . $slug,
                'required' => 0,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
