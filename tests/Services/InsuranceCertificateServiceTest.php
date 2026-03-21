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
use App\Services\InsuranceCertificateService;

/**
 * InsuranceCertificateService Tests
 *
 * Tests CRUD, verification, rejection, and statistics.
 */
class InsuranceCertificateServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;
    private InsuranceCertificateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->service = new InsuranceCertificateService();
    }

    // ==========================================
    // create
    // ==========================================

    public function testCreateReturnsId(): void
    {
        $this->requireTables(['insurance_certificates']);

        $userId = $this->createUser('ins-create');

        $id = $this->service->create([
            'user_id' => $userId,
            'insurance_type' => 'public_liability',
            'provider_name' => 'Acme Insurance',
            'policy_number' => 'POL-12345',
            'coverage_amount' => 1000000.00,
            'start_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'status' => 'pending',
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    // ==========================================
    // getById
    // ==========================================

    public function testGetByIdReturnsNullForNonexistent(): void
    {
        $this->requireTables(['insurance_certificates']);

        $result = $this->service->getById(999999);
        $this->assertNull($result);
    }

    public function testGetByIdReturnsCreatedRecord(): void
    {
        $this->requireTables(['insurance_certificates']);

        $userId = $this->createUser('ins-getbyid');

        $id = $this->service->create([
            'user_id' => $userId,
            'insurance_type' => 'professional_indemnity',
            'provider_name' => 'Safe Corp',
            'policy_number' => 'SC-999',
        ]);

        $record = $this->service->getById($id);

        $this->assertNotNull($record);
        $this->assertSame('professional_indemnity', $record['insurance_type']);
        $this->assertSame('Safe Corp', $record['provider_name']);
        $this->assertSame('SC-999', $record['policy_number']);
    }

    // ==========================================
    // getUserCertificates
    // ==========================================

    public function testGetUserCertificatesReturnsEmptyForNewUser(): void
    {
        $this->requireTables(['insurance_certificates']);

        $userId = $this->createUser('ins-empty');

        $result = $this->service->getUserCertificates($userId);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetUserCertificatesReturnsCreatedRecords(): void
    {
        $this->requireTables(['insurance_certificates']);

        $userId = $this->createUser('ins-list');

        $this->service->create(['user_id' => $userId, 'insurance_type' => 'public_liability']);
        $this->service->create(['user_id' => $userId, 'insurance_type' => 'employers_liability']);

        $result = $this->service->getUserCertificates($userId);

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    // ==========================================
    // getAll (admin)
    // ==========================================

    public function testGetAllReturnsPaginatedResult(): void
    {
        $this->requireTables(['insurance_certificates']);

        $result = $this->service->getAll();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('total', $result['pagination']);
        $this->assertArrayHasKey('page', $result['pagination']);
        $this->assertArrayHasKey('per_page', $result['pagination']);
    }

    public function testGetAllFiltersByStatus(): void
    {
        $this->requireTables(['insurance_certificates']);

        $userId = $this->createUser('ins-filter');

        $this->service->create(['user_id' => $userId, 'status' => 'pending']);
        $this->service->create(['user_id' => $userId, 'status' => 'verified']);

        $result = $this->service->getAll(['status' => 'pending']);

        foreach ($result['data'] as $record) {
            $this->assertSame('pending', $record['status']);
        }
    }

    // ==========================================
    // update
    // ==========================================

    public function testUpdateReturnsFalseForNonexistent(): void
    {
        $this->requireTables(['insurance_certificates']);

        $result = $this->service->update(999999, ['provider_name' => 'New']);
        $this->assertFalse($result);
    }

    public function testUpdateModifiesRecord(): void
    {
        $this->requireTables(['insurance_certificates']);

        $userId = $this->createUser('ins-update');

        $id = $this->service->create([
            'user_id' => $userId,
            'provider_name' => 'Old Provider',
        ]);

        $result = $this->service->update($id, ['provider_name' => 'New Provider']);
        $this->assertTrue($result);

        $record = $this->service->getById($id);
        $this->assertSame('New Provider', $record['provider_name']);
    }

    // ==========================================
    // verify
    // ==========================================

    public function testVerifyReturnsFalseForNonexistent(): void
    {
        $this->requireTables(['insurance_certificates']);

        $result = $this->service->verify(999999, 1);
        $this->assertFalse($result);
    }

    public function testVerifySetsStatusAndAdminId(): void
    {
        $this->requireTables(['insurance_certificates']);

        $userId = $this->createUser('ins-verify');
        $adminId = $this->createUser('ins-admin');

        $id = $this->service->create([
            'user_id' => $userId,
            'status' => 'pending',
        ]);

        $result = $this->service->verify($id, $adminId);
        $this->assertTrue($result);

        $record = $this->service->getById($id);
        $this->assertSame('verified', $record['status']);
        $this->assertEquals($adminId, (int) $record['verified_by']);
        $this->assertNotNull($record['verified_at']);
    }

    // ==========================================
    // reject
    // ==========================================

    public function testRejectReturnsFalseForNonexistent(): void
    {
        $this->requireTables(['insurance_certificates']);

        $result = $this->service->reject(999999, 1, 'Invalid document');
        $this->assertFalse($result);
    }

    public function testRejectSetsStatusAndReason(): void
    {
        $this->requireTables(['insurance_certificates']);

        $userId = $this->createUser('ins-reject');
        $adminId = $this->createUser('ins-rejadmin');

        $id = $this->service->create([
            'user_id' => $userId,
            'status' => 'pending',
        ]);

        $result = $this->service->reject($id, $adminId, 'Document is expired');
        $this->assertTrue($result);

        $record = $this->service->getById($id);
        $this->assertSame('rejected', $record['status']);
        $this->assertSame('Document is expired', $record['notes']);
    }

    // ==========================================
    // delete
    // ==========================================

    public function testDeleteReturnsFalseForNonexistent(): void
    {
        $this->requireTables(['insurance_certificates']);

        $result = $this->service->delete(999999);
        $this->assertFalse($result);
    }

    public function testDeleteRemovesRecord(): void
    {
        $this->requireTables(['insurance_certificates']);

        $userId = $this->createUser('ins-delete');

        $id = $this->service->create([
            'user_id' => $userId,
            'provider_name' => 'Delete Me Inc',
        ]);

        $result = $this->service->delete($id);
        $this->assertTrue($result);

        $this->assertNull($this->service->getById($id));
    }

    // ==========================================
    // getStats
    // ==========================================

    public function testGetStatsReturnsAllStatusCounts(): void
    {
        $this->requireTables(['insurance_certificates']);

        $stats = $this->service->getStats();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('submitted', $stats);
        $this->assertArrayHasKey('verified', $stats);
        $this->assertArrayHasKey('expired', $stats);
        $this->assertArrayHasKey('rejected', $stats);
        $this->assertArrayHasKey('revoked', $stats);
        $this->assertArrayHasKey('expiring_soon', $stats);

        // All values should be non-negative integers
        foreach ($stats as $key => $value) {
            $this->assertIsInt($value, "Stats key '{$key}' should be an integer");
            $this->assertGreaterThanOrEqual(0, $value);
        }
    }

    public function testGetStatsReflectsCreatedRecords(): void
    {
        $this->requireTables(['insurance_certificates']);

        $userId = $this->createUser('ins-stats');

        $statsBefore = $this->service->getStats();

        $this->service->create(['user_id' => $userId, 'status' => 'pending']);
        $this->service->create(['user_id' => $userId, 'status' => 'verified']);

        $statsAfter = $this->service->getStats();

        $this->assertGreaterThanOrEqual($statsBefore['total'] + 2, $statsAfter['total']);
        $this->assertGreaterThanOrEqual($statsBefore['pending'] + 1, $statsAfter['pending']);
        $this->assertGreaterThanOrEqual($statsBefore['verified'] + 1, $statsAfter['verified']);
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
