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
use Nexus\Services\GroupAssignmentService;

/**
 * GroupAssignmentService Tests
 *
 * Tests automated user assignment to geographic hub groups
 * based on location matching.
 */
class GroupAssignmentServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testGroupId = null;

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

        // Create test user with location
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, location, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [self::$testTenantId, "grpassign_{$ts}@test.com", "grpassign_{$ts}", 'Assign', 'User', 'Assign User', 'Dublin, Ireland']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test hub group
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, created_by, status, created_at)
             VALUES (?, ?, ?, ?, 'active', NOW())",
            [self::$testTenantId, "Dublin {$ts}", 'Dublin hub group', self::$testUserId]
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testGroupId) {
            try {
                Database::query("DELETE FROM group_members WHERE group_id = ?", [self::$testGroupId]);
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testGroupId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Assign User Tests
    // ==========================================

    public function testAssignUserReturnsString(): void
    {
        $service = new GroupAssignmentService();
        $user = ['id' => self::$testUserId, 'location' => 'Dublin'];

        $result = $service->assignUser($user);
        $this->assertIsString($result);
    }

    public function testAssignUserSkipsWhenNoLocation(): void
    {
        $service = new GroupAssignmentService();
        $user = ['id' => self::$testUserId, 'location' => ''];

        $result = $service->assignUser($user);
        $this->assertStringContainsString('SKIPPED', $result);
    }

    public function testAssignUserSkipsWhenNoLeafGroups(): void
    {
        $service = new GroupAssignmentService();
        $user = ['id' => self::$testUserId, 'location' => 'NonExistentCity'];

        $result = $service->assignUser($user);
        $this->assertIsString($result);
    }

    public function testAssignUserHandlesCommaSeparatedLocation(): void
    {
        $service = new GroupAssignmentService();
        $user = ['id' => self::$testUserId, 'location' => 'Dublin, Ireland'];

        $result = $service->assignUser($user);
        $this->assertIsString($result);
    }

    // ==========================================
    // Sanitization Tests
    // ==========================================

    public function testSanitizeMethodExists(): void
    {
        $service = new GroupAssignmentService();
        $this->assertTrue(method_exists($service, 'sanitize'));
    }

    // ==========================================
    // Leaf Group Tests
    // ==========================================

    public function testGetLeafGroupsReturnsArray(): void
    {
        $service = new GroupAssignmentService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getLeafGroups');
        $method->setAccessible(true);

        $result = $method->invoke($service);
        $this->assertIsArray($result);
    }

    // ==========================================
    // Confidence Threshold Tests
    // ==========================================

    public function testConfidenceThresholdConstantExists(): void
    {
        $reflection = new \ReflectionClass(GroupAssignmentService::class);
        $this->assertTrue($reflection->hasConstant('CONFIDENCE_THRESHOLD'));
    }
}
