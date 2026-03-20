<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use App\Core\TenantContext;
use App\Services\ShiftSwapService;
use App\Services\ShiftWaitlistService;
use App\Services\VolunteerCheckInService;
use App\Services\VolunteerService;

/**
 * VolunteerFlowIntegrationTest
 *
 * End-to-end integration tests spanning multiple volunteer services.
 * Each test exercises a realistic multi-step user journey.
 */
class VolunteerFlowIntegrationTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ==========================================
    // Full lifecycle: create opp -> apply -> approve -> log hours -> summary
    // ==========================================

    public function testFullLifecycleCreateOppApplyApproveLogHours(): void
    {
        $this->requireTables(['vol_organizations', 'vol_opportunities', 'vol_applications', 'vol_logs']);

        $ownerId  = $this->createUser('lifecycle-owner');
        $userId   = $this->createUser('lifecycle-volunteer');
        $orgId    = $this->createOrganization($ownerId, 'approved');

        // Create opportunity via service
        $oppId = VolunteerService::createOpportunity($ownerId, [
            'organization_id' => $orgId,
            'title'           => 'Integration Test Opportunity',
            'description'     => 'Test opportunity for lifecycle test',
            'location'        => 'Remote',
        ]);
        $this->assertNotNull($oppId, 'createOpportunity should succeed');

        // Apply
        $appId = VolunteerService::apply($userId, $oppId);
        $this->assertNotNull($appId, 'apply should succeed');

        // Approve application
        $approved = VolunteerService::handleApplication($appId, $ownerId, 'approve');
        $this->assertTrue($approved, 'handleApplication approve should succeed');

        // Log hours
        $logId = VolunteerService::logHours($userId, [
            'organization_id' => $orgId,
            'date'            => date('Y-m-d', strtotime('-1 day')),
            'hours'           => 3,
            'description'     => 'Integration test hours',
        ]);
        $this->assertNotNull($logId, 'logHours should succeed');

        // Get summary
        $summary = VolunteerService::getHoursSummary($userId);
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('total_verified', $summary);
        $this->assertArrayHasKey('total_pending', $summary);
        // At least some hours should be recorded (pending or verified)
        $totalRecorded = ((float)$summary['total_verified']) + ((float)$summary['total_pending']);
        $this->assertGreaterThan(0, $totalRecorded);
    }

    // ==========================================
    // Waitlist reorder: 3 users join, middle user leaves, last user moves up
    // ==========================================

    public function testWaitlistFlowJoinLeaveReorder(): void
    {
        $this->requireTables(['vol_shift_waitlist']);

        $ownerId = $this->createUser('wl-flow-owner');
        $user1   = $this->createUser('wl-flow-user1');
        $user2   = $this->createUser('wl-flow-user2');
        $user3   = $this->createUser('wl-flow-user3');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        ShiftWaitlistService::join($shiftId, $user1);
        ShiftWaitlistService::join($shiftId, $user2);
        ShiftWaitlistService::join($shiftId, $user3);

        // Verify initial positions
        $pos1 = ShiftWaitlistService::getUserPosition($shiftId, $user1);
        $pos2 = ShiftWaitlistService::getUserPosition($shiftId, $user2);
        $pos3 = ShiftWaitlistService::getUserPosition($shiftId, $user3);
        $this->assertSame(1, $pos1['position']);
        $this->assertSame(2, $pos2['position']);
        $this->assertSame(3, $pos3['position']);

        // User 2 leaves
        $left = ShiftWaitlistService::leave($shiftId, $user2);
        $this->assertTrue($left);

        // User 3 should now be at position 2
        $pos3After = ShiftWaitlistService::getUserPosition($shiftId, $user3);
        $this->assertNotNull($pos3After);
        $this->assertSame(2, $pos3After['position']);

        // User 1 still at position 1
        $pos1After = ShiftWaitlistService::getUserPosition($shiftId, $user1);
        $this->assertSame(1, $pos1After['position']);
    }

    // ==========================================
    // Swap flow: request and cancel
    // ==========================================

    public function testSwapFlowRequestAndCancel(): void
    {
        $this->requireTables(['vol_shift_swap_requests']);

        $ownerId    = $this->createUser('swap-flow-owner');
        $userA      = $this->createUser('swap-flow-userA');
        $userB      = $this->createUser('swap-flow-userB');
        [$opportunityId, $shiftA] = $this->createOpportunityAndShift($ownerId);

        // Create a second shift
        Database::query(
            'INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 5 DAY), INTERVAL 2 HOUR), 5, NOW())',
            [self::TENANT_ID, $opportunityId]
        );
        $shiftB = (int)Database::getInstance()->lastInsertId();

        // Approve both users
        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $userA, $shiftA]
        );
        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $userB, $shiftB]
        );

        // User A requests swap
        $swapId = ShiftSwapService::requestSwap($userA, [
            'from_shift_id' => $shiftA,
            'to_shift_id'   => $shiftB,
            'to_user_id'    => $userB,
        ]);
        $this->assertNotNull($swapId, 'requestSwap should succeed');

        // User A cancels the swap
        $cancelled = ShiftSwapService::cancel($swapId, $userA);
        $this->assertTrue($cancelled);

        // Assert swap status is cancelled
        $status = (string)Database::query(
            'SELECT status FROM vol_shift_swap_requests WHERE id = ? AND tenant_id = ?',
            [$swapId, self::TENANT_ID]
        )->fetchColumn();
        $this->assertSame('cancelled', $status);
    }

    // ==========================================
    // Check-in flow: generate token, set shift in window, verify, check out
    // ==========================================

    public function testCheckInFlowGenerateAndVerify(): void
    {
        $this->requireTables(['vol_shift_checkins']);

        $ownerId = $this->createUser('checkin-flow-owner');
        $userId  = $this->createUser('checkin-flow-user');
        [$opportunityId, $shiftId] = $this->createOpportunityAndShift($ownerId);

        // Approve the user for the shift
        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::TENANT_ID, $opportunityId, $userId, $shiftId]
        );

        // Generate check-in token
        $token = VolunteerCheckInService::generateToken($shiftId, $userId);
        $this->assertNotNull($token, 'generateToken should succeed for approved user');
        $this->assertNotEmpty($token);

        // Move shift into the 30-minute check-in window
        Database::query(
            'UPDATE vol_shifts SET start_time = DATE_ADD(NOW(), INTERVAL 10 MINUTE), end_time = DATE_ADD(NOW(), INTERVAL 2 HOUR) WHERE id = ?',
            [$shiftId]
        );

        // Verify check-in
        $checkIn = VolunteerCheckInService::verifyCheckIn($token);
        $this->assertNotNull($checkIn, 'verifyCheckIn should succeed within window');
        $this->assertSame('checked_in', $checkIn['status']);

        // Check out
        $checkedOut = VolunteerCheckInService::checkOut($token);
        $this->assertTrue($checkedOut, 'checkOut should succeed');

        // Verify DB status
        $dbStatus = (string)Database::query(
            'SELECT status FROM vol_shift_checkins WHERE qr_token = ? AND tenant_id = ?',
            [$token, self::TENANT_ID]
        )->fetchColumn();
        $this->assertSame('checked_out', $dbStatus);
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
