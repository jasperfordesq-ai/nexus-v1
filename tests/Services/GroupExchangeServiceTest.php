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
use Nexus\Services\GroupExchangeService;

/**
 * GroupExchangeService Tests
 *
 * Tests multi-participant group exchanges with split types,
 * participant confirmation, and transaction creation.
 */
class GroupExchangeServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testExchangeId = null;

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

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [self::$testTenantId, "grpex1_{$ts}@test.com", "grpex1_{$ts}", 'GroupEx', 'One', 'GroupEx One', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [self::$testTenantId, "grpex2_{$ts}@test.com", "grpex2_{$ts}", 'GroupEx', 'Two', 'GroupEx Two', 50]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create test group exchange
        self::$testExchangeId = GroupExchangeService::create(self::$testUserId, [
            'title' => "Test Group Exchange {$ts}",
            'description' => 'Test group exchange for testing',
            'split_type' => 'equal',
            'total_hours' => 10.0
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testExchangeId) {
            try {
                Database::query("DELETE FROM group_exchange_participants WHERE group_exchange_id = ?", [self::$testExchangeId]);
                Database::query("DELETE FROM group_exchanges WHERE id = ?", [self::$testExchangeId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId && self::$testUser2Id) {
            try {
                Database::query("DELETE FROM users WHERE id IN (?, ?)", [self::$testUserId, self::$testUser2Id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function testCreateReturnsExchangeId(): void
    {
        $this->assertNotNull(self::$testExchangeId);
        $this->assertIsInt(self::$testExchangeId);
    }

    public function testCreateSetsDefaultStatus(): void
    {
        $exchange = GroupExchangeService::get(self::$testExchangeId);
        $this->assertNotNull($exchange);
        $this->assertEquals('draft', $exchange['status']);
    }

    // ==========================================
    // Get Tests
    // ==========================================

    public function testGetReturnsValidStructure(): void
    {
        $exchange = GroupExchangeService::get(self::$testExchangeId);

        $this->assertNotNull($exchange);
        $this->assertArrayHasKey('id', $exchange);
        $this->assertArrayHasKey('title', $exchange);
        $this->assertArrayHasKey('organizer_id', $exchange);
        $this->assertArrayHasKey('split_type', $exchange);
        $this->assertArrayHasKey('participants', $exchange);
    }

    public function testGetReturnsNullForInvalidId(): void
    {
        $exchange = GroupExchangeService::get(999999);
        $this->assertNull($exchange);
    }

    public function testGetIncludesParticipants(): void
    {
        $exchange = GroupExchangeService::get(self::$testExchangeId);
        $this->assertIsArray($exchange['participants']);
    }

    // ==========================================
    // Participant Tests
    // ==========================================

    public function testAddParticipantReturnsTrue(): void
    {
        $result = GroupExchangeService::addParticipant(
            self::$testExchangeId,
            self::$testUserId,
            'provider',
            5.0
        );
        $this->assertTrue($result);

        // Cleanup
        GroupExchangeService::removeParticipant(self::$testExchangeId, self::$testUserId);
    }

    public function testRemoveParticipantReturnsTrue(): void
    {
        // First add a participant
        GroupExchangeService::addParticipant(self::$testExchangeId, self::$testUserId, 'provider');

        $result = GroupExchangeService::removeParticipant(self::$testExchangeId, self::$testUserId);
        $this->assertTrue($result);
    }

    public function testConfirmParticipationReturnsTrue(): void
    {
        // Add participant first
        GroupExchangeService::addParticipant(self::$testExchangeId, self::$testUserId, 'provider');

        $result = GroupExchangeService::confirmParticipation(self::$testExchangeId, self::$testUserId);
        $this->assertTrue($result);

        // Cleanup
        GroupExchangeService::removeParticipant(self::$testExchangeId, self::$testUserId);
    }

    // ==========================================
    // List Tests
    // ==========================================

    public function testListForUserReturnsValidStructure(): void
    {
        $result = GroupExchangeService::listForUser(self::$testUserId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    public function testListForUserIncludesOrganizerExchanges(): void
    {
        $result = GroupExchangeService::listForUser(self::$testUserId);

        // Should include the exchange we created
        $ids = array_column($result['items'], 'id');
        $this->assertContains(self::$testExchangeId, $ids);
    }

    public function testListForUserRespectsStatusFilter(): void
    {
        $result = GroupExchangeService::listForUser(self::$testUserId, ['status' => 'draft']);
        $this->assertIsArray($result);

        foreach ($result['items'] as $item) {
            $this->assertEquals('draft', $item['status']);
        }
    }

    // ==========================================
    // Status Update Tests
    // ==========================================

    public function testUpdateStatusChangesStatus(): void
    {
        $result = GroupExchangeService::updateStatus(self::$testExchangeId, 'active');
        $this->assertTrue($result);

        $exchange = GroupExchangeService::get(self::$testExchangeId);
        $this->assertEquals('active', $exchange['status']);

        // Reset
        GroupExchangeService::updateStatus(self::$testExchangeId, 'draft');
    }

    // ==========================================
    // Split Calculation Tests
    // ==========================================

    public function testCalculateSplitReturnsArray(): void
    {
        // Add participants
        GroupExchangeService::addParticipant(self::$testExchangeId, self::$testUserId, 'provider', 5.0);
        GroupExchangeService::addParticipant(self::$testExchangeId, self::$testUser2Id, 'receiver', 5.0);

        $splits = GroupExchangeService::calculateSplit(self::$testExchangeId);
        $this->assertIsArray($splits);

        // Cleanup
        GroupExchangeService::removeParticipant(self::$testExchangeId, self::$testUserId);
        GroupExchangeService::removeParticipant(self::$testExchangeId, self::$testUser2Id);
    }

    public function testCalculateSplitHandlesEqualSplit(): void
    {
        // Set up equal split
        Database::query("UPDATE group_exchanges SET split_type = 'equal', total_hours = 10 WHERE id = ?", [self::$testExchangeId]);

        GroupExchangeService::addParticipant(self::$testExchangeId, self::$testUserId, 'provider');
        GroupExchangeService::addParticipant(self::$testExchangeId, self::$testUser2Id, 'receiver');

        $splits = GroupExchangeService::calculateSplit(self::$testExchangeId);
        $this->assertNotEmpty($splits);

        // Cleanup
        GroupExchangeService::removeParticipant(self::$testExchangeId, self::$testUserId);
        GroupExchangeService::removeParticipant(self::$testExchangeId, self::$testUser2Id);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $result = GroupExchangeService::update(self::$testExchangeId, [
            'title' => 'Updated Title'
        ]);
        $this->assertTrue($result);

        $exchange = GroupExchangeService::get(self::$testExchangeId);
        $this->assertEquals('Updated Title', $exchange['title']);
    }

    public function testUpdateIgnoresInvalidFields(): void
    {
        $result = GroupExchangeService::update(self::$testExchangeId, [
            'invalid_field' => 'value'
        ]);
        // Should return false since no valid fields
        $this->assertFalse($result);
    }

    // ==========================================
    // Complete Tests
    // ==========================================

    public function testCompleteRequiresPendingConfirmationStatus(): void
    {
        $result = GroupExchangeService::complete(self::$testExchangeId);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testCompleteReturnsErrorForInvalidId(): void
    {
        $result = GroupExchangeService::complete(999999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Exchange not found', $result['error']);
    }
}
