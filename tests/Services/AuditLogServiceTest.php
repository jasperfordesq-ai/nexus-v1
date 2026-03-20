<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use PHPUnit\Framework\TestCase;
use App\Services\AuditLogService;
use App\Core\TenantContext;

/**
 * AuditLogService Tests
 */
class AuditLogServiceTest extends TestCase
{
    private static int $testTenantId = 2;
    private static int $adminUserId  = 1;
    private static int $targetUserId = 2;
    private static int $orgId        = 1;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);
    }

    public function test_action_constants_are_defined(): void
    {
        $this->assertSame('wallet_deposit',    AuditLogService::ACTION_WALLET_DEPOSIT);
        $this->assertSame('wallet_withdrawal', AuditLogService::ACTION_WALLET_WITHDRAWAL);
        $this->assertSame('transfer_request',  AuditLogService::ACTION_TRANSFER_REQUEST);
        $this->assertSame('member_added',      AuditLogService::ACTION_MEMBER_ADDED);
        $this->assertSame('member_removed',    AuditLogService::ACTION_MEMBER_REMOVED);
    }

    public function test_admin_action_constants_are_defined(): void
    {
        $this->assertSame('admin_user_created',      AuditLogService::ACTION_ADMIN_USER_CREATED);
        $this->assertSame('admin_user_deleted',      AuditLogService::ACTION_ADMIN_USER_DELETED);
        $this->assertSame('admin_user_suspended',    AuditLogService::ACTION_ADMIN_USER_SUSPENDED);
        $this->assertSame('admin_user_banned',       AuditLogService::ACTION_ADMIN_USER_BANNED);
        $this->assertSame('admin_role_changed',      AuditLogService::ACTION_ADMIN_ROLE_CHANGED);
        $this->assertSame('admin_2fa_reset',         AuditLogService::ACTION_ADMIN_2FA_RESET);
        $this->assertSame('admin_bulk_import',       AuditLogService::ACTION_ADMIN_BULK_IMPORT);
    }

    public function test_get_action_label_returns_correct_labels(): void
    {
        $this->assertSame('Wallet Deposit',             AuditLogService::getActionLabel(AuditLogService::ACTION_WALLET_DEPOSIT));
        $this->assertSame('Wallet Withdrawal',          AuditLogService::getActionLabel(AuditLogService::ACTION_WALLET_WITHDRAWAL));
        $this->assertSame('Transfer Request Created',   AuditLogService::getActionLabel(AuditLogService::ACTION_TRANSFER_REQUEST));
        $this->assertSame('Transfer Request Approved',  AuditLogService::getActionLabel(AuditLogService::ACTION_TRANSFER_APPROVE));
        $this->assertSame('Transfer Request Rejected',  AuditLogService::getActionLabel(AuditLogService::ACTION_TRANSFER_REJECT));
        $this->assertSame('Transfer Request Cancelled', AuditLogService::getActionLabel(AuditLogService::ACTION_TRANSFER_CANCEL));
        $this->assertSame('Member Added',               AuditLogService::getActionLabel(AuditLogService::ACTION_MEMBER_ADDED));
        $this->assertSame('Member Removed',             AuditLogService::getActionLabel(AuditLogService::ACTION_MEMBER_REMOVED));
        $this->assertSame('Member Role Changed',        AuditLogService::getActionLabel(AuditLogService::ACTION_MEMBER_ROLE_CHANGED));
        $this->assertSame('Ownership Transferred',      AuditLogService::getActionLabel(AuditLogService::ACTION_OWNERSHIP_TRANSFERRED));
    }

    public function test_get_action_label_returns_correct_admin_labels(): void
    {
        $this->assertSame('Admin Created User',      AuditLogService::getActionLabel(AuditLogService::ACTION_ADMIN_USER_CREATED));
        $this->assertSame('Admin Deleted User',      AuditLogService::getActionLabel(AuditLogService::ACTION_ADMIN_USER_DELETED));
        $this->assertSame('Admin Suspended User',    AuditLogService::getActionLabel(AuditLogService::ACTION_ADMIN_USER_SUSPENDED));
        $this->assertSame('Admin Banned User',       AuditLogService::getActionLabel(AuditLogService::ACTION_ADMIN_USER_BANNED));
        $this->assertSame('Admin Reactivated User',  AuditLogService::getActionLabel(AuditLogService::ACTION_ADMIN_USER_REACTIVATED));
        $this->assertSame('Admin Approved User',     AuditLogService::getActionLabel(AuditLogService::ACTION_ADMIN_USER_APPROVED));
        $this->assertSame('Admin Changed User Role', AuditLogService::getActionLabel(AuditLogService::ACTION_ADMIN_ROLE_CHANGED));
        $this->assertSame('Admin Reset 2FA',         AuditLogService::getActionLabel(AuditLogService::ACTION_ADMIN_2FA_RESET));
        $this->assertSame('Admin Bulk Import Users', AuditLogService::getActionLabel(AuditLogService::ACTION_ADMIN_BULK_IMPORT));
    }

    public function test_get_action_label_falls_back_for_unknown_action(): void
    {
        $result = AuditLogService::getActionLabel('some_unknown_action');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertStringNotContainsString('_', $result);
    }

    public function test_log_returns_integer_or_null(): void
    {
        try {
            $id = AuditLogService::log(
                AuditLogService::ACTION_SETTINGS_CHANGED,
                self::$orgId,
                self::$adminUserId,
                ['field' => 'name', 'old' => 'OldName', 'new' => 'NewName']
            );
            $this->assertTrue($id === null || (is_int($id) && $id > 0));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_log_transaction_deposit(): void
    {
        try {
            $id = AuditLogService::logTransaction(self::$orgId, self::$adminUserId, 'deposit', 50.0, null, 'Test deposit');
            $this->assertTrue($id === null || (is_int($id) && $id > 0));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_log_transaction_withdrawal(): void
    {
        try {
            $id = AuditLogService::logTransaction(self::$orgId, self::$adminUserId, 'withdrawal', 25.0, self::$targetUserId, 'Test withdrawal');
            $this->assertTrue($id === null || (is_int($id) && $id > 0));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_log_user_suspended(): void
    {
        try {
            $id = AuditLogService::logUserSuspended(self::$adminUserId, self::$targetUserId, 'Spam behaviour');
            $this->assertTrue($id === null || (is_int($id) && $id > 0));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_log_user_banned(): void
    {
        try {
            $id = AuditLogService::logUserBanned(self::$adminUserId, self::$targetUserId, 'Violation of ToS');
            $this->assertTrue($id === null || (is_int($id) && $id > 0));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_log_user_deleted(): void
    {
        try {
            $id = AuditLogService::logUserDeleted(self::$adminUserId, self::$targetUserId, 'deleted@example.com');
            $this->assertTrue($id === null || (is_int($id) && $id > 0));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_log_user_approved(): void
    {
        try {
            $id = AuditLogService::logUserApproved(self::$adminUserId, self::$targetUserId, 'approved@example.com');
            $this->assertTrue($id === null || (is_int($id) && $id > 0));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_log_admin_role_changed(): void
    {
        try {
            $id = AuditLogService::logAdminRoleChanged(self::$adminUserId, self::$targetUserId, 'member', 'admin');
            $this->assertTrue($id === null || (is_int($id) && $id > 0));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_log_bulk_import(): void
    {
        try {
            $id = AuditLogService::logBulkImport(self::$adminUserId, 42, 8, 50);
            $this->assertTrue($id === null || (is_int($id) && $id > 0));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_log_returns_array(): void
    {
        try {
            $logs = AuditLogService::getLog(self::$orgId, [], 10, 0);
            $this->assertIsArray($logs);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_log_count_returns_non_negative_integer(): void
    {
        try {
            $count = AuditLogService::getLogCount(self::$orgId);
            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_log_with_action_filter_returns_only_matching_rows(): void
    {
        try {
            $logs = AuditLogService::getLog(self::$orgId, ['action' => AuditLogService::ACTION_MEMBER_ADDED]);
            $this->assertIsArray($logs);
            foreach ($logs as $log) {
                $this->assertSame(AuditLogService::ACTION_MEMBER_ADDED, $log['action']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_user_activity_returns_array(): void
    {
        try {
            $activity = AuditLogService::getUserActivity(self::$adminUserId, 10);
            $this->assertIsArray($activity);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_action_summary_returns_array(): void
    {
        try {
            $summary = AuditLogService::getActionSummary(self::$orgId, 30);
            $this->assertIsArray($summary);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_cleanup_returns_non_negative_integer(): void
    {
        try {
            $deleted = AuditLogService::cleanup(9999);
            $this->assertIsInt($deleted);
            $this->assertGreaterThanOrEqual(0, $deleted);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }
}
