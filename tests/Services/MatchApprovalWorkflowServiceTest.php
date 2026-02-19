<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\MatchApprovalWorkflowService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * MatchApprovalWorkflowServiceTest
 *
 * Tests for the broker approval workflow for matches.
 * Uses strict @depends chain to ensure correct execution order
 * (pending tests before approval tests).
 */
class MatchApprovalWorkflowServiceTest extends TestCase
{
    private static $testTenantId = 1;
    private static $testUserId;
    private static $testListingId;
    private static $testAdminId;
    private static $testApprovalId;
    private static $dbAvailable = false;

    public static function setUpBeforeClass(): void
    {
        try {
            TenantContext::setById(self::$testTenantId);
        } catch (\Throwable $e) {
            return;
        }

        try {
            $timestamp = time() . rand(1000, 9999);

            Database::query(
                "INSERT INTO users (tenant_id, email, first_name, last_name, name, role, is_approved, status, created_at)
                 VALUES (?, ?, 'Test', 'Member', 'Test Member', 'member', 1, 'active', NOW())",
                [self::$testTenantId, 'test_approval_user_' . $timestamp . '@test.com']
            );
            self::$testUserId = (int) Database::getInstance()->lastInsertId();

            Database::query(
                "INSERT INTO users (tenant_id, email, first_name, last_name, name, role, is_approved, status, created_at)
                 VALUES (?, ?, 'Test', 'Broker', 'Test Broker', 'broker', 1, 'active', NOW())",
                [self::$testTenantId, 'test_approval_admin_' . $timestamp . '@test.com']
            );
            self::$testAdminId = (int) Database::getInstance()->lastInsertId();

            Database::query(
                "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
                 VALUES (?, ?, 'Test Listing for Approval', 'Description', 'offer', 'active', NOW())",
                [self::$testTenantId, self::$testAdminId]
            );
            self::$testListingId = (int) Database::getInstance()->lastInsertId();

            // Clean up any stale match_approvals for this user/listing pair
            Database::query(
                "DELETE FROM match_approvals WHERE tenant_id = ? AND user_id = ? AND listing_id = ?",
                [self::$testTenantId, self::$testUserId, self::$testListingId]
            );

            self::$dbAvailable = true;
        } catch (\Throwable $e) {
            error_log("MatchApprovalWorkflowServiceTest setup failed: " . $e->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$dbAvailable) {
            return;
        }

        try {
            if (self::$testApprovalId) {
                Database::query("DELETE FROM match_approvals WHERE id = ?", [self::$testApprovalId]);
            }
            if (self::$testUserId) {
                Database::query(
                    "DELETE FROM match_approvals WHERE tenant_id = ? AND user_id = ?",
                    [self::$testTenantId, self::$testUserId]
                );
            }
            if (self::$testListingId) {
                Database::query("DELETE FROM listings WHERE id = ?", [self::$testListingId]);
            }
            if (self::$testUserId) {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            }
            if (self::$testAdminId) {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testAdminId]);
            }
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }
    }

    protected function setUp(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available for integration test');
        }
        TenantContext::setById(self::$testTenantId);
    }

    // === PHASE 1: Submit & Pending State ===

    public function testSubmitForApproval(): int
    {
        $matchData = [
            'match_score' => 85.5,
            'match_type' => 'one_way',
            'match_reasons' => ['Category match', 'Nearby location'],
            'distance_km' => 5.2
        ];

        $requestId = MatchApprovalWorkflowService::submitForApproval(
            self::$testUserId,
            self::$testListingId,
            $matchData
        );

        $this->assertNotNull($requestId, 'Should return a request ID');
        $this->assertIsInt($requestId, 'Request ID should be an integer');

        self::$testApprovalId = $requestId;

        $request = MatchApprovalWorkflowService::getRequest($requestId);
        $this->assertNotNull($request, 'Request should be retrievable');
        $this->assertEquals('pending', $request['status'], 'Status should be pending');
        $this->assertEquals(self::$testUserId, (int) $request['user_id'], 'User ID should match');
        $this->assertEquals(self::$testListingId, (int) $request['listing_id'], 'Listing ID should match');
        $this->assertEqualsWithDelta(85.5, (float) $request['match_score'], 0.1, 'Match score should match');

        return $requestId;
    }

    /**
     * @depends testSubmitForApproval
     */
    public function testPreventsDuplicatePendingApprovals(int $approvalId): int
    {
        $duplicateId = MatchApprovalWorkflowService::submitForApproval(
            self::$testUserId,
            self::$testListingId,
            ['match_score' => 75, 'match_type' => 'one_way', 'match_reasons' => ['Test duplicate'], 'distance_km' => 10]
        );

        $this->assertEquals($approvalId, $duplicateId, 'Should return existing approval ID for duplicates');

        return $approvalId;
    }

    /**
     * @depends testPreventsDuplicatePendingApprovals
     */
    public function testGetPendingRequests(int $approvalId): int
    {
        $pendingRequests = MatchApprovalWorkflowService::getPendingRequests(50, 0);

        $this->assertIsArray($pendingRequests, 'Should return an array');

        $found = false;
        foreach ($pendingRequests as $request) {
            if ((int) $request['id'] === $approvalId) {
                $found = true;
                $this->assertEquals('pending', $request['status']);
                break;
            }
        }

        $this->assertTrue($found, 'Test request should be in pending list');

        return $approvalId;
    }

    /**
     * @depends testGetPendingRequests
     */
    public function testGetPendingCount(): void
    {
        $count = MatchApprovalWorkflowService::getPendingCount();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(1, $count, 'Should have at least one pending request');
    }

    /**
     * @depends testGetPendingCount
     */
    public function testIsMatchApprovedReturnsFalseForPending(): void
    {
        $isApproved = MatchApprovalWorkflowService::isMatchApproved(
            self::$testUserId,
            self::$testListingId
        );
        $this->assertFalse($isApproved, 'Pending match should not be considered approved');
    }

    // === PHASE 2: Approve & Post-Approval State ===

    /**
     * @depends testIsMatchApprovedReturnsFalseForPending
     */
    public function testApproveMatch(): void
    {
        $this->assertNotNull(self::$testApprovalId, 'Approval ID must be set');

        $result = MatchApprovalWorkflowService::approveMatch(
            self::$testApprovalId,
            self::$testAdminId,
            'Approved for testing'
        );

        $this->assertTrue($result, 'Approval should succeed');

        $request = MatchApprovalWorkflowService::getRequest(self::$testApprovalId);
        $this->assertEquals('approved', $request['status']);
        $this->assertEquals(self::$testAdminId, (int) $request['reviewed_by']);
        $this->assertEquals('Approved for testing', $request['review_notes']);
        $this->assertNotNull($request['reviewed_at']);
    }

    /**
     * @depends testApproveMatch
     */
    public function testIsMatchApprovedReturnsTrueAfterApproval(): void
    {
        $isApproved = MatchApprovalWorkflowService::isMatchApproved(
            self::$testUserId,
            self::$testListingId
        );
        $this->assertTrue($isApproved, 'Approved match should return true');
    }

    /**
     * @depends testApproveMatch
     */
    public function testCannotApproveNonPendingRequest(): void
    {
        $result = MatchApprovalWorkflowService::approveMatch(
            self::$testApprovalId,
            self::$testAdminId,
            'Try again'
        );
        $this->assertFalse($result, 'Should not be able to approve non-pending request');
    }

    /**
     * @depends testApproveMatch
     */
    public function testGetApprovalHistory(): void
    {
        $history = MatchApprovalWorkflowService::getApprovalHistory([], 50, 0);
        $this->assertIsArray($history);

        $found = false;
        foreach ($history as $item) {
            if ((int) $item['id'] === self::$testApprovalId) {
                $found = true;
                $this->assertEquals('approved', $item['status']);
                break;
            }
        }
        $this->assertTrue($found, 'Approved request should be in history');
    }

    /**
     * @depends testApproveMatch
     */
    public function testGetStatistics(): void
    {
        $stats = MatchApprovalWorkflowService::getStatistics(30);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('pending_count', $stats);
        $this->assertArrayHasKey('approved_count', $stats);
        $this->assertArrayHasKey('rejected_count', $stats);
        $this->assertArrayHasKey('avg_approval_time', $stats);
        $this->assertArrayHasKey('approval_rate', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['approved_count'], 'Should have at least 1 approval');
    }

    // === PHASE 3: Independent Tests ===

    public function testRejectMatch(): void
    {
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, type, status, created_at)
             VALUES (?, ?, 'Reject Test Listing', 'offer', 'active', NOW())",
            [self::$testTenantId, self::$testAdminId]
        );
        $rejectListingId = (int) Database::getInstance()->lastInsertId();

        $requestId = MatchApprovalWorkflowService::submitForApproval(
            self::$testUserId,
            $rejectListingId,
            ['match_score' => 65, 'match_type' => 'one_way', 'match_reasons' => ['Test rejection'], 'distance_km' => 20]
        );

        $result = MatchApprovalWorkflowService::rejectMatch(
            $requestId,
            self::$testAdminId,
            'Member has mobility issues that prevent this match'
        );

        $this->assertTrue($result, 'Rejection should succeed');

        $request = MatchApprovalWorkflowService::getRequest($requestId);
        $this->assertEquals('rejected', $request['status']);
        $this->assertStringContainsString('mobility issues', $request['review_notes']);

        $isApproved = MatchApprovalWorkflowService::isMatchApproved(self::$testUserId, $rejectListingId);
        $this->assertFalse($isApproved, 'Rejected match should not be approved');

        Database::query("DELETE FROM match_approvals WHERE id = ?", [$requestId]);
        Database::query("DELETE FROM listings WHERE id = ?", [$rejectListingId]);
    }

    public function testBulkApprove(): void
    {
        $requestIds = [];

        for ($i = 0; $i < 3; $i++) {
            Database::query(
                "INSERT INTO listings (tenant_id, user_id, title, type, status, created_at)
                 VALUES (?, ?, ?, 'offer', 'active', NOW())",
                [self::$testTenantId, self::$testAdminId, 'Bulk Test ' . $i]
            );
            $listingId = (int) Database::getInstance()->lastInsertId();

            $requestId = MatchApprovalWorkflowService::submitForApproval(
                self::$testUserId,
                $listingId,
                ['match_score' => 70, 'match_type' => 'one_way']
            );
            $requestIds[] = $requestId;
        }

        $count = MatchApprovalWorkflowService::bulkApprove($requestIds, self::$testAdminId, 'Bulk approved');
        $this->assertEquals(3, $count, 'Should approve all 3 requests');

        foreach ($requestIds as $id) {
            $request = MatchApprovalWorkflowService::getRequest($id);
            if ($request) {
                Database::query("DELETE FROM match_approvals WHERE id = ?", [$id]);
                Database::query("DELETE FROM listings WHERE id = ?", [$request['listing_id']]);
            }
        }
    }

    public function testBulkReject(): void
    {
        $requestIds = [];

        for ($i = 0; $i < 2; $i++) {
            Database::query(
                "INSERT INTO listings (tenant_id, user_id, title, type, status, created_at)
                 VALUES (?, ?, ?, 'offer', 'active', NOW())",
                [self::$testTenantId, self::$testAdminId, 'Bulk Reject Test ' . $i]
            );
            $listingId = (int) Database::getInstance()->lastInsertId();

            $requestId = MatchApprovalWorkflowService::submitForApproval(
                self::$testUserId,
                $listingId,
                ['match_score' => 55, 'match_type' => 'one_way']
            );
            $requestIds[] = $requestId;
        }

        $count = MatchApprovalWorkflowService::bulkReject($requestIds, self::$testAdminId, 'Insurance coverage issue');
        $this->assertEquals(2, $count, 'Should reject all 2 requests');

        foreach ($requestIds as $id) {
            $request = MatchApprovalWorkflowService::getRequest($id);
            $this->assertEquals('rejected', $request['status']);
        }

        foreach ($requestIds as $id) {
            $request = MatchApprovalWorkflowService::getRequest($id);
            if ($request) {
                Database::query("DELETE FROM match_approvals WHERE id = ?", [$id]);
                Database::query("DELETE FROM listings WHERE id = ?", [$request['listing_id']]);
            }
        }
    }

    public function testGetNonExistentRequest(): void
    {
        $request = MatchApprovalWorkflowService::getRequest(999999999);
        $this->assertNull($request, 'Should return null for non-existent request');
    }

    public function testGetApprovalHistoryWithStatusFilter(): void
    {
        $approvedHistory = MatchApprovalWorkflowService::getApprovalHistory(['status' => 'approved'], 50, 0);
        $this->assertIsArray($approvedHistory);

        foreach ($approvedHistory as $item) {
            $this->assertEquals('approved', $item['status'], 'All items should be approved');
        }
    }
}
