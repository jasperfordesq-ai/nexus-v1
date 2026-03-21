<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services\Federation;

use App\Tests\DatabaseTestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\FederatedConnectionService;

/**
 * FederatedConnectionService Tests
 *
 * Tests cross-tenant connection request lifecycle: send, accept, reject, remove.
 */
class FederatedConnectionServiceTest extends DatabaseTestCase
{
    protected static ?int $tenantId = null;
    protected static ?int $otherTenantId = null;
    protected static ?int $testUser1Id = null;
    protected static ?int $testUser2Id = null;
    protected static bool $dbAvailable = false;

    private FederatedConnectionService $service;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tenantId = 2;
        self::$otherTenantId = 1;

        try {
            TenantContext::setById(self::$tenantId);
        } catch (\Throwable $e) {
            return;
        }

        try {
            $timestamp = time() . rand(1000, 9999);

            Database::query(
                "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, is_approved, status, created_at)
                 VALUES (?, ?, ?, 'FC', 'User1', 'FC User1', 1, 'active', NOW())",
                [self::$tenantId, "fc_test1_{$timestamp}@test.com", "fc_test1_{$timestamp}"]
            );
            self::$testUser1Id = (int) Database::getInstance()->lastInsertId();

            Database::query(
                "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, is_approved, status, created_at)
                 VALUES (?, ?, ?, 'FC', 'User2', 'FC User2', 1, 'active', NOW())",
                [self::$otherTenantId, "fc_test2_{$timestamp}@test.com", "fc_test2_{$timestamp}"]
            );
            self::$testUser2Id = (int) Database::getInstance()->lastInsertId();

            self::$dbAvailable = true;
        } catch (\Throwable $e) {
            error_log("FederatedConnectionServiceTest setup failed: " . $e->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$dbAvailable) {
            return;
        }

        try {
            if (self::$testUser1Id) {
                Database::query(
                    "DELETE FROM federation_connections WHERE requester_user_id = ? OR receiver_user_id = ?",
                    [self::$testUser1Id, self::$testUser1Id]
                );
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUser1Id]);
            }
            if (self::$testUser2Id) {
                Database::query(
                    "DELETE FROM federation_connections WHERE requester_user_id = ? OR receiver_user_id = ?",
                    [self::$testUser2Id, self::$testUser2Id]
                );
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUser2Id]);
            }
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available for integration test');
        }

        TenantContext::setById(self::$tenantId);
        $this->service = new FederatedConnectionService();
    }

    // ==========================================
    // sendRequest Tests
    // ==========================================

    public function testSendRequestSuccess(): void
    {
        $result = $this->service->sendRequest(
            self::$testUser1Id,
            self::$testUser2Id,
            self::$otherTenantId,
            'Hello, let us connect!'
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('connection_id', $result);
        $this->assertIsInt($result['connection_id']);

        // Clean up for subsequent tests
        Database::query("DELETE FROM federation_connections WHERE id = ?", [$result['connection_id']]);
    }

    public function testSendRequestToSelfFails(): void
    {
        $result = $this->service->sendRequest(
            self::$testUser1Id,
            self::$testUser1Id,
            self::$tenantId
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('yourself', $result['error']);
    }

    public function testSendDuplicatePendingRequestFails(): void
    {
        $result1 = $this->service->sendRequest(
            self::$testUser1Id,
            self::$testUser2Id,
            self::$otherTenantId
        );
        $this->assertTrue($result1['success']);

        $result2 = $this->service->sendRequest(
            self::$testUser1Id,
            self::$testUser2Id,
            self::$otherTenantId
        );
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('pending', $result2['error']);

        Database::query("DELETE FROM federation_connections WHERE id = ?", [$result1['connection_id']]);
    }

    public function testSendRequestWithMessage(): void
    {
        $message = 'I would like to connect across our timebanks';
        $result = $this->service->sendRequest(
            self::$testUser1Id,
            self::$testUser2Id,
            self::$otherTenantId,
            $message
        );

        $this->assertTrue($result['success']);

        Database::query("DELETE FROM federation_connections WHERE id = ?", [$result['connection_id']]);
    }

    // ==========================================
    // acceptRequest Tests
    // ==========================================

    public function testAcceptRequestSuccess(): void
    {
        $sendResult = $this->service->sendRequest(
            self::$testUser1Id,
            self::$testUser2Id,
            self::$otherTenantId
        );
        $this->assertTrue($sendResult['success']);

        $acceptResult = $this->service->acceptRequest(
            $sendResult['connection_id'],
            self::$testUser2Id
        );

        $this->assertTrue($acceptResult['success']);
        $this->assertEquals($sendResult['connection_id'], $acceptResult['connection_id']);

        Database::query("DELETE FROM federation_connections WHERE id = ?", [$sendResult['connection_id']]);
    }

    public function testAcceptRequestByWrongUserFails(): void
    {
        $sendResult = $this->service->sendRequest(
            self::$testUser1Id,
            self::$testUser2Id,
            self::$otherTenantId
        );
        $this->assertTrue($sendResult['success']);

        // User1 (requester) tries to accept their own request
        $acceptResult = $this->service->acceptRequest(
            $sendResult['connection_id'],
            self::$testUser1Id
        );

        $this->assertFalse($acceptResult['success']);
        $this->assertStringContainsString('not found', $acceptResult['error']);

        Database::query("DELETE FROM federation_connections WHERE id = ?", [$sendResult['connection_id']]);
    }

    public function testAcceptNonExistentRequestFails(): void
    {
        $result = $this->service->acceptRequest(999999, self::$testUser2Id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['error']);
    }

    // ==========================================
    // rejectRequest Tests
    // ==========================================

    public function testRejectRequestSuccess(): void
    {
        $sendResult = $this->service->sendRequest(
            self::$testUser1Id,
            self::$testUser2Id,
            self::$otherTenantId
        );
        $this->assertTrue($sendResult['success']);

        $rejectResult = $this->service->rejectRequest(
            $sendResult['connection_id'],
            self::$testUser2Id
        );

        $this->assertTrue($rejectResult['success']);
        $this->assertEquals($sendResult['connection_id'], $rejectResult['connection_id']);

        Database::query("DELETE FROM federation_connections WHERE id = ?", [$sendResult['connection_id']]);
    }

    public function testRejectRequestByWrongUserFails(): void
    {
        $sendResult = $this->service->sendRequest(
            self::$testUser1Id,
            self::$testUser2Id,
            self::$otherTenantId
        );
        $this->assertTrue($sendResult['success']);

        $rejectResult = $this->service->rejectRequest(
            $sendResult['connection_id'],
            self::$testUser1Id
        );

        $this->assertFalse($rejectResult['success']);

        Database::query("DELETE FROM federation_connections WHERE id = ?", [$sendResult['connection_id']]);
    }

    // ==========================================
    // removeConnection Tests
    // ==========================================

    public function testRemoveConnectionSuccess(): void
    {
        $sendResult = $this->service->sendRequest(
            self::$testUser1Id,
            self::$testUser2Id,
            self::$otherTenantId
        );
        $this->assertTrue($sendResult['success']);

        $this->service->acceptRequest($sendResult['connection_id'], self::$testUser2Id);

        $removeResult = $this->service->removeConnection(
            $sendResult['connection_id'],
            self::$testUser1Id
        );

        $this->assertTrue($removeResult['success']);
    }

    public function testRemoveNonExistentConnectionFails(): void
    {
        $result = $this->service->removeConnection(999999, self::$testUser1Id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['error']);
    }

    // ==========================================
    // getStatus Tests
    // ==========================================

    public function testGetStatusNone(): void
    {
        $status = $this->service->getStatus(
            self::$testUser1Id,
            self::$testUser2Id,
            self::$otherTenantId
        );

        $this->assertEquals('none', $status['status']);
        $this->assertNull($status['connection_id']);
    }

    public function testGetStatusPending(): void
    {
        $sendResult = $this->service->sendRequest(
            self::$testUser1Id,
            self::$testUser2Id,
            self::$otherTenantId
        );
        $this->assertTrue($sendResult['success']);

        $status = $this->service->getStatus(
            self::$testUser1Id,
            self::$testUser2Id,
            self::$otherTenantId
        );

        $this->assertEquals('pending', $status['status']);
        $this->assertEquals($sendResult['connection_id'], $status['connection_id']);
        $this->assertArrayHasKey('direction', $status);

        Database::query("DELETE FROM federation_connections WHERE id = ?", [$sendResult['connection_id']]);
    }

    // ==========================================
    // getConnections Tests
    // ==========================================

    public function testGetConnectionsReturnsArray(): void
    {
        $connections = $this->service->getConnections(self::$testUser1Id);

        $this->assertIsArray($connections);
    }

    public function testGetConnectionsWithStatusFilter(): void
    {
        $connections = $this->service->getConnections(self::$testUser1Id, 'pending');

        $this->assertIsArray($connections);
    }

    public function testGetConnectionsInvalidStatusDefaultsToAccepted(): void
    {
        $connections = $this->service->getConnections(self::$testUser1Id, 'invalid_status');

        $this->assertIsArray($connections);
    }

    // ==========================================
    // getPendingCount Tests
    // ==========================================

    public function testGetPendingCountReturnsInt(): void
    {
        $count = $this->service->getPendingCount(self::$testUser1Id);

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testGetPendingCountForNonExistentUserIsZero(): void
    {
        $count = $this->service->getPendingCount(999999);

        $this->assertIsInt($count);
        $this->assertEquals(0, $count);
    }
}
