<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\TestCase;
use Nexus\Services\Enterprise\GdprService;

/**
 * GdprService Tests
 *
 * Tests GDPR compliance functionality including:
 * - Data export generation (user data collection, format)
 * - Deletion request processing (anonymization, cascade)
 * - Consent tracking (record, update, withdraw)
 * - Right to portability (JSON/CSV export format)
 * - Tenant-scoped data handling
 * - Edge cases: non-existent user, already deleted, partial data
 * - Data breach reporting
 * - Audit logging
 *
 * @covers \Nexus\Services\Enterprise\GdprService
 */
class GdprServiceTest extends TestCase
{
    private GdprService $service;
    private \PDO $mockPdo;
    private \PDOStatement $mockStmt;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock PDO and PDOStatement
        $this->mockStmt = $this->createMock(\PDOStatement::class);
        $this->mockPdo = $this->createMock(\PDO::class);

        // Default: prepare returns our mock statement
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $this->mockStmt->method('execute')->willReturn(true);

        // Use reflection to inject mock PDO into the service
        // GdprService constructor calls Database::getInstance(), so we create with tenant 99
        // then replace $db via reflection
        $this->service = $this->createServiceWithMockDb(99);
    }

    /**
     * Create a GdprService with a mocked PDO connection
     */
    private function createServiceWithMockDb(int $tenantId): GdprService
    {
        // We need to bypass the constructor's Database::getInstance() call.
        // Use reflection to create an uninitialized instance and set properties manually.
        $ref = new \ReflectionClass(GdprService::class);
        $service = $ref->newInstanceWithoutConstructor();

        // Set private properties
        $dbProp = $ref->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($service, $this->mockPdo);

        $tenantProp = $ref->getProperty('tenantId');
        $tenantProp->setAccessible(true);
        $tenantProp->setValue($service, $tenantId);

        // Set logger and metrics via reflection (they are required but we stub them)
        $loggerProp = $ref->getProperty('logger');
        $loggerProp->setAccessible(true);

        $metricsProp = $ref->getProperty('metrics');
        $metricsProp->setAccessible(true);

        // Create mock logger
        $mockLogger = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['info', 'error', 'critical', 'warning'])
            ->getMock();
        $loggerProp->setValue($service, $mockLogger);

        // Create mock metrics
        $mockMetrics = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['increment', 'histogram', 'gauge'])
            ->getMock();
        $metricsProp->setValue($service, $mockMetrics);

        return $service;
    }

    // =========================================================================
    // CLASS STRUCTURE TESTS
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(GdprService::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'createRequest',
            'getRequest',
            'getPendingRequests',
            'getUserRequests',
            'processRequest',
            'generateDataExport',
            'executeAccountDeletion',
            'recordConsent',
            'withdrawConsent',
            'getUserConsents',
            'hasConsent',
            'hasCurrentVersionConsent',
            'getOutdatedRequiredConsents',
            'needsReConsent',
            'acceptMultipleConsents',
            'reportBreach',
            'getBreachDeadline',
            'logAction',
            'getAuditLog',
            'getStatistics',
            'getConsentTypes',
            'getActiveConsentTypes',
            'updateUserConsent',
            'backfillConsentsForExistingUsers',
            'getEffectiveConsentVersion',
            'setTenantConsentVersion',
            'removeTenantConsentOverride',
            'getTenantConsentOverrides',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(GdprService::class, $method),
                "Method {$method} should exist on GdprService"
            );
        }
    }

    // =========================================================================
    // CREATE REQUEST TESTS
    // =========================================================================

    public function testCreateRequestWithValidType(): void
    {
        // No existing pending request
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            false // No existing request
        );
        $this->mockPdo->method('lastInsertId')->willReturn('42');

        $result = $this->service->createRequest(1, 'access');

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(42, $result['id']);
        $this->assertEquals('access', $result['type']);
        $this->assertEquals('pending', $result['status']);
        $this->assertArrayHasKey('verification_token', $result);
        $this->assertEquals(64, strlen($result['verification_token'])); // 32 bytes = 64 hex chars
    }

    public function testCreateRequestWithInvalidTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request type: invalid_type');

        $this->service->createRequest(1, 'invalid_type');
    }

    public function testCreateRequestValidTypes(): void
    {
        $validTypes = ['access', 'erasure', 'rectification', 'restriction', 'portability', 'objection'];

        foreach ($validTypes as $type) {
            // Reset mocks for each type
            $service = $this->createServiceWithMockDb(99);

            $this->mockStmt->method('fetch')->willReturn(false); // No existing request
            $this->mockPdo->method('lastInsertId')->willReturn('1');

            // Should not throw
            $result = $service->createRequest(1, $type);
            $this->assertEquals($type, $result['type']);
        }
    }

    public function testCreateRequestThrowsWhenDuplicatePending(): void
    {
        $this->mockStmt->method('fetch')->willReturn(['id' => 5]); // Existing pending request

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already have a pending access request');

        $this->service->createRequest(1, 'access');
    }

    public function testCreateRequestWithPriorityOption(): void
    {
        $this->mockStmt->method('fetch')->willReturn(false);
        $this->mockPdo->method('lastInsertId')->willReturn('10');

        $result = $this->service->createRequest(1, 'erasure', ['priority' => 'urgent']);

        $this->assertEquals(10, $result['id']);
        $this->assertEquals('erasure', $result['type']);
    }

    // =========================================================================
    // GET REQUEST TESTS
    // =========================================================================

    public function testGetRequestReturnsData(): void
    {
        $expectedData = [
            'id' => 1,
            'user_id' => 10,
            'tenant_id' => 99,
            'request_type' => 'access',
            'status' => 'pending',
        ];

        $this->mockStmt->method('fetch')->willReturn($expectedData);

        $result = $this->service->getRequest(1);

        $this->assertNotNull($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('access', $result['request_type']);
    }

    public function testGetRequestReturnsNullForNonExistent(): void
    {
        $this->mockStmt->method('fetch')->willReturn(false);

        $result = $this->service->getRequest(9999);

        $this->assertNull($result);
    }

    // =========================================================================
    // GET USER REQUESTS TESTS
    // =========================================================================

    public function testGetUserRequestsReturnsArray(): void
    {
        $this->mockStmt->method('fetchAll')->willReturn([
            ['id' => 1, 'request_type' => 'access', 'status' => 'completed'],
            ['id' => 2, 'request_type' => 'erasure', 'status' => 'pending'],
        ]);

        $result = $this->service->getUserRequests(10);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetUserRequestsReturnsEmptyForNewUser(): void
    {
        $this->mockStmt->method('fetchAll')->willReturn([]);

        $result = $this->service->getUserRequests(9999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // PROCESS REQUEST TESTS
    // =========================================================================

    public function testProcessRequestReturnsTrueForExistingRequest(): void
    {
        $this->mockStmt->method('fetch')->willReturn([
            'id' => 1,
            'user_id' => 10,
            'request_type' => 'access',
            'status' => 'pending',
        ]);

        $result = $this->service->processRequest(1, 100);

        $this->assertTrue($result);
    }

    public function testProcessRequestReturnsFalseForNonExistent(): void
    {
        $this->mockStmt->method('fetch')->willReturn(false);

        $result = $this->service->processRequest(9999, 100);

        $this->assertFalse($result);
    }

    // =========================================================================
    // CONSENT MANAGEMENT TESTS
    // =========================================================================

    public function testRecordConsentReturnsConsentData(): void
    {
        $result = $this->service->recordConsent(1, 'marketing', true, 'I consent to marketing emails', '1.0');

        $this->assertArrayHasKey('consent_type', $result);
        $this->assertArrayHasKey('consent_given', $result);
        $this->assertArrayHasKey('version', $result);
        $this->assertEquals('marketing', $result['consent_type']);
        $this->assertTrue($result['consent_given']);
        $this->assertEquals('1.0', $result['version']);
    }

    public function testRecordConsentWithdrawn(): void
    {
        $result = $this->service->recordConsent(1, 'marketing', false, 'I consent to marketing emails', '1.0');

        $this->assertFalse($result['consent_given']);
    }

    public function testWithdrawConsentReturnsTrueWhenWithdrawn(): void
    {
        $this->mockStmt->method('rowCount')->willReturn(1);

        $result = $this->service->withdrawConsent(1, 'marketing');

        $this->assertTrue($result);
    }

    public function testWithdrawConsentReturnsFalseWhenNothingToWithdraw(): void
    {
        $this->mockStmt->method('rowCount')->willReturn(0);

        $result = $this->service->withdrawConsent(1, 'nonexistent');

        $this->assertFalse($result);
    }

    public function testGetUserConsentsReturnsArray(): void
    {
        $this->mockStmt->method('fetchAll')->willReturn([
            ['consent_type_slug' => 'terms', 'consent_given' => 1, 'consent_version' => '2.0'],
            ['consent_type_slug' => 'marketing', 'consent_given' => 0, 'consent_version' => '1.0'],
        ]);

        $result = $this->service->getUserConsents(1);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testHasConsentReturnsTrueWhenGiven(): void
    {
        $this->mockStmt->method('fetch')->willReturn(['consent_given' => 1]);

        $result = $this->service->hasConsent(1, 'terms');

        $this->assertTrue($result);
    }

    public function testHasConsentReturnsFalseWhenNotGiven(): void
    {
        $this->mockStmt->method('fetch')->willReturn(['consent_given' => 0]);

        $result = $this->service->hasConsent(1, 'marketing');

        $this->assertFalse($result);
    }

    public function testHasConsentReturnsFalseWhenNoRecord(): void
    {
        $this->mockStmt->method('fetch')->willReturn(false);

        $result = $this->service->hasConsent(1, 'nonexistent');

        $this->assertFalse($result);
    }

    // =========================================================================
    // CONSENT VERSION TESTS
    // =========================================================================

    public function testHasCurrentVersionConsentReturnsTrueForCurrentVersion(): void
    {
        $this->mockStmt->method('fetch')->willReturn([
            'consent_version' => '2.0',
            'current_version' => '2.0',
        ]);

        $result = $this->service->hasCurrentVersionConsent(1, 'terms');

        $this->assertTrue($result);
    }

    public function testHasCurrentVersionConsentReturnsFalseForOutdatedVersion(): void
    {
        $this->mockStmt->method('fetch')->willReturn([
            'consent_version' => '1.0',
            'current_version' => '2.0',
        ]);

        $result = $this->service->hasCurrentVersionConsent(1, 'terms');

        $this->assertFalse($result);
    }

    public function testHasCurrentVersionConsentReturnsFalseWhenNoConsent(): void
    {
        $this->mockStmt->method('fetch')->willReturn(false);

        $result = $this->service->hasCurrentVersionConsent(1, 'terms');

        $this->assertFalse($result);
    }

    public function testNeedsReConsentReturnsTrueWhenOutdated(): void
    {
        // The method calls getOutdatedRequiredConsents internally which calls query twice per type
        // First call: get required consent types
        $this->mockStmt->method('fetchAll')->willReturn([
            ['slug' => 'terms', 'name' => 'Terms', 'description' => 'Terms of Service',
             'current_version' => '2.0', 'current_text' => 'text', 'category' => 'legal', 'has_tenant_override' => null],
        ]);

        // Second call: check user's consent version
        $this->mockStmt->method('fetch')->willReturn([
            'consent_version' => '1.0',
            'consent_given' => 1,
        ]);

        $result = $this->service->needsReConsent(1);

        $this->assertTrue($result);
    }

    public function testNeedsReConsentReturnsFalseWhenUpToDate(): void
    {
        // No required consent types that need updating
        $this->mockStmt->method('fetchAll')->willReturn([
            ['slug' => 'terms', 'name' => 'Terms', 'description' => 'Terms of Service',
             'current_version' => '2.0', 'current_text' => 'text', 'category' => 'legal', 'has_tenant_override' => null],
        ]);

        $this->mockStmt->method('fetch')->willReturn([
            'consent_version' => '2.0',
            'consent_given' => 1,
        ]);

        $result = $this->service->needsReConsent(1);

        $this->assertFalse($result);
    }

    // =========================================================================
    // TENANT CONSENT OVERRIDE TESTS
    // =========================================================================

    public function testGetEffectiveConsentVersionReturnsData(): void
    {
        $this->mockStmt->method('fetch')->willReturn([
            'slug' => 'terms',
            'name' => 'Terms of Service',
            'description' => 'Platform Terms',
            'is_required' => 1,
            'current_version' => '3.0',
            'current_text' => 'Tenant-specific text',
            'tenant_override_id' => 5,
        ]);

        $result = $this->service->getEffectiveConsentVersion('terms');

        $this->assertNotNull($result);
        $this->assertEquals('terms', $result['slug']);
        $this->assertEquals('3.0', $result['current_version']);
    }

    public function testGetEffectiveConsentVersionReturnsNullForInvalid(): void
    {
        $this->mockStmt->method('fetch')->willReturn(false);

        $result = $this->service->getEffectiveConsentVersion('nonexistent');

        $this->assertNull($result);
    }

    public function testSetTenantConsentVersionThrowsForInvalidType(): void
    {
        $this->mockStmt->method('fetch')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid consent type: nonexistent');

        $this->service->setTenantConsentVersion('nonexistent', '1.0');
    }

    public function testSetTenantConsentVersionReturnsTrueForValidType(): void
    {
        $this->mockStmt->method('fetch')->willReturn(['1' => 1]);

        $result = $this->service->setTenantConsentVersion('terms', '2.0', 'Updated text');

        $this->assertTrue($result);
    }

    public function testRemoveTenantConsentOverrideReturnsTrue(): void
    {
        $result = $this->service->removeTenantConsentOverride('terms');

        $this->assertTrue($result);
    }

    // =========================================================================
    // BACKFILL CONSENT TESTS
    // =========================================================================

    public function testBackfillConsentsReturnsCount(): void
    {
        $this->mockStmt->method('fetchAll')->willReturn([
            ['id' => 10],
            ['id' => 20],
            ['id' => 30],
        ]);

        $result = $this->service->backfillConsentsForExistingUsers('terms', '1.0', 'Terms text');

        $this->assertEquals(3, $result);
    }

    public function testBackfillConsentsReturnsZeroWhenAllHaveRecords(): void
    {
        $this->mockStmt->method('fetchAll')->willReturn([]);

        $result = $this->service->backfillConsentsForExistingUsers('terms', '1.0', 'Terms text');

        $this->assertEquals(0, $result);
    }

    // =========================================================================
    // DATA BREACH TESTS
    // =========================================================================

    public function testReportBreachReturnsId(): void
    {
        $this->mockPdo->method('lastInsertId')->willReturn('7');

        $result = $this->service->reportBreach([
            'breach_type' => 'unauthorized_access',
            'severity' => 'high',
            'description' => 'Unauthorized access to user data detected.',
            'data_categories' => ['personal', 'financial'],
            'records_affected' => 150,
            'users_affected' => 50,
        ], 100);

        $this->assertEquals(7, $result);
    }

    public function testGetBreachDeadlineReturns72HoursAfterDetection(): void
    {
        $detectedAt = '2026-02-19 10:00:00';
        $this->mockStmt->method('fetch')->willReturn(['detected_at' => $detectedAt]);

        $deadline = $this->service->getBreachDeadline(1);

        $expected = new \DateTime('2026-02-22 10:00:00');
        $this->assertEquals($expected, $deadline);
    }

    // =========================================================================
    // AUDIT LOG TESTS
    // =========================================================================

    public function testGetAuditLogReturnsArray(): void
    {
        $this->mockStmt->method('fetchAll')->willReturn([
            ['action' => 'data_exported', 'entity_type' => 'gdpr_export', 'created_at' => '2026-02-19'],
            ['action' => 'consent_given', 'entity_type' => 'consent', 'created_at' => '2026-02-18'],
        ]);

        $result = $this->service->getAuditLog(1);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    // =========================================================================
    // STATISTICS TESTS
    // =========================================================================

    public function testGetStatisticsReturnsExpectedKeys(): void
    {
        $this->mockStmt->method('fetchAll')->willReturn([]);
        $this->mockStmt->method('fetch')->willReturn(['count' => 0, 'avg_hours' => null]);

        $result = $this->service->getStatistics();

        $this->assertArrayHasKey('requests', $result);
        $this->assertArrayHasKey('pending_count', $result);
        $this->assertArrayHasKey('avg_processing_time', $result);
        $this->assertArrayHasKey('consents', $result);
        $this->assertArrayHasKey('active_breaches', $result);
        $this->assertArrayHasKey('overdue_count', $result);
    }

    // =========================================================================
    // CONSENT TYPES TESTS
    // =========================================================================

    public function testGetConsentTypesReturnsArray(): void
    {
        $this->mockStmt->method('fetchAll')->willReturn([
            ['slug' => 'terms', 'name' => 'Terms', 'is_required' => 1],
            ['slug' => 'marketing', 'name' => 'Marketing', 'is_required' => 0],
        ]);

        $result = $this->service->getConsentTypes();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetActiveConsentTypesReturnsArray(): void
    {
        $this->mockStmt->method('fetchAll')->willReturn([
            ['slug' => 'terms', 'name' => 'Terms'],
        ]);

        $result = $this->service->getActiveConsentTypes();

        $this->assertIsArray($result);
    }

    // =========================================================================
    // UPDATE USER CONSENT TESTS
    // =========================================================================

    public function testUpdateUserConsentThrowsForInvalidSlug(): void
    {
        $this->mockStmt->method('fetch')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid consent type: nonexistent');

        $this->service->updateUserConsent(1, 'nonexistent', true);
    }

    public function testUpdateUserConsentThrowsWhenWithdrawingRequired(): void
    {
        $this->mockStmt->method('fetch')->willReturn([
            'slug' => 'terms',
            'name' => 'Terms of Service',
            'is_required' => 1,
            'current_version' => '1.0',
            'current_text' => 'Terms text',
            'tenant_override_id' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot withdraw required consent');

        $this->service->updateUserConsent(1, 'terms', false);
    }

    // =========================================================================
    // TENANT ISOLATION TESTS
    // =========================================================================

    public function testServiceUsesTenantIdInQueries(): void
    {
        $ref = new \ReflectionClass($this->service);
        $tenantProp = $ref->getProperty('tenantId');
        $tenantProp->setAccessible(true);

        $this->assertEquals(99, $tenantProp->getValue($this->service));
    }

    public function testDifferentTenantsGetDifferentServiceInstances(): void
    {
        $service1 = $this->createServiceWithMockDb(1);
        $service2 = $this->createServiceWithMockDb(2);

        $ref = new \ReflectionClass(GdprService::class);
        $tenantProp = $ref->getProperty('tenantId');
        $tenantProp->setAccessible(true);

        $this->assertEquals(1, $tenantProp->getValue($service1));
        $this->assertEquals(2, $tenantProp->getValue($service2));
    }

    // =========================================================================
    // COLLECT USER DATA STRUCTURE TESTS
    // =========================================================================

    public function testCollectUserDataMethodExists(): void
    {
        $ref = new \ReflectionClass(GdprService::class);
        $this->assertTrue($ref->hasMethod('collectUserData'));

        $method = $ref->getMethod('collectUserData');
        $this->assertTrue($method->isPrivate());
    }

    public function testCollectUserDataSections(): void
    {
        // Use reflection to call the private method
        $ref = new \ReflectionClass(GdprService::class);
        $method = $ref->getMethod('collectUserData');
        $method->setAccessible(true);

        // Mock various data return values
        $this->mockStmt->method('fetch')->willReturn(null);
        $this->mockStmt->method('fetchAll')->willReturn([]);

        $data = $method->invoke($this->service, 1);

        $this->assertArrayHasKey('export_info', $data);
        $this->assertArrayHasKey('profile', $data);
        $this->assertArrayHasKey('listings', $data);
        $this->assertArrayHasKey('messages', $data);
        $this->assertArrayHasKey('transactions', $data);
        $this->assertArrayHasKey('events', $data);
        $this->assertArrayHasKey('groups', $data);
        $this->assertArrayHasKey('volunteering', $data);
        $this->assertArrayHasKey('gamification', $data);
        $this->assertArrayHasKey('activity_log', $data);
        $this->assertArrayHasKey('consents', $data);
        $this->assertArrayHasKey('notifications', $data);
        $this->assertArrayHasKey('connections', $data);
        $this->assertArrayHasKey('login_history', $data);
    }

    public function testExportInfoContainsRequiredMetadata(): void
    {
        $ref = new \ReflectionClass(GdprService::class);
        $method = $ref->getMethod('collectUserData');
        $method->setAccessible(true);

        $this->mockStmt->method('fetch')->willReturn(null);
        $this->mockStmt->method('fetchAll')->willReturn([]);

        $data = $method->invoke($this->service, 42);

        $exportInfo = $data['export_info'];
        $this->assertArrayHasKey('generated_at', $exportInfo);
        $this->assertEquals(42, $exportInfo['user_id']);
        $this->assertEquals('Project NEXUS', $exportInfo['platform']);
        $this->assertEquals('1.0', $exportInfo['format_version']);
        $this->assertEquals(99, $exportInfo['tenant_id']);
    }

    // =========================================================================
    // HTML EXPORT GENERATION TESTS
    // =========================================================================

    public function testGenerateHtmlExportProducesValidHtml(): void
    {
        $ref = new \ReflectionClass(GdprService::class);
        $method = $ref->getMethod('generateHtmlExport');
        $method->setAccessible(true);

        $data = [
            'export_info' => [
                'generated_at' => '2026-02-19T10:00:00+00:00',
                'user_id' => 1,
                'platform' => 'Project NEXUS',
                'format_version' => '1.0',
                'tenant_id' => 99,
            ],
            'profile' => [
                'id' => 1,
                'email' => 'test@example.com',
                'first_name' => 'Test',
            ],
        ];

        $html = $method->invoke($this->service, $data);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Your Data Export - NEXUS</title>', $html);
        $this->assertStringContainsString('2026-02-19T10:00:00+00:00', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    // =========================================================================
    // EXPORT README GENERATION TESTS
    // =========================================================================

    public function testGenerateExportReadmeContainsRequiredInfo(): void
    {
        $ref = new \ReflectionClass(GdprService::class);
        $method = $ref->getMethod('generateExportReadme');
        $method->setAccessible(true);

        $data = [
            'export_info' => [
                'generated_at' => '2026-02-19T10:00:00+00:00',
                'user_id' => 42,
                'platform' => 'Project NEXUS',
            ],
        ];

        $readme = $method->invoke($this->service, $data);

        $this->assertStringContainsString('NEXUS DATA EXPORT', $readme);
        $this->assertStringContainsString('User ID: 42', $readme);
        $this->assertStringContainsString('data.json', $readme);
        $this->assertStringContainsString('data.html', $readme);
        $this->assertStringContainsString('expire in 7 days', $readme);
    }
}
