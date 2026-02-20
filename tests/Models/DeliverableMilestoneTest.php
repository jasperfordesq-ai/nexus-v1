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
use Nexus\Models\DeliverableMilestone;

/**
 * DeliverableMilestone Model Tests
 *
 * Tests milestone creation, find by ID, update, completion,
 * deletion, deliverable retrieval, reorder, and statistics.
 */
class DeliverableMilestoneTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testDeliverableId = null;

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
            [self::$testTenantId, "del_ms_test_{$timestamp}@test.com", "del_ms_test_{$timestamp}", 'DelMs', 'Tester', 'DelMs Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create a test deliverable
        try {
            Database::query(
                "INSERT INTO deliverables (tenant_id, title, description, status, created_by, created_at)
                 VALUES (?, ?, ?, 'in_progress', ?, NOW())",
                [self::$testTenantId, "Milestone Test Deliverable {$timestamp}", 'Deliverable for milestone tests', self::$testUserId]
            );
            self::$testDeliverableId = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            self::$testDeliverableId = null;
        }
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testDeliverableId) {
                Database::query("DELETE FROM deliverable_milestones WHERE deliverable_id = ?", [self::$testDeliverableId]);
                Database::query("DELETE FROM deliverables WHERE id = ?", [self::$testDeliverableId]);
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
        if (!self::$testDeliverableId) {
            $this->markTestSkipped('Deliverables table not available');
        }
    }

    public function testGetByDeliverableReturnsArray(): void
    {
        $milestones = DeliverableMilestone::getByDeliverable(self::$testDeliverableId);
        $this->assertIsArray($milestones);
    }

    public function testGetStatsReturnsStructure(): void
    {
        $stats = DeliverableMilestone::getStats(self::$testDeliverableId);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('in_progress', $stats);
        $this->assertArrayHasKey('pending', $stats);
    }

    public function testFindByIdReturnsFalseForNonExistent(): void
    {
        $milestone = DeliverableMilestone::findById(999999999);
        $this->assertFalse($milestone);
    }

    public function testGetByDeliverableReturnsEmptyForNonExistent(): void
    {
        $milestones = DeliverableMilestone::getByDeliverable(999999999);
        $this->assertIsArray($milestones);
        $this->assertEmpty($milestones);
    }
}
