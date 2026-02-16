<?php

declare(strict_types=1);

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\Auth;
use Nexus\Core\Mailer;
use Nexus\Core\EmailTemplate;

/**
 * LegalDocumentService
 *
 * Manages legal documents (Terms of Service, Privacy Policy, etc.) with:
 * - Version control and history
 * - User acceptance tracking for GDPR compliance
 * - Audit trail for regulatory review
 *
 * @package Nexus\Services
 */
class LegalDocumentService
{
    // Document types
    public const TYPE_TERMS = 'terms';
    public const TYPE_PRIVACY = 'privacy';
    public const TYPE_COOKIES = 'cookies';
    public const TYPE_ACCESSIBILITY = 'accessibility';
    public const TYPE_COMMUNITY_GUIDELINES = 'community_guidelines';
    public const TYPE_ACCEPTABLE_USE = 'acceptable_use';

    // Acceptance methods
    public const ACCEPTANCE_REGISTRATION = 'registration';
    public const ACCEPTANCE_LOGIN_PROMPT = 'login_prompt';
    public const ACCEPTANCE_SETTINGS = 'settings';
    public const ACCEPTANCE_API = 'api';
    public const ACCEPTANCE_FORCED_UPDATE = 'forced_update';

    // Acceptance statuses
    public const STATUS_NOT_ACCEPTED = 'not_accepted';
    public const STATUS_CURRENT = 'current';
    public const STATUS_OUTDATED = 'outdated';

    /**
     * Get a legal document by type for current tenant
     */
    public static function getByType(string $type): ?array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT ld.*, ldv.version_number, ldv.content, ldv.effective_date, ldv.summary_of_changes
             FROM legal_documents ld
             LEFT JOIN legal_document_versions ldv ON ld.current_version_id = ldv.id
             WHERE ld.tenant_id = ? AND ld.document_type = ? AND ld.is_active = 1",
            [$tenantId, $type]
        );

        return $stmt->fetch() ?: null;
    }

    /**
     * Get a legal document by ID
     */
    public static function getById(int $id): ?array
    {
        $stmt = Database::query(
            "SELECT ld.*, ldv.version_number, ldv.content, ldv.effective_date, ldv.summary_of_changes
             FROM legal_documents ld
             LEFT JOIN legal_document_versions ldv ON ld.current_version_id = ldv.id
             WHERE ld.id = ?",
            [$id]
        );

        return $stmt->fetch() ?: null;
    }

    /**
     * Get all active legal documents for current tenant
     */
    public static function getAllForTenant(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $stmt = Database::query(
            "SELECT ld.*, ldv.version_number, ldv.effective_date,
                    (SELECT COUNT(*) FROM legal_document_versions WHERE document_id = ld.id) as version_count
             FROM legal_documents ld
             LEFT JOIN legal_document_versions ldv ON ld.current_version_id = ldv.id
             WHERE ld.tenant_id = ? AND ld.is_active = 1
             ORDER BY ld.document_type",
            [$tenantId]
        );

        return $stmt->fetchAll();
    }

    /**
     * Get all versions for a document
     */
    public static function getVersions(int $documentId): array
    {
        $stmt = Database::query(
            "SELECT ldv.*, u.name as created_by_name, u2.name as published_by_name
             FROM legal_document_versions ldv
             LEFT JOIN users u ON ldv.created_by = u.id
             LEFT JOIN users u2 ON ldv.published_by = u2.id
             WHERE ldv.document_id = ?
             ORDER BY ldv.created_at DESC",
            [$documentId]
        );

        return $stmt->fetchAll();
    }

    /**
     * Get a specific version
     */
    public static function getVersion(int $versionId): ?array
    {
        $stmt = Database::query(
            "SELECT ldv.*, ld.document_type, ld.title, ld.tenant_id
             FROM legal_document_versions ldv
             JOIN legal_documents ld ON ldv.document_id = ld.id
             WHERE ldv.id = ?",
            [$versionId]
        );

        return $stmt->fetch() ?: null;
    }

    /**
     * Get current version for a document
     */
    public static function getCurrentVersion(int $documentId): ?array
    {
        $stmt = Database::query(
            "SELECT ldv.*
             FROM legal_document_versions ldv
             WHERE ldv.document_id = ? AND ldv.is_current = 1",
            [$documentId]
        );

        return $stmt->fetch() ?: null;
    }

    /**
     * Create a new legal document
     */
    public static function createDocument(array $data): int
    {
        $tenantId = $data['tenant_id'] ?? TenantContext::getId();
        $userId = Auth::id();

        Database::query(
            "INSERT INTO legal_documents
             (tenant_id, document_type, title, slug, requires_acceptance, acceptance_required_for, notify_on_update, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tenantId,
                $data['document_type'],
                $data['title'],
                $data['slug'] ?? $data['document_type'],
                $data['requires_acceptance'] ?? 1,
                $data['acceptance_required_for'] ?? 'registration',
                $data['notify_on_update'] ?? 1,
                $data['is_active'] ?? 1,
                $userId
            ]
        );

        return (int) Database::lastInsertId();
    }

    /**
     * Update a legal document
     */
    public static function updateDocument(int $id, array $data): bool
    {
        $sets = [];
        $params = [];

        $allowedFields = ['title', 'slug', 'requires_acceptance', 'acceptance_required_for', 'notify_on_update', 'is_active'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $params[] = $id;

        Database::query(
            "UPDATE legal_documents SET " . implode(', ', $sets) . " WHERE id = ?",
            $params
        );

        return true;
    }

    /**
     * Create a new version for a document
     */
    public static function createVersion(int $documentId, array $data): int
    {
        $userId = Auth::id();

        // Generate plain text version from HTML
        $plainText = !empty($data['content']) ? strip_tags($data['content']) : null;

        Database::query(
            "INSERT INTO legal_document_versions
             (document_id, version_number, version_label, content, content_plain, summary_of_changes, effective_date, is_draft, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $documentId,
                $data['version_number'],
                $data['version_label'] ?? null,
                $data['content'],
                $plainText,
                $data['summary_of_changes'] ?? null,
                $data['effective_date'],
                $data['is_draft'] ?? 1,
                $userId
            ]
        );

        return (int) Database::lastInsertId();
    }

    /**
     * Update a version
     */
    public static function updateVersion(int $versionId, array $data): bool
    {
        $sets = [];
        $params = [];

        $allowedFields = ['version_number', 'version_label', 'content', 'summary_of_changes', 'effective_date', 'is_draft'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        // Update plain text if content changed
        if (isset($data['content'])) {
            $sets[] = "content_plain = ?";
            $params[] = strip_tags($data['content']);
        }

        if (empty($sets)) {
            return false;
        }

        $params[] = $versionId;

        Database::query(
            "UPDATE legal_document_versions SET " . implode(', ', $sets) . " WHERE id = ?",
            $params
        );

        return true;
    }

    /**
     * Publish a version (make it the current version)
     *
     * This also syncs with the GDPR consent system so users will be
     * prompted to re-accept if their previous acceptance is outdated.
     */
    public static function publishVersion(int $versionId): bool
    {
        $version = self::getVersion($versionId);
        if (!$version) {
            return false;
        }

        $userId = Auth::id();

        // Start transaction
        Database::beginTransaction();

        try {
            // Unset current flag on all other versions
            Database::query(
                "UPDATE legal_document_versions SET is_current = 0 WHERE document_id = ?",
                [$version['document_id']]
            );

            // Set this version as current and published
            Database::query(
                "UPDATE legal_document_versions
                 SET is_current = 1, is_draft = 0, published_at = NOW(), published_by = ?
                 WHERE id = ?",
                [$userId, $versionId]
            );

            // Update document's current version pointer
            Database::query(
                "UPDATE legal_documents SET current_version_id = ? WHERE id = ?",
                [$versionId, $version['document_id']]
            );

            // Sync with GDPR consent system - update tenant_consent_overrides
            // This triggers re-consent prompts for users with outdated acceptances
            self::syncWithConsentSystem($version['document_id'], $version);

            Database::commit();
            return true;
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Sync legal document version with GDPR consent system
     *
     * Updates tenant_consent_overrides so that users who have accepted
     * an older version will be prompted to re-accept via the existing
     * consent_check.php middleware.
     */
    private static function syncWithConsentSystem(int $documentId, array $version): void
    {
        $document = self::getById($documentId);
        if (!$document) {
            return;
        }

        $tenantId = $document['tenant_id'];
        $documentType = $document['document_type'];
        $versionNumber = $version['version_number'];
        $content = $version['content'] ?? '';

        // Map document types to consent type slugs
        $consentSlugMap = [
            self::TYPE_TERMS => 'terms_of_service',
            self::TYPE_PRIVACY => 'privacy_policy',
            self::TYPE_COOKIES => 'cookie_policy',
            self::TYPE_COMMUNITY_GUIDELINES => 'community_guidelines',
            self::TYPE_ACCEPTABLE_USE => 'acceptable_use',
        ];

        $consentSlug = $consentSlugMap[$documentType] ?? null;
        if (!$consentSlug) {
            return; // Document type doesn't map to a consent type
        }

        // Check if consent_types table exists and has this slug
        try {
            $consentType = Database::query(
                "SELECT id, slug FROM consent_types WHERE slug = ?",
                [$consentSlug]
            )->fetch();

            if (!$consentType) {
                // Create the consent type if it doesn't exist
                Database::query(
                    "INSERT INTO consent_types (slug, name, description, category, is_required, is_active, current_version, current_text, display_order)
                     VALUES (?, ?, ?, 'legal', 1, 1, ?, ?, 10)
                     ON DUPLICATE KEY UPDATE current_version = VALUES(current_version), current_text = VALUES(current_text)",
                    [$consentSlug, $document['title'], $document['title'] . ' document', $versionNumber, $content]
                );
            }

            // Update or create tenant-specific override
            Database::query(
                "INSERT INTO tenant_consent_overrides (tenant_id, consent_type_slug, current_version, current_text, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    current_version = VALUES(current_version),
                    current_text = VALUES(current_text),
                    is_active = 1,
                    updated_at = NOW()",
                [$tenantId, $consentSlug, $versionNumber, $content]
            );

            // Log the sync
            error_log("[LegalDocumentService] Synced {$documentType} v{$versionNumber} with consent system for tenant {$tenantId}");

        } catch (\Exception $e) {
            // Log but don't fail - consent system might not be set up yet
            error_log("[LegalDocumentService] Warning: Could not sync with consent system: " . $e->getMessage());
        }
    }

    /**
     * Sync user acceptance to GDPR consent system (user_consents table)
     */
    private static function syncAcceptanceWithConsent(
        int $userId,
        int $documentId,
        string $versionNumber,
        ?string $ipAddress = null
    ): void {
        $document = self::getById($documentId);
        if (!$document) {
            return;
        }

        $documentType = $document['document_type'];

        // Map document types to consent type slugs
        $consentSlugMap = [
            self::TYPE_TERMS => 'terms_of_service',
            self::TYPE_PRIVACY => 'privacy_policy',
            self::TYPE_COOKIES => 'cookie_policy',
            self::TYPE_COMMUNITY_GUIDELINES => 'community_guidelines',
            self::TYPE_ACCEPTABLE_USE => 'acceptable_use',
        ];

        $consentSlug = $consentSlugMap[$documentType] ?? null;
        if (!$consentSlug) {
            return; // Document type doesn't map to a consent type
        }

        try {
            // Check if consent type exists
            $consentType = Database::query(
                "SELECT id FROM consent_types WHERE slug = ?",
                [$consentSlug]
            )->fetch();

            if (!$consentType) {
                return; // Consent type not set up yet
            }

            // Record acceptance in user_consents table
            Database::query(
                "INSERT INTO user_consents (user_id, consent_type_id, version_accepted, is_accepted, ip_address, accepted_at)
                 VALUES (?, ?, ?, 1, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    version_accepted = VALUES(version_accepted),
                    is_accepted = 1,
                    ip_address = VALUES(ip_address),
                    accepted_at = NOW()",
                [$userId, $consentType['id'], $versionNumber, $ipAddress]
            );

            error_log("[LegalDocumentService] Synced user {$userId} acceptance of {$documentType} v{$versionNumber} to consent system");

        } catch (\Exception $e) {
            // Log but don't fail
            error_log("[LegalDocumentService] Warning: Could not sync acceptance with consent system: " . $e->getMessage());
        }
    }

    /**
     * Delete a version (only drafts can be deleted)
     */
    public static function deleteVersion(int $versionId): bool
    {
        $version = self::getVersion($versionId);
        if (!$version || !$version['is_draft']) {
            return false; // Can only delete drafts
        }

        Database::query("DELETE FROM legal_document_versions WHERE id = ?", [$versionId]);
        return true;
    }

    // =========================================================================
    // USER ACCEPTANCE TRACKING
    // =========================================================================

    /**
     * Record user acceptance of a document version
     */
    public static function recordAcceptance(
        int $userId,
        int $documentId,
        int $versionId,
        string $method = self::ACCEPTANCE_REGISTRATION,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $additionalContext = null
    ): int {
        // Get version number
        $version = self::getVersion($versionId);
        $versionNumber = $version['version_number'] ?? 'unknown';

        // Get session ID
        $sessionId = session_id() ?: null;

        Database::query(
            "INSERT INTO user_legal_acceptances
             (user_id, document_id, version_id, version_number, acceptance_method, ip_address, user_agent, session_id, additional_context)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
             accepted_at = NOW(), acceptance_method = VALUES(acceptance_method), ip_address = VALUES(ip_address),
             user_agent = VALUES(user_agent), session_id = VALUES(session_id), additional_context = VALUES(additional_context)",
            [
                $userId,
                $documentId,
                $versionId,
                $versionNumber,
                $method,
                $ipAddress,
                $userAgent,
                $sessionId,
                $additionalContext ? json_encode($additionalContext) : null
            ]
        );

        $insertId = (int) Database::lastInsertId();

        // Sync with GDPR consent system
        self::syncAcceptanceWithConsent($userId, $documentId, $versionNumber, $ipAddress);

        return $insertId;
    }

    /**
     * Record acceptance from current request context
     */
    public static function recordAcceptanceFromRequest(
        int $userId,
        int $documentId,
        int $versionId,
        string $method = self::ACCEPTANCE_REGISTRATION
    ): int {
        return self::recordAcceptance(
            $userId,
            $documentId,
            $versionId,
            $method,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );
    }

    /**
     * Check if user has accepted current version of a document
     */
    public static function hasAcceptedCurrent(int $userId, string $documentType): bool
    {
        $document = self::getByType($documentType);
        if (!$document || !$document['current_version_id']) {
            return true; // No document or version = nothing to accept
        }

        $stmt = Database::query(
            "SELECT id FROM user_legal_acceptances
             WHERE user_id = ? AND version_id = ?",
            [$userId, $document['current_version_id']]
        );

        return (bool) $stmt->fetch();
    }

    /**
     * Get user's acceptance status for all required documents
     */
    public static function getUserAcceptanceStatus(int $userId, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $stmt = Database::query(
            "SELECT
                ld.id AS document_id,
                ld.document_type,
                ld.title,
                ld.requires_acceptance,
                ld.current_version_id,
                ldv.version_number AS current_version,
                ldv.effective_date,
                ula.id AS acceptance_id,
                ula.version_id AS accepted_version_id,
                ula.version_number AS accepted_version,
                ula.accepted_at,
                CASE
                    WHEN ula.version_id IS NULL THEN 'not_accepted'
                    WHEN ula.version_id = ld.current_version_id THEN 'current'
                    ELSE 'outdated'
                END AS acceptance_status
             FROM legal_documents ld
             LEFT JOIN legal_document_versions ldv ON ld.current_version_id = ldv.id
             LEFT JOIN user_legal_acceptances ula ON ula.user_id = ?
                 AND ula.document_id = ld.id
                 AND ula.version_id = (
                     SELECT MAX(ula2.version_id)
                     FROM user_legal_acceptances ula2
                     WHERE ula2.user_id = ? AND ula2.document_id = ld.id
                 )
             WHERE ld.tenant_id = ?
             AND ld.is_active = 1
             AND ld.requires_acceptance = 1",
            [$userId, $userId, $tenantId]
        );

        return $stmt->fetchAll();
    }

    /**
     * Get list of documents user needs to accept
     */
    public static function getDocumentsRequiringAcceptance(int $userId, ?int $tenantId = null): array
    {
        $statuses = self::getUserAcceptanceStatus($userId, $tenantId);

        return array_filter($statuses, function ($doc) {
            return $doc['acceptance_status'] !== self::STATUS_CURRENT;
        });
    }

    /**
     * Check if user has any pending acceptances
     */
    public static function hasPendingAcceptances(int $userId, ?int $tenantId = null): bool
    {
        $pending = self::getDocumentsRequiringAcceptance($userId, $tenantId);
        return !empty($pending);
    }

    /**
     * Get acceptance history for a user
     */
    public static function getUserAcceptanceHistory(int $userId): array
    {
        $stmt = Database::query(
            "SELECT ula.*, ld.document_type, ld.title, ldv.version_label
             FROM user_legal_acceptances ula
             JOIN legal_documents ld ON ula.document_id = ld.id
             JOIN legal_document_versions ldv ON ula.version_id = ldv.id
             WHERE ula.user_id = ?
             ORDER BY ula.accepted_at DESC",
            [$userId]
        );

        return $stmt->fetchAll();
    }

    /**
     * Get all acceptances for a document version (for admin/audit)
     */
    public static function getVersionAcceptances(int $versionId, int $limit = 100, int $offset = 0): array
    {
        $stmt = Database::query(
            "SELECT ula.*, u.name as user_name, u.email as user_email
             FROM user_legal_acceptances ula
             JOIN users u ON ula.user_id = u.id
             WHERE ula.version_id = ?
             ORDER BY ula.accepted_at DESC
             LIMIT ? OFFSET ?",
            [$versionId, $limit, $offset]
        );

        return $stmt->fetchAll();
    }

    /**
     * Count total acceptances for a version
     */
    public static function countVersionAcceptances(int $versionId): int
    {
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM user_legal_acceptances WHERE version_id = ?",
            [$versionId]
        );

        $result = $stmt->fetch();
        return (int) ($result['count'] ?? 0);
    }

    // =========================================================================
    // STATISTICS & REPORTING
    // =========================================================================

    /**
     * Get acceptance statistics for a document
     */
    public static function getDocumentStats(int $documentId): array
    {
        $stmt = Database::query(
            "SELECT
                ldv.id as version_id,
                ldv.version_number,
                ldv.effective_date,
                ldv.is_current,
                COUNT(DISTINCT ula.user_id) as total_acceptances,
                MIN(ula.accepted_at) as first_acceptance,
                MAX(ula.accepted_at) as last_acceptance
             FROM legal_document_versions ldv
             LEFT JOIN user_legal_acceptances ula ON ula.version_id = ldv.id
             WHERE ldv.document_id = ?
             GROUP BY ldv.id, ldv.version_number, ldv.effective_date, ldv.is_current
             ORDER BY ldv.effective_date DESC",
            [$documentId]
        );

        return $stmt->fetchAll();
    }

    /**
     * Get compliance summary for tenant
     */
    public static function getComplianceSummary(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // Get total active users for this tenant
        $userStmt = Database::query(
            "SELECT COUNT(*) as count FROM users WHERE tenant_id = ? AND status = 'active'",
            [$tenantId]
        );
        $totalUsers = (int) ($userStmt->fetch()['count'] ?? 0);

        // Get acceptance stats per document
        $stmt = Database::query(
            "SELECT
                ld.id,
                ld.document_type,
                ld.title,
                ld.current_version_id,
                ldv.version_number,
                ldv.effective_date,
                COUNT(DISTINCT ula.user_id) as users_accepted
             FROM legal_documents ld
             LEFT JOIN legal_document_versions ldv ON ld.current_version_id = ldv.id
             LEFT JOIN user_legal_acceptances ula ON ula.version_id = ld.current_version_id
             WHERE ld.tenant_id = ?
             AND ld.is_active = 1
             AND ld.requires_acceptance = 1
             GROUP BY ld.id, ld.document_type, ld.title, ld.current_version_id, ldv.version_number, ldv.effective_date",
            [$tenantId]
        );

        $documents = $stmt->fetchAll();

        // Calculate per-document stats
        $totalAccepted = 0;
        $documentCount = count($documents);

        $documents = array_map(function ($doc) use ($totalUsers, &$totalAccepted) {
            $usersAccepted = (int) $doc['users_accepted'];
            $totalAccepted += $usersAccepted;

            $doc['users_not_accepted'] = $totalUsers - $usersAccepted;
            $doc['acceptance_rate'] = $totalUsers > 0
                ? round(($usersAccepted / $totalUsers) * 100, 1)
                : 0;
            return $doc;
        }, $documents);

        // Calculate overall compliance (average acceptance rate across all required docs)
        $overallComplianceRate = 0;
        $usersPendingAcceptance = 0;

        if ($documentCount > 0 && $totalUsers > 0) {
            $overallComplianceRate = round(($totalAccepted / ($totalUsers * $documentCount)) * 100, 1);
            // Users who haven't accepted at least one document
            $usersPendingAcceptance = max(0, $totalUsers - (int) ($totalAccepted / max(1, $documentCount)));
        }

        return [
            'total_users' => $totalUsers,
            'overall_compliance_rate' => $overallComplianceRate,
            'users_pending_acceptance' => $usersPendingAcceptance,
            'documents' => $documents
        ];
    }

    /**
     * Export acceptance records for compliance audit
     */
    public static function exportAcceptanceRecords(
        int $documentId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $params = [$documentId];
        $dateFilter = "";

        if ($startDate) {
            $dateFilter .= " AND ula.accepted_at >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $dateFilter .= " AND ula.accepted_at <= ?";
            $params[] = $endDate;
        }

        $stmt = Database::query(
            "SELECT
                ula.id as acceptance_id,
                u.id as user_id,
                u.name as user_name,
                u.email as user_email,
                ldv.version_number,
                ula.accepted_at,
                ula.acceptance_method,
                ula.ip_address
             FROM user_legal_acceptances ula
             JOIN users u ON ula.user_id = u.id
             JOIN legal_document_versions ldv ON ula.version_id = ldv.id
             WHERE ula.document_id = ? $dateFilter
             ORDER BY ula.accepted_at DESC",
            $params
        );

        return $stmt->fetchAll();
    }

    // =========================================================================
    // NOTIFICATIONS
    // =========================================================================

    /**
     * Mark notification as sent for a version
     */
    public static function markNotificationSent(int $versionId): bool
    {
        Database::query(
            "UPDATE legal_document_versions
             SET notification_sent = 1, notification_sent_at = NOW()
             WHERE id = ?",
            [$versionId]
        );

        return true;
    }

    /**
     * Get users who need to be notified about a document update
     */
    public static function getUsersToNotify(int $documentId): array
    {
        $document = self::getById($documentId);
        if (!$document || !$document['notify_on_update']) {
            return [];
        }

        $stmt = Database::query(
            "SELECT DISTINCT u.id, u.name, u.email
             FROM users u
             WHERE u.tenant_id = ?
             AND u.status = 'active'
             AND u.email_notifications = 1",
            [$document['tenant_id']]
        );

        return $stmt->fetchAll();
    }

    // =========================================================================
    // EMAIL NOTIFICATIONS
    // =========================================================================

    /**
     * Send notification emails about document update to users who haven't accepted
     *
     * @param int $documentId The document that was updated
     * @param int $versionId The newly published version
     * @param bool $immediate If true, send now; if false, queue for batch
     * @return int Number of emails sent/queued
     */
    public static function notifyUsersOfUpdate(int $documentId, int $versionId, bool $immediate = false): int
    {
        $document = self::getById($documentId);
        $version = self::getVersion($versionId);

        if (!$document || !$version) {
            return 0;
        }

        // Only notify for documents that require acceptance
        if (!$document['requires_acceptance']) {
            return 0;
        }

        $tenantId = $document['tenant_id'];
        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Project NEXUS';

        // Get users who need to re-accept (have accepted old version or never accepted)
        $stmt = Database::query(
            "SELECT u.id, u.name, u.email
             FROM users u
             WHERE u.tenant_id = ?
             AND u.status = 'active'
             AND u.email_notifications = 1
             AND NOT EXISTS (
                 SELECT 1 FROM user_legal_acceptances ula
                 WHERE ula.user_id = u.id AND ula.version_id = ?
             )",
            [$tenantId, $versionId]
        );

        $users = $stmt->fetchAll();
        $sentCount = 0;

        if (empty($users)) {
            return 0;
        }

        // Build email content
        $frontendUrl = \Nexus\Core\TenantContext::getFrontendUrl();
        $basePath = TenantContext::getBasePath();
        $documentUrl = $frontendUrl . $basePath . '/' . $document['slug'];

        $subject = "Updated {$document['title']} - Action Required";

        $body = "<p>We've updated our <strong>{$document['title']}</strong> to version {$version['version_number']}.</p>";

        if ($version['summary_of_changes']) {
            $body .= "<p><strong>Summary of changes:</strong></p>";
            $body .= "<p>" . nl2br(htmlspecialchars($version['summary_of_changes'])) . "</p>";
        }

        $body .= "<p>The updated document is effective from <strong>" . date('F j, Y', strtotime($version['effective_date'])) . "</strong>.</p>";
        $body .= "<p>Please review and accept the updated document to continue using our services.</p>";

        if ($immediate) {
            // Send immediately
            $mailer = new Mailer();

            foreach ($users as $user) {
                try {
                    $html = EmailTemplate::render(
                        "Updated {$document['title']}",
                        "Please review the updated document",
                        $body,
                        'Review & Accept',
                        $documentUrl,
                        $tenantName
                    );

                    $mailer->sendMail($user['email'], $subject, $html);
                    $sentCount++;
                } catch (\Throwable $e) {
                    error_log("[LegalDocumentService] Failed to send update notification to {$user['email']}: " . $e->getMessage());
                }
            }
        } else {
            // Queue for batch sending (store in notifications table for digest)
            foreach ($users as $user) {
                try {
                    Database::query(
                        "INSERT INTO notifications (user_id, type, title, message, link, created_at)
                         VALUES (?, 'legal_update', ?, ?, ?, NOW())",
                        [
                            $user['id'],
                            "Updated: {$document['title']}",
                            "Please review version {$version['version_number']} of our {$document['title']}",
                            $documentUrl
                        ]
                    );
                    $sentCount++;
                } catch (\Throwable $e) {
                    error_log("[LegalDocumentService] Failed to queue notification for user {$user['id']}: " . $e->getMessage());
                }
            }
        }

        error_log("[LegalDocumentService] Notified {$sentCount} users about {$document['title']} v{$version['version_number']} update");

        return $sentCount;
    }

    /**
     * Get count of users who need to accept a document version
     */
    public static function getUsersPendingAcceptanceCount(int $documentId, int $versionId): int
    {
        $document = self::getById($documentId);
        if (!$document) {
            return 0;
        }

        $stmt = Database::query(
            "SELECT COUNT(*) as count
             FROM users u
             WHERE u.tenant_id = ?
             AND u.status = 'active'
             AND NOT EXISTS (
                 SELECT 1 FROM user_legal_acceptances ula
                 WHERE ula.user_id = u.id AND ula.version_id = ?
             )",
            [$document['tenant_id'], $versionId]
        );

        return (int) ($stmt->fetch()['count'] ?? 0);
    }
}
