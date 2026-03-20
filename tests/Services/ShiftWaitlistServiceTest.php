<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\ShiftWaitlistService;

/**
 * ShiftWaitlistService Tests
 *
 * Tests join, leave, position tracking, spot opening notification,
 * and user waitlist listing.
 */
class ShiftWaitlistServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ==========================================
    // join
    // ==========================================

    public function testJoinCreatesWaitlistEntry(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-owner');
        $userId  = $this->createUser('wl-user');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $entryId = ShiftWaitlistService::join($shiftId, $userId);

        $this->assertNotNull($entryId);
        $this->assertIsInt($entryId);
        $this->assertGreaterThan(0, $entryId);
    }

    public function testJoinIsIdempotentPreventsDoubleEntry(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-owner');
        $userId  = $this->createUser('wl-user');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $entryId1 = ShiftWaitlistService::join($shiftId, $userId);
        $this->assertNotNull($entryId1);

        // Second join must fail
        $entryId2 = ShiftWaitlistService::join($shiftId, $userId);
        $this->assertNull($entryId2);
        $errors = ShiftWaitlistService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('ALREADY_EXISTS', $errors[0]['code']);
    }

    public function testJoinFailsIfAlreadySignedUp(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-owner');
        $userId  = $this->createUser('wl-user');
        [$opportunityId, $shiftId] = $this->createOpportunityAndShift($ownerId);

        // Approve the user for the shift directly
        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $userId, $shiftId]
        );

        $entryId = ShiftWaitlistService::join($shiftId, $userId);

        $this->assertNull($entryId);
        $errors = ShiftWaitlistService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('ALREADY_EXISTS', $errors[0]['code']);
    }

    public function testJoinFailsForPastShift(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-owner');
        $userId  = $this->createUser('wl-user');
        [$opportunityId] = $this->createOpportunityAndShift($ownerId);

        // Create a shift in the past
        Database::query(
            'INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 22 HOUR), 5, NOW())',
            [self::TENANT_ID, $opportunityId]
        );
        $pastShiftId = (int)Database::getInstance()->lastInsertId();

        $entryId = ShiftWaitlistService::join($pastShiftId, $userId);

        $this->assertNull($entryId);
        $errors = ShiftWaitlistService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
    }

    // ==========================================
    // leave
    // ==========================================

    public function testLeaveRemovesFromWaitlist(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-owner');
        $userId  = $this->createUser('wl-user');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        ShiftWaitlistService::join($shiftId, $userId);

        $result = ShiftWaitlistService::leave($shiftId, $userId);

        $this->assertTrue($result);

        // Confirm not on waitlist
        $count = (int)Database::query(
            "SELECT COUNT(*) FROM vol_shift_waitlist WHERE shift_id = ? AND user_id = ? AND status = 'waiting' AND tenant_id = ?",
            [$shiftId, $userId, self::TENANT_ID]
        )->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testLeaveFailsIfNotOnWaitlist(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-owner');
        $userId  = $this->createUser('wl-user');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $result = ShiftWaitlistService::leave($shiftId, $userId);

        $this->assertFalse($result);
        $errors = ShiftWaitlistService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('NOT_FOUND', $errors[0]['code']);
    }

    // ==========================================
    // getWaitlist
    // ==========================================

    public function testGetWaitlistReturnsUsers(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-owner');
        $userId  = $this->createUser('wl-user');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        ShiftWaitlistService::join($shiftId, $userId);

        $waitlist = ShiftWaitlistService::getWaitlist($shiftId);

        $this->assertIsArray($waitlist);
        $this->assertNotEmpty($waitlist);

        $userIds = array_column(array_column($waitlist, 'user'), 'id');
        $this->assertContains($userId, $userIds);
    }

    // ==========================================
    // getUserPosition
    // ==========================================

    public function testGetUserPositionReturnsPositionData(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-owner');
        $userId  = $this->createUser('wl-user');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        ShiftWaitlistService::join($shiftId, $userId);

        $position = ShiftWaitlistService::getUserPosition($shiftId, $userId);

        $this->assertNotNull($position);
        $this->assertIsArray($position);
        $this->assertArrayHasKey('id', $position);
        $this->assertArrayHasKey('position', $position);
        $this->assertArrayHasKey('total_waiting', $position);
        $this->assertSame(1, $position['position']);
        $this->assertSame(1, $position['total_waiting']);
    }

    public function testGetUserPositionReturnsNullIfNotOnWaitlist(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-owner');
        $userId  = $this->createUser('wl-user');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $position = ShiftWaitlistService::getUserPosition($shiftId, $userId);

        $this->assertNull($position);
    }

    // ==========================================
    // processSpotOpening
    // ==========================================

    public function testProcessSpotOpeningReturnsFalseWhenEmpty(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-owner');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $result = ShiftWaitlistService::processSpotOpening($shiftId);

        $this->assertFalse($result);
    }

    public function testProcessSpotOpeningNotifiesNextPerson(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-owner');
        $userId  = $this->createUser('wl-user');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $entryId = ShiftWaitlistService::join($shiftId, $userId);
        $this->assertNotNull($entryId);

        $result = ShiftWaitlistService::processSpotOpening($shiftId);

        $this->assertTrue($result);

        // Verify status changed to 'notified'
        $status = (string)Database::query(
            'SELECT status FROM vol_shift_waitlist WHERE id = ? AND tenant_id = ?',
            [$entryId, self::TENANT_ID]
        )->fetchColumn();
        $this->assertSame('notified', $status);
    }

    // ==========================================
    // getUserWaitlists
    // ==========================================

    public function testGetUserWaitlistsReturnsCorrectStructure(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-owner');
        $userId  = $this->createUser('wl-user');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        ShiftWaitlistService::join($shiftId, $userId);

        $waitlists = ShiftWaitlistService::getUserWaitlists($userId);

        $this->assertIsArray($waitlists);
        $this->assertNotEmpty($waitlists);

        $entry = $waitlists[0];
        $this->assertArrayHasKey('id', $entry);
        $this->assertArrayHasKey('position', $entry);
        $this->assertArrayHasKey('shift', $entry);
        $this->assertArrayHasKey('opportunity', $entry);
        $this->assertArrayHasKey('organization', $entry);
        $this->assertArrayHasKey('id', $entry['shift']);
        $this->assertArrayHasKey('title', $entry['opportunity']);
        $this->assertArrayHasKey('name', $entry['organization']);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createUser(string $prefix): int
    {
        $uniq = $prefix . '-' . str_replace('.', '', (string)microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            'INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())',
            [self::TENANT_ID, $uniq . '@example.test', $uniq, 'Test', 'User', 'Test User', 0]
        );
        return (int)Database::getInstance()->lastInsertId();
    }

    private function createOrganization(int $ownerId, string $status = 'approved'): int
    {
        $uniq = 'org-' . str_replace('.', '', (string)microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            'INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [self::TENANT_ID, $ownerId, $uniq, 'Test organization description', $uniq . '@example.test', $status]
        );
        return (int)Database::getInstance()->lastInsertId();
    }

    /** @return array{0:int,1:int} [opportunityId, shiftId] */
    private function createOpportunityAndShift(int $ownerId): array
    {
        $orgId = $this->createOrganization($ownerId, 'approved');
        $uniq  = 'opp-' . str_replace('.', '', (string)microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            "INSERT INTO vol_opportunities (tenant_id, organization_id, created_by, title, description, location, status, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 'open', 1, NOW())",
            [self::TENANT_ID, $orgId, $ownerId, $uniq, 'Test opportunity', 'Remote']
        );
        $opportunityId = (int)Database::getInstance()->lastInsertId();
        Database::query(
            'INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 3 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 3 DAY), INTERVAL 2 HOUR), 5, NOW())',
            [self::TENANT_ID, $opportunityId]
        );
        $shiftId = (int)Database::getInstance()->lastInsertId();
        return [$opportunityId, $shiftId];
    }

    /** @param string[] $tables */
    private function requireTables(array $tables): void
    {
        foreach ($tables as $table) {
            $exists = (int)Database::query(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            )->fetchColumn();
            if ($exists === 0) {
                $this->markTestSkipped('Required table not present in test DB: ' . $table);
            }
        }
    }
}
