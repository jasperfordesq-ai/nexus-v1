<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\OrgTransaction;

/**
 * OrgTransaction Model Tests
 *
 * Tests transaction logging, find by ID, org retrieval,
 * counting, and monthly stats.
 */
class OrgTransactionTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testOrgId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "org_txn_test_{$timestamp}@test.com", "org_txn_test_{$timestamp}", 'OrgTxn', 'Tester', 'OrgTxn Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'approved', NOW())",
            [self::$testTenantId, self::$testUserId, "OrgTxn Test Org {$timestamp}", 'Org for transaction tests', "orgtxn_{$timestamp}@test.com"]
        );
        self::$testOrgId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testOrgId) {
                Database::query("DELETE FROM org_transactions WHERE organization_id = ?", [self::$testOrgId]);
                Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
            }
            if (self::$testUserId) {
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            }
        } catch (\Exception $e) {
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // Log Tests
    // ==========================================

    public function testLogReturnsId(): void
    {
        $id = OrgTransaction::log(
            self::$testOrgId,
            'user',
            self::$testUserId,
            'organization',
            self::$testOrgId,
            5.0,
            'Test transaction'
        );

        $this->assertNotEmpty($id);
        $this->assertGreaterThan(0, (int)$id);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsTransaction(): void
    {
        $id = OrgTransaction::log(
            self::$testOrgId,
            'user',
            self::$testUserId,
            'organization',
            self::$testOrgId,
            3.0,
            'Findable transaction'
        );

        $txn = OrgTransaction::find($id);
        $this->assertNotFalse($txn);
        $this->assertEquals($id, $txn['id']);
        $this->assertEquals(3.0, (float)$txn['amount']);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $txn = OrgTransaction::find(999999999);
        $this->assertFalse($txn);
    }

    // ==========================================
    // GetForOrganization Tests
    // ==========================================

    public function testGetForOrganizationReturnsArray(): void
    {
        $transactions = OrgTransaction::getForOrganization(self::$testOrgId);
        $this->assertIsArray($transactions);
        $this->assertNotEmpty($transactions);
    }

    public function testGetForOrganizationIncludesNames(): void
    {
        $transactions = OrgTransaction::getForOrganization(self::$testOrgId);
        if (!empty($transactions)) {
            $this->assertArrayHasKey('sender_name', $transactions[0]);
            $this->assertArrayHasKey('receiver_name', $transactions[0]);
        }
    }

    public function testGetForOrganizationReturnsEmptyForNonExistent(): void
    {
        $transactions = OrgTransaction::getForOrganization(999999999);
        $this->assertIsArray($transactions);
        $this->assertEmpty($transactions);
    }

    // ==========================================
    // CountForOrganization Tests
    // ==========================================

    public function testCountForOrganizationReturnsInt(): void
    {
        $count = OrgTransaction::countForOrganization(self::$testOrgId);
        $this->assertIsInt($count);
        $this->assertGreaterThan(0, $count);
    }

    public function testCountForOrganizationReturnsZeroForNonExistent(): void
    {
        $count = OrgTransaction::countForOrganization(999999999);
        $this->assertEquals(0, $count);
    }

    // ==========================================
    // GetMonthlyStats Tests
    // ==========================================

    public function testGetMonthlyStatsReturnsArray(): void
    {
        $stats = OrgTransaction::getMonthlyStats(self::$testOrgId);
        $this->assertIsArray($stats);
    }

    public function testGetMonthlyStatsReturnsEmptyForNonExistent(): void
    {
        $stats = OrgTransaction::getMonthlyStats(999999999);
        $this->assertIsArray($stats);
        $this->assertEmpty($stats);
    }
}
