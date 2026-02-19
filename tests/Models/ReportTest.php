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
use Nexus\Models\Report;

/**
 * Report Model Tests
 *
 * Tests content/user report creation, report status tracking,
 * resolution workflow, and tenant scoping.
 */
class ReportTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testTargetUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $timestamp = time();

        // Create reporter user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [
                self::$testTenantId,
                "report_test_reporter_{$timestamp}@test.com",
                "report_reporter_{$timestamp}",
                'Report',
                'Reporter',
                'Report Reporter'
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create target user (reported user)
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [
                self::$testTenantId,
                "report_test_target_{$timestamp}@test.com",
                "report_target_{$timestamp}",
                'Report',
                'Target',
                'Report Target'
            ]
        );
        self::$testTargetUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        $userIds = array_filter([self::$testUserId, self::$testTargetUserId]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM reports WHERE reporter_id = ?", [$uid]);
                Database::query("DELETE FROM users WHERE id = ?", [$uid]);
            } catch (\Exception $e) {
            }
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);

        // Clean reports before each test
        try {
            Database::query("DELETE FROM reports WHERE reporter_id = ?", [self::$testUserId]);
        } catch (\Exception $e) {
        }
    }

    // ==========================================
    // Create Report Tests
    // ==========================================

    public function testCreateUserReport(): void
    {
        Report::create(
            self::$testTenantId,
            self::$testUserId,
            'user',
            self::$testTargetUserId,
            'Inappropriate behavior'
        );

        $report = Database::query(
            "SELECT * FROM reports WHERE reporter_id = ? AND target_type = 'user' ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($report);
        $this->assertEquals(self::$testTenantId, $report['tenant_id']);
        $this->assertEquals(self::$testUserId, $report['reporter_id']);
        $this->assertEquals('user', $report['target_type']);
        $this->assertEquals(self::$testTargetUserId, $report['target_id']);
        $this->assertEquals('Inappropriate behavior', $report['reason']);
    }

    public function testCreateContentReport(): void
    {
        $fakeListingId = 12345;

        Report::create(
            self::$testTenantId,
            self::$testUserId,
            'listing',
            $fakeListingId,
            'Spam content'
        );

        $report = Database::query(
            "SELECT * FROM reports WHERE reporter_id = ? AND target_type = 'listing' ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($report);
        $this->assertEquals('listing', $report['target_type']);
        $this->assertEquals($fakeListingId, $report['target_id']);
        $this->assertEquals('Spam content', $report['reason']);
    }

    public function testCreateReportDefaultsToOpenStatus(): void
    {
        Report::create(
            self::$testTenantId,
            self::$testUserId,
            'user',
            self::$testTargetUserId,
            'Test reason'
        );

        $report = Database::query(
            "SELECT status FROM reports WHERE reporter_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertEquals('open', $report['status']);
    }

    public function testCreateReportSetsCreatedAt(): void
    {
        Report::create(
            self::$testTenantId,
            self::$testUserId,
            'user',
            self::$testTargetUserId,
            'Timestamp test'
        );

        $report = Database::query(
            "SELECT created_at FROM reports WHERE reporter_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotNull($report['created_at']);
        // Should be within last minute
        $createdAt = strtotime($report['created_at']);
        $this->assertGreaterThan(time() - 60, $createdAt);
    }

    public function testCreateReportWithDifferentTargetTypes(): void
    {
        $targetTypes = ['user', 'listing', 'comment', 'post', 'message'];

        foreach ($targetTypes as $type) {
            Report::create(
                self::$testTenantId,
                self::$testUserId,
                $type,
                1,
                "Report for {$type}"
            );
        }

        $count = Database::query(
            "SELECT COUNT(*) as c FROM reports WHERE reporter_id = ?",
            [self::$testUserId]
        )->fetch()['c'];

        $this->assertEquals(count($targetTypes), (int)$count);
    }

    // ==========================================
    // Get Open Reports Tests — Tenant Scoping
    // ==========================================

    public function testGetOpenReturnsOpenReports(): void
    {
        Report::create(self::$testTenantId, self::$testUserId, 'user', self::$testTargetUserId, 'Open report 1');
        Report::create(self::$testTenantId, self::$testUserId, 'user', self::$testTargetUserId, 'Open report 2');

        $openReports = Report::getOpen(self::$testTenantId);

        $this->assertIsArray($openReports);
        $this->assertGreaterThanOrEqual(2, count($openReports));
    }

    public function testGetOpenIncludesReporterName(): void
    {
        Report::create(self::$testTenantId, self::$testUserId, 'user', self::$testTargetUserId, 'Reporter name test');

        $openReports = Report::getOpen(self::$testTenantId);

        $this->assertNotEmpty($openReports);
        $this->assertArrayHasKey('reporter_name', $openReports[0]);
    }

    public function testGetOpenExcludesResolvedReports(): void
    {
        Report::create(self::$testTenantId, self::$testUserId, 'user', self::$testTargetUserId, 'To be resolved');

        $report = Database::query(
            "SELECT id FROM reports WHERE reporter_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        Report::resolve($report['id'], 'resolved');

        $openReports = Report::getOpen(self::$testTenantId);
        $resolvedFound = false;
        foreach ($openReports as $r) {
            if ($r['id'] == $report['id']) {
                $resolvedFound = true;
                break;
            }
        }

        $this->assertFalse($resolvedFound, 'Resolved reports should not appear in getOpen');
    }

    public function testGetOpenExcludesDismissedReports(): void
    {
        Report::create(self::$testTenantId, self::$testUserId, 'user', self::$testTargetUserId, 'To be dismissed');

        $report = Database::query(
            "SELECT id FROM reports WHERE reporter_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        Report::resolve($report['id'], 'dismissed');

        $openReports = Report::getOpen(self::$testTenantId);
        $dismissedFound = false;
        foreach ($openReports as $r) {
            if ($r['id'] == $report['id']) {
                $dismissedFound = true;
                break;
            }
        }

        $this->assertFalse($dismissedFound, 'Dismissed reports should not appear in getOpen');
    }

    public function testGetOpenScopesByTenant(): void
    {
        Report::create(self::$testTenantId, self::$testUserId, 'user', 1, 'Tenant scoped report');

        $openReports = Report::getOpen(self::$testTenantId);

        foreach ($openReports as $report) {
            $this->assertEquals(self::$testTenantId, $report['tenant_id']);
        }
    }

    public function testGetOpenReturnsEmptyForDifferentTenant(): void
    {
        $openReports = Report::getOpen(999999);

        $this->assertIsArray($openReports);
        $this->assertEmpty($openReports);
    }

    public function testGetOpenOrdersByCreatedAtDesc(): void
    {
        Report::create(self::$testTenantId, self::$testUserId, 'user', 1, 'First report');
        usleep(10000);
        Report::create(self::$testTenantId, self::$testUserId, 'user', 2, 'Second report');

        $openReports = Report::getOpen(self::$testTenantId);

        if (count($openReports) >= 2) {
            $first = strtotime($openReports[0]['created_at']);
            $second = strtotime($openReports[1]['created_at']);
            $this->assertGreaterThanOrEqual($second, $first, 'Reports should be ordered by created_at DESC');
        }
    }

    // ==========================================
    // Resolve Report Tests — Status Tracking
    // ==========================================

    public function testResolveReportSetsResolvedStatus(): void
    {
        Report::create(self::$testTenantId, self::$testUserId, 'user', self::$testTargetUserId, 'Resolve test');

        $report = Database::query(
            "SELECT id FROM reports WHERE reporter_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        Report::resolve($report['id'], 'resolved');

        $updated = Database::query(
            "SELECT status FROM reports WHERE id = ?",
            [$report['id']]
        )->fetch();

        $this->assertEquals('resolved', $updated['status']);
    }

    public function testResolveReportSetsDismissedStatus(): void
    {
        Report::create(self::$testTenantId, self::$testUserId, 'user', self::$testTargetUserId, 'Dismiss test');

        $report = Database::query(
            "SELECT id FROM reports WHERE reporter_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        Report::resolve($report['id'], 'dismissed');

        $updated = Database::query(
            "SELECT status FROM reports WHERE id = ?",
            [$report['id']]
        )->fetch();

        $this->assertEquals('dismissed', $updated['status']);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCreateReportWithLongReason(): void
    {
        $longReason = str_repeat('This is a detailed report reason. ', 50);

        Report::create(
            self::$testTenantId,
            self::$testUserId,
            'user',
            self::$testTargetUserId,
            $longReason
        );

        $report = Database::query(
            "SELECT reason FROM reports WHERE reporter_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($report);
        $this->assertNotEmpty($report['reason']);
    }

    public function testCreateReportWithSpecialCharactersInReason(): void
    {
        $reason = 'Report with <script>alert("xss")</script> & "special" characters';

        Report::create(
            self::$testTenantId,
            self::$testUserId,
            'user',
            self::$testTargetUserId,
            $reason
        );

        $report = Database::query(
            "SELECT reason FROM reports WHERE reporter_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($report);
        $this->assertStringContainsString('special', $report['reason']);
    }
}
