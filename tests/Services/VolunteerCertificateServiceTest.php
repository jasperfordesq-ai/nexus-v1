<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\VolunteerCertificateService;

class VolunteerCertificateServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    public function testGenerateReturnsNullForNoApprovedHours(): void
    {
        $this->requireTables(["vol_certificates", "vol_logs", "vol_organizations"]);
        $userId = $this->createUser("cert-no-hours");
        $result = VolunteerCertificateService::generate($userId);
        $this->assertNull($result);
        $errors = VolunteerCertificateService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame("VALIDATION_ERROR", $errors[0]["code"]);
    }
    public function testGenerateReturnsNullForNonExistentUser(): void
    {
        $this->requireTables(["vol_certificates", "vol_logs", "vol_organizations"]);
        $result = VolunteerCertificateService::generate(999999);
        $this->assertNull($result);
        $errors = VolunteerCertificateService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame("NOT_FOUND", $errors[0]["code"]);
    }

    public function testGenerateCreatesCertificateWithApprovedHours(): void
    {
        $this->requireTables(["vol_certificates", "vol_logs", "vol_organizations"]);
        $userId = $this->createUser("cert-with-hours");
        $orgId  = $this->createOrganization($userId);
        $this->createApprovedHoursLog($userId, $orgId, 5.0);
        $result = VolunteerCertificateService::generate($userId);
        $this->assertNotNull($result);
        $this->assertArrayHasKey("id", $result);
        $this->assertArrayHasKey("verification_code", $result);
        $this->assertArrayHasKey("total_hours", $result);
        $this->assertArrayHasKey("user", $result);
        $this->assertArrayHasKey("organizations", $result);
        $this->assertSame(5.0, $result["total_hours"]);
        $this->assertSame($userId, $result["user"]["id"]);
    }

    public function testVerifyReturnsNullForUnknownCode(): void
    {
        $this->requireTables(["vol_certificates", "vol_logs", "vol_organizations"]);
        $result = VolunteerCertificateService::verify("UNKNOWNCODE00000");
        $this->assertNull($result);
    }

    public function testVerifyReturnsCertificateForValidCode(): void
    {
        $this->requireTables(["vol_certificates", "vol_logs", "vol_organizations"]);
        $userId = $this->createUser("cert-verify");
        $orgId  = $this->createOrganization($userId);
        $this->createApprovedHoursLog($userId, $orgId, 3.0);
        $cert = VolunteerCertificateService::generate($userId);
        $this->assertNotNull($cert);
        $verified = VolunteerCertificateService::verify($cert["verification_code"]);
        $this->assertNotNull($verified);
        $this->assertTrue($verified["valid"]);
        $this->assertSame($cert["verification_code"], $verified["verification_code"]);
        $this->assertSame(3.0, $verified["total_hours"]);
        $this->assertArrayHasKey("user", $verified);
        $this->assertArrayHasKey("tenant", $verified);
    }
    public function testGetUserCertificatesReturnsArrayScoped(): void
    {
        $this->requireTables(["vol_certificates", "vol_logs", "vol_organizations"]);
        $userId = $this->createUser("cert-list");
        $orgId  = $this->createOrganization($userId);
        $this->createApprovedHoursLog($userId, $orgId, 4.0);
        $cert = VolunteerCertificateService::generate($userId);
        $this->assertNotNull($cert);
        $certificates = VolunteerCertificateService::getUserCertificates($userId);
        $this->assertIsArray($certificates);
        $this->assertNotEmpty($certificates);
        $codes = array_column($certificates, "verification_code");
        $this->assertContains($cert["verification_code"], $codes);
        $first = $certificates[0];
        $this->assertArrayHasKey("id", $first);
        $this->assertArrayHasKey("verification_code", $first);
        $this->assertArrayHasKey("total_hours", $first);
        $this->assertArrayHasKey("date_range", $first);
        $this->assertArrayHasKey("organizations", $first);
        $this->assertArrayHasKey("generated_at", $first);
    }

    public function testGenerateHtmlReturnsNullForUnknownCode(): void
    {
        $this->requireTables(["vol_certificates", "vol_logs", "vol_organizations"]);
        $result = VolunteerCertificateService::generateHtml("BADCODE00000000");
        $this->assertNull($result);
    }

    public function testGenerateHtmlContainsUserNameAndHours(): void
    {
        $this->requireTables(["vol_certificates", "vol_logs", "vol_organizations"]);
        $userId = $this->createUser("cert-html");
        $orgId  = $this->createOrganization($userId);
        $this->createApprovedHoursLog($userId, $orgId, 7.0);
        $cert = VolunteerCertificateService::generate($userId);
        $this->assertNotNull($cert);
        $html = VolunteerCertificateService::generateHtml($cert["verification_code"]);
        $this->assertNotNull($html);
        $this->assertIsString($html);
        $this->assertStringContainsString("Test User", $html);
        $this->assertStringContainsString("7", $html);
        $this->assertStringContainsString("<!DOCTYPE html>", $html);
    }
    private function createUser(string $prefix): int
    {
        $uniq = $prefix . "-" . str_replace(".", "", (string)microtime(true)) . "-" . random_int(1000, 9999);
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::TENANT_ID, $uniq . "@example.test", $uniq, "Test", "User", "Test User", 0]
        );
        return (int)Database::getInstance()->lastInsertId();
    }

    private function createOrganization(int $ownerId): int
    {
        $uniq = "org-" . str_replace(".", "", (string)microtime(true)) . "-" . random_int(1000, 9999);
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [self::TENANT_ID, $ownerId, $uniq, "Test organization", $uniq . "@example.test", "approved"]
        );
        return (int)Database::getInstance()->lastInsertId();
    }

    private function createApprovedHoursLog(int $userId, int $orgId, float $hours): void
    {
        Database::query(
            "INSERT INTO vol_logs (tenant_id, user_id, organization_id, hours, status, date_logged, created_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
            [self::TENANT_ID, $userId, $orgId, $hours, "approved"]
        );
    }

    private function requireTables(array $tables): void
    {
        foreach ($tables as $table) {
            $exists = (int)Database::query(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            )->fetchColumn();
            if ($exists === 0) {
                $this->markTestSkipped("Required table not present in test DB: {$table}");
            }
        }
    }
}
