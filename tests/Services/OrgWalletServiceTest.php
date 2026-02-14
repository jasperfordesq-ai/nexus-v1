<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\OrgWalletService;
use Nexus\Models\OrgWallet;
use Nexus\Models\OrgMember;
use Nexus\Models\OrgTransferRequest;
use Nexus\Models\User;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class OrgWalletServiceTest extends TestCase
{
    private static $testOwnerId;
    private static $testAdminId;
    private static $testMemberId;
    private static $testOrgId;
    private static $testTenantId = 1;
    private static $initialUserBalance = 100;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        // Use unique emails with timestamp to avoid conflicts
        $timestamp = time() . rand(1000, 9999);

        // Create test owner
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, 'Service', 'Owner', 'Service Owner', ?, 1, NOW())",
            [self::$testTenantId, "service_owner_{$timestamp}@test.com", self::$initialUserBalance]
        );
        self::$testOwnerId = Database::getInstance()->lastInsertId();

        // Create test admin
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, 'Service', 'Admin', 'Service Admin', ?, 1, NOW())",
            [self::$testTenantId, "service_admin_{$timestamp}@test.com", self::$initialUserBalance]
        );
        self::$testAdminId = Database::getInstance()->lastInsertId();

        // Create test member
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, 'Service', 'Member', 'Service Member', ?, 1, NOW())",
            [self::$testTenantId, "service_member_{$timestamp}@test.com", self::$initialUserBalance]
        );
        self::$testMemberId = Database::getInstance()->lastInsertId();

        // Create test organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, status, created_at)
             VALUES (?, ?, 'Service Test Org', 'Test organization', 'approved', NOW())",
            [self::$testTenantId, self::$testOwnerId]
        );
        self::$testOrgId = Database::getInstance()->lastInsertId();

        // Initialize wallet
        OrgWallet::getOrCreate(self::$testOrgId);

        // Add members with roles
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
        $userIds = [self::$testOwnerId, self::$testAdminId, self::$testMemberId];
        if (self::$testOwnerId) {
            Database::query("DELETE FROM notifications WHERE user_id IN (?, ?, ?)", $userIds);
            Database::query("DELETE FROM activity_log WHERE user_id IN (?, ?, ?)", $userIds);
            Database::query("DELETE FROM users WHERE id IN (?, ?, ?)", $userIds);
        }
    }

    protected function setUp(): void
    {
        // Reset user balances
        Database::query(
            "UPDATE users SET balance = ? WHERE id IN (?, ?, ?)",
            [self::$initialUserBalance, self::$testOwnerId, self::$testAdminId, self::$testMemberId]
        );

        // Reset org wallet balance
        Database::query(
            "UPDATE org_wallets SET balance = 0 WHERE organization_id = ?",
            [self::$testOrgId]
        );

        // Clean up transfer requests
        Database::query(
            "DELETE FROM org_transfer_requests WHERE organization_id = ?",
            [self::$testOrgId]
        );
    }

    /**
     * Test depositToOrg from user to organization
     */
    public function testDepositToOrgSuccess(): void
    {
        $amount = 25;
        $result = OrgWalletService::depositToOrg(
            self::$testOwnerId,
            self::$testOrgId,
            $amount,
            'Test deposit'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals($amount, OrgWallet::getBalance(self::$testOrgId));

        $user = User::findById(self::$testOwnerId);
        $this->assertEquals(self::$initialUserBalance - $amount, $user['balance']);
    }

    /**
     * Test depositToOrg fails for non-member
     */
    public function testDepositToOrgFailsForNonMember(): void
    {
        // Remove the member temporarily
        Database::query(
            "UPDATE org_members SET status = 'removed' WHERE organization_id = ? AND user_id = ?",
            [self::$testOrgId, self::$testMemberId]
        );

        $result = OrgWalletService::depositToOrg(
            self::$testMemberId,
            self::$testOrgId,
            10,
            'Should fail'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('member', strtolower($result['message']));

        // Restore
        Database::query(
            "UPDATE org_members SET status = 'active' WHERE organization_id = ? AND user_id = ?",
            [self::$testOrgId, self::$testMemberId]
        );
    }

    /**
     * Test depositToOrg fails with insufficient balance
     */
    public function testDepositToOrgFailsWithInsufficientBalance(): void
    {
        $result = OrgWalletService::depositToOrg(
            self::$testOwnerId,
            self::$testOrgId,
            99999,
            'Should fail'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('insufficient', strtolower($result['message']));
    }

    /**
     * Test depositToOrg fails with invalid amount
     */
    public function testDepositToOrgFailsWithZeroAmount(): void
    {
        $result = OrgWalletService::depositToOrg(
            self::$testOwnerId,
            self::$testOrgId,
            0,
            'Should fail'
        );

        $this->assertFalse($result['success']);
    }

    /**
     * Test createTransferRequest creates pending request
     */
    public function testCreateTransferRequestSuccess(): void
    {
        // Fund the wallet first
        OrgWallet::credit(self::$testOrgId, 100);

        $result = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            self::$testMemberId,
            self::$testMemberId,
            20,
            'Test request'
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('request_id', $result);

        // Verify request exists
        $request = OrgTransferRequest::find($result['request_id']);
        $this->assertNotNull($request);
        $this->assertEquals('pending', $request['status']);
    }

    /**
     * Test createTransferRequest fails for non-member
     */
    public function testCreateTransferRequestFailsForNonMember(): void
    {
        // Create a non-member user
        $timestamp = time() . rand(1000, 9999);
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, 'Non', 'Member', 'Non Member', 0, 1, NOW())",
            [self::$testTenantId, "nonmember_{$timestamp}@test.com"]
        );
        $nonMemberId = Database::getInstance()->lastInsertId();

        $result = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            $nonMemberId,
            $nonMemberId,
            10,
            'Should fail'
        );

        $this->assertFalse($result['success']);

        // Cleanup
        Database::query("DELETE FROM users WHERE id = ?", [$nonMemberId]);
    }

    /**
     * Test createTransferRequest fails when amount exceeds wallet
     */
    public function testCreateTransferRequestFailsExceedsBalance(): void
    {
        // Wallet has 0 balance
        $result = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            self::$testMemberId,
            self::$testMemberId,
            1000,
            'Should fail'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('insufficient', strtolower($result['message']));
    }

    /**
     * Test approveRequest transfers credits
     */
    public function testApproveRequestTransfersCredits(): void
    {
        // Fund wallet
        OrgWallet::credit(self::$testOrgId, 100);

        // Create request
        $createResult = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            self::$testMemberId,
            self::$testMemberId,
            30,
            'Approve test'
        );
        $requestId = $createResult['request_id'];

        // Approve as admin
        $approveResult = OrgWalletService::approveRequest($requestId, self::$testAdminId);

        $this->assertTrue($approveResult['success']);

        // Check balances
        $this->assertEquals(70.0, OrgWallet::getBalance(self::$testOrgId));

        $member = User::findById(self::$testMemberId);
        $this->assertEquals(self::$initialUserBalance + 30, $member['balance']);

        // Check request status
        $request = OrgTransferRequest::find($requestId);
        $this->assertEquals('approved', $request['status']);
    }

    /**
     * Test approveRequest fails for non-admin
     */
    public function testApproveRequestFailsForNonAdmin(): void
    {
        OrgWallet::credit(self::$testOrgId, 100);

        $createResult = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            self::$testMemberId,
            self::$testMemberId,
            20,
            'Non-admin approve test'
        );

        $approveResult = OrgWalletService::approveRequest(
            $createResult['request_id'],
            self::$testMemberId // member, not admin
        );

        $this->assertFalse($approveResult['success']);
        $this->assertStringContainsString('admin', strtolower($approveResult['message']));
    }

    /**
     * Test rejectRequest changes status
     */
    public function testRejectRequestChangesStatus(): void
    {
        OrgWallet::credit(self::$testOrgId, 100);

        $createResult = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            self::$testMemberId,
            self::$testMemberId,
            20,
            'Reject test'
        );

        $rejectResult = OrgWalletService::rejectRequest(
            $createResult['request_id'],
            self::$testAdminId,
            'Test rejection reason'
        );

        $this->assertTrue($rejectResult['success']);

        $request = OrgTransferRequest::find($createResult['request_id']);
        $this->assertEquals('rejected', $request['status']);
        $this->assertEquals('Test rejection reason', $request['rejection_reason']);
    }

    /**
     * Test cancelRequest by requester
     */
    public function testCancelRequestByRequester(): void
    {
        OrgWallet::credit(self::$testOrgId, 100);

        $createResult = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            self::$testMemberId,
            self::$testMemberId,
            20,
            'Cancel test'
        );

        $cancelResult = OrgWalletService::cancelRequest(
            $createResult['request_id'],
            self::$testMemberId
        );

        $this->assertTrue($cancelResult['success']);

        $request = OrgTransferRequest::find($createResult['request_id']);
        $this->assertEquals('cancelled', $request['status']);
    }

    /**
     * Test directTransferFromOrg by admin
     */
    public function testDirectTransferFromOrgByAdmin(): void
    {
        OrgWallet::credit(self::$testOrgId, 100);

        $result = OrgWalletService::directTransferFromOrg(
            self::$testOrgId,
            self::$testMemberId,
            40,
            'Direct transfer test',
            self::$testAdminId
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(60.0, OrgWallet::getBalance(self::$testOrgId));

        $member = User::findById(self::$testMemberId);
        $this->assertEquals(self::$initialUserBalance + 40, $member['balance']);
    }

    /**
     * Test directTransferFromOrg fails for non-admin
     */
    public function testDirectTransferFromOrgFailsForNonAdmin(): void
    {
        OrgWallet::credit(self::$testOrgId, 100);

        $result = OrgWalletService::directTransferFromOrg(
            self::$testOrgId,
            self::$testMemberId,
            40,
            'Should fail',
            self::$testMemberId // member, not admin
        );

        $this->assertFalse($result['success']);
    }

    /**
     * Test getWalletSummary returns correct data
     */
    public function testGetWalletSummaryReturnsCorrectData(): void
    {
        OrgWallet::credit(self::$testOrgId, 50);

        $summary = OrgWalletService::getWalletSummary(self::$testOrgId);

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('balance', $summary);
        $this->assertArrayHasKey('total_received', $summary);
        $this->assertArrayHasKey('total_paid_out', $summary);
        $this->assertArrayHasKey('transaction_count', $summary);
        $this->assertArrayHasKey('pending_requests', $summary);
        $this->assertEquals(50.0, $summary['balance']);
    }
}
