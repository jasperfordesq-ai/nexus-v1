<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

/**
 * SafeguardingService - Guardian angel / safeguarding system
 *
 * Designated "guardian" users can monitor conversations of vulnerable members
 * (with consent). Provides:
 *
 * - Assignment management (guardian <-> ward pairs)
 * - Automatic keyword flagging of concerning messages
 * - Flagged conversation review for guardians
 * - Admin API for managing assignments
 *
 * Tables:
 *   safeguarding_assignments — guardian/ward pairs
 *   safeguarding_flagged_messages — flagged message records
 *
 * Privacy: Guardians ONLY see flagged messages, not all messages.
 * Consent: ward must have consent_given_at set before monitoring is active.
 */
class SafeguardingService
{
    private static array $errors = [];

    /** Concerning keywords for automatic flagging */
    private const CONCERN_KEYWORDS = [
        // Financial abuse
        'send me money', 'bank account', 'wire transfer', 'western union',
        'gift card', 'cryptocurrency', 'bitcoin', 'paypal me',
        // Exploitation
        'come to my house alone', 'dont tell anyone', "don't tell anyone",
        'keep this secret', 'our secret', 'nobody needs to know',
        // Threatening
        'threaten', 'hurt you', 'harm you', 'kill', 'die',
        // Scam indicators
        'nigerian prince', 'lottery', 'you have won', 'claim your prize',
        'investment opportunity', 'guaranteed returns',
        // Safeguarding concerns
        'medication', 'prescription', 'power of attorney',
    ];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    // =========================================================================
    // ASSIGNMENT MANAGEMENT
    // =========================================================================

    /**
     * Create a safeguarding assignment
     *
     * @param int $guardianUserId The guardian/monitor
     * @param int $wardUserId The vulnerable member being protected
     * @param int $assignedBy Admin who created the assignment
     * @param string|null $notes Additional notes
     * @return array Result
     */
    public static function createAssignment(
        int $guardianUserId,
        int $wardUserId,
        int $assignedBy,
        ?string $notes = null
    ): array {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        if ($guardianUserId === $wardUserId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Guardian and ward cannot be the same person'];
            return ['success' => false, 'errors' => self::$errors];
        }

        // Check both users exist in tenant
        $guardianExists = Database::query(
            "SELECT id FROM users WHERE id = ? AND tenant_id = ? AND status = 'active'",
            [$guardianUserId, $tenantId]
        )->fetch();

        $wardExists = Database::query(
            "SELECT id FROM users WHERE id = ? AND tenant_id = ? AND status = 'active'",
            [$wardUserId, $tenantId]
        )->fetch();

        if (!$guardianExists) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Guardian user not found'];
            return ['success' => false, 'errors' => self::$errors];
        }
        if (!$wardExists) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Ward user not found'];
            return ['success' => false, 'errors' => self::$errors];
        }

        try {
            Database::query(
                "INSERT INTO safeguarding_assignments (guardian_user_id, ward_user_id, tenant_id, assigned_by, assigned_at, notes)
                 VALUES (?, ?, ?, ?, NOW(), ?)
                 ON DUPLICATE KEY UPDATE revoked_at = NULL, assigned_by = VALUES(assigned_by), assigned_at = NOW(), notes = VALUES(notes)",
                [$guardianUserId, $wardUserId, $tenantId, $assignedBy, $notes]
            );

            // Notify guardian
            Notification::create(
                $guardianUserId,
                'You have been assigned as a safeguarding guardian. Please review your responsibilities.',
                '/settings?tab=safeguarding',
                'safeguarding'
            );

            return ['success' => true, 'message' => 'Safeguarding assignment created'];
        } catch (\Exception $e) {
            error_log("SafeguardingService::createAssignment error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create assignment'];
        }
    }

    /**
     * Record consent from the ward
     */
    public static function recordConsent(int $wardUserId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "UPDATE safeguarding_assignments SET consent_given_at = NOW()
                 WHERE ward_user_id = ? AND tenant_id = ? AND revoked_at IS NULL AND consent_given_at IS NULL",
                [$wardUserId, $tenantId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("SafeguardingService::recordConsent error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke an assignment
     */
    public static function revokeAssignment(int $assignmentId, int $revokedBy): bool
    {
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "UPDATE safeguarding_assignments SET revoked_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$assignmentId, $tenantId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("SafeguardingService::revokeAssignment error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * List all active assignments for a tenant
     */
    public static function listAssignments(): array
    {
        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT sa.*,
                        g.name as guardian_name, g.avatar_url as guardian_avatar,
                        w.name as ward_name, w.avatar_url as ward_avatar,
                        ab.name as assigned_by_name
                 FROM safeguarding_assignments sa
                 JOIN users g ON sa.guardian_user_id = g.id
                 JOIN users w ON sa.ward_user_id = w.id
                 LEFT JOIN users ab ON sa.assigned_by = ab.id
                 WHERE sa.tenant_id = ? AND sa.revoked_at IS NULL
                 ORDER BY sa.assigned_at DESC",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get assignments where user is guardian
     */
    public static function getGuardianAssignments(int $userId): array
    {
        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT sa.*,
                        w.name as ward_name, w.avatar_url as ward_avatar
                 FROM safeguarding_assignments sa
                 JOIN users w ON sa.ward_user_id = w.id
                 WHERE sa.guardian_user_id = ? AND sa.tenant_id = ?
                   AND sa.revoked_at IS NULL AND sa.consent_given_at IS NOT NULL
                 ORDER BY sa.assigned_at DESC",
                [$userId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    // MESSAGE SCANNING & FLAGGING
    // =========================================================================

    /**
     * Scan a message for concerning keywords
     * Called after each message is sent (from MessageService or Model)
     *
     * @param int $messageId
     * @param string $body Message body text
     * @param int $senderId
     * @param int $receiverId
     */
    public static function scanMessage(int $messageId, string $body, int $senderId, int $receiverId): void
    {
        $tenantId = TenantContext::getId();

        // Check if either party is a monitored ward (with active, consented assignment)
        $isMonitored = self::isUserMonitored($senderId) || self::isUserMonitored($receiverId);
        if (!$isMonitored) {
            return;
        }

        // Check for concerning keywords
        $bodyLower = strtolower($body);
        $matchedKeyword = null;

        foreach (self::CONCERN_KEYWORDS as $keyword) {
            if (strpos($bodyLower, strtolower($keyword)) !== false) {
                $matchedKeyword = $keyword;
                break;
            }
        }

        if ($matchedKeyword === null) {
            return; // No concerning content detected
        }

        // Flag the message
        try {
            Database::query(
                "INSERT INTO safeguarding_flagged_messages (message_id, tenant_id, flagged_reason, matched_keyword, created_at)
                 VALUES (?, ?, 'keyword_match', ?, NOW())",
                [$messageId, $tenantId, $matchedKeyword]
            );

            // Notify guardians
            self::notifyGuardians($senderId, $receiverId, $matchedKeyword);
        } catch (\Exception $e) {
            error_log("SafeguardingService::scanMessage error: " . $e->getMessage());
        }
    }

    /**
     * Check if a user is being monitored (has active, consented guardian)
     */
    public static function isUserMonitored(int $userId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $count = (int)Database::query(
                "SELECT COUNT(*) FROM safeguarding_assignments
                 WHERE ward_user_id = ? AND tenant_id = ?
                   AND revoked_at IS NULL AND consent_given_at IS NOT NULL",
                [$userId, $tenantId]
            )->fetchColumn();

            return $count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get flagged messages for a guardian's wards
     */
    public static function getFlaggedMessages(int $guardianUserId, int $limit = 50): array
    {
        $tenantId = TenantContext::getId();

        try {
            $limitInt = (int)$limit;
            // Get ward IDs for this guardian
            $wardIds = Database::query(
                "SELECT ward_user_id FROM safeguarding_assignments
                 WHERE guardian_user_id = ? AND tenant_id = ?
                   AND revoked_at IS NULL AND consent_given_at IS NOT NULL",
                [$guardianUserId, $tenantId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($wardIds)) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($wardIds), '?'));

            return Database::query(
                "SELECT sfm.*, m.body as message_body, m.sender_id, m.receiver_id, m.created_at as message_sent_at,
                        s.name as sender_name, r.name as receiver_name,
                        rev.name as reviewer_name
                 FROM safeguarding_flagged_messages sfm
                 JOIN messages m ON sfm.message_id = m.id
                 JOIN users s ON m.sender_id = s.id
                 JOIN users r ON m.receiver_id = r.id
                 LEFT JOIN users rev ON sfm.reviewed_by = rev.id
                 WHERE sfm.tenant_id = ?
                   AND (m.sender_id IN ({$placeholders}) OR m.receiver_id IN ({$placeholders}))
                 ORDER BY sfm.created_at DESC
                 LIMIT {$limitInt}",
                array_merge([$tenantId], $wardIds, $wardIds)
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("SafeguardingService::getFlaggedMessages error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Review a flagged message (guardian marks as reviewed)
     */
    public static function reviewFlaggedMessage(int $flagId, int $reviewerId, ?string $notes = null): bool
    {
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "UPDATE safeguarding_flagged_messages SET reviewed_by = ?, reviewed_at = NOW(), review_notes = ?
                 WHERE id = ? AND tenant_id = ?",
                [$reviewerId, $notes, $flagId, $tenantId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("SafeguardingService::reviewFlaggedMessage error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get safeguarding summary for admin dashboard
     */
    public static function getDashboardSummary(): array
    {
        $tenantId = TenantContext::getId();

        try {
            $activeAssignments = (int)Database::query(
                "SELECT COUNT(*) FROM safeguarding_assignments WHERE tenant_id = ? AND revoked_at IS NULL",
                [$tenantId]
            )->fetchColumn();

            $consentedAssignments = (int)Database::query(
                "SELECT COUNT(*) FROM safeguarding_assignments WHERE tenant_id = ? AND revoked_at IS NULL AND consent_given_at IS NOT NULL",
                [$tenantId]
            )->fetchColumn();

            $unreviewedFlags = (int)Database::query(
                "SELECT COUNT(*) FROM safeguarding_flagged_messages WHERE tenant_id = ? AND reviewed_by IS NULL",
                [$tenantId]
            )->fetchColumn();

            $totalFlags = (int)Database::query(
                "SELECT COUNT(*) FROM safeguarding_flagged_messages WHERE tenant_id = ?",
                [$tenantId]
            )->fetchColumn();

            return [
                'active_assignments' => $activeAssignments,
                'consented_assignments' => $consentedAssignments,
                'pending_consent' => $activeAssignments - $consentedAssignments,
                'unreviewed_flags' => $unreviewedFlags,
                'total_flags' => $totalFlags,
            ];
        } catch (\Exception $e) {
            return [
                'active_assignments' => 0,
                'consented_assignments' => 0,
                'pending_consent' => 0,
                'unreviewed_flags' => 0,
                'total_flags' => 0,
            ];
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Notify guardians when a concerning message is detected
     */
    private static function notifyGuardians(int $senderId, int $receiverId, string $keyword): void
    {
        $tenantId = TenantContext::getId();

        try {
            // Find guardians for both sender and receiver
            $guardians = Database::query(
                "SELECT DISTINCT guardian_user_id FROM safeguarding_assignments
                 WHERE tenant_id = ? AND revoked_at IS NULL AND consent_given_at IS NOT NULL
                   AND ward_user_id IN (?, ?)",
                [$tenantId, $senderId, $receiverId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($guardians as $guardianId) {
                Notification::create(
                    (int)$guardianId,
                    'A flagged message has been detected in a monitored conversation. Please review.',
                    '/settings?tab=safeguarding',
                    'safeguarding_alert'
                );
            }
        } catch (\Exception $e) {
            error_log("SafeguardingService::notifyGuardians error: " . $e->getMessage());
        }
    }
}
