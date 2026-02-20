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
use Nexus\Models\VolLog;
use Nexus\Models\VolOpportunity;

/**
 * VolLog Model Tests
 *
 * Tests volunteer hour logging, status updates, user/org retrieval,
 * and verified hours calculation.
 */
class VolLogTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testOrgId = null;
    protected static ?int $testOppId = null;

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
            [self::$testTenantId, "vol_log_test_{$timestamp}@test.com", "vol_log_test_{$timestamp}", 'VolLog', 'Tester', 'VolLog Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'approved', NOW())",
            [self::$testTenantId, self::$testUserId, "VolLog Test Org {$timestamp}", 'Test org', "vollog_{$timestamp}@test.com"]
        );
        self::$testOrgId = (int)Database::getInstance()->lastInsertId();

        // Create test opportunity
        self::$testOppId = (int)VolOpportunity::create(
            self::$testTenantId,
            self::$testUserId,
            self::$testOrgId,
            "VolLog Test Opportunity {$timestamp}",
            'Test opportunity for vol logs',
            'Dublin',
            'testing',
            date('Y-m-d', strtotime('+7 days')),
            date('Y-m-d', strtotime('+14 days'))
        );
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testUserId) {
                Database::query("DELETE FROM vol_logs WHERE user_id = ?", [self::$testUserId]);
            }
            if (self::$testOrgId) {
                Database::query("DELETE FROM vol_shifts WHERE opportunity_id IN (SELECT id FROM vol_opportunities WHERE organization_id = ?)", [self::$testOrgId]);
                Database::query("DELETE FROM vol_applications WHERE opportunity_id IN (SELECT id FROM vol_opportunities WHERE organization_id = ?)", [self::$testOrgId]);
                Database::query("DELETE FROM vol_opportunities WHERE organization_id = ?", [self::$testOrgId]);
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
    // Create Tests
    // ==========================================

    public function testCreateInsertsLog(): void
    {
        VolLog::create(
            self::$testUserId,
            self::$testOrgId,
            self::$testOppId,
            date('Y-m-d'),
            2.5,
            'Test volunteer work'
        );

        $logs = VolLog::getForUser(self::$testUserId);
        $this->assertIsArray($logs);
        $this->assertGreaterThanOrEqual(1, count($logs));
    }

    public function testCreateWithMinimalData(): void
    {
        VolLog::create(
            self::$testUserId,
            self::$testOrgId,
            self::$testOppId,
            date('Y-m-d'),
            1.0,
            'Minimal log'
        );

        $logs = VolLog::getForUser(self::$testUserId);
        $this->assertNotEmpty($logs);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsLog(): void
    {
        VolLog::create(
            self::$testUserId,
            self::$testOrgId,
            self::$testOppId,
            date('Y-m-d'),
            3.0,
            'Findable log entry'
        );
        $logId = (int)Database::getInstance()->lastInsertId();

        $log = VolLog::find($logId);
        $this->assertNotFalse($log);
        $this->assertEquals($logId, $log['id']);
        $this->assertEquals(self::$testUserId, $log['user_id']);
        $this->assertEquals(3.0, (float)$log['hours']);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $log = VolLog::find(999999999);
        $this->assertFalse($log);
    }

    // ==========================================
    // GetForUser Tests
    // ==========================================

    public function testGetForUserReturnsArray(): void
    {
        $logs = VolLog::getForUser(self::$testUserId);
        $this->assertIsArray($logs);
    }

    public function testGetForUserIncludesJoinedFields(): void
    {
        VolLog::create(
            self::$testUserId,
            self::$testOrgId,
            self::$testOppId,
            date('Y-m-d'),
            1.5,
            'Log with joins'
        );

        $logs = VolLog::getForUser(self::$testUserId);
        $this->assertNotEmpty($logs);
        $this->assertArrayHasKey('org_name', $logs[0]);
        $this->assertArrayHasKey('opp_title', $logs[0]);
    }

    public function testGetForUserReturnsEmptyForNonExistent(): void
    {
        $logs = VolLog::getForUser(999999999);
        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
    }

    // ==========================================
    // GetForOrg Tests
    // ==========================================

    public function testGetForOrgReturnsArray(): void
    {
        $logs = VolLog::getForOrg(self::$testOrgId);
        $this->assertIsArray($logs);
    }

    public function testGetForOrgIncludesUserInfo(): void
    {
        VolLog::create(
            self::$testUserId,
            self::$testOrgId,
            self::$testOppId,
            date('Y-m-d'),
            2.0,
            'Org log with user info'
        );

        $logs = VolLog::getForOrg(self::$testOrgId);
        $this->assertNotEmpty($logs);
        $this->assertArrayHasKey('first_name', $logs[0]);
        $this->assertArrayHasKey('last_name', $logs[0]);
        $this->assertArrayHasKey('email', $logs[0]);
    }

    public function testGetForOrgFiltersByStatus(): void
    {
        VolLog::create(
            self::$testUserId,
            self::$testOrgId,
            self::$testOppId,
            date('Y-m-d'),
            1.0,
            'Pending status log'
        );
        $logId = (int)Database::getInstance()->lastInsertId();
        VolLog::updateStatus($logId, 'approved');

        $approvedLogs = VolLog::getForOrg(self::$testOrgId, 'approved');
        $this->assertIsArray($approvedLogs);
        foreach ($approvedLogs as $log) {
            $this->assertEquals('approved', $log['status']);
        }
    }

    public function testGetForOrgReturnsEmptyForNonExistent(): void
    {
        $logs = VolLog::getForOrg(999999999);
        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
    }

    // ==========================================
    // UpdateStatus Tests
    // ==========================================

    public function testUpdateStatusChangesStatus(): void
    {
        VolLog::create(
            self::$testUserId,
            self::$testOrgId,
            self::$testOppId,
            date('Y-m-d'),
            2.0,
            'Status change log'
        );
        $logId = (int)Database::getInstance()->lastInsertId();

        VolLog::updateStatus($logId, 'approved');

        $log = VolLog::find($logId);
        $this->assertEquals('approved', $log['status']);
    }

    public function testUpdateStatusToDeclined(): void
    {
        VolLog::create(
            self::$testUserId,
            self::$testOrgId,
            self::$testOppId,
            date('Y-m-d'),
            1.0,
            'Declined log'
        );
        $logId = (int)Database::getInstance()->lastInsertId();

        // vol_logs.status enum: 'pending', 'approved', 'declined' (not 'rejected')
        VolLog::updateStatus($logId, 'declined');

        $log = VolLog::find($logId);
        $this->assertEquals('declined', $log['status']);
    }

    // ==========================================
    // GetTotalVerifiedHours Tests
    // ==========================================

    public function testGetTotalVerifiedHoursReturnsFloat(): void
    {
        $hours = VolLog::getTotalVerifiedHours(self::$testUserId);
        $this->assertIsFloat($hours);
    }

    public function testGetTotalVerifiedHoursReturnsZeroForNoApproved(): void
    {
        $hours = VolLog::getTotalVerifiedHours(999999999);
        $this->assertEquals(0.0, $hours);
    }

    public function testGetTotalVerifiedHoursOnlyCountsApproved(): void
    {
        // Create and approve a log
        VolLog::create(
            self::$testUserId,
            self::$testOrgId,
            self::$testOppId,
            date('Y-m-d', strtotime('-1 day')),
            5.0,
            'Approved hours for counting'
        );
        $approvedId = (int)Database::getInstance()->lastInsertId();
        VolLog::updateStatus($approvedId, 'approved');

        // Create a pending log (should NOT count)
        VolLog::create(
            self::$testUserId,
            self::$testOrgId,
            self::$testOppId,
            date('Y-m-d'),
            10.0,
            'Pending hours should not count'
        );

        $hours = VolLog::getTotalVerifiedHours(self::$testUserId);
        $this->assertGreaterThanOrEqual(5.0, $hours);
    }
}
