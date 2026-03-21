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
use App\Services\TeamDocumentService;

/**
 * TeamDocumentService Tests
 *
 * Tests document upload, listing, and deletion.
 */
class TeamDocumentServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;
    private TeamDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->service = new TeamDocumentService();
    }

    // ==========================================
    // getDocuments
    // ==========================================

    public function testGetDocumentsReturnsEmptyForNonexistentGroup(): void
    {
        $this->requireTables(['team_documents']);

        $result = $this->service->getDocuments(999999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertEmpty($result['items']);
        $this->assertFalse($result['has_more']);
    }

    public function testGetDocumentsReturnsInsertedDocuments(): void
    {
        $this->requireTables(['team_documents']);

        $groupId = 99990;
        $userId = $this->createUser('doc-list');

        // Insert a document record directly
        Database::query(
            "INSERT INTO team_documents (group_id, tenant_id, title, file_path, file_type, file_size, uploaded_by, created_at)
             VALUES (?, ?, 'Test Doc', '/tmp/test.pdf', 'application/pdf', 1024, ?, NOW())",
            [$groupId, self::TENANT_ID, $userId]
        );

        $result = $this->service->getDocuments($groupId);

        $this->assertNotEmpty($result['items']);
        $this->assertSame('Test Doc', $result['items'][0]['title']);
    }

    public function testGetDocumentsSupportsCursorPagination(): void
    {
        $this->requireTables(['team_documents']);

        $groupId = 99991;
        $userId = $this->createUser('doc-cursor');

        // Insert 3 documents
        for ($i = 1; $i <= 3; $i++) {
            Database::query(
                "INSERT INTO team_documents (group_id, tenant_id, title, file_path, file_type, file_size, uploaded_by, created_at)
                 VALUES (?, ?, ?, '/tmp/test{$i}.pdf', 'application/pdf', 1024, ?, NOW())",
                [$groupId, self::TENANT_ID, "Doc {$i}", $userId]
            );
        }

        // Get first 2
        $page1 = $this->service->getDocuments($groupId, ['limit' => 2]);

        $this->assertCount(2, $page1['items']);
        $this->assertTrue($page1['has_more']);
        $this->assertNotNull($page1['cursor']);

        // Get next page using cursor
        $page2 = $this->service->getDocuments($groupId, ['limit' => 2, 'cursor' => $page1['cursor']]);

        $this->assertCount(1, $page2['items']);
        $this->assertFalse($page2['has_more']);
    }

    // ==========================================
    // upload — validation
    // ==========================================

    public function testUploadReturnsNullWhenNoFileProvided(): void
    {
        $this->requireTables(['team_documents']);

        $result = $this->service->upload(1, 1, []);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function testUploadReturnsNullForUploadError(): void
    {
        $this->requireTables(['team_documents']);

        $result = $this->service->upload(1, 1, [
            'tmp_name' => '/tmp/nonexistent',
            'name' => 'test.pdf',
            'error' => UPLOAD_ERR_PARTIAL,
            'size' => 100,
        ]);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
    }

    // ==========================================
    // delete
    // ==========================================

    public function testDeleteReturnsFalseForNonexistentDocument(): void
    {
        $this->requireTables(['team_documents']);

        $userId = $this->createUser('doc-delbad');
        $result = $this->service->delete(999999, $userId);

        $this->assertFalse($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_NOT_FOUND', $errors[0]['code']);
    }

    public function testDeleteRemovesExistingDocument(): void
    {
        $this->requireTables(['team_documents']);

        $groupId = 99992;
        $userId = $this->createUser('doc-delete');

        Database::query(
            "INSERT INTO team_documents (group_id, tenant_id, title, file_path, file_type, file_size, uploaded_by, created_at)
             VALUES (?, ?, 'To Delete', '/tmp/deleteme.pdf', 'application/pdf', 512, ?, NOW())",
            [$groupId, self::TENANT_ID, $userId]
        );
        $docId = (int) Database::getInstance()->lastInsertId();

        $result = $this->service->delete($docId, $userId);

        $this->assertTrue($result);

        // Verify it's gone
        $remaining = $this->service->getDocuments($groupId);
        $ids = array_column($remaining['items'], 'id');
        $this->assertNotContains($docId, $ids);
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
