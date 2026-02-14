<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\MatchApprovalWorkflowService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * MatchApprovalWorkflowServiceTest
 *
 * Tests for the broker approval workflow for matches.
 * Covers submission, approval, rejection, bulk operations, and statistics.
 */
class MatchApprovalWorkflowServiceTest extends TestCase
{
    private static $testTenantId = 1;
    private static $testUserId;
    private static $testListingId;
    private static $testAdminId;
    private static $testApprovalId;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        $timestamp = time() . rand(1000, 9999);

        // Create test user (member who will receive match)
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, role, is_approved, status, created_at)
             VALUES (?, ?, 'Test', 'Member', 'Test Member', 'member', 1, 'active', NOW())",
            [self::$testTenantId, 'test_approval_user_' . $timestamp . '@test.com']
        );
        self::$testUserId = Database::getInstance()->lastInsertId();

        // Create test admin (broker who will review matches)
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, role, is_approved, status, created_at)
             VALUES (?, ?, 'Test', 'Broker', 'Test Broker', 'broker', 1, 'active', NOW())",
            [self::$testTenantId, 'test_approval_admin_' . $timestamp . '@test.com']
        );
        self::$testAdminId = Database::getInstance()->lastInsertId();

        // Create test listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, 'Test Listing for Approval', 'Description for approval workflow test', 'offer', 'active', NOW())",
            [self::$testTenantId, self::$testAdminId]
        );
        self::$testListingId = Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test data
        if (self::$testApprovalId) {
            Database::query("DELETE FROM match_approvals WHERE id = ?", [self::$testApprovalId]);
        }

        // Clean up any remaining test approvals
        Database::query(
            "DELETE FROM match_approvals WHERE tenant_id = ? AND user_id = ?",
            [self::$testTenantId, self::$testUserId]
        );

        // Clean up test listing
        if (self::$testListingId) {
            Database::query("DELETE FROM listings WHERE id = ?", [self::$testListingId]);
        }

        // Clean up test users
        if (self::$testUserId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
        }
        if (self::$testAdminId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$testAdminId]);
        }
    }

    /**
     * Test submitting a match for approval
     */
    public function testSubmitForApproval(): void
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

        // Verify the request was created
        $request = MatchApprovalWorkflowService::getRequest($requestId);
        $this->assertNotNull($request, 'Request should be retrievable');
        $this->assertEquals('pending', $request['status'], 'Status should be pending');
        $this->assertEquals(self::$testUserId, $request['user_id'], 'User ID should match');
        $this->assertEquals(self::$testListingId, $request['listing_id'], 'Listing ID should match');
        $this->assertEquals(85.5, (float)$request['match_score'], 'Match score should match');
    }

    /**
     * Test that duplicate pending approvals are prevented
     */
    public function testPreventsDuplicatePendingApprovals(): void
    {
        $matchData = [
            'match_score' => 75,
            'match_type' => 'one_way',
            'match_reasons' => ['Test duplicate'],
            'distance_km' => 10
        ];

        // Submit same user/listing pair again
        $duplicateId = MatchApprovalWorkflowService::submitForApproval(
            self::$testUserId,
            self::$testListingId,
            $matchData
        );

        // Should return the existing approval ID
        $this->assertEquals(self::$testApprovalId, $duplicateId, 'Should return existing approval ID for duplicates');
    }

    /**
     * Test getting pending requests
     */
    public function testGetPendingRequests(): void
    {
        $pendingRequests = MatchApprovalWorkflowService::getPendingRequests(50, 0);

        $this->assertIsArray($pendingRequests, 'Should return an array');

        // Find our test request
        $found = false;
        foreach ($pendingRequests as $request) {
            if ($request['id'] == self::$testApprovalId) {
                $found = true;
                $this->assertEquals('pending', $request['status'], 'Status should be pending');
                $this->assertArrayHasKey('user_name', $request, 'Should include user name');
                $this->assertArrayHasKey('listing_title', $request, 'Should include listing title');
                break;
            }
        }

        $this->assertTrue($found, 'Test request should be in pending list');
    }

    /**
     * Test getting pending count
     */
    public function testGetPendingCount(): void
    {
        $count = MatchApprovalWorkflowService::getPendingCount();

        $this->assertIsInt($count, 'Should return an integer');
        $this->assertGreaterThanOrEqual(1, $count, 'Should have at least one pending request');
    }

    /**
     * Test checking if match is approved (should be false for pending)
     */
    public function testIsMatchApprovedReturnsFalseForPending(): void
    {
        $isApproved = MatchApprovalWorkflowService::isMatchApproved(
            self::$testUserId,
            self::$testListingId
        );

        $this->assertFalse($isApproved, 'Pending match should not be considered approved');
    }

    /**
     * Test approving a match
     */
    public function testApproveMatch(): void
    {
        $result = MatchApprovalWorkflowService::approveMatch(
            self::$testApprovalId,
            self::$testAdminId,
            'Approved for testing'
        );

        $this->assertTrue($result, 'Approval should succeed');

        // Verify the status changed
        $request = MatchApprovalWorkflowService::getRequest(self::$testApprovalId);
        $this->assertEquals('approved', $request['status'], 'Status should be approved');
        $this->assertEquals(self::$testAdminId, $request['reviewed_by'], 'Reviewer should be set');
        $this->assertEquals('Approved for testing', $request['review_notes'], 'Notes should be saved');
        $this->assertNotNull($request['reviewed_at'], 'Review timestamp should be set');
    }

    /**
     * Test that approved matches show as approved
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
     * Test that you cannot approve an already-approved request
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
     * Test approval history
     */
    public function testGetApprovalHistory(): void
    {
        $history = MatchApprovalWorkflowService::getApprovalHistory([], 50, 0);

        $this->assertIsArray($history, 'Should return an array');

        // Find our test request
        $found = false;
        foreach ($history as $item) {
            if ($item['id'] == self::$testApprovalId) {
                $found = true;
                $this->assertEquals('approved', $item['status'], 'Status should be approved');
                break;
            }
        }

        $this->assertTrue($found, 'Approved request should be in history');
    }

    /**
     * Test statistics
     */
    public function testGetStatistics(): void
    {
        $stats = MatchApprovalWorkflowService::getStatistics(30);

        $this->assertIsArray($stats, 'Should return an array');
        $this->assertArrayHasKey('pending_count', $stats, 'Should include pending count');
        $this->assertArrayHasKey('approved_count', $stats, 'Should include approved count');
        $this->assertArrayHasKey('rejected_count', $stats, 'Should include rejected count');
        $this->assertArrayHasKey('avg_approval_time', $stats, 'Should include avg approval time');
        $this->assertArrayHasKey('approval_rate', $stats, 'Should include approval rate');

        // We approved one, so approved count should be at least 1
        $this->assertGreaterThanOrEqual(1, $stats['approved_count'], 'Should have at least 1 approval');
    }

    /**
     * Test rejecting a match with reason
     */
    public function testRejectMatch(): void
    {
        // Create a new approval to reject
        $matchData = [
            'match_score' => 65,
            'match_type' => 'one_way',
            'match_reasons' => ['Test rejection'],
            'distance_km' => 20
        ];

        // Create a new listing for this test
        $timestamp = time();
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, type, status, created_at)
             VALUES (?, ?, 'Reject Test Listing', 'offer', 'active', NOW())",
            [self::$testTenantId, self::$testAdminId]
        );
        $rejectListingId = Database::getInstance()->lastInsertId();

        $requestId = MatchApprovalWorkflowService::submitForApproval(
            self::$testUserId,
            $rejectListingId,
            $matchData
        );

        // Reject it
        $result = MatchApprovalWorkflowService::rejectMatch(
            $requestId,
            self::$testAdminId,
            'Member has mobility issues that prevent this match'
        );

        $this->assertTrue($result, 'Rejection should succeed');

        // Verify
        $request = MatchApprovalWorkflowService::getRequest($requestId);
        $this->assertEquals('rejected', $request['status'], 'Status should be rejected');
        $this->assertStringContainsString('mobility issues', $request['review_notes'], 'Reason should be saved');

        // Check that rejected match is not considered approved
        $isApproved = MatchApprovalWorkflowService::isMatchApproved(
            self::$testUserId,
            $rejectListingId
        );
        $this->assertFalse($isApproved, 'Rejected match should not be approved');

        // Clean up
        Database::query("DELETE FROM match_approvals WHERE id = ?", [$requestId]);
        Database::query("DELETE FROM listings WHERE id = ?", [$rejectListingId]);
    }

    /**
     * Test bulk approve
     */
    public function testBulkApprove(): void
    {
        // Create multiple approvals
        $requestIds = [];

        for ($i = 0; $i < 3; $i++) {
            Database::query(
                "INSERT INTO listings (tenant_id, user_id, title, type, status, created_at)
                 VALUES (?, ?, ?, 'offer', 'active', NOW())",
                [self::$testTenantId, self::$testAdminId, 'Bulk Test ' . $i]
            );
            $listingId = Database::getInstance()->lastInsertId();

            $requestId = MatchApprovalWorkflowService::submitForApproval(
                self::$testUserId,
                $listingId,
                ['match_score' => 70, 'match_type' => 'one_way']
            );
            $requestIds[] = $requestId;
        }

        // Bulk approve
        $count = MatchApprovalWorkflowService::bulkApprove(
            $requestIds,
            self::$testAdminId,
            'Bulk approved'
        );

        $this->assertEquals(3, $count, 'Should approve all 3 requests');

        // Clean up
        foreach ($requestIds as $id) {
            $request = MatchApprovalWorkflowService::getRequest($id);
            if ($request) {
                Database::query("DELETE FROM match_approvals WHERE id = ?", [$id]);
                Database::query("DELETE FROM listings WHERE id = ?", [$request['listing_id']]);
            }
        }
    }

    /**
     * Test bulk reject
     */
    public function testBulkReject(): void
    {
        // Create multiple approvals
        $requestIds = [];

        for ($i = 0; $i < 2; $i++) {
            Database::query(
                "INSERT INTO listings (tenant_id, user_id, title, type, status, created_at)
                 VALUES (?, ?, ?, 'offer', 'active', NOW())",
                [self::$testTenantId, self::$testAdminId, 'Bulk Reject Test ' . $i]
            );
            $listingId = Database::getInstance()->lastInsertId();

            $requestId = MatchApprovalWorkflowService::submitForApproval(
                self::$testUserId,
                $listingId,
                ['match_score' => 55, 'match_type' => 'one_way']
            );
            $requestIds[] = $requestId;
        }

        // Bulk reject
        $count = MatchApprovalWorkflowService::bulkReject(
            $requestIds,
            self::$testAdminId,
            'Insurance coverage issue'
        );

        $this->assertEquals(2, $count, 'Should reject all 2 requests');

        // Verify status
        foreach ($requestIds as $id) {
            $request = MatchApprovalWorkflowService::getRequest($id);
            $this->assertEquals('rejected', $request['status'], 'Status should be rejected');
        }

        // Clean up
        foreach ($requestIds as $id) {
            $request = MatchApprovalWorkflowService::getRequest($id);
            if ($request) {
                Database::query("DELETE FROM match_approvals WHERE id = ?", [$id]);
                Database::query("DELETE FROM listings WHERE id = ?", [$request['listing_id']]);
            }
        }
    }

    /**
     * Test getting a non-existent request
     */
    public function testGetNonExistentRequest(): void
    {
        $request = MatchApprovalWorkflowService::getRequest(999999999);

        $this->assertNull($request, 'Should return null for non-existent request');
    }

    /**
     * Test approval with history filter
     */
    public function testGetApprovalHistoryWithStatusFilter(): void
    {
        $approvedHistory = MatchApprovalWorkflowService::getApprovalHistory(
            ['status' => 'approved'],
            50,
            0
        );

        $this->assertIsArray($approvedHistory, 'Should return an array');

        // All items should be approved
        foreach ($approvedHistory as $item) {
            $this->assertEquals('approved', $item['status'], 'All items should be approved');
        }
    }
}
