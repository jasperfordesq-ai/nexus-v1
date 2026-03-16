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
    // =========================================================================
    // SAFEGUARDING TRAINING
    // =========================================================================

    /**
     * Record a training completion for a user
     *
     * @param int $userId
     * @param array $data [training_type, training_name, provider, completed_at, expires_at, certificate_url, notes]
     * @return array The created record
     */
    public static function recordTraining(int $userId, array $data): array
    {
        $tenantId = TenantContext::getId();

        // Validate training_type ENUM
        $validTrainingTypes = ['children_first', 'vulnerable_adults', 'first_aid', 'manual_handling', 'other'];
        $trainingType = $data['training_type'] ?? '';
        if (!in_array($trainingType, $validTrainingTypes, true)) {
            throw new \InvalidArgumentException(
                "Invalid training_type '{$trainingType}'. Must be one of: " . implode(', ', $validTrainingTypes)
            );
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO vol_safeguarding_training
                    (user_id, tenant_id, training_type, training_name, provider,
                     completed_at, expires_at, certificate_url, notes, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())"
            );
            $stmt->execute([
                $userId,
                $tenantId,
                $data['training_type'] ?? '',
                $data['training_name'] ?? '',
                $data['provider'] ?? null,
                $data['completed_at'] ?? date('Y-m-d'),
                $data['expires_at'] ?? null,
                $data['certificate_url'] ?? null,
                $data['notes'] ?? null,
            ]);

            $id = (int)$db->lastInsertId();

            $record = Database::query(
                "SELECT * FROM vol_safeguarding_training WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            return $record ?: [];
        } catch (\Exception $e) {
            error_log("SafeguardingService::recordTraining error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verify a training record (admin/DLP approval)
     */
    public static function verifyTraining(int $id, int $verifierId, ?string $notes = null, ?string $certificateUrl = null): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $sql = "UPDATE vol_safeguarding_training
                    SET status = 'verified', verified_by = ?, verified_at = NOW(), updated_at = NOW()";
            $params = [$verifierId];

            if ($notes !== null) {
                $sql .= ", notes = ?";
                $params[] = $notes;
            }
            if ($certificateUrl !== null) {
                $sql .= ", certificate_url = ?";
                $params[] = $certificateUrl;
            }

            $sql .= " WHERE id = ? AND tenant_id = ?";
            $params[] = $id;
            $params[] = $tenantId;

            Database::query($sql, $params);
            return true;
        } catch (\Exception $e) {
            error_log("SafeguardingService::verifyTraining error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reject a training record
     */
    public static function rejectTraining(int $id, int $verifierId, ?string $notes = null): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $sql = "UPDATE vol_safeguarding_training
                    SET status = 'rejected', verified_by = ?, verified_at = NOW(), updated_at = NOW()";
            $params = [$verifierId];

            if ($notes !== null) {
                $sql .= ", notes = ?";
                $params[] = $notes;
            }

            $sql .= " WHERE id = ? AND tenant_id = ?";
            $params[] = $id;
            $params[] = $tenantId;

            Database::query($sql, $params);
            return true;
        } catch (\Exception $e) {
            error_log("SafeguardingService::rejectTraining error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all training records for a user
     */
    public static function getTrainingForUser(int $userId): array
    {
        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT st.*, v.name as verified_by_name
                 FROM vol_safeguarding_training st
                 LEFT JOIN users v ON st.verified_by = v.id
                 WHERE st.user_id = ? AND st.tenant_id = ?
                 ORDER BY st.completed_at DESC",
                [$userId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("SafeguardingService::getTrainingForUser error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get training records for admin view with pagination and filters
     *
     * @param array $filters [status, training_type, user_id, page, per_page]
     * @return array ['items' => [], 'total' => int, 'page' => int, 'per_page' => int]
     */
    public static function getTrainingForAdmin(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int)($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        try {
            $where = "st.tenant_id = ?";
            $params = [$tenantId];

            if (!empty($filters['status'])) {
                $where .= " AND st.status = ?";
                $params[] = $filters['status'];
            }
            if (!empty($filters['training_type'])) {
                $where .= " AND st.training_type = ?";
                $params[] = $filters['training_type'];
            }
            if (!empty($filters['user_id'])) {
                $where .= " AND st.user_id = ?";
                $params[] = (int)$filters['user_id'];
            }

            $total = (int)Database::query(
                "SELECT COUNT(*) FROM vol_safeguarding_training st WHERE {$where}",
                $params
            )->fetchColumn();

            $items = Database::query(
                "SELECT st.*, u.name as user_name, u.avatar_url as user_avatar,
                        v.name as verified_by_name
                 FROM vol_safeguarding_training st
                 JOIN users u ON st.user_id = u.id
                 LEFT JOIN users v ON st.verified_by = v.id
                 WHERE {$where}
                 ORDER BY st.created_at DESC
                 LIMIT {$perPage} OFFSET {$offset}",
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ];
        } catch (\Exception $e) {
            error_log("SafeguardingService::getTrainingForAdmin error: " . $e->getMessage());
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }
    }

    /**
     * Check if a user has valid (verified, not expired) training of a given type
     */
    public static function checkTrainingCompliance(int $userId, string $trainingType): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $count = (int)Database::query(
                "SELECT COUNT(*) FROM vol_safeguarding_training
                 WHERE user_id = ? AND tenant_id = ? AND training_type = ?
                   AND status = 'verified'
                   AND (expires_at IS NULL OR expires_at > NOW())",
                [$userId, $tenantId, $trainingType]
            )->fetchColumn();

            return $count > 0;
        } catch (\Exception $e) {
            error_log("SafeguardingService::checkTrainingCompliance error: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // SAFEGUARDING INCIDENTS
    // =========================================================================

    /**
     * Report a safeguarding incident
     *
     * @param int $reportedBy User ID of reporter
     * @param array $data [title, description, severity, incident_type, incident_date, involved_user_id, organization_id, shift_id, category]
     * @return array The created incident record
     */
    public static function reportIncident(int $reportedBy, array $data): array
    {
        $tenantId = TenantContext::getId();

        // Validate severity ENUM
        $validSeverities = ['low', 'medium', 'high', 'critical'];
        $severity = $data['severity'] ?? 'medium';
        if (!in_array($severity, $validSeverities, true)) {
            throw new \InvalidArgumentException(
                "Invalid severity '{$severity}'. Must be one of: " . implode(', ', $validSeverities)
            );
        }

        // Validate incident_type ENUM
        $validIncidentTypes = ['concern', 'allegation', 'disclosure', 'near_miss', 'other'];
        $incidentType = $data['incident_type'] ?? 'other';
        if (!in_array($incidentType, $validIncidentTypes, true)) {
            throw new \InvalidArgumentException(
                "Invalid incident_type '{$incidentType}'. Must be one of: " . implode(', ', $validIncidentTypes)
            );
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO vol_safeguarding_incidents
                    (tenant_id, reported_by, title, description, severity, incident_type, incident_date,
                     involved_user_id, organization_id, shift_id, category, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())"
            );
            $stmt->execute([
                $tenantId,
                $reportedBy,
                $data['title'] ?? '',
                $data['description'] ?? '',
                $severity,
                $incidentType,
                $data['incident_date'] ?? date('Y-m-d'),
                $data['involved_user_id'] ?? null,
                $data['organization_id'] ?? null,
                $data['shift_id'] ?? null,
                $data['category'] ?? 'general',
            ]);

            $id = (int)$db->lastInsertId();

            // Notify DLP if incident is linked to an organization
            if (!empty($data['organization_id'])) {
                $dlpInfo = self::getDlpForOrg((int)$data['organization_id']);
                if ($dlpInfo && !empty($dlpInfo['dlp_user_id'])) {
                    Notification::create(
                        (int)$dlpInfo['dlp_user_id'],
                        'A safeguarding incident has been reported for your organization. Please review.',
                        '/admin/safeguarding/incidents/' . $id,
                        'safeguarding_incident'
                    );
                }
                if ($dlpInfo && !empty($dlpInfo['deputy_dlp_user_id'])) {
                    Notification::create(
                        (int)$dlpInfo['deputy_dlp_user_id'],
                        'A safeguarding incident has been reported for your organization. Please review.',
                        '/admin/safeguarding/incidents/' . $id,
                        'safeguarding_incident'
                    );
                }
            }

            $record = Database::query(
                "SELECT * FROM vol_safeguarding_incidents WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            return $record ?: [];
        } catch (\Exception $e) {
            error_log("SafeguardingService::reportIncident error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update a safeguarding incident
     *
     * @param int $id Incident ID
     * @param array $data [status, action_taken, resolution_notes, assigned_to, severity]
     */
    public static function updateIncident(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $sets = [];
            $params = [];

            $allowedFields = ['status', 'action_taken', 'resolution_notes', 'assigned_to', 'severity'];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $sets[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($sets)) {
                return false;
            }

            // If resolving, set resolved_at
            if (isset($data['status']) && in_array($data['status'], ['resolved', 'closed'])) {
                $sets[] = "resolved_at = NOW()";
            }

            $sets[] = "updated_at = NOW()";
            $params[] = $id;
            $params[] = $tenantId;

            $sql = "UPDATE vol_safeguarding_incidents SET " . implode(', ', $sets)
                 . " WHERE id = ? AND tenant_id = ?";

            Database::query($sql, $params);
            return true;
        } catch (\Exception $e) {
            error_log("SafeguardingService::updateIncident error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get safeguarding incidents with filters and pagination
     *
     * @param array $filters [status, severity, organization_id, shift_id, page, per_page]
     * @return array ['items' => [], 'total' => int, 'page' => int, 'per_page' => int]
     */
    public static function getIncidents(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int)($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        try {
            $where = "si.tenant_id = ?";
            $params = [$tenantId];

            if (!empty($filters['status'])) {
                $where .= " AND si.status = ?";
                $params[] = $filters['status'];
            }
            if (!empty($filters['severity'])) {
                $where .= " AND si.severity = ?";
                $params[] = $filters['severity'];
            }
            if (!empty($filters['organization_id'])) {
                $where .= " AND si.organization_id = ?";
                $params[] = (int)$filters['organization_id'];
            }
            if (!empty($filters['shift_id'])) {
                $where .= " AND si.shift_id = ?";
                $params[] = (int)$filters['shift_id'];
            }
            if (!empty($filters['reported_by'])) {
                $where .= " AND si.reported_by = ?";
                $params[] = (int)$filters['reported_by'];
            }

            $total = (int)Database::query(
                "SELECT COUNT(*) FROM vol_safeguarding_incidents si WHERE {$where}",
                $params
            )->fetchColumn();

            $items = Database::query(
                "SELECT si.*, u.name as reported_by_name, u.avatar_url as reported_by_avatar,
                        iu.name as involved_user_name,
                        org.name as organization_name,
                        au.name as assigned_to_name
                 FROM vol_safeguarding_incidents si
                 JOIN users u ON si.reported_by = u.id
                 LEFT JOIN users iu ON si.involved_user_id = iu.id
                 LEFT JOIN vol_organizations org ON si.organization_id = org.id
                 LEFT JOIN users au ON si.assigned_to = au.id
                 WHERE {$where}
                 ORDER BY si.created_at DESC
                 LIMIT {$perPage} OFFSET {$offset}",
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ];
        } catch (\Exception $e) {
            error_log("SafeguardingService::getIncidents error: " . $e->getMessage());
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }
    }

    /**
     * Get a single safeguarding incident by ID
     */
    public static function getIncident(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $record = Database::query(
                "SELECT si.*, u.name as reported_by_name, u.avatar_url as reported_by_avatar,
                        iu.name as involved_user_name, iu.avatar_url as involved_user_avatar,
                        org.name as organization_name,
                        au.name as assigned_to_name
                 FROM vol_safeguarding_incidents si
                 JOIN users u ON si.reported_by = u.id
                 LEFT JOIN users iu ON si.involved_user_id = iu.id
                 LEFT JOIN vol_organizations org ON si.organization_id = org.id
                 LEFT JOIN users au ON si.assigned_to = au.id
                 WHERE si.id = ? AND si.tenant_id = ?",
                [$id, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            return $record ?: null;
        } catch (\Exception $e) {
            error_log("SafeguardingService::getIncident error: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // DESIGNATED LIAISON PERSON (DLP)
    // =========================================================================

    /**
     * Assign a DLP and optional deputy to an organization
     */
    public static function assignDlp(int $organizationId, int $dlpUserId, ?int $deputyDlpUserId = null): bool
    {
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "UPDATE vol_organizations
                 SET dlp_user_id = ?, deputy_dlp_user_id = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$dlpUserId, $deputyDlpUserId, $organizationId, $tenantId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("SafeguardingService::assignDlp error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get DLP and deputy info for an organization
     */
    public static function getDlpForOrg(int $organizationId): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $record = Database::query(
                "SELECT org.dlp_user_id, org.deputy_dlp_user_id,
                        dlp.name as dlp_name, dlp.email as dlp_email, dlp.avatar_url as dlp_avatar,
                        ddlp.name as deputy_dlp_name, ddlp.email as deputy_dlp_email, ddlp.avatar_url as deputy_dlp_avatar
                 FROM vol_organizations org
                 LEFT JOIN users dlp ON org.dlp_user_id = dlp.id
                 LEFT JOIN users ddlp ON org.deputy_dlp_user_id = ddlp.id
                 WHERE org.id = ? AND org.tenant_id = ?",
                [$organizationId, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            return $record ?: null;
        } catch (\Exception $e) {
            error_log("SafeguardingService::getDlpForOrg error: " . $e->getMessage());
            return null;
        }
    }
}
