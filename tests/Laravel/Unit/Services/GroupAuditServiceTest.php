<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\GroupAuditService;
use App\Services\GroupDataExportService;

/**
 * GroupAuditService Tests
 *
 * Tests comprehensive audit logging for group actions,
 * including creation, updates, member management, and content moderation.
 */
class GroupAuditServiceTest extends \Tests\Laravel\TestCase
{
    protected static ?int $staticTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testGroupId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Boot a Laravel app so facade-backed helpers (DB, TenantContext) work
        // inside this static hook — Laravel only boots the app in instance setUp.
        self::bootApplicationForClass();

        self::$staticTenantId = 2;
        TenantContext::setById(self::$staticTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $ts = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [self::$staticTenantId, "grpaudit_{$ts}@test.com", "grpaudit_{$ts}", 'Audit', 'User', 'Audit User']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test group
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, owner_id, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [self::$staticTenantId, "Audit Group {$ts}", 'Test group for audit', self::$testUserId]
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testGroupId) {
            try {
                Database::query("DELETE FROM group_audit_log WHERE group_id = ?", [self::$testGroupId]);
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
    // Action Constants Tests
    // ==========================================

    public function testGroupActionConstantsExist(): void
    {
        $this->assertEquals('group_created', GroupAuditService::ACTION_GROUP_CREATED);
        $this->assertEquals('group_updated', GroupAuditService::ACTION_GROUP_UPDATED);
        $this->assertEquals('group_deleted', GroupAuditService::ACTION_GROUP_DELETED);
        $this->assertEquals('group_featured', GroupAuditService::ACTION_GROUP_FEATURED);
    }

    public function testMemberActionConstantsExist(): void
    {
        $this->assertEquals('member_joined', GroupAuditService::ACTION_MEMBER_JOINED);
        $this->assertEquals('member_left', GroupAuditService::ACTION_MEMBER_LEFT);
        $this->assertEquals('member_kicked', GroupAuditService::ACTION_MEMBER_KICKED);
        $this->assertEquals('member_banned', GroupAuditService::ACTION_MEMBER_BANNED);
    }

    public function testContentActionConstantsExist(): void
    {
        $this->assertEquals('discussion_created', GroupAuditService::ACTION_DISCUSSION_CREATED);
        $this->assertEquals('post_created', GroupAuditService::ACTION_POST_CREATED);
        $this->assertEquals('post_moderated', GroupAuditService::ACTION_POST_MODERATED);
    }

    // ==========================================
    // Log Tests
    // ==========================================

    public function testLogCreatesAuditEntry(): void
    {
        $logId = GroupAuditService::log(
            GroupAuditService::ACTION_GROUP_UPDATED,
            self::$testGroupId,
            self::$testUserId,
            ['field' => 'name', 'old' => 'Old Name', 'new' => 'New Name']
        );

        $this->assertNotNull($logId);
        // log() returns via Database::lastInsertId() which may be string
        $this->assertIsNumeric($logId);

        // Cleanup
        Database::query("DELETE FROM group_audit_log WHERE id = ?", [$logId]);
    }

    public function testLogIncludesDetails(): void
    {
        $details = ['action' => 'test', 'value' => 123];
        $logId = GroupAuditService::log(
            GroupAuditService::ACTION_GROUP_CREATED,
            self::$testGroupId,
            self::$testUserId,
            $details
        );

        // Verify details stored as JSON
        $stmt = Database::query("SELECT details FROM group_audit_log WHERE id = ?", [$logId]);
        $log = $stmt->fetch();
        $storedDetails = json_decode($log['details'], true);

        $this->assertEquals($details, $storedDetails);

        // Cleanup
        Database::query("DELETE FROM group_audit_log WHERE id = ?", [$logId]);
    }

    public function testLogRedactsSecretsRecursively(): void
    {
        $logId = GroupAuditService::log(
            GroupAuditService::ACTION_GROUP_UPDATED,
            self::$testGroupId,
            self::$testUserId,
            [
                'token' => 'plain-token',
                'nested' => ['api_key' => 'plain-key', 'safe' => 'visible'],
            ],
        );

        $stmt = Database::query("SELECT details FROM group_audit_log WHERE id = ?", [$logId]);
        $stored = json_decode($stmt->fetch()['details'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('[REDACTED]', $stored['token']);
        self::assertSame('[REDACTED]', $stored['nested']['api_key']);
        self::assertSame('visible', $stored['nested']['safe']);

        Database::query("DELETE FROM group_audit_log WHERE id = ?", [$logId]);
    }

    public function testLogRedactsCookiePassphraseAndSecretLikeValuesUnderSafeKeys(): void
    {
        $logId = GroupAuditService::log(
            GroupAuditService::ACTION_GROUP_UPDATED,
            self::$testGroupId,
            self::$testUserId,
            [
                'cookie_jar' => 'session=raw-cookie-value',
                'signing_passphrase' => 'correct horse battery staple',
                'transport' => 'Bearer abcdefghijklmnopqrstuvwxyz',
                'credential' => 'sk_live_abcdefghijklmnop',
                'payload' => 'legacy-secret-token-value',
                'nested' => [
                    'summary' => 'authorization=raw-authorization-value',
                    'safe' => 'visible metadata',
                ],
            ],
        );

        $stmt = Database::query("SELECT details FROM group_audit_log WHERE id = ?", [$logId]);
        $stored = json_decode($stmt->fetch()['details'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('[REDACTED]', $stored['cookie_jar']);
        self::assertSame('[REDACTED]', $stored['signing_passphrase']);
        self::assertSame('[REDACTED]', $stored['transport']);
        self::assertSame('[REDACTED]', $stored['credential']);
        self::assertSame('[REDACTED]', $stored['payload']);
        self::assertSame('[REDACTED]', $stored['nested']['summary']);
        self::assertSame('visible metadata', $stored['nested']['safe']);

        Database::query("DELETE FROM group_audit_log WHERE id = ?", [$logId]);
    }

    public function testLegacySecretsAreSanitizedAcrossAuditReadsAndGroupExport(): void
    {
        $safeMarker = 'safe-' . bin2hex(random_bytes(4));
        Database::query(
            "INSERT INTO group_audit_log (tenant_id, group_id, user_id, action, details, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                self::$staticTenantId,
                self::$testGroupId,
                self::$testUserId,
                GroupAuditService::ACTION_GROUP_UPDATED,
                json_encode([
                    'safe' => $safeMarker,
                    'token' => 'legacy-token-value',
                    'nested' => [
                        'authorization' => 'Bearer legacy-authorization-value',
                        'webhook_secret' => 'legacy-webhook-secret-value',
                        'transport' => 'Bearer legacy-safe-key-bearer-value',
                        'cookie_jar' => 'session=legacy-cookie-value',
                        'passphrase_hint' => 'legacy-passphrase-value',
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        );
        $validId = (int) Database::getInstance()->lastInsertId();

        try {
            $collections = [
                GroupAuditService::getGroupLog(self::$testGroupId),
                GroupAuditService::getGroupLogPage(self::$testGroupId, ['per_page' => 100])['items'],
                GroupAuditService::getUserGroupActivity(self::$testGroupId, self::$testUserId),
            ];
            $export = GroupDataExportService::exportAll(self::$testGroupId, self::$testUserId);
            self::assertNotNull($export);
            $collections[] = $export['audit_log'];

            foreach ($collections as $rows) {
                $valid = current(array_filter($rows, static fn (array $row): bool => (int) $row['id'] === $validId));
                self::assertIsArray($valid);

                $validJson = (string) $valid['details'];
                self::assertStringContainsString($safeMarker, $validJson);
                self::assertStringNotContainsString('legacy-token-value', $validJson);
                self::assertStringNotContainsString('legacy-authorization-value', $validJson);
                self::assertStringNotContainsString('legacy-webhook-secret-value', $validJson);
                self::assertStringNotContainsString('legacy-safe-key-bearer-value', $validJson);
                self::assertStringNotContainsString('legacy-cookie-value', $validJson);
                self::assertStringNotContainsString('legacy-passphrase-value', $validJson);
                self::assertStringContainsString('[REDACTED]', $validJson);
            }

            $invalid = GroupAuditService::sanitizeRowForOutput([
                'details' => 'invalid legacy token=raw-secret-value',
            ]);
            $invalidJson = (string) $invalid['details'];
            self::assertStringNotContainsString('raw-secret-value', $invalidJson);
            self::assertStringContainsString('[REDACTED]', $invalidJson);
        } finally {
            Database::query("DELETE FROM group_audit_log WHERE id = ?", [$validId]);
        }
    }

    public function testLogCapturesIPAddress(): void
    {
        $logId = GroupAuditService::log(
            GroupAuditService::ACTION_GROUP_UPDATED,
            self::$testGroupId,
            self::$testUserId
        );

        $stmt = Database::query("SELECT ip_address FROM group_audit_log WHERE id = ?", [$logId]);
        $log = $stmt->fetch();

        $this->assertArrayHasKey('ip_address', $log);

        // Cleanup
        Database::query("DELETE FROM group_audit_log WHERE id = ?", [$logId]);
    }

    // ==========================================
    // Get Logs Tests
    // ==========================================

    public function testGetGroupLogReturnsArray(): void
    {
        $logs = GroupAuditService::getGroupLog(self::$testGroupId);
        $this->assertIsArray($logs);
    }

    public function testGetGroupLogFiltersByAction(): void
    {
        // Create test log
        $logId = GroupAuditService::log(
            GroupAuditService::ACTION_GROUP_UPDATED,
            self::$testGroupId,
            self::$testUserId
        );

        $logs = GroupAuditService::getGroupLog(
            self::$testGroupId,
            ['action' => GroupAuditService::ACTION_GROUP_UPDATED]
        );
        $this->assertIsArray($logs);

        foreach ($logs as $log) {
            $this->assertEquals(GroupAuditService::ACTION_GROUP_UPDATED, $log['action']);
        }

        // Cleanup
        Database::query("DELETE FROM group_audit_log WHERE id = ?", [$logId]);
    }

    public function testGetGroupLogPageIsBoundedFilteredAndReportsActions(): void
    {
        $ids = [];
        foreach ([
            GroupAuditService::ACTION_GROUP_UPDATED,
            GroupAuditService::ACTION_GROUP_UPDATED,
            GroupAuditService::ACTION_MEMBER_JOINED,
        ] as $action) {
            $ids[] = GroupAuditService::log($action, self::$testGroupId, self::$testUserId);
        }

        $page = GroupAuditService::getGroupLogPage(self::$testGroupId, [
            'action' => GroupAuditService::ACTION_GROUP_UPDATED,
            'page' => 1,
            'per_page' => 1,
        ]);

        self::assertCount(1, $page['items']);
        self::assertSame(GroupAuditService::ACTION_GROUP_UPDATED, $page['items'][0]['action']);
        self::assertTrue($page['pagination']['has_more']);
        self::assertContains(GroupAuditService::ACTION_MEMBER_JOINED, $page['actions']);
        self::assertContains(GroupAuditService::ACTION_GROUP_UPDATED, $page['actions']);

        foreach ($ids as $id) {
            Database::query("DELETE FROM group_audit_log WHERE id = ?", [$id]);
        }
    }

    // ==========================================
    // Get User Activity Tests
    // ==========================================

    public function testGetUserGroupActivityReturnsArray(): void
    {
        $logs = GroupAuditService::getUserGroupActivity(self::$testGroupId, self::$testUserId);
        $this->assertIsArray($logs);
    }

    // ==========================================
    // Statistics Tests
    // ==========================================

    public function testGetActivitySummaryReturnsArray(): void
    {
        $stats = GroupAuditService::getActivitySummary(self::$testGroupId);
        $this->assertIsArray($stats);
    }
}
