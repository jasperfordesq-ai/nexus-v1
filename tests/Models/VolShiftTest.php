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
use Nexus\Models\VolShift;
use Nexus\Models\VolOpportunity;

/**
 * VolShift Model Tests
 *
 * Tests volunteer shift creation, retrieval by opportunity,
 * find by ID, and deletion.
 */
class VolShiftTest extends DatabaseTestCase
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
            [self::$testTenantId, "vol_shift_test_{$timestamp}@test.com", "vol_shift_test_{$timestamp}", 'VolShift', 'Tester', 'VolShift Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'approved', NOW())",
            [self::$testTenantId, self::$testUserId, "Shift Test Org {$timestamp}", 'Org for shift tests', "shift_{$timestamp}@test.com"]
        );
        self::$testOrgId = (int)Database::getInstance()->lastInsertId();

        // Create test opportunity
        self::$testOppId = (int)VolOpportunity::create(
            self::$testTenantId,
            self::$testUserId,
            self::$testOrgId,
            "Shift Test Opportunity {$timestamp}",
            'Opportunity for shift tests',
            'Dublin',
            'testing',
            date('Y-m-d', strtotime('+7 days')),
            date('Y-m-d', strtotime('+14 days'))
        );
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testOppId) {
                Database::query("DELETE FROM vol_shifts WHERE opportunity_id = ?", [self::$testOppId]);
            }
            if (self::$testOrgId) {
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

    public function testCreateInsertsShift(): void
    {
        $startTime = date('Y-m-d H:i:s', strtotime('+7 days 09:00'));
        $endTime = date('Y-m-d H:i:s', strtotime('+7 days 12:00'));

        VolShift::create(self::$testOppId, $startTime, $endTime, 10);

        $shifts = VolShift::getForOpportunity(self::$testOppId);
        $this->assertIsArray($shifts);
        $this->assertGreaterThanOrEqual(1, count($shifts));
    }

    public function testCreateWithDifferentCapacities(): void
    {
        $startTime = date('Y-m-d H:i:s', strtotime('+8 days 09:00'));
        $endTime = date('Y-m-d H:i:s', strtotime('+8 days 17:00'));

        VolShift::create(self::$testOppId, $startTime, $endTime, 50);
        $shiftId = (int)Database::getInstance()->lastInsertId();

        $shift = VolShift::find($shiftId);
        $this->assertNotFalse($shift);
        $this->assertEquals(50, (int)$shift['capacity']);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsShift(): void
    {
        $startTime = date('Y-m-d H:i:s', strtotime('+9 days 10:00'));
        $endTime = date('Y-m-d H:i:s', strtotime('+9 days 14:00'));

        VolShift::create(self::$testOppId, $startTime, $endTime, 5);
        $shiftId = (int)Database::getInstance()->lastInsertId();

        $shift = VolShift::find($shiftId);
        $this->assertNotFalse($shift);
        $this->assertEquals($shiftId, $shift['id']);
        $this->assertEquals(self::$testOppId, $shift['opportunity_id']);
        $this->assertEquals(5, (int)$shift['capacity']);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $shift = VolShift::find(999999999);
        $this->assertFalse($shift);
    }

    // ==========================================
    // GetForOpportunity Tests
    // ==========================================

    public function testGetForOpportunityReturnsArray(): void
    {
        $shifts = VolShift::getForOpportunity(self::$testOppId);
        $this->assertIsArray($shifts);
    }

    public function testGetForOpportunityReturnsAllShifts(): void
    {
        // Create multiple shifts for the same opportunity
        $startTime1 = date('Y-m-d H:i:s', strtotime('+10 days 08:00'));
        $endTime1 = date('Y-m-d H:i:s', strtotime('+10 days 12:00'));
        VolShift::create(self::$testOppId, $startTime1, $endTime1, 5);

        $startTime2 = date('Y-m-d H:i:s', strtotime('+10 days 13:00'));
        $endTime2 = date('Y-m-d H:i:s', strtotime('+10 days 17:00'));
        VolShift::create(self::$testOppId, $startTime2, $endTime2, 5);

        $shifts = VolShift::getForOpportunity(self::$testOppId);
        $this->assertGreaterThanOrEqual(2, count($shifts));
    }

    public function testGetForOpportunityOrdersByStartTime(): void
    {
        $shifts = VolShift::getForOpportunity(self::$testOppId);

        if (count($shifts) >= 2) {
            for ($i = 1; $i < count($shifts); $i++) {
                $this->assertGreaterThanOrEqual(
                    $shifts[$i - 1]['start_time'],
                    $shifts[$i]['start_time'],
                    'Shifts should be ordered by start_time ASC'
                );
            }
        }
    }

    public function testGetForOpportunityReturnsEmptyForNonExistent(): void
    {
        $shifts = VolShift::getForOpportunity(999999999);
        $this->assertIsArray($shifts);
        $this->assertEmpty($shifts);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesShift(): void
    {
        $startTime = date('Y-m-d H:i:s', strtotime('+11 days 09:00'));
        $endTime = date('Y-m-d H:i:s', strtotime('+11 days 12:00'));

        VolShift::create(self::$testOppId, $startTime, $endTime, 3);
        $shiftId = (int)Database::getInstance()->lastInsertId();

        // Verify it exists
        $shift = VolShift::find($shiftId);
        $this->assertNotFalse($shift);

        // Delete it
        VolShift::delete($shiftId);

        // Verify it's gone
        $shift = VolShift::find($shiftId);
        $this->assertFalse($shift);
    }

    public function testDeleteDoesNotAffectOtherShifts(): void
    {
        $startTime1 = date('Y-m-d H:i:s', strtotime('+12 days 09:00'));
        $endTime1 = date('Y-m-d H:i:s', strtotime('+12 days 12:00'));
        VolShift::create(self::$testOppId, $startTime1, $endTime1, 5);
        $keepId = (int)Database::getInstance()->lastInsertId();

        $startTime2 = date('Y-m-d H:i:s', strtotime('+12 days 13:00'));
        $endTime2 = date('Y-m-d H:i:s', strtotime('+12 days 16:00'));
        VolShift::create(self::$testOppId, $startTime2, $endTime2, 5);
        $deleteId = (int)Database::getInstance()->lastInsertId();

        VolShift::delete($deleteId);

        // The other shift should still exist
        $shift = VolShift::find($keepId);
        $this->assertNotFalse($shift);
    }
}
