<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\DatabaseTestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\VolunteerCertificateService;

/**
 * VolunteerCertificateService Tests
 *
 * Tests certificate generation, verification, and user certificate retrieval.
 */
class VolunteerCertificateServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ==========================================
    // generate
    // ==========================================

    public function testGenerateReturnsNullWhenNoApprovedHours(): void
    {
        $this->requireTables(['vol_certificates', 'vol_logs', 'vol_organizations', 'users']);

        $userId = $this->createUser('cert-nohours');

        $result = VolunteerCertificateService::generate($userId);

        $this->assertNull($result);
        $errors = VolunteerCertificateService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function testGenerateCreatesCertificateWithApprovedHours(): void
    {
        $this->requireTables(['vol_certificates', 'vol_logs', 'vol_organizations', 'users']);

        $userId = $this->createUser('cert-hours');
        $orgId = $this->createOrganization($userId);
        $this->insertApprovedLog($userId, $orgId, 5.0);

        $result = VolunteerCertificateService::generate($userId);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('verification_code', $result);
        $this->assertArrayHasKey('total_hours', $result);
        $this->assertArrayHasKey('organizations', $result);
        $this->assertArrayHasKey('user_name', $result);
        $this->assertArrayHasKey('generated_at', $result);
        $this->assertEquals(5.0, $result['total_hours']);
        $this->assertNotEmpty($result['verification_code']);
        $this->assertIsArray($result['organizations']);
    }

    public function testGenerateRespectsOrganizationFilter(): void
    {
        $this->requireTables(['vol_certificates', 'vol_logs', 'vol_organizations', 'users']);

        $userId = $this->createUser('cert-orgfilter');
        $orgId1 = $this->createOrganization($userId);
        $orgId2 = $this->createOrganization($userId);
        $this->insertApprovedLog($userId, $orgId1, 3.0);
        $this->insertApprovedLog($userId, $orgId2, 7.0);

        $result = VolunteerCertificateService::generate($userId, ['organization_id' => $orgId1]);

        $this->assertNotNull($result);
        $this->assertEquals(3.0, $result['total_hours']);
    }

    public function testGenerateRespectsDateFilters(): void
    {
        $this->requireTables(['vol_certificates', 'vol_logs', 'vol_organizations', 'users']);

        $userId = $this->createUser('cert-datefilter');
        $orgId = $this->createOrganization($userId);

        Database::query(
            "INSERT INTO vol_logs (tenant_id, user_id, organization_id, hours, date_logged, status, created_at)
             VALUES (?, ?, ?, 2.0, '2025-01-15', 'approved', NOW())",
            [self::TENANT_ID, $userId, $orgId]
        );
        Database::query(
            "INSERT INTO vol_logs (tenant_id, user_id, organization_id, hours, date_logged, status, created_at)
             VALUES (?, ?, ?, 4.0, '2025-06-15', 'approved', NOW())",
            [self::TENANT_ID, $userId, $orgId]
        );

        $result = VolunteerCertificateService::generate($userId, [
            'date_from' => '2025-06-01',
            'date_to' => '2025-12-31',
        ]);

        $this->assertNotNull($result);
        $this->assertEquals(4.0, $result['total_hours']);
    }

    // ==========================================
    // verify
    // ==========================================

    public function testVerifyReturnsNullForEmptyCode(): void
    {
        $result = VolunteerCertificateService::verify('');
        $this->assertNull($result);
    }

    public function testVerifyReturnsNullForNonexistentCode(): void
    {
        $this->requireTables(['vol_certificates']);

        $result = VolunteerCertificateService::verify('NONEXISTENT123');
        $this->assertNull($result);
    }

    public function testVerifyReturnsCertificateForValidCode(): void
    {
        $this->requireTables(['vol_certificates', 'vol_logs', 'vol_organizations', 'users']);

        $userId = $this->createUser('cert-verify');
        $orgId = $this->createOrganization($userId);
        $this->insertApprovedLog($userId, $orgId, 10.0);

        $generated = VolunteerCertificateService::generate($userId);
        $this->assertNotNull($generated);

        $verified = VolunteerCertificateService::verify($generated['verification_code']);

        $this->assertNotNull($verified);
        $this->assertTrue($verified['verified']);
        $this->assertSame($generated['verification_code'], $verified['verification_code']);
        $this->assertEquals(10.0, $verified['total_hours']);
        $this->assertArrayHasKey('user_name', $verified);
        $this->assertArrayHasKey('organizations', $verified);
    }

    // ==========================================
    // getUserCertificates
    // ==========================================

    public function testGetUserCertificatesReturnsEmptyForNewUser(): void
    {
        $this->requireTables(['vol_certificates', 'users']);

        $userId = $this->createUser('cert-empty');
        $certs = VolunteerCertificateService::getUserCertificates($userId);

        $this->assertIsArray($certs);
        $this->assertEmpty($certs);
    }

    public function testGetUserCertificatesReturnsCertificatesAfterGeneration(): void
    {
        $this->requireTables(['vol_certificates', 'vol_logs', 'vol_organizations', 'users']);

        $userId = $this->createUser('cert-list');
        $orgId = $this->createOrganization($userId);
        $this->insertApprovedLog($userId, $orgId, 8.0);

        $generated = VolunteerCertificateService::generate($userId);
        $this->assertNotNull($generated);

        $certs = VolunteerCertificateService::getUserCertificates($userId);

        $this->assertIsArray($certs);
        $this->assertNotEmpty($certs);
        $this->assertArrayHasKey('id', $certs[0]);
        $this->assertArrayHasKey('verification_code', $certs[0]);
        $this->assertArrayHasKey('total_hours', $certs[0]);
        $this->assertArrayHasKey('organizations', $certs[0]);
        $this->assertArrayHasKey('generated_at', $certs[0]);
    }

    // ==========================================
    // generateHtml
    // ==========================================

    public function testGenerateHtmlReturnsNullForUnknownCode(): void
    {
        $this->requireTables(['vol_certificates']);

        $result = VolunteerCertificateService::generateHtml('BADCODE000');
        $this->assertNull($result);
    }

    public function testGenerateHtmlContainsExpectedContent(): void
    {
        $this->requireTables(['vol_certificates', 'vol_logs', 'vol_organizations', 'users']);

        $userId = $this->createUser('cert-html');
        $orgId = $this->createOrganization($userId);
        $this->insertApprovedLog($userId, $orgId, 6.0);

        $generated = VolunteerCertificateService::generate($userId);
        $this->assertNotNull($generated);

        $html = VolunteerCertificateService::generateHtml($generated['verification_code']);

        $this->assertNotNull($html);
        $this->assertIsString($html);
        $this->assertStringContainsString('Volunteer Impact Certificate', $html);
        $this->assertStringContainsString($generated['verification_code'], $html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
    }

    // ==========================================
    // markDownloaded
    // ==========================================

    public function testMarkDownloadedDoesNotThrowForEmptyCode(): void
    {
        VolunteerCertificateService::markDownloaded('');
        $this->assertTrue(true);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createUser(string $prefix): int
    {
        $uniq = $prefix . '-' . str_replace('.', '', (string) microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            'INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())',
            [self::TENANT_ID, $uniq . '@example.test', $uniq, 'Test', 'User', 'Test User', 0]
        );
        return (int) Database::getInstance()->lastInsertId();
    }

    private function createOrganization(int $ownerId): int
    {
        $uniq = 'org-' . str_replace('.', '', (string) microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            'INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [self::TENANT_ID, $ownerId, $uniq, 'Test organization', $uniq . '@example.test', 'approved']
        );
        return (int) Database::getInstance()->lastInsertId();
    }

    private function insertApprovedLog(int $userId, int $orgId, float $hours): void
    {
        Database::query(
            "INSERT INTO vol_logs (tenant_id, user_id, organization_id, hours, date_logged, status, created_at)
             VALUES (?, ?, ?, ?, CURDATE(), 'approved', NOW())",
            [self::TENANT_ID, $userId, $orgId, $hours]
        );
    }

    /** @param string[] $tables */
    private function requireTables(array $tables): void
    {
        foreach ($tables as $table) {
            $exists = (int) Database::query(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            )->fetchColumn();
            if ($exists === 0) {
                $this->markTestSkipped('Required table not present in test DB: ' . $table);
            }
        }
    }
}
