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
use App\Services\ShiftSwapService;

/**
 * ShiftSwapService Tests
 *
 * Tests shift swap request creation, response, and cancellation flows.
 */
class ShiftSwapServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ==========================================
    // requestSwap - validation
    // ==========================================

    public function testRequestSwapRequiresAllFields(): void
    {
        $this->requireTables(['vol_shift_swap_requests']);

        $fromUserId = $this->createUser('swap-from');
        [, $fromShiftId] = $this->createOpportunityAndShift($fromUserId);

        // Missing to_user_id
        $result = ShiftSwapService::requestSwap($fromUserId, [
            'from_shift_id' => $fromShiftId,
            'to_shift_id'   => $fromShiftId,
        ]);

        $this->assertNull($result);
        $errors = ShiftSwapService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function testRequestSwapPreventsSwapWithSelf(): void
    {
        $this->requireTables(['vol_shift_swap_requests']);

        $userId = $this->createUser('swap-user');
        [, $shiftId] = $this->createOpportunityAndShift($userId);

        $result = ShiftSwapService::requestSwap($userId, [
            'from_shift_id' => $shiftId,
            'to_shift_id'   => $shiftId,
            'to_user_id'    => $userId,
        ]);

        $this->assertNull($result);
        $errors = ShiftSwapService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function testRequestSwapRequiresFromUserToBeAssigned(): void
    {
        $this->requireTables(['vol_shift_swap_requests']);

        $ownerId    = $this->createUser('swap-owner');
        $fromUserId = $this->createUser('swap-from');
        $toUserId   = $this->createUser('swap-to');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        // fromUser is NOT approved for the shift
        $result = ShiftSwapService::requestSwap($fromUserId, [
            'from_shift_id' => $shiftId,
            'to_shift_id'   => $shiftId,
            'to_user_id'    => $toUserId,
        ]);

        $this->assertNull($result);
        $errors = ShiftSwapService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('FORBIDDEN', $errors[0]['code']);
    }

    public function testRequestSwapRequiresToUserToBeAssigned(): void
    {
        $this->requireTables(['vol_shift_swap_requests']);

        $ownerId    = $this->createUser('swap-owner');
        $fromUserId = $this->createUser('swap-from');
        $toUserId   = $this->createUser('swap-to');
        [$opportunityId, $fromShiftId] = $this->createOpportunityAndShift($ownerId);

        // Create a second shift
        Database::query(
            'INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 4 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 4 DAY), INTERVAL 2 HOUR), 5, NOW())',
            [self::TENANT_ID, $opportunityId]
        );
        $toShiftId = (int)Database::getInstance()->lastInsertId();

        // Approve fromUser for fromShift
        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $fromUserId, $fromShiftId]
        );

        // toUser is NOT approved for toShift
        $result = ShiftSwapService::requestSwap($fromUserId, [
            'from_shift_id' => $fromShiftId,
            'to_shift_id'   => $toShiftId,
            'to_user_id'    => $toUserId,
        ]);

        $this->assertNull($result);
        $errors = ShiftSwapService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function testRequestSwapSucceedsWithValidData(): void
    {
        $this->requireTables(['vol_shift_swap_requests']);

        $ownerId    = $this->createUser('swap-owner');
        $fromUserId = $this->createUser('swap-from');
        $toUserId   = $this->createUser('swap-to');
        [$opportunityId, $fromShiftId] = $this->createOpportunityAndShift($ownerId);

        // Create second shift
        Database::query(
            'INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 4 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 4 DAY), INTERVAL 2 HOUR), 5, NOW())',
            [self::TENANT_ID, $opportunityId]
        );
        $toShiftId = (int)Database::getInstance()->lastInsertId();

        // Approve both users for their respective shifts
        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $fromUserId, $fromShiftId]
        );
        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $toUserId, $toShiftId]
        );

        $swapId = ShiftSwapService::requestSwap($fromUserId, [
            'from_shift_id' => $fromShiftId,
            'to_shift_id'   => $toShiftId,
            'to_user_id'    => $toUserId,
            'message'       => 'Can we swap shifts?',
        ]);

        $this->assertNotNull($swapId);
        $this->assertIsInt($swapId);
        $this->assertGreaterThan(0, $swapId);
    }

    // ==========================================
    // respond - validation and happy path
    // ==========================================

    public function testRespondRejectsWrongUser(): void
    {
        $this->requireTables(['vol_shift_swap_requests']);

        $ownerId    = $this->createUser('swap-owner');
        $fromUserId = $this->createUser('swap-from');
        $toUserId   = $this->createUser('swap-to');
        $outsiderId = $this->createUser('swap-outsider');
        [$opportunityId, $fromShiftId] = $this->createOpportunityAndShift($ownerId);

        Database::query(
            'INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 4 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 4 DAY), INTERVAL 2 HOUR), 5, NOW())',
            [self::TENANT_ID, $opportunityId]
        );
        $toShiftId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $fromUserId, $fromShiftId]
        );
        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $toUserId, $toShiftId]
        );

        $swapId = ShiftSwapService::requestSwap($fromUserId, [
            'from_shift_id' => $fromShiftId,
            'to_shift_id'   => $toShiftId,
            'to_user_id'    => $toUserId,
        ]);
        $this->assertNotNull($swapId);

        // outsider tries to respond - not the to_user_id
        $result = ShiftSwapService::respond($swapId, $outsiderId, 'accept');

        $this->assertFalse($result);
        $errors = ShiftSwapService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('FORBIDDEN', $errors[0]['code']);
    }

    public function testRespondAcceptWithNoAdminApprovalExecutesSwap(): void
    {
        $this->requireTables(['vol_shift_swap_requests']);

        $ownerId    = $this->createUser('swap-owner');
        $fromUserId = $this->createUser('swap-from');
        $toUserId   = $this->createUser('swap-to');
        [$opportunityId, $fromShiftId] = $this->createOpportunityAndShift($ownerId);

        Database::query(
            'INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 4 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 4 DAY), INTERVAL 2 HOUR), 5, NOW())',
            [self::TENANT_ID, $opportunityId]
        );
        $toShiftId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $fromUserId, $fromShiftId]
        );
        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $toUserId, $toShiftId]
        );

        // Ensure no admin-approval requirement is set for this tenant
        Database::query(
            "DELETE FROM tenant_settings WHERE tenant_id = ? AND setting_key = 'volunteering.swap_requires_admin'",
            [self::TENANT_ID]
        );

        $swapId = ShiftSwapService::requestSwap($fromUserId, [
            'from_shift_id' => $fromShiftId,
            'to_shift_id'   => $toShiftId,
            'to_user_id'    => $toUserId,
        ]);
        $this->assertNotNull($swapId);

        $result = ShiftSwapService::respond($swapId, $toUserId, 'accept');

        $this->assertTrue($result);

        // Verify swap status is accepted
        $status = (string)Database::query(
            'SELECT status FROM vol_shift_swap_requests WHERE id = ? AND tenant_id = ?',
            [$swapId, self::TENANT_ID]
        )->fetchColumn();
        $this->assertSame('accepted', $status);

        // Verify shift assignments were swapped
        $fromUserShift = (int)Database::query(
            'SELECT shift_id FROM vol_applications WHERE user_id = ? AND tenant_id = ? AND status = ?',
            [$fromUserId, self::TENANT_ID, 'approved']
        )->fetchColumn();
        $this->assertSame($toShiftId, $fromUserShift);

        $toUserShift = (int)Database::query(
            'SELECT shift_id FROM vol_applications WHERE user_id = ? AND tenant_id = ? AND status = ?',
            [$toUserId, self::TENANT_ID, 'approved']
        )->fetchColumn();
        $this->assertSame($fromShiftId, $toUserShift);
    }

    // ==========================================
    // cancel
    // ==========================================

    public function testCancelSwapFailsForNonCreator(): void
    {
        $this->requireTables(['vol_shift_swap_requests']);

        $ownerId    = $this->createUser('swap-owner');
        $fromUserId = $this->createUser('swap-from');
        $toUserId   = $this->createUser('swap-to');
        [$opportunityId, $fromShiftId] = $this->createOpportunityAndShift($ownerId);

        Database::query(
            'INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 4 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 4 DAY), INTERVAL 2 HOUR), 5, NOW())',
            [self::TENANT_ID, $opportunityId]
        );
        $toShiftId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $fromUserId, $fromShiftId]
        );
        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $toUserId, $toShiftId]
        );

        $swapId = ShiftSwapService::requestSwap($fromUserId, [
            'from_shift_id' => $fromShiftId,
            'to_shift_id'   => $toShiftId,
            'to_user_id'    => $toUserId,
        ]);
        $this->assertNotNull($swapId);

        // toUser (not the creator) tries to cancel
        $result = ShiftSwapService::cancel($swapId, $toUserId);

        $this->assertFalse($result);
        $errors = ShiftSwapService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('NOT_FOUND', $errors[0]['code']);
    }

    // ==========================================
    // getSwapRequests
    // ==========================================

    public function testGetSwapRequestsReturnsArray(): void
    {
        $this->requireTables(['vol_shift_swap_requests']);

        $userId = $this->createUser('swap-user');

        $result = ShiftSwapService::getSwapRequests($userId, 'all');

        $this->assertIsArray($result);
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
