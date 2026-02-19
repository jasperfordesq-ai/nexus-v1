<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use Nexus\Models\OrgTransferRequest;
use Nexus\Models\OrgMember;
use Nexus\Models\OrgWallet;
use Nexus\Models\User;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class OrgTransferRequestTest extends TestCase
{
    private static $testOwnerId;
    private static $testAdminId;
    private static $testMemberId;
    private static $testRecipientId;
    private static $testOrgId;
    private static $testTenantId = 1;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        // Use unique emails with timestamp to avoid conflicts
        $timestamp = time() . rand(1000, 9999);

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, 'Request', 'Owner', 'Request Owner', 100, 1, NOW())",
            [self::$testTenantId, "request_owner_{$timestamp}@test.com"]
        );
        self::$testOwnerId = Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, 'Request', 'Admin', 'Request Admin', 100, 1, NOW())",
            [self::$testTenantId, "request_admin_{$timestamp}@test.com"]
        );
        self::$testAdminId = Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, 'Request', 'Member', 'Request Member', 100, 1, NOW())",
            [self::$testTenantId, "request_member_{$timestamp}@test.com"]
        );
        self::$testMemberId = Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, 'Request', 'Recipient', 'Request Recipient', 0, 1, NOW())",
            [self::$testTenantId, "request_recipient_{$timestamp}@test.com"]
        );
        self::$testRecipientId = Database::getInstance()->lastInsertId();

        // Create test organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, status, created_at)
             VALUES (?, ?, 'Request Test Org', 'Test organization', 'active', NOW())",
            [self::$testTenantId, self::$testOwnerId]
        );
        self::$testOrgId = Database::getInstance()->lastInsertId();

        // Set up memberships
        OrgMember::add(self::$testOrgId, self::$testOwnerId, 'owner', 'active');
        OrgMember::add(self::$testOrgId, self::$testAdminId, 'admin', 'active');
        OrgMember::add(self::$testOrgId, self::$testMemberId, 'member', 'active');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testOrgId) {
            Database::query("DELETE FROM org_transactions WHERE organization_id = ?", [self::$testOrgId]);
            Database::query("DELETE FROM org_transfer_requests WHERE organization_id = ?", [self::$testOrgId]);
            Database::query("DELETE FROM org_wallets WHERE organization_id = ?", [self::$testOrgId]);
            Database::query("DELETE FROM org_members WHERE organization_id = ?", [self::$testOrgId]);
            Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
        }
        $userIds = [self::$testOwnerId, self::$testAdminId, self::$testMemberId, self::$testRecipientId];
        Database::query("DELETE FROM notifications WHERE user_id IN (?, ?, ?, ?)", $userIds);
        Database::query("DELETE FROM activity_log WHERE user_id IN (?, ?, ?, ?)", $userIds);
        Database::query("DELETE FROM users WHERE id IN (?, ?, ?, ?)", $userIds);
    }

    protected function setUp(): void
    {
        // Clean up requests before each test
        Database::query("DELETE FROM org_transfer_requests WHERE organization_id = ?", [self::$testOrgId]);
        Database::query("DELETE FROM org_transactions WHERE organization_id = ?", [self::$testOrgId]);

        // Reset org wallet
        Database::query("DELETE FROM org_wallets WHERE organization_id = ?", [self::$testOrgId]);
        OrgWallet::credit(self::$testOrgId, 500); // Fund wallet

        // Reset user balances
        Database::query("UPDATE users SET balance = 100 WHERE id IN (?, ?, ?)",
            [self::$testOwnerId, self::$testAdminId, self::$testMemberId]);
        Database::query("UPDATE users SET balance = 0 WHERE id = ?", [self::$testRecipientId]);
    }

    /**
     * Test member can create transfer request
     */
    public function testMemberCanCreateTransferRequest(): void
    {
        $requestId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testMemberId,
            self::$testRecipientId,
            50,
            'Test transfer request'
        );

        $this->assertNotEmpty($requestId);
        $this->assertIsNumeric($requestId);
    }

    /**
     * Test find returns request with full details
     */
    public function testFindReturnsRequestWithDetails(): void
    {
        $requestId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testMemberId,
            self::$testRecipientId,
            50,
            'Find test'
        );

        $request = OrgTransferRequest::find($requestId);

        $this->assertIsArray($request);
        $this->assertEquals($requestId, $request['id']);
        $this->assertEquals('pending', $request['status']);
        $this->assertArrayHasKey('requester_name', $request);
        $this->assertArrayHasKey('recipient_name', $request);
        $this->assertArrayHasKey('organization_name', $request);
    }

    /**
     * Test non-member cannot create request
     */
    public function testNonMemberCannotCreateRequest(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only organization members');

        OrgTransferRequest::create(
            self::$testOrgId,
            self::$testRecipientId, // Not a member
            self::$testMemberId,
            50,
            'Should fail'
        );
    }

    /**
     * Test invalid amount throws exception
     */
    public function testInvalidAmountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OrgTransferRequest::create(
            self::$testOrgId,
            self::$testMemberId,
            self::$testRecipientId,
            0,
            'Invalid amount'
        );
    }

    /**
     * Test owner can approve request
     */
    public function testOwnerCanApproveRequest(): void
    {
        $requestId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testMemberId,
            self::$testRecipientId,
            50,
            'To be approved'
        );

        $transactionId = OrgTransferRequest::approve($requestId, self::$testOwnerId);

        $this->assertNotEmpty($transactionId);

        // Check request status
        $request = OrgTransferRequest::find($requestId);
        $this->assertEquals('approved', $request['status']);

        // Check recipient received credits
        $recipient = User::findById(self::$testRecipientId);
        $this->assertEquals(50, $recipient['balance']);
    }

    /**
     * Test admin can approve request
     */
    public function testAdminCanApproveRequest(): void
    {
        $requestId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testMemberId,
            self::$testRecipientId,
            30,
            'Admin approval'
        );

        $transactionId = OrgTransferRequest::approve($requestId, self::$testAdminId);

        $this->assertNotEmpty($transactionId);

        $request = OrgTransferRequest::find($requestId);
        $this->assertEquals('approved', $request['status']);
    }

    /**
     * Test member cannot approve request
     */
    public function testMemberCannotApproveRequest(): void
    {
        $requestId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testAdminId,
            self::$testRecipientId,
            30,
            'Member tries to approve'
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only owners and admins');

        OrgTransferRequest::approve($requestId, self::$testMemberId);
    }

    /**
     * Test requester cannot approve own request
     */
    public function testRequesterCannotApproveOwnRequest(): void
    {
        $requestId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testAdminId,
            self::$testRecipientId,
            30,
            'Self approval attempt'
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot approve your own');

        OrgTransferRequest::approve($requestId, self::$testAdminId);
    }

    /**
     * Test owner can reject request
     */
    public function testOwnerCanRejectRequest(): void
    {
        $requestId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testMemberId,
            self::$testRecipientId,
            50,
            'To be rejected'
        );

        OrgTransferRequest::reject($requestId, self::$testOwnerId, 'Not approved');

        $request = OrgTransferRequest::find($requestId);
        $this->assertEquals('rejected', $request['status']);
        $this->assertEquals('Not approved', $request['rejection_reason']);
    }

    /**
     * Test requester can cancel own request
     */
    public function testRequesterCanCancelOwnRequest(): void
    {
        $requestId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testMemberId,
            self::$testRecipientId,
            50,
            'To be cancelled'
        );

        OrgTransferRequest::cancel($requestId, self::$testMemberId);

        $request = OrgTransferRequest::find($requestId);
        $this->assertEquals('cancelled', $request['status']);
    }

    /**
     * Test non-requester cannot cancel request
     */
    public function testNonRequesterCannotCancelRequest(): void
    {
        $requestId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testMemberId,
            self::$testRecipientId,
            50,
            'Not my request'
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only the requester');

        OrgTransferRequest::cancel($requestId, self::$testAdminId);
    }

    /**
     * Test cannot approve already processed request
     */
    public function testCannotApproveAlreadyProcessedRequest(): void
    {
        $requestId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testMemberId,
            self::$testRecipientId,
            50,
            'Already processed'
        );

        // First approve it
        OrgTransferRequest::approve($requestId, self::$testOwnerId);

        // Try to approve again
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no longer pending');

        OrgTransferRequest::approve($requestId, self::$testAdminId);
    }

    /**
     * Test getPendingForOrganization returns only pending
     */
    public function testGetPendingForOrganizationReturnsOnlyPending(): void
    {
        // Create multiple requests with different statuses
        $pendingId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testMemberId,
            self::$testRecipientId,
            10,
            'Pending'
        );

        $approvedId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testMemberId,
            self::$testRecipientId,
            20,
            'Approved'
        );
        OrgTransferRequest::approve($approvedId, self::$testOwnerId);

        $pending = OrgTransferRequest::getPendingForOrganization(self::$testOrgId);

        $this->assertCount(1, $pending);
        $this->assertEquals($pendingId, $pending[0]['id']);
    }

    /**
     * Test getAllForOrganization returns all requests
     */
    public function testGetAllForOrganizationReturnsAllRequests(): void
    {
        OrgTransferRequest::create(self::$testOrgId, self::$testMemberId, self::$testRecipientId, 10, 'First');
        OrgTransferRequest::create(self::$testOrgId, self::$testMemberId, self::$testRecipientId, 20, 'Second');
        $thirdId = OrgTransferRequest::create(self::$testOrgId, self::$testMemberId, self::$testRecipientId, 30, 'Third');
        OrgTransferRequest::approve($thirdId, self::$testOwnerId);

        $all = OrgTransferRequest::getAllForOrganization(self::$testOrgId);

        $this->assertCount(3, $all);
    }

    /**
     * Test getByRequester returns user's requests
     */
    public function testGetByRequesterReturnsUsersRequests(): void
    {
        OrgTransferRequest::create(self::$testOrgId, self::$testMemberId, self::$testRecipientId, 10, 'My request');
        OrgTransferRequest::create(self::$testOrgId, self::$testAdminId, self::$testRecipientId, 20, 'Other request');

        $myRequests = OrgTransferRequest::getByRequester(self::$testMemberId);

        $this->assertCount(1, $myRequests);
        $this->assertEquals(self::$testMemberId, $myRequests[0]['requester_id']);
    }

    /**
     * Test countPending returns correct count
     */
    public function testCountPendingReturnsCorrectCount(): void
    {
        OrgTransferRequest::create(self::$testOrgId, self::$testMemberId, self::$testRecipientId, 10, 'First');
        OrgTransferRequest::create(self::$testOrgId, self::$testMemberId, self::$testRecipientId, 20, 'Second');
        $thirdId = OrgTransferRequest::create(self::$testOrgId, self::$testMemberId, self::$testRecipientId, 30, 'Third');
        OrgTransferRequest::approve($thirdId, self::$testOwnerId);

        $count = OrgTransferRequest::countPending(self::$testOrgId);

        $this->assertEquals(2, $count);
    }

    /**
     * Test approve deducts from org wallet
     */
    public function testApproveDeductsFromOrgWallet(): void
    {
        $initialBalance = OrgWallet::getBalance(self::$testOrgId);

        $requestId = OrgTransferRequest::create(
            self::$testOrgId,
            self::$testMemberId,
            self::$testRecipientId,
            100,
            'Deduct test'
        );

        OrgTransferRequest::approve($requestId, self::$testOwnerId);

        $newBalance = OrgWallet::getBalance(self::$testOrgId);
        $this->assertEquals($initialBalance - 100, $newBalance);
    }
}
