<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\GroupApprovalWorkflowService;

/**
 * GroupApprovalWorkflowService Tests
 *
 * Tests group creation approval workflow including
 * submission, approval, and rejection processes.
 */
class GroupApprovalWorkflowServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testGroupId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $ts = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [self::$testTenantId, "grpappr_{$ts}@test.com", "grpappr_{$ts}", 'Approval', 'User', 'Approval User']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test group
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, owner_id, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [self::$testTenantId, "Test Group {$ts}", 'Test group for approval', self::$testUserId]
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testGroupId) {
            try {
                Database::query("DELETE FROM group_approval_requests WHERE group_id = ?", [self::$testGroupId]);
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testGroupId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Status Constants Tests
    // ==========================================

    public function testStatusConstantsExist(): void
    {
        $this->assertEquals('pending', GroupApprovalWorkflowService::STATUS_PENDING);
        $this->assertEquals('approved', GroupApprovalWorkflowService::STATUS_APPROVED);
        $this->assertEquals('rejected', GroupApprovalWorkflowService::STATUS_REJECTED);
        $this->assertEquals('changes_requested', GroupApprovalWorkflowService::STATUS_CHANGES_REQUESTED);
    }

    // ==========================================
    // Submit For Approval Tests
    // ==========================================

    public function testSubmitForApprovalReturnsRequestId(): void
    {
        $requestId = GroupApprovalWorkflowService::submitForApproval(
            self::$testGroupId,
            self::$testUserId,
            'Please approve this group'
        );

        $this->assertNotNull($requestId);
        $this->assertIsNumeric($requestId);

        // Cleanup
        Database::query("DELETE FROM group_approval_requests WHERE id = ?", [$requestId]);
    }

    public function testSubmitForApprovalCreatesRequest(): void
    {
        $requestId = GroupApprovalWorkflowService::submitForApproval(
            self::$testGroupId,
            self::$testUserId,
            'Test notes'
        );

        // Verify request was created with pending status
        $stmt = Database::query("SELECT status FROM group_approval_requests WHERE id = ?", [$requestId]);
        $request = $stmt->fetch();
        $this->assertEquals('pending', $request['status']);

        // Cleanup
        Database::query("DELETE FROM group_approval_requests WHERE id = ?", [$requestId]);
    }

    public function testSubmitForApprovalPreventsDuplicates(): void
    {
        $requestId1 = GroupApprovalWorkflowService::submitForApproval(
            self::$testGroupId,
            self::$testUserId
        );

        $requestId2 = GroupApprovalWorkflowService::submitForApproval(
            self::$testGroupId,
            self::$testUserId
        );

        // Should return the same ID (existing pending request)
        $this->assertEquals($requestId1, $requestId2);

        // Cleanup
        Database::query("DELETE FROM group_approval_requests WHERE id = ?", [$requestId1]);
    }

    // ==========================================
    // Get Request Tests
    // ==========================================

    public function testGetRequestReturnsValidStructure(): void
    {
        $requestId = GroupApprovalWorkflowService::submitForApproval(
            self::$testGroupId,
            self::$testUserId
        );

        $request = GroupApprovalWorkflowService::getRequest($requestId);

        $this->assertNotEmpty($request);
        $this->assertArrayHasKey('group_id', $request);
        $this->assertArrayHasKey('submitted_by', $request);
        $this->assertArrayHasKey('status', $request);

        // Cleanup
        Database::query("DELETE FROM group_approval_requests WHERE id = ?", [$requestId]);
    }

    // ==========================================
    // Approve/Reject Tests
    // ==========================================

    public function testApproveGroupChangesRequestStatus(): void
    {
        $requestId = GroupApprovalWorkflowService::submitForApproval(
            self::$testGroupId,
            self::$testUserId
        );

        $result = GroupApprovalWorkflowService::approveGroup(
            $requestId,
            self::$testUserId,
            'Approved!'
        );

        $this->assertTrue($result);

        // Verify request status changed to approved
        $request = GroupApprovalWorkflowService::getRequest($requestId);
        $this->assertEquals('approved', $request['status']);

        // Cleanup
        Database::query("DELETE FROM group_approval_requests WHERE id = ?", [$requestId]);
    }

    public function testApproveGroupReturnsFalseForInvalidId(): void
    {
        $result = GroupApprovalWorkflowService::approveGroup(999999, self::$testUserId);
        $this->assertFalse($result);
    }

    public function testRejectGroupChangesRequestStatus(): void
    {
        $requestId = GroupApprovalWorkflowService::submitForApproval(
            self::$testGroupId,
            self::$testUserId
        );

        $result = GroupApprovalWorkflowService::rejectGroup(
            $requestId,
            self::$testUserId,
            'Needs changes'
        );

        $this->assertTrue($result);

        // Verify request status changed to rejected
        $request = GroupApprovalWorkflowService::getRequest($requestId);
        $this->assertEquals('rejected', $request['status']);

        // Cleanup
        Database::query("DELETE FROM group_approval_requests WHERE id = ?", [$requestId]);
    }
}
