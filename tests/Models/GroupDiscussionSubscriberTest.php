<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\GroupDiscussion;
use Nexus\Models\GroupDiscussionSubscriber;

/**
 * GroupDiscussionSubscriber Model Tests
 *
 * Tests thread subscription, unsubscription, subscription check,
 * subscriber listing, and subscription retrieval.
 */
class GroupDiscussionSubscriberTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testGroupId = null;
    protected static ?int $testDiscussionId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "grp_sub_test_{$timestamp}@test.com", "grp_sub_test_{$timestamp}", 'GrpSub', 'Tester', 'GrpSub Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test group (groups table uses owner_id, not user_id, and has no slug column)
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, owner_id, visibility, created_at)
             VALUES (?, ?, ?, ?, 'public', NOW())",
            [self::$testTenantId, "Subscriber Test Group {$timestamp}", 'Group for subscriber tests', self::$testUserId]
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();

        // Create test discussion
        self::$testDiscussionId = (int)GroupDiscussion::create(self::$testGroupId, self::$testUserId, 'Subscription Test Discussion');
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testDiscussionId && self::$testUserId) {
                Database::query(
                    "DELETE FROM notification_settings WHERE user_id = ? AND context_type = 'thread' AND context_id = ?",
                    [self::$testUserId, self::$testDiscussionId]
                );
            }
            if (self::$testGroupId) {
                Database::query("DELETE FROM group_posts WHERE discussion_id IN (SELECT id FROM group_discussions WHERE group_id = ?)", [self::$testGroupId]);
                Database::query("DELETE FROM group_discussions WHERE group_id = ?", [self::$testGroupId]);
                Database::query("DELETE FROM group_members WHERE group_id = ?", [self::$testGroupId]);
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testGroupId]);
            }
            if (self::$testUserId) {
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            }
        } catch (\Exception $e) {
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // Subscribe Tests
    // ==========================================

    public function testSubscribeCreatesSubscription(): void
    {
        GroupDiscussionSubscriber::subscribe(self::$testUserId, self::$testDiscussionId);

        $isSubscribed = GroupDiscussionSubscriber::isSubscribed(self::$testUserId, self::$testDiscussionId);
        $this->assertTrue($isSubscribed);
    }

    // ==========================================
    // IsSubscribed Tests
    // ==========================================

    public function testIsSubscribedReturnsFalseWhenNotSubscribed(): void
    {
        $result = GroupDiscussionSubscriber::isSubscribed(999999999, self::$testDiscussionId);
        $this->assertFalse($result);
    }

    // ==========================================
    // Unsubscribe Tests
    // ==========================================

    public function testUnsubscribeSetsFrequencyToOff(): void
    {
        GroupDiscussionSubscriber::subscribe(self::$testUserId, self::$testDiscussionId);
        GroupDiscussionSubscriber::unsubscribe(self::$testUserId, self::$testDiscussionId);

        $isSubscribed = GroupDiscussionSubscriber::isSubscribed(self::$testUserId, self::$testDiscussionId);
        $this->assertFalse($isSubscribed);
    }

    // ==========================================
    // GetSubscribers Tests
    // ==========================================

    public function testGetSubscribersReturnsArray(): void
    {
        GroupDiscussionSubscriber::subscribe(self::$testUserId, self::$testDiscussionId);

        $subscribers = GroupDiscussionSubscriber::getSubscribers(self::$testDiscussionId);
        $this->assertIsArray($subscribers);
    }

    public function testGetSubscribersIncludesUserInfo(): void
    {
        GroupDiscussionSubscriber::subscribe(self::$testUserId, self::$testDiscussionId);

        $subscribers = GroupDiscussionSubscriber::getSubscribers(self::$testDiscussionId);
        if (!empty($subscribers)) {
            $this->assertArrayHasKey('email', $subscribers[0]);
            $this->assertArrayHasKey('id', $subscribers[0]);
            $this->assertArrayHasKey('first_name', $subscribers[0]);
        }
    }

    // ==========================================
    // GetSubscription Tests
    // ==========================================

    public function testGetSubscriptionReturnsFrequency(): void
    {
        GroupDiscussionSubscriber::subscribe(self::$testUserId, self::$testDiscussionId);

        $subscription = GroupDiscussionSubscriber::getSubscription(self::$testUserId, self::$testDiscussionId);
        $this->assertNotFalse($subscription);
        $this->assertArrayHasKey('frequency', $subscription);
        $this->assertEquals('instant', $subscription['frequency']);
    }

    public function testGetSubscriptionReturnsFalseForNonExistent(): void
    {
        $subscription = GroupDiscussionSubscriber::getSubscription(999999999, self::$testDiscussionId);
        $this->assertFalse($subscription);
    }
}
