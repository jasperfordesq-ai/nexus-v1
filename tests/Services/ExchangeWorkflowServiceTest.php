<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\ExchangeWorkflowService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ExchangeWorkflowServiceTest
 *
 * Tests for the exchange workflow service.
 * Covers request creation, status transitions, dual-party confirmation, and transaction creation.
 */
class ExchangeWorkflowServiceTest extends TestCase
{
    private static $testTenantId = 1;
    private static $testRequesterId;
    private static $testProviderId;
    private static $testBrokerId;
    private static $testListingId;
    private static $testExchangeId;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        $timestamp = time() . rand(1000, 9999);

        // Create test requester
        Database::query(
            "INSERT INTO users (tenant_id, email, name, first_name, last_name, role, is_approved, status, created_at)
             VALUES (?, ?, 'Test Requester', 'Test', 'Requester', 'member', 1, 'active', NOW())",
            [self::$testTenantId, 'exchange_requester_' . $timestamp . '@test.com']
        );
        self::$testRequesterId = Database::getInstance()->lastInsertId();

        // Create test provider
        Database::query(
            "INSERT INTO users (tenant_id, email, name, first_name, last_name, role, is_approved, status, created_at)
             VALUES (?, ?, 'Test Provider', 'Test', 'Provider', 'member', 1, 'active', NOW())",
            [self::$testTenantId, 'exchange_provider_' . $timestamp . '@test.com']
        );
        self::$testProviderId = Database::getInstance()->lastInsertId();

        // Create test broker
        Database::query(
            "INSERT INTO users (tenant_id, email, name, first_name, last_name, role, is_approved, status, created_at)
             VALUES (?, ?, 'Test Broker', 'Test', 'Broker', 'broker', 1, 'active', NOW())",
            [self::$testTenantId, 'exchange_broker_' . $timestamp . '@test.com']
        );
        self::$testBrokerId = Database::getInstance()->lastInsertId();

        // Create test listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, 'Test Service Offer', 'Description for exchange test', 'offer', 'active', NOW())",
            [self::$testTenantId, self::$testProviderId]
        );
        self::$testListingId = Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up exchange requests and history
        if (self::$testExchangeId) {
            Database::query("DELETE FROM exchange_history WHERE exchange_id = ?", [self::$testExchangeId]);
            Database::query("DELETE FROM exchange_requests WHERE id = ?", [self::$testExchangeId]);
        }

        // Clean up any other test exchanges
        Database::query(
            "DELETE FROM exchange_history WHERE exchange_id IN (SELECT id FROM exchange_requests WHERE tenant_id = ? AND requester_id = ?)",
            [self::$testTenantId, self::$testRequesterId]
        );
        Database::query(
            "DELETE FROM exchange_requests WHERE tenant_id = ? AND requester_id = ?",
            [self::$testTenantId, self::$testRequesterId]
        );

        // Clean up test listing
        if (self::$testListingId) {
            Database::query("DELETE FROM listings WHERE id = ?", [self::$testListingId]);
        }

        // Clean up test users
        if (self::$testRequesterId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$testRequesterId]);
        }
        if (self::$testProviderId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$testProviderId]);
        }
        if (self::$testBrokerId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$testBrokerId]);
        }
    }

    /**
     * Test creating an exchange request
     */
    public function testCreateRequest(): int
    {
        $data = [
            'proposed_hours' => 2.0,
            'message' => 'I would like to request this service',
        ];

        $exchangeId = ExchangeWorkflowService::createRequest(
            self::$testRequesterId,
            self::$testListingId,
            $data
        );

        $this->assertNotNull($exchangeId, 'Should return an exchange ID');
        $this->assertIsInt($exchangeId, 'Exchange ID should be an integer');

        self::$testExchangeId = $exchangeId;

        // Verify the exchange was created
        $exchange = ExchangeWorkflowService::getExchange($exchangeId);
        $this->assertNotNull($exchange, 'Exchange should be retrievable');
        $this->assertEquals(ExchangeWorkflowService::STATUS_PENDING_PROVIDER, $exchange['status']);
        $this->assertEquals(self::$testRequesterId, $exchange['requester_id']);
        $this->assertEquals(self::$testProviderId, $exchange['provider_id']);
        $this->assertEquals(2.0, (float)$exchange['proposed_hours']);

        return $exchangeId;
    }

    /**
     * Test getting exchange by ID
     * @depends testCreateRequest
     */
    public function testGetExchange(int $exchangeId): void
    {
        $exchange = ExchangeWorkflowService::getExchange($exchangeId);

        $this->assertIsArray($exchange, 'Should return an array');
        $this->assertArrayHasKey('id', $exchange);
        $this->assertArrayHasKey('status', $exchange);
        $this->assertArrayHasKey('requester_id', $exchange);
        $this->assertArrayHasKey('provider_id', $exchange);
        $this->assertArrayHasKey('listing_id', $exchange);
        $this->assertArrayHasKey('proposed_hours', $exchange);
    }

    /**
     * Test getting non-existent exchange
     */
    public function testGetNonExistentExchange(): void
    {
        $exchange = ExchangeWorkflowService::getExchange(999999999);
        $this->assertNull($exchange, 'Should return null for non-existent exchange');
    }

    /**
     * Test provider accepting exchange request
     * @depends testCreateRequest
     */
    public function testAcceptRequest(int $exchangeId): int
    {
        $result = ExchangeWorkflowService::acceptRequest(
            $exchangeId,
            self::$testProviderId
        );

        $this->assertTrue($result, 'Accept should succeed');

        // Verify status changed
        $exchange = ExchangeWorkflowService::getExchange($exchangeId);
        // Status depends on whether broker approval is required
        $this->assertContains(
            $exchange['status'],
            [ExchangeWorkflowService::STATUS_ACCEPTED, ExchangeWorkflowService::STATUS_PENDING_BROKER],
            'Status should be accepted or pending_broker'
        );

        return $exchangeId;
    }

    /**
     * Test that wrong user cannot accept request
     */
    public function testWrongUserCannotAcceptRequest(): void
    {
        // Create a new exchange for this test
        $data = ['proposed_hours' => 1.0];
        $exchangeId = ExchangeWorkflowService::createRequest(
            self::$testRequesterId,
            self::$testListingId,
            $data
        );

        // Try to accept as requester (wrong user)
        $result = ExchangeWorkflowService::acceptRequest(
            $exchangeId,
            self::$testRequesterId // Should be provider
        );

        $this->assertFalse($result, 'Wrong user should not be able to accept');

        // Clean up
        Database::query("DELETE FROM exchange_history WHERE exchange_id = ?", [$exchangeId]);
        Database::query("DELETE FROM exchange_requests WHERE id = ?", [$exchangeId]);
    }

    /**
     * Test provider declining exchange request
     */
    public function testDeclineRequest(): void
    {
        // Create a new exchange to decline
        $data = ['proposed_hours' => 1.5];
        $exchangeId = ExchangeWorkflowService::createRequest(
            self::$testRequesterId,
            self::$testListingId,
            $data
        );

        $result = ExchangeWorkflowService::declineRequest(
            $exchangeId,
            self::$testProviderId,
            'I am not available at the moment'
        );

        $this->assertTrue($result, 'Decline should succeed');

        $exchange = ExchangeWorkflowService::getExchange($exchangeId);
        $this->assertEquals(ExchangeWorkflowService::STATUS_CANCELLED, $exchange['status']);

        // Clean up
        Database::query("DELETE FROM exchange_history WHERE exchange_id = ?", [$exchangeId]);
        Database::query("DELETE FROM exchange_requests WHERE id = ?", [$exchangeId]);
    }

    /**
     * Test starting an exchange
     * @depends testAcceptRequest
     */
    public function testStartExchange(int $exchangeId): int
    {
        // First ensure our main exchange is in accepted state
        $exchange = ExchangeWorkflowService::getExchange($exchangeId);

        // If it needs broker approval, approve it first
        if ($exchange['status'] === ExchangeWorkflowService::STATUS_PENDING_BROKER) {
            ExchangeWorkflowService::approveExchange(
                $exchangeId,
                self::$testBrokerId,
                'Approved for test'
            );
        }

        // Now start the exchange (use startProgress method)
        $result = ExchangeWorkflowService::startProgress(
            $exchangeId,
            self::$testProviderId
        );

        $this->assertTrue($result, 'Start should succeed');

        $exchange = ExchangeWorkflowService::getExchange($exchangeId);
        $this->assertEquals(ExchangeWorkflowService::STATUS_IN_PROGRESS, $exchange['status']);

        return $exchangeId;
    }

    /**
     * Test marking exchange as ready for confirmation
     * @depends testStartExchange
     */
    public function testMarkReadyForConfirmation(int $exchangeId): int
    {
        $result = ExchangeWorkflowService::markReadyForConfirmation(
            $exchangeId,
            self::$testProviderId
        );

        $this->assertTrue($result, 'Mark ready for confirmation should succeed');

        $exchange = ExchangeWorkflowService::getExchange($exchangeId);
        $this->assertEquals(ExchangeWorkflowService::STATUS_PENDING_CONFIRMATION, $exchange['status']);

        return $exchangeId;
    }

    /**
     * Test dual-party confirmation - requester confirms
     * @depends testMarkReadyForConfirmation
     */
    public function testRequesterConfirmsCompletion(int $exchangeId): int
    {
        $result = ExchangeWorkflowService::confirmCompletion(
            $exchangeId,
            self::$testRequesterId,
            2.0
        );

        $this->assertTrue($result, 'Requester confirmation should succeed');

        $exchange = ExchangeWorkflowService::getExchange($exchangeId);
        $this->assertNotNull($exchange['requester_confirmed_at'], 'Requester confirmed timestamp should be set');
        $this->assertEquals(2.0, (float)$exchange['requester_confirmed_hours']);
        // Should still be pending as provider hasn't confirmed yet
        $this->assertEquals(ExchangeWorkflowService::STATUS_PENDING_CONFIRMATION, $exchange['status']);

        return $exchangeId;
    }

    /**
     * Test dual-party confirmation - provider confirms (finalizes exchange)
     * @depends testRequesterConfirmsCompletion
     */
    public function testProviderConfirmsCompletion(int $exchangeId): int
    {
        $result = ExchangeWorkflowService::confirmCompletion(
            $exchangeId,
            self::$testProviderId,
            2.0
        );

        $this->assertTrue($result, 'Provider confirmation should succeed');

        $exchange = ExchangeWorkflowService::getExchange($exchangeId);
        $this->assertNotNull($exchange['provider_confirmed_at'], 'Provider confirmed timestamp should be set');
        // Both confirmed, should be completed
        $this->assertEquals(ExchangeWorkflowService::STATUS_COMPLETED, $exchange['status']);
        $this->assertEquals(2.0, (float)$exchange['final_hours']);

        // Store for cleanup
        self::$testExchangeId = $exchangeId;
        return $exchangeId;
    }

    /**
     * Test getting exchanges for a user
     * @depends testProviderConfirmsCompletion
     */
    public function testGetExchangesForUser(int $exchangeId): int
    {
        $result = ExchangeWorkflowService::getExchangesForUser(self::$testRequesterId);

        $this->assertIsArray($result, 'Should return an array');
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertNotEmpty($result['items'], 'Should have at least one exchange');

        // Find our test exchange
        $found = false;
        foreach ($result['items'] as $exchange) {
            if ($exchange['id'] == $exchangeId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Test exchange should be in user exchanges');

        return $exchangeId;
    }

    /**
     * Test getting exchanges with status filter
     * @depends testGetExchangesForUser
     */
    public function testGetExchangesWithStatusFilter(int $exchangeId): int
    {
        $result = ExchangeWorkflowService::getExchangesForUser(
            self::$testRequesterId,
            ['status' => ExchangeWorkflowService::STATUS_COMPLETED]
        );

        $this->assertIsArray($result);
        foreach ($result['items'] as $exchange) {
            $this->assertEquals(ExchangeWorkflowService::STATUS_COMPLETED, $exchange['status']);
        }

        return $exchangeId;
    }

    /**
     * Test getting pending broker approvals
     */
    public function testGetPendingBrokerApprovals(): void
    {
        $result = ExchangeWorkflowService::getPendingBrokerApprovals();

        $this->assertIsArray($result, 'Should return an array');
        $this->assertArrayHasKey('items', $result);
        // All items should be pending_broker status
        foreach ($result['items'] as $exchange) {
            $this->assertEquals(ExchangeWorkflowService::STATUS_PENDING_BROKER, $exchange['status']);
        }
    }

    /**
     * Test getting exchange history
     * @depends testGetExchangesWithStatusFilter
     */
    public function testGetExchangeHistory(int $exchangeId): void
    {
        $history = ExchangeWorkflowService::getExchangeHistory($exchangeId);

        $this->assertIsArray($history, 'Should return an array');
        $this->assertNotEmpty($history, 'Should have history entries');

        // Check structure
        $firstEntry = $history[0];
        $this->assertArrayHasKey('action', $firstEntry);
        $this->assertArrayHasKey('actor_role', $firstEntry);
        $this->assertArrayHasKey('created_at', $firstEntry);
    }

    /**
     * Test broker approval flow
     */
    public function testBrokerApprovalFlow(): void
    {
        // Create a new exchange that needs broker approval
        $data = ['proposed_hours' => 3.0];
        $exchangeId = ExchangeWorkflowService::createRequest(
            self::$testRequesterId,
            self::$testListingId,
            $data
        );

        // Provider accepts
        ExchangeWorkflowService::acceptRequest($exchangeId, self::$testProviderId);

        // Force status to pending_broker for this test
        Database::query(
            "UPDATE exchange_requests SET status = ? WHERE id = ?",
            [ExchangeWorkflowService::STATUS_PENDING_BROKER, $exchangeId]
        );

        // Broker approves
        $result = ExchangeWorkflowService::approveExchange(
            $exchangeId,
            self::$testBrokerId,
            'Verified and approved'
        );

        $this->assertTrue($result, 'Broker approval should succeed');

        $exchange = ExchangeWorkflowService::getExchange($exchangeId);
        $this->assertEquals(ExchangeWorkflowService::STATUS_ACCEPTED, $exchange['status']);
        $this->assertEquals(self::$testBrokerId, $exchange['broker_id']);

        // Clean up
        Database::query("DELETE FROM exchange_history WHERE exchange_id = ?", [$exchangeId]);
        Database::query("DELETE FROM exchange_requests WHERE id = ?", [$exchangeId]);
    }

    /**
     * Test broker rejection flow
     */
    public function testBrokerRejectionFlow(): void
    {
        // Create a new exchange
        $data = ['proposed_hours' => 1.0];
        $exchangeId = ExchangeWorkflowService::createRequest(
            self::$testRequesterId,
            self::$testListingId,
            $data
        );

        // Provider accepts
        ExchangeWorkflowService::acceptRequest($exchangeId, self::$testProviderId);

        // Force status to pending_broker
        Database::query(
            "UPDATE exchange_requests SET status = ? WHERE id = ?",
            [ExchangeWorkflowService::STATUS_PENDING_BROKER, $exchangeId]
        );

        // Broker rejects
        $result = ExchangeWorkflowService::rejectExchange(
            $exchangeId,
            self::$testBrokerId,
            'Service not covered by insurance'
        );

        $this->assertTrue($result, 'Broker rejection should succeed');

        $exchange = ExchangeWorkflowService::getExchange($exchangeId);
        $this->assertEquals(ExchangeWorkflowService::STATUS_CANCELLED, $exchange['status']);

        // Clean up
        Database::query("DELETE FROM exchange_history WHERE exchange_id = ?", [$exchangeId]);
        Database::query("DELETE FROM exchange_requests WHERE id = ?", [$exchangeId]);
    }

    /**
     * Test hours disagreement creates dispute
     */
    public function testHoursDisagreementCreatesDispute(): void
    {
        // Create and progress an exchange
        $data = ['proposed_hours' => 2.0];
        $exchangeId = ExchangeWorkflowService::createRequest(
            self::$testRequesterId,
            self::$testListingId,
            $data
        );

        ExchangeWorkflowService::acceptRequest($exchangeId, self::$testProviderId);

        // Update to in_progress then pending_confirmation
        Database::query(
            "UPDATE exchange_requests SET status = ? WHERE id = ?",
            [ExchangeWorkflowService::STATUS_PENDING_CONFIRMATION, $exchangeId]
        );

        // Requester confirms 2 hours
        ExchangeWorkflowService::confirmCompletion($exchangeId, self::$testRequesterId, 2.0);

        // Provider confirms different hours (3 hours)
        ExchangeWorkflowService::confirmCompletion($exchangeId, self::$testProviderId, 3.0);

        $exchange = ExchangeWorkflowService::getExchange($exchangeId);
        // Should be disputed due to hour mismatch
        $this->assertEquals(ExchangeWorkflowService::STATUS_DISPUTED, $exchange['status']);

        // Clean up
        Database::query("DELETE FROM exchange_history WHERE exchange_id = ?", [$exchangeId]);
        Database::query("DELETE FROM exchange_requests WHERE id = ?", [$exchangeId]);
    }

    /**
     * Test cancellation by requester
     */
    public function testCancelByRequester(): void
    {
        // Create a new exchange
        $data = ['proposed_hours' => 1.0];
        $exchangeId = ExchangeWorkflowService::createRequest(
            self::$testRequesterId,
            self::$testListingId,
            $data
        );

        $result = ExchangeWorkflowService::cancelExchange(
            $exchangeId,
            self::$testRequesterId,
            'Changed my mind'
        );

        $this->assertTrue($result, 'Cancel should succeed');

        $exchange = ExchangeWorkflowService::getExchange($exchangeId);
        $this->assertEquals(ExchangeWorkflowService::STATUS_CANCELLED, $exchange['status']);

        // Clean up
        Database::query("DELETE FROM exchange_history WHERE exchange_id = ?", [$exchangeId]);
        Database::query("DELETE FROM exchange_requests WHERE id = ?", [$exchangeId]);
    }

    /**
     * Test statistics
     */
    public function testGetStatistics(): void
    {
        $stats = ExchangeWorkflowService::getStatistics(30);

        $this->assertIsArray($stats, 'Should return an array');
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('pending_broker', $stats);
    }
}
