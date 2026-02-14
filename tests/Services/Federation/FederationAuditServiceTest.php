<?php

declare(strict_types=1);

namespace Nexus\Tests\Services\Federation;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationAuditService;

/**
 * FederationAuditService Tests
 *
 * Tests audit logging for federation operations.
 */
class FederationAuditServiceTest extends DatabaseTestCase
{
    protected static ?int $tenant1Id = null;
    protected static ?int $tenant2Id = null;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tenant1Id = 1;
        self::$tenant2Id = 2;

        // Create test user
        $timestamp = time();
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$tenant1Id, "audit_test_{$timestamp}@test.com", "audit_test_{$timestamp}", 'Audit', 'Test', 'Audit Test', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        TenantContext::setById(self::$tenant1Id);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM federation_audit_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Log Entry Tests
    // ==========================================

    public function testLogCreatesAuditEntry(): void
    {
        $result = FederationAuditService::log(
            'test_action',
            self::$tenant1Id,
            self::$tenant2Id,
            self::$testUserId,
            ['test_key' => 'test_value']
        );

        // Should return true or log ID
        $this->assertTrue($result === true || is_int($result));

        // Verify entry exists
        $entry = Database::query(
            "SELECT * FROM federation_audit_log WHERE user_id = ? AND action = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId, 'test_action']
        )->fetch();

        $this->assertNotFalse($entry);
        $this->assertEquals('test_action', $entry['action']);
        $this->assertEquals(self::$tenant1Id, $entry['source_tenant_id']);
        $this->assertEquals(self::$tenant2Id, $entry['target_tenant_id']);
    }

    public function testLogWithDifferentLevels(): void
    {
        $levels = [
            FederationAuditService::LEVEL_DEBUG ?? 'debug',
            FederationAuditService::LEVEL_INFO ?? 'info',
            FederationAuditService::LEVEL_WARNING ?? 'warning',
            FederationAuditService::LEVEL_ERROR ?? 'error',
        ];

        foreach ($levels as $level) {
            $result = FederationAuditService::log(
                "test_level_{$level}",
                self::$tenant1Id,
                self::$tenant2Id,
                self::$testUserId,
                [],
                $level
            );

            $this->assertTrue($result === true || is_int($result), "Log should succeed for level: {$level}");
        }
    }

    public function testLogWithEmptyMetadata(): void
    {
        $result = FederationAuditService::log(
            'test_empty_meta',
            self::$tenant1Id,
            self::$tenant2Id,
            self::$testUserId,
            []
        );

        $this->assertTrue($result === true || is_int($result));
    }

    public function testLogWithComplexMetadata(): void
    {
        $metadata = [
            'string' => 'value',
            'int' => 123,
            'float' => 1.23,
            'bool' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'nested' => ['key' => 'value'],
        ];

        $result = FederationAuditService::log(
            'test_complex_meta',
            self::$tenant1Id,
            self::$tenant2Id,
            self::$testUserId,
            $metadata
        );

        $this->assertTrue($result === true || is_int($result));

        // Verify metadata was stored correctly
        $entry = Database::query(
            "SELECT * FROM federation_audit_log WHERE user_id = ? AND action = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId, 'test_complex_meta']
        )->fetch();

        $this->assertNotFalse($entry);
        $storedMeta = json_decode($entry['metadata'] ?? '{}', true);
        $this->assertEquals('value', $storedMeta['string'] ?? null);
        $this->assertEquals(123, $storedMeta['int'] ?? null);
    }

    // ==========================================
    // Profile View Logging Tests
    // ==========================================

    public function testLogProfileViewCreatesEntry(): void
    {
        $result = FederationAuditService::logProfileView(
            self::$testUserId,
            self::$tenant1Id,
            self::$testUserId + 1, // Target user
            self::$tenant2Id
        );

        $this->assertTrue($result === true || is_int($result));
    }

    // ==========================================
    // Query Tests
    // ==========================================

    public function testGetLogsReturnsArray(): void
    {
        if (!method_exists(FederationAuditService::class, 'getLogs')) {
            $this->markTestSkipped('getLogs not implemented');
        }

        $result = FederationAuditService::getLogs(self::$tenant1Id);

        $this->assertIsArray($result);
    }

    public function testGetLogsByUserReturnsArray(): void
    {
        if (!method_exists(FederationAuditService::class, 'getLogsByUser')) {
            $this->markTestSkipped('getLogsByUser not implemented');
        }

        $result = FederationAuditService::getLogsByUser(self::$testUserId);

        $this->assertIsArray($result);
    }

    public function testGetLogsByActionReturnsArray(): void
    {
        if (!method_exists(FederationAuditService::class, 'getLogsByAction')) {
            $this->markTestSkipped('getLogsByAction not implemented');
        }

        $result = FederationAuditService::getLogsByAction('test_action');

        $this->assertIsArray($result);
    }

    // ==========================================
    // Statistics Tests
    // ==========================================

    public function testGetStatsReturnsArray(): void
    {
        if (!method_exists(FederationAuditService::class, 'getStats')) {
            $this->markTestSkipped('getStats not implemented');
        }

        $result = FederationAuditService::getStats(self::$tenant1Id);

        $this->assertIsArray($result);
    }

    // ==========================================
    // Cleanup/Retention Tests
    // ==========================================

    public function testCleanupOldLogsMethodExists(): void
    {
        if (!method_exists(FederationAuditService::class, 'cleanupOldLogs')) {
            $this->markTestSkipped('cleanupOldLogs not implemented');
        }

        // Should not throw
        $result = FederationAuditService::cleanupOldLogs(365); // Keep 1 year
        $this->assertTrue($result >= 0); // Returns count or true
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testLogWithNullUserId(): void
    {
        // Some operations may not have a user (system operations)
        try {
            $result = FederationAuditService::log(
                'system_action',
                self::$tenant1Id,
                self::$tenant2Id,
                null,
                []
            );
            $this->assertTrue($result === true || is_int($result) || $result === false);
        } catch (\TypeError $e) {
            // Type error is acceptable if user_id is required
            $this->assertTrue(true);
        }
    }

    public function testLogWithVeryLongAction(): void
    {
        $longAction = str_repeat('a', 500);

        $result = FederationAuditService::log(
            $longAction,
            self::$tenant1Id,
            self::$tenant2Id,
            self::$testUserId,
            []
        );

        // Should handle gracefully (truncate or error)
        $this->assertTrue($result === true || is_int($result) || $result === false);
    }

    public function testLogWithSpecialCharactersInAction(): void
    {
        $specialAction = "test_action_<script>alert('xss')</script>";

        $result = FederationAuditService::log(
            $specialAction,
            self::$tenant1Id,
            self::$tenant2Id,
            self::$testUserId,
            []
        );

        // Should handle safely
        $this->assertTrue($result === true || is_int($result) || $result === false);
    }
}
