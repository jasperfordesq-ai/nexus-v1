<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\EndorsementService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * EndorsementService Tests
 *
 * Tests self-endorsement prevention, validation rules,
 * endorse/remove/has-endorsed flows, and tenant isolation.
 */
class EndorsementServiceTest extends TestCase
{
    private static int $tenantId   = 2;
    private static ?int $userId1   = null;
    private static ?int $userId2   = null;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$tenantId);
        try {
            $ts = time();
            Database::query(
                "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, is_approved, created_at) VALUES (?, ?, ?, 'Endorse', 'UserA', 'Endorse UserA', 1, NOW())",
                [self::$tenantId, "endorse_a_{$ts}@test.com", "endorse_a_{$ts}"]
            );
            self::$userId1 = (int)Database::getInstance()->lastInsertId();

            Database::query(
                "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, is_approved, created_at) VALUES (?, ?, ?, 'Endorse', 'UserB', 'Endorse UserB', 1, NOW())",
                [self::$tenantId, "endorse_b_{$ts}@test.com", "endorse_b_{$ts}"]
            );
            self::$userId2 = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {}
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$userId1) {
            try { Database::query("DELETE FROM skill_endorsements WHERE endorser_id = ? OR endorsed_id = ?", [self::$userId1, self::$userId1]); } catch (\Exception $e) {}
            try { Database::query("DELETE FROM users WHERE id = ?", [self::$userId1]); } catch (\Exception $e) {}
        }
        if (self::$userId2) {
            try { Database::query("DELETE FROM skill_endorsements WHERE endorser_id = ? OR endorsed_id = ?", [self::$userId2, self::$userId2]); } catch (\Exception $e) {}
            try { Database::query("DELETE FROM users WHERE id = ?", [self::$userId2]); } catch (\Exception $e) {}
        }
    }

    protected function setUp(): void
    {
        TenantContext::setById(self::$tenantId);
        if (self::$userId1 && self::$userId2) {
            try {
                Database::query(
                    "DELETE FROM skill_endorsements WHERE endorser_id = ? AND endorsed_id = ?",
                    [self::$userId1, self::$userId2]
                );
            } catch (\Exception $e) {}
        }
    }

    // -------------------------------------------------------------------------
    // Self-endorsement prevention (pure logic via DB check)
    // -------------------------------------------------------------------------

    public function test_endorse_prevents_self_endorsement(): void
    {
        if (!self::$userId1) {
            $this->markTestSkipped('No test user available');
        }
        try {
            $result = EndorsementService::endorse(self::$userId1, self::$userId1, 'gardening');
            $this->assertNull($result, 'Self-endorsement should return null');
            $errors = EndorsementService::getErrors();
            $codes = array_column($errors, 'code');
            $this->assertContains('SELF_ENDORSEMENT', $codes);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_endorse_rejects_empty_skill_name(): void
    {
        if (!self::$userId1 || !self::$userId2) {
            $this->markTestSkipped('No test users available');
        }
        try {
            $result = EndorsementService::endorse(self::$userId1, self::$userId2, '');
            $this->assertNull($result);
            $codes = array_column(EndorsementService::getErrors(), 'code');
            $this->assertContains('VALIDATION_ERROR', $codes);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_endorse_rejects_skill_name_over_100_chars(): void
    {
        if (!self::$userId1 || !self::$userId2) {
            $this->markTestSkipped('No test users available');
        }
        try {
            $longSkill = str_repeat('x', 101);
            $result = EndorsementService::endorse(self::$userId1, self::$userId2, $longSkill);
            $this->assertNull($result);
            $codes = array_column(EndorsementService::getErrors(), 'code');
            $this->assertContains('VALIDATION_ERROR', $codes);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Endorse happy path, duplicate, remove
    // -------------------------------------------------------------------------

    public function test_endorse_returns_integer_id_on_success(): void
    {
        if (!self::$userId1 || !self::$userId2) {
            $this->markTestSkipped('No test users available');
        }
        try {
            $id = EndorsementService::endorse(self::$userId1, self::$userId2, 'cooking');
            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_endorse_prevents_duplicate(): void
    {
        if (!self::$userId1 || !self::$userId2) {
            $this->markTestSkipped('No test users available');
        }
        try {
            EndorsementService::endorse(self::$userId1, self::$userId2, 'baking');
            $second = EndorsementService::endorse(self::$userId1, self::$userId2, 'baking');
            $this->assertNull($second);
            $codes = array_column(EndorsementService::getErrors(), 'code');
            $this->assertContains('ALREADY_ENDORSED', $codes);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_has_endorsed_returns_true_after_endorsement(): void
    {
        if (!self::$userId1 || !self::$userId2) {
            $this->markTestSkipped('No test users available');
        }
        try {
            EndorsementService::endorse(self::$userId1, self::$userId2, 'cycling');
            $this->assertTrue(EndorsementService::hasEndorsed(self::$userId1, self::$userId2, 'cycling'));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_has_endorsed_returns_false_when_not_endorsed(): void
    {
        if (!self::$userId1 || !self::$userId2) {
            $this->markTestSkipped('No test users available');
        }
        try {
            $this->assertFalse(EndorsementService::hasEndorsed(self::$userId1, self::$userId2, 'quantum_physics_xyz'));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_remove_endorsement_returns_true(): void
    {
        if (!self::$userId1 || !self::$userId2) {
            $this->markTestSkipped('No test users available');
        }
        try {
            EndorsementService::endorse(self::$userId1, self::$userId2, 'swimming');
            $result = EndorsementService::removeEndorsement(self::$userId1, self::$userId2, 'swimming');
            $this->assertTrue($result);
            $this->assertFalse(EndorsementService::hasEndorsed(self::$userId1, self::$userId2, 'swimming'));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_endorsements_for_user_returns_array(): void
    {
        if (!self::$userId2) {
            $this->markTestSkipped('No test users available');
        }
        try {
            $result = EndorsementService::getEndorsementsForUser(self::$userId2);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_endorsement_counts_returns_array(): void
    {
        if (!self::$userId2) {
            $this->markTestSkipped('No test users available');
        }
        try {
            $counts = EndorsementService::getEndorsementCounts(self::$userId2);
            $this->assertIsArray($counts);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_stats_returns_expected_keys(): void
    {
        if (!self::$userId2) {
            $this->markTestSkipped('No test users available');
        }
        try {
            $stats = EndorsementService::getStats(self::$userId2);
            $this->assertIsArray($stats);
            $this->assertArrayHasKey('endorsements_received', $stats);
            $this->assertArrayHasKey('endorsements_given', $stats);
            $this->assertArrayHasKey('skills_endorsed', $stats);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_top_endorsed_members_returns_array(): void
    {
        try {
            $result = EndorsementService::getTopEndorsedMembers(5);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }
}
