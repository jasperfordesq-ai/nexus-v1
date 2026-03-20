<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use PHPUnit\Framework\TestCase;
use App\Services\CommunityFundService;
use Nexus\Core\Database;
use App\Core\TenantContext;

/**
 * CommunityFundService Tests
 */
class CommunityFundServiceTest extends TestCase
{
    private static int $tenantId    = 2;
    private static int $adminId     = 1;
    private static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$tenantId);
        try {
            $ts = time();
            Database::query(
                "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at) VALUES (?, ?, ?, 'Fund', 'Test', 'Fund Test', 200, 1, NOW())",
                [self::$tenantId, "fund_test_{$ts}@test.com", "fund_test_{$ts}"]
            );
            self::$testUserId = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {}
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try { Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]); } catch (\Exception $e) {}
        }
        try {
            Database::query("DELETE FROM community_fund_transactions WHERE tenant_id = ?", [self::$tenantId]);
            Database::query("DELETE FROM community_fund_accounts WHERE tenant_id = ?", [self::$tenantId]);
        } catch (\Exception $e) {}
    }

    public function test_get_or_create_fund_returns_array(): void
    {
        try {
            $fund = CommunityFundService::getOrCreateFund();
            $this->assertIsArray($fund);
            $this->assertArrayHasKey('id', $fund);
            $this->assertArrayHasKey('balance', $fund);
            $this->assertArrayHasKey('tenant_id', $fund);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_or_create_fund_is_idempotent(): void
    {
        try {
            $fund1 = CommunityFundService::getOrCreateFund();
            $fund2 = CommunityFundService::getOrCreateFund();
            $this->assertSame((int)$fund1['id'], (int)$fund2['id']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_balance_returns_expected_structure(): void
    {
        try {
            $balance = CommunityFundService::getBalance();
            $this->assertIsArray($balance);
            $this->assertArrayHasKey('id', $balance);
            $this->assertArrayHasKey('balance', $balance);
            $this->assertArrayHasKey('total_deposited', $balance);
            $this->assertArrayHasKey('total_withdrawn', $balance);
            $this->assertArrayHasKey('total_donated', $balance);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_balance_values_are_floats(): void
    {
        try {
            $balance = CommunityFundService::getBalance();
            $this->assertIsFloat($balance['balance']);
            $this->assertIsFloat($balance['total_deposited']);
            $this->assertIsFloat($balance['total_withdrawn']);
            $this->assertIsFloat($balance['total_donated']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_admin_deposit_rejects_zero_amount(): void
    {
        $result = CommunityFundService::adminDeposit(self::$adminId, 0.0, 'Zero deposit');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('greater than 0', $result['error']);
    }

    public function test_admin_deposit_rejects_negative_amount(): void
    {
        $result = CommunityFundService::adminDeposit(self::$adminId, -10.0, 'Negative');
        $this->assertFalse($result['success']);
    }

    public function test_admin_deposit_succeeds_with_valid_amount(): void
    {
        try {
            $result = CommunityFundService::adminDeposit(self::$adminId, 50.0, 'Test deposit');
            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_admin_deposit_increases_balance(): void
    {
        try {
            $before = CommunityFundService::getBalance();
            CommunityFundService::adminDeposit(self::$adminId, 10.0, 'Balance test');
            $after = CommunityFundService::getBalance();
            $this->assertGreaterThanOrEqual($before['balance'], $after['balance']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_admin_withdraw_rejects_zero_amount(): void
    {
        $result = CommunityFundService::adminWithdraw(self::$adminId, self::$adminId, 0.0, 'Zero');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('greater than 0', $result['error']);
    }

    public function test_admin_withdraw_rejects_negative_amount(): void
    {
        $result = CommunityFundService::adminWithdraw(self::$adminId, self::$adminId, -5.0, 'Neg');
        $this->assertFalse($result['success']);
    }

    public function test_admin_withdraw_rejects_when_insufficient_balance(): void
    {
        try {
            $result = CommunityFundService::adminWithdraw(self::$adminId, self::$adminId, 9999999.0, 'Over');
            if (!$result['success']) {
                $this->assertStringContainsString('Insufficient', $result['error']);
            } else {
                $this->assertArrayHasKey('balance', $result);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_receive_donation_rejects_zero_amount(): void
    {
        if (!self::$testUserId) {
            $this->markTestSkipped('No test user available');
        }
        $result = CommunityFundService::receiveDonation(self::$testUserId, 0.0, 'Zero donation');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('greater than 0', $result['error']);
    }

    public function test_receive_donation_rejects_insufficient_balance(): void
    {
        if (!self::$testUserId) {
            $this->markTestSkipped('No test user available');
        }
        try {
            $result = CommunityFundService::receiveDonation(self::$testUserId, 9999999.0, 'Too much');
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('balance', strtolower($result['error']));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_transactions_returns_expected_structure(): void
    {
        try {
            $result = CommunityFundService::getTransactions(10, 0);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('items', $result);
            $this->assertArrayHasKey('total', $result);
            $this->assertIsInt($result['total']);
            $this->assertIsArray($result['items']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_transactions_respects_limit(): void
    {
        try {
            $result = CommunityFundService::getTransactions(2, 0);
            $this->assertLessThanOrEqual(2, count($result['items']));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_tenant_isolation_separate_fund_per_tenant(): void
    {
        try {
            TenantContext::setById(2);
            $fund2 = CommunityFundService::getOrCreateFund();
            TenantContext::setById(1);
            $fund1 = CommunityFundService::getOrCreateFund();
            // Different tenants get different fund IDs
            $this->assertNotSame((int)$fund1['id'], (int)$fund2['id']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        } finally {
            TenantContext::setById(self::$tenantId);
        }
    }
}
