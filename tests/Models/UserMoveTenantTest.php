<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use PHPUnit\Framework\TestCase;
use Nexus\Models\User;
use ReflectionMethod;

/**
 * User::moveTenant() Contract Tests
 *
 * Unit tests that verify the moveTenant() and verifyTenantData() method
 * signatures, return type structure, and internal table coverage without
 * requiring a database connection. Uses reflection to inspect the method
 * internals and validate the table/column arrays are comprehensive.
 */
class UserMoveTenantTest extends TestCase
{
    // ==========================================
    // Method Existence & Signature Tests
    // ==========================================

    public function testMoveTenantMethodExists(): void
    {
        $this->assertTrue(
            method_exists(User::class, 'moveTenant'),
            'User::moveTenant() method should exist'
        );
    }

    public function testMoveTenantIsPublicStatic(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');

        $this->assertTrue($method->isPublic(), 'moveTenant should be public');
        $this->assertTrue($method->isStatic(), 'moveTenant should be static');
    }

    public function testMoveTenantParameterCount(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $params = $method->getParameters();

        $this->assertCount(4, $params, 'moveTenant should have 4 parameters');
    }

    public function testMoveTenantParameterNames(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $params = $method->getParameters();

        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('newTenantId', $params[1]->getName());
        $this->assertEquals('moveContent', $params[2]->getName());
        $this->assertEquals('dryRun', $params[3]->getName());
    }

    public function testMoveTenantParameterTypes(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $params = $method->getParameters();

        $this->assertEquals('int', $params[0]->getType()->getName());
        $this->assertEquals('int', $params[1]->getType()->getName());
        $this->assertEquals('bool', $params[2]->getType()->getName());
        $this->assertEquals('bool', $params[3]->getType()->getName());
    }

    public function testMoveTenantDefaultValues(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $params = $method->getParameters();

        // userId and newTenantId are required (no default)
        $this->assertFalse($params[0]->isOptional(), 'userId should be required');
        $this->assertFalse($params[1]->isOptional(), 'newTenantId should be required');

        // moveContent defaults to true
        $this->assertTrue($params[2]->isOptional(), 'moveContent should be optional');
        $this->assertTrue($params[2]->getDefaultValue(), 'moveContent should default to true');

        // dryRun defaults to false
        $this->assertTrue($params[3]->isOptional(), 'dryRun should be optional');
        $this->assertFalse($params[3]->getDefaultValue(), 'dryRun should default to false');
    }

    public function testMoveTenantReturnType(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType, 'moveTenant should declare a return type');
        $this->assertEquals('array', $returnType->getName());
    }

    // ==========================================
    // verifyTenantData Method Existence Tests
    // ==========================================

    public function testVerifyTenantDataMethodExists(): void
    {
        $this->assertTrue(
            method_exists(User::class, 'verifyTenantData'),
            'User::verifyTenantData() method should exist'
        );
    }

    public function testVerifyTenantDataIsPublicStatic(): void
    {
        $method = new ReflectionMethod(User::class, 'verifyTenantData');

        $this->assertTrue($method->isPublic(), 'verifyTenantData should be public');
        $this->assertTrue($method->isStatic(), 'verifyTenantData should be static');
    }

    public function testVerifyTenantDataParameterCount(): void
    {
        $method = new ReflectionMethod(User::class, 'verifyTenantData');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'verifyTenantData should have 2 parameters');
    }

    public function testVerifyTenantDataParameterNames(): void
    {
        $method = new ReflectionMethod(User::class, 'verifyTenantData');
        $params = $method->getParameters();

        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('expectedTenantId', $params[1]->getName());
    }

    public function testVerifyTenantDataReturnType(): void
    {
        $method = new ReflectionMethod(User::class, 'verifyTenantData');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType, 'verifyTenantData should declare a return type');
        $this->assertEquals('array', $returnType->getName());
    }

    // ==========================================
    // moveAvatarToNewTenant Helper Exists
    // ==========================================

    public function testMoveAvatarToNewTenantHelperExists(): void
    {
        $method = new ReflectionMethod(User::class, 'moveAvatarToNewTenant');

        $this->assertTrue($method->isPrivate(), 'moveAvatarToNewTenant should be private');
        $this->assertTrue($method->isStatic(), 'moveAvatarToNewTenant should be static');
    }

    // ==========================================
    // Return Structure Contract Tests
    // ==========================================

    /**
     * Verify the initial result structure defined at the top of moveTenant().
     * We extract this by reading the method source via reflection.
     */
    public function testReturnStructureHasRequiredKeys(): void
    {
        // The method initialises: ['success' => false, 'moved' => [], 'failed' => [], 'verification' => []]
        // Verify by inspecting the source code
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));

        // Confirm the result array is initialised with the expected keys
        $this->assertStringContainsString("'success' => false", $source);
        $this->assertStringContainsString("'moved' => []", $source);
        $this->assertStringContainsString("'failed' => []", $source);
        $this->assertStringContainsString("'verification' => []", $source);
    }

    public function testSuccessIsSetToTrueOnCompletion(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $source = implode('', array_slice(
            file($method->getFileName()),
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        $this->assertStringContainsString("\$result['success'] = true", $source);
    }

    // ==========================================
    // Table Coverage Tests (Reflection)
    // ==========================================

    /**
     * Extract the $userIdTables array from the moveTenant source and verify
     * it contains the critical tables that must be covered.
     */
    public function testUserIdTablesContainsCriticalTables(): void
    {
        $userIdTables = $this->extractUserIdTables();

        // Core content tables
        $criticalTables = [
            'listings',
            'feed_posts',
            'events',
            'goals',
            'polls',
            'resources',
            'comments',
            'likes',
        ];

        foreach ($criticalTables as $table) {
            $this->assertContains($table, $userIdTables, "userIdTables should contain '{$table}'");
        }
    }

    public function testUserIdTablesContainsSocialTables(): void
    {
        $userIdTables = $this->extractUserIdTables();

        $socialTables = [
            'feed_hidden',
            'feed_muted_users',
            'group_discussions',
            'group_posts',
            'group_members',
            'group_views',
        ];

        foreach ($socialTables as $table) {
            $this->assertContains($table, $userIdTables, "userIdTables should contain '{$table}'");
        }
    }

    public function testUserIdTablesContainsGamificationTables(): void
    {
        $userIdTables = $this->extractUserIdTables();

        $gamificationTables = [
            'user_badges',
            'user_gamification_summary',
            'user_streaks',
            'user_xp_log',
            'user_points_log',
            'user_challenge_progress',
            'challenge_progress',
            'xp_history',
            'daily_rewards',
            'season_rankings',
            'nexus_scores',
            'leaderboard_cache',
        ];

        foreach ($gamificationTables as $table) {
            $this->assertContains($table, $userIdTables, "userIdTables should contain '{$table}'");
        }
    }

    public function testUserIdTablesContainsNotificationTables(): void
    {
        $userIdTables = $this->extractUserIdTables();

        $notificationTables = [
            'notifications',
            'notification_settings',
            'notification_queue',
            'push_subscriptions',
            'fcm_device_tokens',
        ];

        foreach ($notificationTables as $table) {
            $this->assertContains($table, $userIdTables, "userIdTables should contain '{$table}'");
        }
    }

    public function testUserIdTablesContainsSecurityTables(): void
    {
        $userIdTables = $this->extractUserIdTables();

        $securityTables = [
            'sessions',
            'webauthn_credentials',
            'user_totp_settings',
            'user_backup_codes',
            'user_trusted_devices',
            'revoked_tokens',
        ];

        foreach ($securityTables as $table) {
            $this->assertContains($table, $userIdTables, "userIdTables should contain '{$table}'");
        }
    }

    public function testUserIdTablesContainsAiTables(): void
    {
        $userIdTables = $this->extractUserIdTables();

        $aiTables = [
            'ai_conversations',
            'ai_usage',
            'ai_user_limits',
        ];

        foreach ($aiTables as $table) {
            $this->assertContains($table, $userIdTables, "userIdTables should contain '{$table}'");
        }
    }

    public function testUserIdTablesContainsAuditTables(): void
    {
        $userIdTables = $this->extractUserIdTables();

        $auditTables = [
            'gdpr_audit_log',
            'gdpr_requests',
            'activity_log',
            'group_audit_log',
            'permission_audit_log',
        ];

        foreach ($auditTables as $table) {
            $this->assertContains($table, $userIdTables, "userIdTables should contain '{$table}'");
        }
    }

    public function testUserIdTablesContainsVolunteeringTables(): void
    {
        $userIdTables = $this->extractUserIdTables();

        $volunteeringTables = [
            'vol_organizations',
            'vol_applications',
            'vol_logs',
        ];

        foreach ($volunteeringTables as $table) {
            $this->assertContains($table, $userIdTables, "userIdTables should contain '{$table}'");
        }
    }

    public function testUserIdTablesContainsFederationTables(): void
    {
        $userIdTables = $this->extractUserIdTables();

        $federationTables = [
            'federation_user_settings',
            'federation_rate_limits',
            'federation_realtime_queue',
            'federation_notifications',
            'federation_reputation',
        ];

        foreach ($federationTables as $table) {
            $this->assertContains($table, $userIdTables, "userIdTables should contain '{$table}'");
        }
    }

    public function testUserIdTablesContainsMatchingTables(): void
    {
        $userIdTables = $this->extractUserIdTables();

        $matchingTables = [
            'match_approvals',
            'match_cache',
            'match_history',
            'match_preferences',
        ];

        foreach ($matchingTables as $table) {
            $this->assertContains($table, $userIdTables, "userIdTables should contain '{$table}'");
        }
    }

    public function testUserIdTablesContainsUserPreferenceTables(): void
    {
        $userIdTables = $this->extractUserIdTables();

        $preferenceTables = [
            'user_consents',
            'user_permissions',
            'user_roles',
            'user_categories',
            'user_interests',
            'user_email_preferences',
            'user_legal_acceptances',
            'cookie_consents',
        ];

        foreach ($preferenceTables as $table) {
            $this->assertContains($table, $userIdTables, "userIdTables should contain '{$table}'");
        }
    }

    /**
     * Ensure the total count of user_id tables is at or above the expected
     * minimum. This catches accidental deletions from the list.
     */
    public function testUserIdTablesMinimumCount(): void
    {
        $userIdTables = $this->extractUserIdTables();

        // The method currently covers 100+ user_id tables.
        // If this count drops significantly, something was accidentally removed.
        $this->assertGreaterThanOrEqual(90, count($userIdTables),
            'userIdTables should contain at least 90 tables (currently has ' . count($userIdTables) . ')'
        );
    }

    // ==========================================
    // Multi-Column Tables Tests (Reflection)
    // ==========================================

    public function testMultiColumnTablesContainsMessageColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['messages', 'sender_id'], $multiColumnTables);
        $this->assertContains(['messages', 'receiver_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsTransactionColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['transactions', 'sender_id'], $multiColumnTables);
        $this->assertContains(['transactions', 'receiver_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsConnectionColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['connections', 'requester_id'], $multiColumnTables);
        $this->assertContains(['connections', 'receiver_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsExchangeRequestColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['exchange_requests', 'requester_id'], $multiColumnTables);
        $this->assertContains(['exchange_requests', 'provider_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsMentionColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['mentions', 'mentioned_user_id'], $multiColumnTables);
        $this->assertContains(['mentions', 'mentioning_user_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsAuthorColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['blog_posts', 'author_id'], $multiColumnTables);
        $this->assertContains(['posts', 'author_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsOwnerColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['deliverables', 'owner_id'], $multiColumnTables);
        $this->assertContains(['groups', 'owner_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsAdminActionColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['admin_actions', 'admin_id'], $multiColumnTables);
        $this->assertContains(['admin_actions', 'target_user_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsReportColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['reports', 'reporter_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsFederationColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['federation_messages', 'sender_user_id'], $multiColumnTables);
        $this->assertContains(['federation_messages', 'receiver_user_id'], $multiColumnTables);
        $this->assertContains(['federation_transactions', 'sender_user_id'], $multiColumnTables);
        $this->assertContains(['federation_transactions', 'receiver_user_id'], $multiColumnTables);
        $this->assertContains(['federation_audit_log', 'actor_user_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsOrgColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['org_transactions', 'sender_id'], $multiColumnTables);
        $this->assertContains(['org_transactions', 'receiver_id'], $multiColumnTables);
        $this->assertContains(['org_transfer_requests', 'requester_id'], $multiColumnTables);
        $this->assertContains(['org_audit_log', 'user_id'], $multiColumnTables);
        $this->assertContains(['org_audit_log', 'target_user_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsBrokerMessageColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['broker_message_copies', 'sender_id'], $multiColumnTables);
        $this->assertContains(['broker_message_copies', 'receiver_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsUserBlockColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['user_blocks', 'user_id'], $multiColumnTables);
        $this->assertContains(['user_blocks', 'blocked_user_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsMutedUserColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['feed_muted_users', 'muted_user_id'], $multiColumnTables);
        $this->assertContains(['user_muted_users', 'muted_user_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsReferralColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['referral_tracking', 'referrer_id'], $multiColumnTables);
        $this->assertContains(['referral_tracking', 'referred_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsFriendChallengeColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['friend_challenges', 'challenger_id'], $multiColumnTables);
        $this->assertContains(['friend_challenges', 'challenged_id'], $multiColumnTables);
    }

    public function testMultiColumnTablesContainsVolunteerColumns(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        $this->assertContains(['vol_reviews', 'reviewer_id'], $multiColumnTables);
        $this->assertContains(['vol_opportunities', 'created_by'], $multiColumnTables);
    }

    /**
     * Ensure the multi-column table list has a minimum count.
     */
    public function testMultiColumnTablesMinimumCount(): void
    {
        $multiColumnTables = $this->extractMultiColumnTables();

        // Currently around 50 entries. Guard against accidental deletion.
        $this->assertGreaterThanOrEqual(40, count($multiColumnTables),
            'multiColumnTables should contain at least 40 entries (currently has ' . count($multiColumnTables) . ')'
        );
    }

    // ==========================================
    // Special Handling Tests
    // ==========================================

    /**
     * Verify reviews table has special handling (reviewer_tenant_id, receiver_tenant_id).
     */
    public function testReviewsHaveSpecialTenantSubColumns(): void
    {
        $source = $this->getMoveTenantSource();

        $this->assertStringContainsString('reviewer_tenant_id', $source,
            'moveTenant should update reviewer_tenant_id on reviews table');
        $this->assertStringContainsString('receiver_tenant_id', $source,
            'moveTenant should update receiver_tenant_id on reviews table');
    }

    /**
     * Verify message_attachments has JOIN-based cascade logic.
     */
    public function testMessageAttachmentsCascadeViaJoin(): void
    {
        $source = $this->getMoveTenantSource();

        $this->assertStringContainsString('message_attachments', $source);
        $this->assertStringContainsString('JOIN messages m ON ma.message_id = m.id', $source,
            'message_attachments should cascade tenant_id via JOIN on messages');
    }

    /**
     * Verify avatar file move logic exists.
     */
    public function testAvatarFileMoveLogicExists(): void
    {
        $source = $this->getMoveTenantSource();

        $this->assertStringContainsString('moveAvatarToNewTenant', $source,
            'moveTenant should call moveAvatarToNewTenant for avatar file migration');
    }

    // ==========================================
    // Transaction Safety Tests
    // ==========================================

    public function testUsesTransactionForNonDryRun(): void
    {
        $source = $this->getMoveTenantSource();

        $this->assertStringContainsString('Database::beginTransaction()', $source);
        $this->assertStringContainsString('Database::commit()', $source);
        $this->assertStringContainsString('Database::rollback()', $source);
    }

    public function testDryRunSkipsTransaction(): void
    {
        $source = $this->getMoveTenantSource();

        // Verify the pattern: if (!$dryRun) { Database::beginTransaction(); }
        $this->assertStringContainsString('if (!$dryRun)', $source,
            'moveTenant should guard transactions and writes with $dryRun checks');
    }

    public function testDryRunUsesSelectCountInsteadOfUpdate(): void
    {
        $source = $this->getMoveTenantSource();

        // The $move closure should contain both SELECT COUNT (dry run) and UPDATE (real)
        $this->assertStringContainsString('SELECT COUNT(*) AS cnt', $source,
            'Dry run mode should use SELECT COUNT to preview changes');
        $this->assertStringContainsString('UPDATE', $source,
            'Non-dry-run mode should use UPDATE to apply changes');
    }

    // ==========================================
    // Error Handling Tests
    // ==========================================

    public function testHandlesSameTenantError(): void
    {
        $source = $this->getMoveTenantSource();

        $this->assertStringContainsString('already on tenant', $source,
            'moveTenant should throw when user is already on the target tenant');
    }

    public function testHandlesUserNotFoundError(): void
    {
        $source = $this->getMoveTenantSource();

        $this->assertStringContainsString('not found', $source,
            'moveTenant should throw when user is not found');
    }

    public function testFailedTablesAreRecordedInResult(): void
    {
        $source = $this->getMoveTenantSource();

        $this->assertStringContainsString("\$result['failed']", $source,
            'Failed table moves should be recorded in the result array');
    }

    public function testTransactionRollbackOnCriticalError(): void
    {
        $source = $this->getMoveTenantSource();

        // The catch block should record the error in failed._transaction
        $this->assertStringContainsString("'_transaction'", $source,
            'Critical transaction errors should be recorded under the _transaction key');
    }

    // ==========================================
    // Verification Integration Tests
    // ==========================================

    public function testVerificationCalledAfterNonDryRunMove(): void
    {
        $source = $this->getMoveTenantSource();

        $this->assertStringContainsString('verifyTenantData', $source,
            'moveTenant should call verifyTenantData after a non-dry-run move');
    }

    public function testVerifyTenantDataChecksUserIdTables(): void
    {
        $method = new ReflectionMethod(User::class, 'verifyTenantData');
        $source = implode('', array_slice(
            file($method->getFileName()),
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        // Verify it checks core user_id tables
        $this->assertStringContainsString("'listings', 'user_id'", $source);
        $this->assertStringContainsString("'feed_posts', 'user_id'", $source);
        $this->assertStringContainsString("'events', 'user_id'", $source);
        $this->assertStringContainsString("'notifications', 'user_id'", $source);
    }

    public function testVerifyTenantDataChecksMultiColumnTables(): void
    {
        $method = new ReflectionMethod(User::class, 'verifyTenantData');
        $source = implode('', array_slice(
            file($method->getFileName()),
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        // Verify it checks multi-column tables
        $this->assertStringContainsString("'messages', 'sender_id'", $source);
        $this->assertStringContainsString("'messages', 'receiver_id'", $source);
        $this->assertStringContainsString("'transactions', 'sender_id'", $source);
        $this->assertStringContainsString("'transactions', 'receiver_id'", $source);
        $this->assertStringContainsString("'connections', 'requester_id'", $source);
    }

    // ==========================================
    // Logging Tests
    // ==========================================

    public function testLogsComprehensiveSummary(): void
    {
        $source = $this->getMoveTenantSource();

        $this->assertStringContainsString('error_log(', $source,
            'moveTenant should log its operations');
        $this->assertStringContainsString('User::moveTenant', $source,
            'Log messages should identify the method');
    }

    public function testDryRunPrefixInLogs(): void
    {
        $source = $this->getMoveTenantSource();

        $this->assertStringContainsString('[DRY RUN]', $source,
            'Dry run operations should be prefixed in log messages');
    }

    // ==========================================
    // Helpers
    // ==========================================

    /**
     * Get the source code of User::moveTenant() as a string.
     */
    private function getMoveTenantSource(): string
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $lines = file($method->getFileName());

        return implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));
    }

    /**
     * Extract the $userIdTables array from moveTenant() source code via regex.
     *
     * @return string[] List of table names
     */
    private function extractUserIdTables(): array
    {
        $source = $this->getMoveTenantSource();

        // Match the $userIdTables = [...] block
        if (!preg_match('/\$userIdTables\s*=\s*\[(.*?)\];/s', $source, $match)) {
            $this->fail('Could not extract $userIdTables from moveTenant() source');
        }

        // Extract all quoted table names
        preg_match_all("/'([a-z_]+)'/", $match[1], $tableMatches);

        return $tableMatches[1];
    }

    /**
     * Extract the $multiColumnTables array from moveTenant() source code via regex.
     *
     * @return array<array{0: string, 1: string}> List of [table, column] pairs
     */
    private function extractMultiColumnTables(): array
    {
        $source = $this->getMoveTenantSource();

        // Match the $multiColumnTables = [...] block
        if (!preg_match('/\$multiColumnTables\s*=\s*\[(.*?)\];/s', $source, $match)) {
            $this->fail('Could not extract $multiColumnTables from moveTenant() source');
        }

        // Extract all [table, column] pairs
        preg_match_all("/\['([a-z_]+)',\s*'([a-z_]+)'\]/", $match[1], $pairMatches, PREG_SET_ORDER);

        $result = [];
        foreach ($pairMatches as $m) {
            $result[] = [$m[1], $m[2]];
        }

        return $result;
    }
}
