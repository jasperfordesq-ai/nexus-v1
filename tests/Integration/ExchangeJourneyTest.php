<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Integration;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\WalletService;
use Nexus\Services\ReviewService;

/**
 * Exchange Journey Integration Test
 *
 * Tests complete exchange workflow:
 * - User A creates offer → User B creates request
 * - Match suggestion → exchange request
 * - Accept exchange → complete exchange
 * - Leave reviews → time credits transfer
 */
class ExchangeJourneyTest extends DatabaseTestCase
{
    private static int $testTenantId = 2;
    private int $userA_Id;
    private int $userB_Id;
    private array $createdListingIds = [];
    private array $createdExchangeIds = [];
    private array $createdReviewIds = [];
    private int $testCategoryId;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();

        // Create User A (service provider)
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 100)",
            [
                self::$testTenantId,
                "userA_{$timestamp}@example.com",
                "userA_{$timestamp}",
                'Alice',
                'Provider',
                'Alice Provider',
                password_hash('password', PASSWORD_DEFAULT)
            ]
        );
        $this->userA_Id = (int)Database::lastInsertId();

        // Create User B (service requester)
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 100)",
            [
                self::$testTenantId,
                "userB_{$timestamp}@example.com",
                "userB_{$timestamp}",
                'Bob',
                'Requester',
                'Bob Requester',
                password_hash('password', PASSWORD_DEFAULT)
            ]
        );
        $this->userB_Id = (int)Database::lastInsertId();

        // Get test category
        $stmt = Database::query(
            "SELECT id FROM categories WHERE tenant_id = ? AND type = 'listing' LIMIT 1",
            [self::$testTenantId]
        );
        $category = $stmt->fetch();
        $this->testCategoryId = $category ? (int)$category['id'] : 1;
    }

    protected function tearDown(): void
    {
        // Clean up in reverse order of dependencies
        foreach ($this->createdReviewIds as $reviewId) {
            try {
                Database::query("DELETE FROM reviews WHERE id = ?", [$reviewId]);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        foreach ($this->createdExchangeIds as $exchangeId) {
            try {
                Database::query("DELETE FROM exchanges WHERE id = ?", [$exchangeId]);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        foreach ($this->createdListingIds as $listingId) {
            try {
                Database::query("DELETE FROM listings WHERE id = ?", [$listingId]);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        try {
            Database::query("DELETE FROM transactions WHERE sender_id = ? OR receiver_id = ?", [$this->userA_Id, $this->userA_Id]);
            Database::query("DELETE FROM transactions WHERE sender_id = ? OR receiver_id = ?", [$this->userB_Id, $this->userB_Id]);
            Database::query("DELETE FROM users WHERE id IN (?, ?)", [$this->userA_Id, $this->userB_Id]);
        } catch (\Exception $e) {
            // Ignore
        }

        parent::tearDown();
    }

    /**
     * Test: Complete exchange workflow from creation to completion
     */
    public function testCompleteExchangeWorkflow(): void
    {
        // Step 1: User A creates an "offer" listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, time_credits, status, created_at)
             VALUES (?, ?, ?, ?, 'offer', ?, 2, 'active', NOW())",
            [
                self::$testTenantId,
                $this->userA_Id,
                'Professional Gardening Services',
                'Expert gardening help available',
                $this->testCategoryId
            ]
        );
        $offerListingId = (int)Database::lastInsertId();
        $this->createdListingIds[] = $offerListingId;

        // Step 2: User B creates a "request" listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, time_credits, status, created_at)
             VALUES (?, ?, ?, ?, 'request', ?, 2, 'active', NOW())",
            [
                self::$testTenantId,
                $this->userB_Id,
                'Need Garden Cleanup Help',
                'Looking for someone to help tidy my garden',
                $this->testCategoryId
            ]
        );
        $requestListingId = (int)Database::lastInsertId();
        $this->createdListingIds[] = $requestListingId;

        // Verify both listings exist
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM listings WHERE id IN (?, ?) AND tenant_id = ?",
            [$offerListingId, $requestListingId, self::$testTenantId]
        );
        $this->assertEquals(2, $stmt->fetch()['count'], 'Both listings should exist');

        // Step 3: User B requests exchange with User A
        Database::query(
            "INSERT INTO exchanges (tenant_id, listing_id, requester_id, provider_id, status, time_credits, created_at)
             VALUES (?, ?, ?, ?, 'pending', 2, NOW())",
            [
                self::$testTenantId,
                $offerListingId,
                $this->userB_Id,
                $this->userA_Id
            ]
        );
        $exchangeId = (int)Database::lastInsertId();
        $this->createdExchangeIds[] = $exchangeId;

        $this->assertGreaterThan(0, $exchangeId, 'Exchange should be created');

        // Step 4: User A accepts the exchange
        Database::query(
            "UPDATE exchanges SET status = 'accepted', updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$exchangeId, self::$testTenantId]
        );

        $stmt = Database::query("SELECT status FROM exchanges WHERE id = ?", [$exchangeId]);
        $exchange = $stmt->fetch();
        $this->assertEquals('accepted', $exchange['status'], 'Exchange should be accepted');

        // Step 5: Both users complete the exchange
        Database::query(
            "UPDATE exchanges
             SET status = 'completed',
                 provider_confirmed = 1,
                 requester_confirmed = 1,
                 completed_at = NOW(),
                 updated_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$exchangeId, self::$testTenantId]
        );

        $stmt = Database::query("SELECT * FROM exchanges WHERE id = ?", [$exchangeId]);
        $completedExchange = $stmt->fetch();
        $this->assertEquals('completed', $completedExchange['status']);
        $this->assertEquals(1, $completedExchange['provider_confirmed']);
        $this->assertEquals(1, $completedExchange['requester_confirmed']);
        $this->assertNotNull($completedExchange['completed_at']);

        // Step 6: Time credits transfer (User B pays User A)
        $timeCredits = 2;

        // Get initial balances
        $userA_Initial = Database::query("SELECT balance FROM users WHERE id = ?", [$this->userA_Id])->fetch();
        $userB_Initial = Database::query("SELECT balance FROM users WHERE id = ?", [$this->userB_Id])->fetch();

        // Transfer credits
        Database::query(
            "UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
            [$timeCredits, $this->userA_Id, self::$testTenantId]
        );
        Database::query(
            "UPDATE users SET balance = balance - ? WHERE id = ? AND tenant_id = ?",
            [$timeCredits, $this->userB_Id, self::$testTenantId]
        );

        // Record transaction
        Database::query(
            "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, exchange_id, created_at)
             VALUES (?, ?, ?, ?, 'Exchange completion', ?, NOW())",
            [
                self::$testTenantId,
                $this->userB_Id,
                $this->userA_Id,
                $timeCredits,
                $exchangeId
            ]
        );

        // Verify balances updated correctly
        $userA_Final = Database::query("SELECT balance FROM users WHERE id = ?", [$this->userA_Id])->fetch();
        $userB_Final = Database::query("SELECT balance FROM users WHERE id = ?", [$this->userB_Id])->fetch();

        $this->assertEquals(
            $userA_Initial['balance'] + $timeCredits,
            $userA_Final['balance'],
            'Provider should receive time credits'
        );
        $this->assertEquals(
            $userB_Initial['balance'] - $timeCredits,
            $userB_Final['balance'],
            'Requester should pay time credits'
        );

        // Step 7: Verify transaction recorded
        $stmt = Database::query(
            "SELECT * FROM transactions WHERE exchange_id = ? AND tenant_id = ?",
            [$exchangeId, self::$testTenantId]
        );
        $transaction = $stmt->fetch();

        $this->assertNotFalse($transaction, 'Transaction should be recorded');
        $this->assertEquals($this->userB_Id, $transaction['sender_id']);
        $this->assertEquals($this->userA_Id, $transaction['receiver_id']);
        $this->assertEquals($timeCredits, $transaction['amount']);
    }

    /**
     * Test: Exchange with reviews
     */
    public function testExchangeWithReviews(): void
    {
        // Create and complete an exchange
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, time_credits, status, created_at)
             VALUES (?, ?, 'Computer Repair', 'Fix your computer issues', 'offer', ?, 3, 'active', NOW())",
            [self::$testTenantId, $this->userA_Id, $this->testCategoryId]
        );
        $listingId = (int)Database::lastInsertId();
        $this->createdListingIds[] = $listingId;

        Database::query(
            "INSERT INTO exchanges (tenant_id, listing_id, requester_id, provider_id, status, time_credits, created_at, completed_at)
             VALUES (?, ?, ?, ?, 'completed', 3, NOW(), NOW())",
            [self::$testTenantId, $listingId, $this->userB_Id, $this->userA_Id]
        );
        $exchangeId = (int)Database::lastInsertId();
        $this->createdExchangeIds[] = $exchangeId;

        // Step 1: User B reviews User A (provider)
        Database::query(
            "INSERT INTO reviews (tenant_id, reviewer_id, reviewee_id, exchange_id, rating, comment, created_at)
             VALUES (?, ?, ?, ?, 5, 'Excellent service! Very professional.', NOW())",
            [self::$testTenantId, $this->userB_Id, $this->userA_Id, $exchangeId]
        );
        $reviewA_Id = (int)Database::lastInsertId();
        $this->createdReviewIds[] = $reviewA_Id;

        // Step 2: User A reviews User B (requester)
        Database::query(
            "INSERT INTO reviews (tenant_id, reviewer_id, reviewee_id, exchange_id, rating, comment, created_at)
             VALUES (?, ?, ?, ?, 5, 'Great communication, easy to work with.', NOW())",
            [self::$testTenantId, $this->userA_Id, $this->userB_Id, $exchangeId]
        );
        $reviewB_Id = (int)Database::lastInsertId();
        $this->createdReviewIds[] = $reviewB_Id;

        // Step 3: Verify both reviews exist
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM reviews WHERE exchange_id = ? AND tenant_id = ?",
            [$exchangeId, self::$testTenantId]
        );
        $this->assertEquals(2, $stmt->fetch()['count'], 'Both users should have reviewed each other');

        // Step 4: Verify review content
        $stmt = Database::query(
            "SELECT * FROM reviews WHERE id = ?",
            [$reviewA_Id]
        );
        $review = $stmt->fetch();

        $this->assertEquals(5, $review['rating']);
        $this->assertEquals($this->userB_Id, $review['reviewer_id']);
        $this->assertEquals($this->userA_Id, $review['reviewee_id']);
        $this->assertStringContainsString('Excellent service', $review['comment']);
    }

    /**
     * Test: Exchange cancellation flow
     */
    public function testExchangeCancellationFlow(): void
    {
        // Create a listing and exchange
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, time_credits, status, created_at)
             VALUES (?, ?, 'Tutoring Service', 'Math tutoring available', 'offer', ?, 2, 'active', NOW())",
            [self::$testTenantId, $this->userA_Id, $this->testCategoryId]
        );
        $listingId = (int)Database::lastInsertId();
        $this->createdListingIds[] = $listingId;

        // Step 1: Create pending exchange
        Database::query(
            "INSERT INTO exchanges (tenant_id, listing_id, requester_id, provider_id, status, time_credits, created_at)
             VALUES (?, ?, ?, ?, 'pending', 2, NOW())",
            [self::$testTenantId, $listingId, $this->userB_Id, $this->userA_Id]
        );
        $exchangeId = (int)Database::lastInsertId();
        $this->createdExchangeIds[] = $exchangeId;

        $stmt = Database::query("SELECT status FROM exchanges WHERE id = ?", [$exchangeId]);
        $this->assertEquals('pending', $stmt->fetch()['status']);

        // Step 2: Cancel the exchange
        Database::query(
            "UPDATE exchanges SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$exchangeId, self::$testTenantId]
        );

        // Step 3: Verify exchange is cancelled
        $stmt = Database::query("SELECT status FROM exchanges WHERE id = ?", [$exchangeId]);
        $this->assertEquals('cancelled', $stmt->fetch()['status']);

        // Step 4: Verify no transaction was created
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM transactions WHERE exchange_id = ?",
            [$exchangeId]
        );
        $this->assertEquals(0, $stmt->fetch()['count'], 'No transaction should exist for cancelled exchange');
    }
}
