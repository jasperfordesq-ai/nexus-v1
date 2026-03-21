<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services\Federation;

use App\Tests\DatabaseTestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\FederationCreditService;

/**
 * FederationCreditService Tests
 *
 * Tests credit agreement CRUD, approval workflow, and status management
 * between federated tenants.
 */
class FederationCreditServiceTest extends DatabaseTestCase
{
    protected static ?int $tenantAId = null;
    protected static ?int $tenantBId = null;
    protected static ?int $testAdminId = null;
    protected static bool $dbAvailable = false;

    private FederationCreditService $service;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tenantAId = 2;
        self::$tenantBId = 1;

        try {
            TenantContext::setById(self::$tenantAId);
        } catch (\Throwable $e) {
            return;
        }

        try {
            $timestamp = time() . rand(1000, 9999);

            Database::query(
                "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, role, is_approved, status, created_at)
                 VALUES (?, ?, ?, 'Credit', 'Admin', 'Credit Admin', 'admin', 1, 'active', NOW())",
                [self::$tenantAId, "credit_admin_{$timestamp}@test.com", "credit_admin_{$timestamp}"]
            );
            self::$testAdminId = (int) Database::getInstance()->lastInsertId();

            // Clean up any leftover test agreements between these tenants
            Database::query(
                "DELETE FROM federation_credit_agreements
                 WHERE (from_tenant_id = ? AND to_tenant_id = ?) OR (from_tenant_id = ? AND to_tenant_id = ?)",
                [self::$tenantAId, self::$tenantBId, self::$tenantBId, self::$tenantAId]
            );

            self::$dbAvailable = true;
        } catch (\Throwable $e) {
            error_log("FederationCreditServiceTest setup failed: " . $e->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$dbAvailable) {
            return;
        }

        try {
            Database::query(
                "DELETE FROM federation_credit_agreements
                 WHERE (from_tenant_id = ? AND to_tenant_id = ?) OR (from_tenant_id = ? AND to_tenant_id = ?)",
                [self::$tenantAId, self::$tenantBId, self::$tenantBId, self::$tenantAId]
            );
            if (self::$testAdminId) {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testAdminId]);
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available for integration test');
        }

        TenantContext::setById(self::$tenantAId);
        $this->service = new FederationCreditService();

        // Clean agreements between test tenants before each test
        try {
            Database::query(
                "DELETE FROM federation_credit_agreements
                 WHERE (from_tenant_id = ? AND to_tenant_id = ?) OR (from_tenant_id = ? AND to_tenant_id = ?)",
                [self::$tenantAId, self::$tenantBId, self::$tenantBId, self::$tenantAId]
            );
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    // ==========================================
    // listAgreementsStatic Tests
    // ==========================================

    public function testListAgreementsStaticReturnsArray(): void
    {
        $result = FederationCreditService::listAgreementsStatic(self::$tenantAId);

        $this->assertIsArray($result);
    }

    public function testListAgreementsStaticIncludesCreatedAgreement(): void
    {
        $createResult = FederationCreditService::createAgreementStatic(
            self::$tenantAId,
            self::$tenantBId,
            1.0,
            100.0,
            self::$testAdminId
        );
        $this->assertTrue($createResult['success']);

        $agreements = FederationCreditService::listAgreementsStatic(self::$tenantAId);
        $this->assertIsArray($agreements);

        $found = false;
        foreach ($agreements as $agreement) {
            if ($agreement['id'] === $createResult['id']) {
                $found = true;
                $this->assertEquals(self::$tenantAId, $agreement['from_tenant_id']);
                $this->assertEquals(self::$tenantBId, $agreement['to_tenant_id']);
                $this->assertEquals(1.0, $agreement['exchange_rate']);
                $this->assertEquals('pending', $agreement['status']);
                break;
            }
        }
        $this->assertTrue($found, 'Created agreement should appear in list');
    }

    public function testListAgreementsStaticResultStructure(): void
    {
        FederationCreditService::createAgreementStatic(
            self::$tenantAId,
            self::$tenantBId,
            1.5,
            null,
            self::$testAdminId
        );

        $agreements = FederationCreditService::listAgreementsStatic(self::$tenantAId);
        $this->assertNotEmpty($agreements);

        $agreement = $agreements[0];
        $this->assertArrayHasKey('id', $agreement);
        $this->assertArrayHasKey('from_tenant_id', $agreement);
        $this->assertArrayHasKey('to_tenant_id', $agreement);
        $this->assertArrayHasKey('exchange_rate', $agreement);
        $this->assertArrayHasKey('max_monthly_credits', $agreement);
        $this->assertArrayHasKey('status', $agreement);
        $this->assertArrayHasKey('from_tenant_name', $agreement);
        $this->assertArrayHasKey('to_tenant_name', $agreement);
        $this->assertArrayHasKey('created_at', $agreement);
    }

    // ==========================================
    // createAgreementStatic Tests
    // ==========================================

    public function testCreateAgreementSuccess(): void
    {
        $result = FederationCreditService::createAgreementStatic(
            self::$tenantAId,
            self::$tenantBId,
            1.5,
            200.0,
            self::$testAdminId
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(self::$tenantAId, $result['from_tenant_id']);
        $this->assertEquals(self::$tenantBId, $result['to_tenant_id']);
        $this->assertEquals(1.5, $result['exchange_rate']);
        $this->assertEquals(200.0, $result['max_monthly_credits']);
        $this->assertEquals('pending', $result['status']);
    }

    public function testCreateAgreementSameTenantFails(): void
    {
        $result = FederationCreditService::createAgreementStatic(
            self::$tenantAId,
            self::$tenantAId
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('same tenant', $result['error']);
    }

    public function testCreateAgreementDuplicateFails(): void
    {
        $result1 = FederationCreditService::createAgreementStatic(
            self::$tenantAId,
            self::$tenantBId,
            1.0,
            null,
            self::$testAdminId
        );
        $this->assertTrue($result1['success']);

        $result2 = FederationCreditService::createAgreementStatic(
            self::$tenantAId,
            self::$tenantBId,
            2.0,
            null,
            self::$testAdminId
        );
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('already exists', $result2['error']);
    }

    public function testCreateAgreementWithNullMaxCredits(): void
    {
        $result = FederationCreditService::createAgreementStatic(
            self::$tenantAId,
            self::$tenantBId,
            1.0,
            null,
            self::$testAdminId
        );

        $this->assertTrue($result['success']);
        $this->assertNull($result['max_monthly_credits']);
    }

    public function testCreateAgreementInstanceMethod(): void
    {
        $result = $this->service->createAgreement(
            self::$tenantAId,
            self::$tenantBId,
            1.0,
            50.0,
            self::$testAdminId
        );

        $this->assertTrue($result['success']);
    }

    // ==========================================
    // approveAgreement Tests
    // ==========================================

    public function testApproveAgreementSuccess(): void
    {
        $createResult = FederationCreditService::createAgreementStatic(
            self::$tenantAId,
            self::$tenantBId,
            1.0,
            null,
            self::$testAdminId
        );
        $this->assertTrue($createResult['success']);

        $approveResult = $this->service->approveAgreement($createResult['id'], self::$testAdminId);

        $this->assertTrue($approveResult['success']);
        $this->assertEquals($createResult['id'], $approveResult['id']);
        $this->assertEquals('active', $approveResult['status']);
    }

    public function testApproveNonExistentAgreementFails(): void
    {
        $result = $this->service->approveAgreement(999999, self::$testAdminId);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['error']);
        $this->assertNotEmpty($this->service->getErrors());
    }

    public function testApproveAlreadyActiveAgreementFails(): void
    {
        $createResult = FederationCreditService::createAgreementStatic(
            self::$tenantAId,
            self::$tenantBId,
            1.0,
            null,
            self::$testAdminId
        );
        $this->assertTrue($createResult['success']);

        $this->service->approveAgreement($createResult['id'], self::$testAdminId);

        $secondApproval = $this->service->approveAgreement($createResult['id'], self::$testAdminId);
        $this->assertFalse($secondApproval['success']);
    }

    // ==========================================
    // updateAgreementStatus Tests
    // ==========================================

    public function testUpdateAgreementStatusToSuspended(): void
    {
        $createResult = FederationCreditService::createAgreementStatic(
            self::$tenantAId,
            self::$tenantBId,
            1.0,
            null,
            self::$testAdminId
        );
        $this->assertTrue($createResult['success']);

        $this->service->approveAgreement($createResult['id'], self::$testAdminId);

        $result = $this->service->updateAgreementStatus($createResult['id'], 'suspended');

        $this->assertTrue($result['success']);
        $this->assertEquals('suspended', $result['status']);
    }

    public function testUpdateAgreementStatusInvalidStatusFails(): void
    {
        $result = $this->service->updateAgreementStatus(1, 'invalid_status');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid status', $result['error']);
        $this->assertNotEmpty($this->service->getErrors());
    }

    public function testUpdateAgreementStatusNonExistentFails(): void
    {
        $result = $this->service->updateAgreementStatus(999999, 'terminated');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['error']);
    }

    // ==========================================
    // getAgreement Tests
    // ==========================================

    public function testGetAgreementBetweenTenants(): void
    {
        $createResult = FederationCreditService::createAgreementStatic(
            self::$tenantAId,
            self::$tenantBId,
            2.0,
            150.0,
            self::$testAdminId
        );
        $this->assertTrue($createResult['success']);

        $agreement = $this->service->getAgreement(self::$tenantAId, self::$tenantBId);

        $this->assertNotNull($agreement);
        $this->assertEquals($createResult['id'], $agreement['id']);
        $this->assertEquals(2.0, $agreement['exchange_rate']);
        $this->assertEquals(150.0, $agreement['max_monthly_credits']);
        $this->assertArrayHasKey('from_tenant_name', $agreement);
        $this->assertArrayHasKey('to_tenant_name', $agreement);
    }

    public function testGetAgreementReverseLookup(): void
    {
        FederationCreditService::createAgreementStatic(
            self::$tenantAId,
            self::$tenantBId,
            1.0,
            null,
            self::$testAdminId
        );

        // Lookup in reverse direction should still find it
        $agreement = $this->service->getAgreement(self::$tenantBId, self::$tenantAId);

        $this->assertNotNull($agreement);
    }

    public function testGetAgreementNonExistentReturnsNull(): void
    {
        $agreement = $this->service->getAgreement(999998, 999999);

        $this->assertNull($agreement);
    }

    // ==========================================
    // getErrors Tests
    // ==========================================

    public function testGetErrorsInitiallyEmpty(): void
    {
        $service = new FederationCreditService();
        $this->assertEmpty($service->getErrors());
    }
}
