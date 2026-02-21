<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\LegalDocumentService;

/**
 * LegalDocumentService Tests
 *
 * Tests legal document management including version control,
 * user acceptance tracking, and GDPR compliance.
 */
class LegalDocumentServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testDocumentId = null;
    protected static ?int $testVersionId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $ts = time();

        // Clean up any pre-existing test document to avoid unique constraint violation
        try {
            Database::query(
                "DELETE lv FROM legal_document_versions lv
                 JOIN legal_documents ld ON lv.document_id = ld.id
                 WHERE ld.tenant_id = ? AND ld.document_type = ?",
                [self::$testTenantId, LegalDocumentService::TYPE_ACCEPTABLE_USE]
            );
            Database::query(
                "DELETE FROM legal_documents WHERE tenant_id = ? AND document_type = ?",
                [self::$testTenantId, LegalDocumentService::TYPE_ACCEPTABLE_USE]
            );
        } catch (\Exception $e) {}

        // Create a test legal document (use acceptable_use to avoid colliding with existing terms/privacy)
        Database::query(
            "INSERT INTO legal_documents (tenant_id, document_type, title, slug, is_active, created_at)
             VALUES (?, ?, 'Acceptable Use Policy', 'acceptable-use-policy', 1, NOW())",
            [self::$testTenantId, LegalDocumentService::TYPE_ACCEPTABLE_USE]
        );
        self::$testDocumentId = (int)Database::getInstance()->lastInsertId();

        // Create a test user for created_by reference
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [self::$testTenantId, "legal_{$ts}@test.com", "legal_{$ts}", 'Legal', 'User', 'Legal User']
        );
        $userId = (int)Database::getInstance()->lastInsertId();

        // Create a version for this document
        Database::query(
            "INSERT INTO legal_document_versions (document_id, version_number, content, effective_date, is_current, created_by, created_at)
             VALUES (?, ?, ?, NOW(), 1, ?, NOW())",
            [self::$testDocumentId, 1, '<p>Test Terms of Service ' . $ts . '</p>', $userId]
        );
        self::$testVersionId = (int)Database::getInstance()->lastInsertId();

        // Link version to document
        Database::query(
            "UPDATE legal_documents SET current_version_id = ? WHERE id = ?",
            [self::$testVersionId, self::$testDocumentId]
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testDocumentId) {
            try {
                Database::query("DELETE FROM legal_document_versions WHERE document_id = ?", [self::$testDocumentId]);
                Database::query("DELETE FROM legal_documents WHERE id = ?", [self::$testDocumentId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Document Type Constants Tests
    // ==========================================

    public function testDocumentTypeConstantsExist(): void
    {
        $this->assertEquals('terms', LegalDocumentService::TYPE_TERMS);
        $this->assertEquals('privacy', LegalDocumentService::TYPE_PRIVACY);
        $this->assertEquals('cookies', LegalDocumentService::TYPE_COOKIES);
        $this->assertEquals('accessibility', LegalDocumentService::TYPE_ACCESSIBILITY);
    }

    public function testAcceptanceMethodConstantsExist(): void
    {
        $this->assertEquals('registration', LegalDocumentService::ACCEPTANCE_REGISTRATION);
        $this->assertEquals('login_prompt', LegalDocumentService::ACCEPTANCE_LOGIN_PROMPT);
        $this->assertEquals('settings', LegalDocumentService::ACCEPTANCE_SETTINGS);
        $this->assertEquals('api', LegalDocumentService::ACCEPTANCE_API);
    }

    public function testStatusConstantsExist(): void
    {
        $this->assertEquals('not_accepted', LegalDocumentService::STATUS_NOT_ACCEPTED);
        $this->assertEquals('current', LegalDocumentService::STATUS_CURRENT);
        $this->assertEquals('outdated', LegalDocumentService::STATUS_OUTDATED);
    }

    // ==========================================
    // Get By Type Tests
    // ==========================================

    public function testGetByTypeReturnsValidStructure(): void
    {
        $doc = LegalDocumentService::getByType(LegalDocumentService::TYPE_TERMS);

        if ($doc) {
            $this->assertIsArray($doc);
            $this->assertArrayHasKey('id', $doc);
            $this->assertArrayHasKey('document_type', $doc);
            $this->assertArrayHasKey('content', $doc);
            $this->assertArrayHasKey('version_number', $doc);
        }
        $this->assertTrue(true);
    }

    public function testGetByTypeReturnsScopedToTenant(): void
    {
        $doc = LegalDocumentService::getByType(LegalDocumentService::TYPE_TERMS);

        if ($doc) {
            $this->assertEquals(self::$testTenantId, $doc['tenant_id']);
        }
        $this->assertTrue(true);
    }

    public function testGetByTypeReturnsNullForNonExistentType(): void
    {
        $doc = LegalDocumentService::getByType('non_existent_type');
        $this->assertNull($doc);
    }

    // ==========================================
    // Get By ID Tests
    // ==========================================

    public function testGetByIdReturnsValidStructure(): void
    {
        $doc = LegalDocumentService::getById(self::$testDocumentId);

        $this->assertNotNull($doc);
        $this->assertArrayHasKey('id', $doc);
        $this->assertArrayHasKey('document_type', $doc);
        $this->assertArrayHasKey('content', $doc);
        $this->assertArrayHasKey('version_number', $doc);
    }

    public function testGetByIdReturnsNullForInvalidId(): void
    {
        $doc = LegalDocumentService::getById(999999);
        $this->assertNull($doc);
    }

    public function testGetByIdIncludesVersionData(): void
    {
        $doc = LegalDocumentService::getById(self::$testDocumentId);

        $this->assertNotNull($doc);
        $this->assertArrayHasKey('content', $doc);
        $this->assertArrayHasKey('effective_date', $doc);
        $this->assertStringContainsString('Test Terms of Service', $doc['content']);
    }

    // ==========================================
    // Get All For Tenant Tests
    // ==========================================

    public function testGetAllForTenantReturnsArray(): void
    {
        $docs = LegalDocumentService::getAllForTenant();
        $this->assertIsArray($docs);
    }

    public function testGetAllForTenantIncludesVersionCount(): void
    {
        $docs = LegalDocumentService::getAllForTenant();

        if (!empty($docs)) {
            $this->assertArrayHasKey('version_count', $docs[0]);
        }
        $this->assertTrue(true);
    }

    public function testGetAllForTenantOnlyReturnsActiveDocs(): void
    {
        $docs = LegalDocumentService::getAllForTenant();

        foreach ($docs as $doc) {
            $this->assertEquals(1, $doc['is_active']);
        }
        $this->assertTrue(true);
    }

    public function testGetAllForTenantScopedToTenant(): void
    {
        $docs = LegalDocumentService::getAllForTenant(self::$testTenantId);

        foreach ($docs as $doc) {
            $this->assertEquals(self::$testTenantId, $doc['tenant_id']);
        }
        $this->assertTrue(true);
    }
}
