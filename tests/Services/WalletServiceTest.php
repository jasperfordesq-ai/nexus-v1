<?php

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\OrgWalletService;

/**
 * OrgWalletService Tests
 *
 * Tests organization wallet transfer operations.
 */
class WalletServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testRecipientId = null;
    protected static ?int $testOrgId = null;

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

        // Create test requester user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "wallet_requester_{$timestamp}@test.com", "wallet_requester_{$timestamp}", 'Wallet', 'Requester', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test recipient user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "wallet_recipient_{$timestamp}@test.com", "wallet_recipient_{$timestamp}", 'Wallet', 'Recipient', 50]
        );
        self::$testRecipientId = (int)Database::getInstance()->lastInsertId();

        // Create test organization with wallet
        try {
            Database::query(
                "INSERT INTO vol_organizations (tenant_id, name, slug, status, created_at)
                 VALUES (?, ?, ?, 'active', NOW())",
                [self::$testTenantId, "Wallet Test Org {$timestamp}", "wallet-test-org-{$timestamp}"]
            );
            self::$testOrgId = (int)Database::getInstance()->lastInsertId();

            // Create org wallet with balance
            Database::query(
                "INSERT INTO org_wallets (organization_id, balance, created_at)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE balance = VALUES(balance)",
                [self::$testOrgId, 1000]
            );

            // Add user as org member
            Database::query(
                "INSERT INTO org_members (organization_id, user_id, role, status, created_at)
                 VALUES (?, ?, 'admin', 'active', NOW())",
                [self::$testOrgId, self::$testUserId]
            );
        } catch (\Exception $e) {
            // Table may not exist in test DB
            self::$testOrgId = 1;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testOrgId && self::$testOrgId > 1) {
            try {
                Database::query("DELETE FROM org_transfer_requests WHERE organization_id = ?", [self::$testOrgId]);
                Database::query("DELETE FROM org_transactions WHERE organization_id = ?", [self::$testOrgId]);
                Database::query("DELETE FROM org_members WHERE organization_id = ?", [self::$testOrgId]);
                Database::query("DELETE FROM org_wallets WHERE organization_id = ?", [self::$testOrgId]);
                Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testRecipientId) {
            try {
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testRecipientId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testRecipientId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Create Transfer Request Tests
    // ==========================================

    public function testCreateTransferRequestReturnsExpectedStructure(): void
    {
        $result = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            self::$testUserId,
            self::$testRecipientId,
            10,
            'Test transfer'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertIsBool($result['success']);
        $this->assertIsString($result['message']);
    }

    public function testCreateTransferRequestRejectsZeroAmount(): void
    {
        $result = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            self::$testUserId,
            self::$testRecipientId,
            0,
            'Zero transfer'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('positive', $result['message']);
    }

    public function testCreateTransferRequestRejectsNegativeAmount(): void
    {
        $result = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            self::$testUserId,
            self::$testRecipientId,
            -10,
            'Negative transfer'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('positive', $result['message']);
    }

    public function testCreateTransferRequestRejectsNonExistentRecipient(): void
    {
        $result = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            self::$testUserId,
            999999, // Non-existent recipient
            10,
            'Transfer to ghost'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Recipient not found', $result['message']);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCreateTransferRequestWithInsufficientBalance(): void
    {
        $result = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            self::$testUserId,
            self::$testRecipientId,
            999999, // More than wallet balance
            'Over-limit transfer'
        );

        // Should fail for either insufficient balance or limit exceeded
        $this->assertFalse($result['success']);
    }

    public function testCreateTransferRequestWithEmptyDescription(): void
    {
        $result = OrgWalletService::createTransferRequest(
            self::$testOrgId,
            self::$testUserId,
            self::$testRecipientId,
            10,
            '' // Empty description
        );

        $this->assertIsArray($result);
        // Should still work with empty description
        $this->assertArrayHasKey('success', $result);
    }
}
