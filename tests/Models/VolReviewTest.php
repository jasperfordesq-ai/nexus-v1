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
use Nexus\Models\VolReview;

/**
 * VolReview Model Tests
 *
 * Tests volunteer review creation and target-based retrieval.
 */
class VolReviewTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testOrgId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();

        // Create test user (reviewer)
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "vol_review_test_{$timestamp}@test.com", "vol_review_test_{$timestamp}", 'VolReview', 'Tester', 'VolReview Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test organization as review target
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'approved', NOW())",
            [self::$testTenantId, self::$testUserId, "Review Target Org {$timestamp}", 'Org for reviews', "review_{$timestamp}@test.com"]
        );
        self::$testOrgId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testUserId) {
                Database::query("DELETE FROM vol_reviews WHERE reviewer_id = ?", [self::$testUserId]);
            }
            if (self::$testOrgId) {
                Database::query("DELETE FROM vol_reviews WHERE target_type = 'organization' AND target_id = ?", [self::$testOrgId]);
                Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
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
    // Create Tests
    // ==========================================

    public function testCreateInsertsReview(): void
    {
        VolReview::create(
            self::$testUserId,
            'organization',
            self::$testOrgId,
            5,
            'Excellent organization to volunteer with!'
        );

        $reviews = VolReview::getForTarget('organization', self::$testOrgId);
        $this->assertIsArray($reviews);
        $this->assertGreaterThanOrEqual(1, count($reviews));
    }

    public function testCreateWithDifferentRatings(): void
    {
        VolReview::create(
            self::$testUserId,
            'organization',
            self::$testOrgId,
            3,
            'Average experience'
        );

        $reviews = VolReview::getForTarget('organization', self::$testOrgId);
        $found = false;
        foreach ($reviews as $review) {
            if ($review['comment'] === 'Average experience') {
                $this->assertEquals(3, (int)$review['rating']);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should find the review with rating 3');
    }

    public function testCreateWithUserTargetType(): void
    {
        VolReview::create(
            self::$testUserId,
            'user',
            self::$testUserId,
            4,
            'Great volunteer!'
        );

        $reviews = VolReview::getForTarget('user', self::$testUserId);
        $this->assertIsArray($reviews);
        $this->assertGreaterThanOrEqual(1, count($reviews));
    }

    // ==========================================
    // GetForTarget Tests
    // ==========================================

    public function testGetForTargetReturnsArray(): void
    {
        $reviews = VolReview::getForTarget('organization', self::$testOrgId);
        $this->assertIsArray($reviews);
    }

    public function testGetForTargetIncludesReviewerInfo(): void
    {
        VolReview::create(
            self::$testUserId,
            'organization',
            self::$testOrgId,
            5,
            'Review with reviewer info'
        );

        $reviews = VolReview::getForTarget('organization', self::$testOrgId);
        $this->assertNotEmpty($reviews);
        $this->assertArrayHasKey('first_name', $reviews[0]);
        $this->assertArrayHasKey('last_name', $reviews[0]);
        $this->assertArrayHasKey('avatar_url', $reviews[0]);
    }

    public function testGetForTargetReturnsEmptyForNonExistent(): void
    {
        $reviews = VolReview::getForTarget('organization', 999999999);
        $this->assertIsArray($reviews);
        $this->assertEmpty($reviews);
    }

    public function testGetForTargetOrdersByCreatedAtDesc(): void
    {
        // Create multiple reviews
        VolReview::create(self::$testUserId, 'organization', self::$testOrgId, 4, 'First review');
        VolReview::create(self::$testUserId, 'organization', self::$testOrgId, 5, 'Second review');

        $reviews = VolReview::getForTarget('organization', self::$testOrgId);
        $this->assertGreaterThanOrEqual(2, count($reviews));

        // Most recent should be first
        if (count($reviews) >= 2) {
            $this->assertGreaterThanOrEqual($reviews[1]['created_at'], $reviews[0]['created_at']);
        }
    }
}
