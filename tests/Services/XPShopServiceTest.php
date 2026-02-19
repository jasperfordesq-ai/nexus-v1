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
use Nexus\Services\XPShopService;

/**
 * XPShopService Tests
 *
 * Tests XP shop purchase operations, including transaction atomicity.
 */
class XPShopServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testItemId = null;
    private static int $initialXp = 1000;

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

        // Create test user with XP
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, xp, level, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())",
            [self::$testTenantId, "xpshop_user_{$timestamp}@test.com", "xpshop_user_{$timestamp}", 'XP', 'Shopper', 'XP Shopper', self::$initialXp]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test shop item
        Database::query(
            "INSERT INTO xp_shop_items (tenant_id, item_key, name, description, icon, item_type, xp_cost, per_user_limit, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [self::$testTenantId, "test_item_{$timestamp}", 'Test Item', 'A test shop item', 'gift', 'perk', 100, 1]
        );
        self::$testItemId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testItemId) {
            try {
                Database::query("DELETE FROM user_xp_purchases WHERE item_id = ?", [self::$testItemId]);
                Database::query("DELETE FROM xp_shop_items WHERE id = ?", [self::$testItemId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Reset user XP before each test
        Database::query(
            "UPDATE users SET xp = ? WHERE id = ?",
            [self::$initialXp, self::$testUserId]
        );

        // Clear any previous purchases
        Database::query(
            "DELETE FROM user_xp_purchases WHERE user_id = ?",
            [self::$testUserId]
        );
    }

    // ==========================================
    // Get Items Tests
    // ==========================================

    public function testGetAvailableItemsReturnsArray(): void
    {
        $items = XPShopService::getAvailableItems();

        $this->assertIsArray($items);
    }

    public function testGetItemsWithUserStatusIncludesUserXp(): void
    {
        $data = XPShopService::getItemsWithUserStatus(self::$testUserId);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('user_xp', $data);
        $this->assertEquals(self::$initialXp, $data['user_xp']);
    }

    public function testGetItemsWithUserStatusIncludesCanPurchase(): void
    {
        $data = XPShopService::getItemsWithUserStatus(self::$testUserId);

        $this->assertNotEmpty($data['items']);
        $item = $data['items'][0];

        $this->assertArrayHasKey('can_purchase', $item);
        $this->assertArrayHasKey('user_purchases', $item);
    }

    // ==========================================
    // Can Purchase Tests
    // ==========================================

    public function testCanPurchaseReturnsTrueWithSufficientXp(): void
    {
        $item = Database::query(
            "SELECT * FROM xp_shop_items WHERE id = ?",
            [self::$testItemId]
        )->fetch();

        $canPurchase = XPShopService::canPurchase(self::$testUserId, $item);

        $this->assertTrue($canPurchase);
    }

    public function testCanPurchaseReturnsFalseWithInsufficientXp(): void
    {
        // Set user XP to 0
        Database::query("UPDATE users SET xp = 0 WHERE id = ?", [self::$testUserId]);

        $item = Database::query(
            "SELECT * FROM xp_shop_items WHERE id = ?",
            [self::$testItemId]
        )->fetch();

        $canPurchase = XPShopService::canPurchase(self::$testUserId, $item);

        $this->assertFalse($canPurchase);
    }

    public function testCanPurchaseReturnsFalseWhenPerUserLimitReached(): void
    {
        // Make a purchase first
        XPShopService::purchase(self::$testUserId, self::$testItemId);

        $item = Database::query(
            "SELECT * FROM xp_shop_items WHERE id = ?",
            [self::$testItemId]
        )->fetch();

        // Now check if user can purchase again (per_user_limit = 1)
        $canPurchase = XPShopService::canPurchase(self::$testUserId, $item);

        $this->assertFalse($canPurchase);
    }

    // ==========================================
    // Purchase Tests
    // ==========================================

    public function testPurchaseSucceedsWithValidData(): void
    {
        $result = XPShopService::purchase(self::$testUserId, self::$testItemId);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('item', $result);
        $this->assertArrayHasKey('xp_spent', $result);
    }

    public function testPurchaseDeductsXpFromUser(): void
    {
        $item = Database::query(
            "SELECT * FROM xp_shop_items WHERE id = ?",
            [self::$testItemId]
        )->fetch();

        $initialXp = self::$initialXp;

        XPShopService::purchase(self::$testUserId, self::$testItemId);

        $user = Database::query("SELECT xp FROM users WHERE id = ?", [self::$testUserId])->fetch();

        $this->assertEquals($initialXp - $item['xp_cost'], $user['xp']);
    }

    public function testPurchaseCreatesRecord(): void
    {
        XPShopService::purchase(self::$testUserId, self::$testItemId);

        $purchase = Database::query(
            "SELECT * FROM user_xp_purchases WHERE user_id = ? AND item_id = ?",
            [self::$testUserId, self::$testItemId]
        )->fetch();

        $this->assertNotEmpty($purchase);
        $this->assertEquals(100, $purchase['xp_spent']);
    }

    public function testPurchaseFailsWithNonExistentItem(): void
    {
        $result = XPShopService::purchase(self::$testUserId, 999999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Item not found', $result['error']);
    }

    public function testPurchaseFailsWithInsufficientXp(): void
    {
        // Set user XP to 0
        Database::query("UPDATE users SET xp = 0 WHERE id = ?", [self::$testUserId]);

        $result = XPShopService::purchase(self::$testUserId, self::$testItemId);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('XP', $result['error']);
    }

    public function testPurchaseFailsWhenPerUserLimitReached(): void
    {
        // Make first purchase
        XPShopService::purchase(self::$testUserId, self::$testItemId);

        // Reset XP for second purchase attempt
        Database::query(
            "UPDATE users SET xp = ? WHERE id = ?",
            [self::$initialXp, self::$testUserId]
        );

        // Try to purchase again
        $result = XPShopService::purchase(self::$testUserId, self::$testItemId);

        $this->assertFalse($result['success']);
        // The purchase method checks XP balance or per-user limit; accept either error
        $this->assertTrue(
            str_contains($result['error'], 'Already owned') || str_contains($result['error'], 'XP') || str_contains($result['error'], 'limit'),
            "Expected purchase limit or XP error, got: {$result['error']}"
        );
    }

    // ==========================================
    // Transaction Atomicity Tests
    // ==========================================

    public function testPurchaseIsAtomicXpNotDeductedOnFailure(): void
    {
        $initialXp = self::$initialXp;

        // Try to purchase non-existent item
        $result = XPShopService::purchase(self::$testUserId, 999999);

        $this->assertFalse($result['success']);

        // XP should not be deducted
        $user = Database::query("SELECT xp FROM users WHERE id = ?", [self::$testUserId])->fetch();
        $this->assertEquals($initialXp, $user['xp']);
    }

    public function testPurchaseRecordNotCreatedOnXpDeductionFailure(): void
    {
        // Set XP to less than item cost
        Database::query("UPDATE users SET xp = 50 WHERE id = ?", [self::$testUserId]);

        $result = XPShopService::purchase(self::$testUserId, self::$testItemId);

        $this->assertFalse($result['success']);

        // No purchase record should exist
        $purchase = Database::query(
            "SELECT * FROM user_xp_purchases WHERE user_id = ? AND item_id = ?",
            [self::$testUserId, self::$testItemId]
        )->fetch();

        $this->assertFalse($purchase);
    }

    // ==========================================
    // User Perks Tests
    // ==========================================

    public function testGetUserActivePerksReturnsArray(): void
    {
        $perks = XPShopService::getUserActivePerks(self::$testUserId);

        $this->assertIsArray($perks);
    }

    public function testHasPerkReturnsFalseWithoutPurchase(): void
    {
        $hasPerk = XPShopService::hasPerk(self::$testUserId, 'test_item_' . time());

        $this->assertFalse($hasPerk);
    }

    // ==========================================
    // Admin Functions Tests
    // ==========================================

    public function testCreateItemReturnsId(): void
    {
        $timestamp = time();

        $itemId = XPShopService::createItem([
            'item_key' => "admin_test_{$timestamp}",
            'name' => 'Admin Test Item',
            'description' => 'Created by test',
            'icon' => 'test',
            'item_type' => 'perk',
            'xp_cost' => 50,
            'per_user_limit' => 1,
        ]);

        $this->assertIsNumeric($itemId);
        $this->assertGreaterThan(0, (int)$itemId);

        // Clean up
        Database::query("DELETE FROM xp_shop_items WHERE id = ?", [$itemId]);
    }
}
