<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Services\Enterprise;

use Nexus\Core\Database;
use Nexus\Services\Enterprise\LoggerService;
use Nexus\Services\Enterprise\MetricsService;
use ZipArchive;

/**
 * GDPR Compliance Service
 *
 * Handles all GDPR-related functionality including data export, deletion,
 * consent management, and audit logging.
 */
class GdprService
{
    private \PDO $db;
    private int $tenantId;
    private LoggerService $logger;
    private MetricsService $metrics;

    public function __construct(?int $tenantId = null)
    {
        $this->db = Database::getInstance();
        $this->tenantId = $tenantId ?? (int) ($_SESSION['tenant_id'] ?? 1);
        $this->logger = LoggerService::getInstance('gdpr');
        $this->metrics = MetricsService::getInstance();
    }

    /**
     * Execute a prepared statement
     */
    private function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Get last insert ID
     */
    private function lastInsertId(): int
    {
        return (int) $this->db->lastInsertId();
    }

    // =========================================================================
    // DATA SUBJECT REQUESTS
    // =========================================================================

    /**
     * Create a new GDPR request
     */
    public function createRequest(int $userId, string $type, array $options = []): array
    {
        // Validate request type
        $validTypes = ['access', 'erasure', 'rectification', 'restriction', 'portability', 'objection'];
        if (!in_array($type, $validTypes)) {
            throw new \InvalidArgumentException("Invalid request type: {$type}");
        }

        // Check for existing pending request of same type
        $existing = $this->query(
            "SELECT id FROM gdpr_requests
             WHERE user_id = ? AND request_type = ? AND tenant_id = ?
             AND status IN ('pending', 'processing')",
            [$userId, $type, $this->tenantId]
        )->fetch();

        if ($existing) {
            throw new \RuntimeException("You already have a pending {$type} request.");
        }

        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32));

        // Create request
        $this->query(
            "INSERT INTO gdpr_requests
             (user_id, tenant_id, request_type, status, priority, verification_token, notes, metadata)
             VALUES (?, ?, ?, 'pending', ?, ?, ?, ?)",
            [
                $userId,
                $this->tenantId,
                $type,
                $options['priority'] ?? 'normal',
                $verificationToken,
                $options['notes'] ?? null,
                json_encode($options['metadata'] ?? []),
            ]
        );

        $requestId = (int) $this->lastInsertId();

        // Log the action
        $this->logAction($userId, "{$type}_requested", 'gdpr_request', $requestId);

        // Track metric
        $this->metrics->increment('gdpr.request.created', ['type' => $type]);

        $this->logger->info("GDPR {$type} request created", [
            'request_id' => $requestId,
            'user_id' => $userId,
            'type' => $type,
        ]);

        return [
            'id' => $requestId,
            'type' => $type,
            'status' => 'pending',
            'verification_token' => $verificationToken,
        ];
    }

    /**
     * Get request by ID
     */
    public function getRequest(int $requestId): ?array
    {
        return $this->query(
            "SELECT * FROM gdpr_requests WHERE id = ? AND tenant_id = ?",
            [$requestId, $this->tenantId]
        )->fetch() ?: null;
    }

    /**
     * Get pending requests for admin
     */
    public function getPendingRequests(int $limit = 50, int $offset = 0): array
    {
        // LIMIT/OFFSET must be integers in SQL, not bound as string params
        $limit = (int) $limit;
        $offset = (int) $offset;

        return $this->query(
            "SELECT r.*, u.email, u.first_name, u.last_name
             FROM gdpr_requests r
             LEFT JOIN users u ON r.user_id = u.id
             WHERE r.tenant_id = ? AND r.status IN ('pending', 'processing')
             ORDER BY
                CASE r.priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    ELSE 3
                END,
                r.requested_at ASC
             LIMIT {$limit} OFFSET {$offset}",
            [$this->tenantId]
        )->fetchAll();
    }

    /**
     * Get user's requests
     */
    public function getUserRequests(int $userId): array
    {
        return $this->query(
            "SELECT id, request_type, status, requested_at, processed_at,
                    export_file_path, export_expires_at
             FROM gdpr_requests
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY requested_at DESC",
            [$userId, $this->tenantId]
        )->fetchAll();
    }

    /**
     * Process a request (admin action)
     */
    public function processRequest(int $requestId, int $adminId): bool
    {
        $request = $this->getRequest($requestId);
        if (!$request) {
            return false;
        }

        $this->query(
            "UPDATE gdpr_requests SET status = 'processing', acknowledged_at = NOW()
             WHERE id = ?",
            [$requestId]
        );

        $this->logAction($request['user_id'], 'request_processing_started', 'gdpr_request', $requestId, $adminId);

        return true;
    }

    // =========================================================================
    // DATA EXPORT (Article 15 - Right of Access)
    // =========================================================================

    /**
     * Generate data export for a user
     */
    public function generateDataExport(int $userId, int $requestId = null): string
    {
        $this->logger->info("Starting data export", ['user_id' => $userId]);

        // Collect all user data
        $data = $this->collectUserData($userId);

        // Create export directory
        $exportDir = $this->getStoragePath("exports/gdpr/{$userId}_" . time());
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        // Create JSON export
        $jsonPath = "{$exportDir}/data.json";
        file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Create HTML export
        $htmlPath = "{$exportDir}/data.html";
        file_put_contents($htmlPath, $this->generateHtmlExport($data));

        // Create README
        $readmePath = "{$exportDir}/README.txt";
        file_put_contents($readmePath, $this->generateExportReadme($data));

        // Copy user uploads
        $this->copyUserUploads($userId, $exportDir);

        // Create ZIP archive
        $timestamp = date('Ymd_His');
        $zipFilename = "nexus_data_export_{$userId}_{$timestamp}.zip";
        $zipPath = $this->getStoragePath("exports/{$zipFilename}");

        $this->createZipArchive($exportDir, $zipPath);

        // Cleanup temp directory
        $this->deleteDirectory($exportDir);

        // Update request if provided
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

        if ($requestId) {
            $this->query(
                "UPDATE gdpr_requests
                 SET status = 'completed', processed_at = NOW(),
                     export_file_path = ?, export_expires_at = ?
                 WHERE id = ?",
                [$zipPath, $expiresAt, $requestId]
            );
        }

        // Log action
        $this->logAction($userId, 'data_exported', 'gdpr_export', null, null, null, [
            'file_size' => filesize($zipPath),
            'expires_at' => $expiresAt,
        ]);

        $this->metrics->increment('gdpr.export.completed');
        $this->metrics->histogram('gdpr.export.file_size', filesize($zipPath));

        $this->logger->info("Data export completed", [
            'user_id' => $userId,
            'file_path' => $zipPath,
            'file_size' => filesize($zipPath),
        ]);

        return $zipPath;
    }

    /**
     * Collect all user data
     */
    private function collectUserData(int $userId): array
    {
        return [
            'export_info' => [
                'generated_at' => date('c'),
                'user_id' => $userId,
                'platform' => 'Project NEXUS',
                'format_version' => '1.0',
                'tenant_id' => $this->tenantId,
            ],
            'profile' => $this->getProfileData($userId),
            'listings' => $this->getListingsData($userId),
            'messages' => $this->getMessagesData($userId),
            'transactions' => $this->getTransactionsData($userId),
            'events' => $this->getEventsData($userId),
            'groups' => $this->getGroupsData($userId),
            'volunteering' => $this->getVolunteeringData($userId),
            'gamification' => $this->getGamificationData($userId),
            'activity_log' => $this->getActivityLogData($userId),
            'consents' => $this->getConsentsData($userId),
            'notifications' => $this->getNotificationsData($userId),
            'connections' => $this->getConnectionsData($userId),
            'login_history' => $this->getLoginHistoryData($userId),
        ];
    }

    private function getProfileData(int $userId): ?array
    {
        return $this->query(
            "SELECT id, email, first_name, last_name, phone, bio,
                    skills, interests, location, latitude, longitude,
                    profile_picture, cover_image, website, social_links,
                    timezone, locale, created_at, updated_at, last_login,
                    is_verified
             FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $this->tenantId]
        )->fetch() ?: null;
    }

    private function getListingsData(int $userId): array
    {
        return $this->query(
            "SELECT id, title, description, type, category_id, subcategory_id,
                    time_credits, location, latitude, longitude, status,
                    views_count, created_at, updated_at
             FROM listings WHERE user_id = ? AND tenant_id = ?",
            [$userId, $this->tenantId]
        )->fetchAll();
    }

    private function getMessagesData(int $userId): array
    {
        return $this->query(
            "SELECT m.id, m.content, m.created_at, m.read_at,
                    CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as direction,
                    CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END as other_user_id
             FROM messages m
             WHERE (m.sender_id = ? OR m.receiver_id = ?) AND m.tenant_id = ?
             ORDER BY m.created_at DESC
             LIMIT 1000",
            [$userId, $userId, $userId, $userId, $this->tenantId]
        )->fetchAll();
    }

    private function getTransactionsData(int $userId): array
    {
        return $this->query(
            "SELECT t.id, t.amount, t.description, t.transaction_type,
                    t.status, t.created_at,
                    CASE WHEN t.from_user_id = ? THEN 'outgoing' ELSE 'incoming' END as direction
             FROM transactions t
             WHERE (t.from_user_id = ? OR t.to_user_id = ?) AND t.tenant_id = ?
             ORDER BY t.created_at DESC",
            [$userId, $userId, $userId, $this->tenantId]
        )->fetchAll();
    }

    private function getEventsData(int $userId): array
    {
        return $this->query(
            "SELECT e.id, e.title, e.description, e.start_date, e.end_date,
                    e.location, er.status as rsvp_status, er.created_at as rsvp_date
             FROM event_rsvps er
             JOIN events e ON er.event_id = e.id
             WHERE er.user_id = ? AND e.tenant_id = ?
             ORDER BY e.start_date DESC",
            [$userId, $this->tenantId]
        )->fetchAll();
    }

    private function getGroupsData(int $userId): array
    {
        return $this->query(
            "SELECT g.id, g.name, g.description, gm.role, gm.joined_at
             FROM group_members gm
             JOIN groups g ON gm.group_id = g.id
             WHERE gm.user_id = ? AND g.tenant_id = ?",
            [$userId, $this->tenantId]
        )->fetchAll();
    }

    private function getVolunteeringData(int $userId): array
    {
        return $this->query(
            "SELECT vo.id, vo.title, va.status, va.created_at as applied_at,
                    vh.hours, CASE WHEN vh.status = 'approved' THEN 1 ELSE 0 END as verified
             FROM vol_applications va
             JOIN vol_opportunities vo ON va.opportunity_id = vo.id
             LEFT JOIN vol_logs vh ON vh.opportunity_id = va.opportunity_id AND vh.user_id = va.user_id
             WHERE va.user_id = ? AND vo.tenant_id = ?",
            [$userId, $this->tenantId]
        )->fetchAll();
    }

    private function getGamificationData(int $userId): array
    {
        $badges = $this->query(
            "SELECT b.name, b.description, ub.awarded_at
             FROM user_badges ub
             JOIN badges b ON ub.badge_key = b.badge_key AND ub.tenant_id = b.tenant_id
             WHERE ub.user_id = ?",
            [$userId]
        )->fetchAll();

        $stats = $this->query(
            "SELECT xp_points, level, login_streak
             FROM users WHERE id = ?",
            [$userId]
        )->fetch();

        return [
            'stats' => $stats,
            'badges' => $badges,
        ];
    }

    private function getActivityLogData(int $userId): array
    {
        return $this->query(
            "SELECT action, entity_type, entity_id, created_at
             FROM activity_log
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC
             LIMIT 500",
            [$userId, $this->tenantId]
        )->fetchAll();
    }

    private function getConsentsData(int $userId): array
    {
        return $this->query(
            "SELECT consent_type, consent_given, consent_version, given_at, withdrawn_at
             FROM user_consents
             WHERE user_id = ? AND tenant_id = ?",
            [$userId, $this->tenantId]
        )->fetchAll();
    }

    private function getNotificationsData(int $userId): array
    {
        return $this->query(
            "SELECT type, title, message, read_at, created_at
             FROM notifications
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC
             LIMIT 200",
            [$userId, $this->tenantId]
        )->fetchAll();
    }

    private function getConnectionsData(int $userId): array
    {
        return $this->query(
            "SELECT u.id, u.first_name, u.last_name, c.created_at
             FROM connections c
             JOIN users u ON (c.user_id = u.id OR c.connected_user_id = u.id) AND u.id != ?
             WHERE (c.user_id = ? OR c.connected_user_id = ?) AND c.status = 'accepted'",
            [$userId, $userId, $userId]
        )->fetchAll();
    }

    private function getLoginHistoryData(int $userId): array
    {
        return $this->query(
            "SELECT ip_address, user_agent, created_at
             FROM activity_log
             WHERE user_id = ? AND action = 'login'
             ORDER BY created_at DESC
             LIMIT 100",
            [$userId]
        )->fetchAll();
    }

    // =========================================================================
    // ACCOUNT DELETION (Article 17 - Right to Erasure)
    // =========================================================================

    /**
     * Execute account deletion
     */
    public function executeAccountDeletion(int $userId, ?int $adminId = null, ?int $requestId = null): void
    {
        $this->logger->info("Starting account deletion", ['user_id' => $userId]);

        $this->db->beginTransaction();

        try {
            // 1. Generate final data export for legal retention
            $exportPath = $this->generateDataExport($userId);

            // 2. Anonymize user record
            $anonymizedEmail = "deleted_{$userId}_" . bin2hex(random_bytes(8)) . "@anonymized.local";
            $this->query(
                "UPDATE users SET
                    email = ?,
                    first_name = 'Deleted',
                    last_name = 'User',
                    phone = NULL,
                    bio = NULL,
                    skills = NULL,
                    interests = NULL,
                    location = NULL,
                    latitude = NULL,
                    longitude = NULL,
                    profile_picture = NULL,
                    cover_image = NULL,
                    website = NULL,
                    social_links = NULL,
                    password = '',
                    remember_token = NULL,
                    deleted_at = NOW(),
                    anonymized_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$anonymizedEmail, $userId, $this->tenantId]
            );

            // 3. Delete personal content
            $this->query(
                "DELETE FROM messages WHERE (sender_id = ? OR receiver_id = ?) AND tenant_id = ?",
                [$userId, $userId, $this->tenantId]
            );
            $this->query(
                "DELETE FROM notifications WHERE user_id = ? AND tenant_id = ?",
                [$userId, $this->tenantId]
            );
            $this->query(
                "DELETE FROM user_consents WHERE user_id = ? AND tenant_id = ?",
                [$userId, $this->tenantId]
            );
            $this->query("DELETE FROM push_subscriptions WHERE user_id = ?", [$userId]);
            $this->query("DELETE FROM fcm_device_tokens WHERE user_id = ?", [$userId]);

            // 4. Soft delete listings
            $this->query(
                "UPDATE listings SET status = 'deleted', description = '[DELETED]', deleted_at = NOW()
                 WHERE user_id = ? AND tenant_id = ?",
                [$userId, $this->tenantId]
            );

            // 5. Anonymize activity logs
            $this->query(
                "UPDATE activity_log SET ip_address = NULL, user_agent = NULL
                 WHERE user_id = ? AND tenant_id = ?",
                [$userId, $this->tenantId]
            );

            // 6. Delete uploaded files
            $this->deleteUserUploads($userId);

            // 7. Invalidate all sessions
            $this->query("DELETE FROM sessions WHERE user_id = ?", [$userId]);

            // 8. Update request if provided
            if ($requestId) {
                $this->query(
                    "UPDATE gdpr_requests
                     SET status = 'completed', processed_at = NOW(), processed_by = ?
                     WHERE id = ?",
                    [$adminId, $requestId]
                );
            }

            $this->db->commit();

            // Log action
            $this->logAction($userId, 'account_deleted', null, null, $adminId, null, [
                'export_path' => $exportPath,
            ]);

            $this->metrics->increment('gdpr.deletion.completed');

            $this->logger->info("Account deletion completed", ['user_id' => $userId]);
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Account deletion failed", [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // =========================================================================
    // CONSENT MANAGEMENT
    // =========================================================================

    /**
     * Record user consent
     */
    public function recordConsent(int $userId, string $consentType, bool $consented, string $consentText, string $version): array
    {
        $consentHash = hash('sha256', $consentText);

        $this->query(
            "INSERT INTO user_consents
             (user_id, tenant_id, consent_type, consent_given, consent_text, consent_version,
              consent_hash, ip_address, user_agent, source, given_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'web', ?)
             ON DUPLICATE KEY UPDATE
                consent_given = VALUES(consent_given),
                consent_text = VALUES(consent_text),
                consent_version = VALUES(consent_version),
                consent_hash = VALUES(consent_hash),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                given_at = IF(VALUES(consent_given), VALUES(given_at), given_at),
                withdrawn_at = IF(VALUES(consent_given), NULL, NOW()),
                updated_at = NOW()",
            [
                $userId,
                $this->tenantId,
                $consentType,
                $consented ? 1 : 0,
                $consentText,
                $version,
                $consentHash,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $consented ? date('Y-m-d H:i:s') : null,
            ]
        );

        $action = $consented ? 'consent_given' : 'consent_withdrawn';
        $this->logAction($userId, $action, 'consent', null, null, null, [
            'consent_type' => $consentType,
            'version' => $version,
        ]);

        $this->metrics->increment("gdpr.consent.{$action}", ['type' => $consentType]);

        return [
            'consent_type' => $consentType,
            'consent_given' => $consented,
            'version' => $version,
        ];
    }

    /**
     * Withdraw consent
     */
    public function withdrawConsent(int $userId, string $consentType): bool
    {
        $result = $this->query(
            "UPDATE user_consents
             SET consent_given = FALSE, withdrawn_at = NOW()
             WHERE user_id = ? AND consent_type = ? AND tenant_id = ? AND consent_given = TRUE",
            [$userId, $consentType, $this->tenantId]
        );

        if ($result->rowCount() > 0) {
            $this->logAction($userId, 'consent_withdrawn', 'consent', null, null, null, [
                'consent_type' => $consentType,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get user's consents
     */
    public function getUserConsents(int $userId): array
    {
        return $this->query(
            "SELECT uc.consent_type as consent_type_slug, uc.consent_given, uc.consent_version, uc.given_at, uc.withdrawn_at,
                    ct.name, ct.description, ct.category, ct.is_required
             FROM user_consents uc
             LEFT JOIN consent_types ct ON uc.consent_type = ct.slug
             WHERE uc.user_id = ? AND uc.tenant_id = ?",
            [$userId, $this->tenantId]
        )->fetchAll();
    }

    /**
     * Check if user has given specific consent
     */
    public function hasConsent(int $userId, string $consentType): bool
    {
        $result = $this->query(
            "SELECT consent_given FROM user_consents
             WHERE user_id = ? AND consent_type = ? AND tenant_id = ?
             ORDER BY created_at DESC LIMIT 1",
            [$userId, $consentType, $this->tenantId]
        )->fetch();

        return $result && $result['consent_given'];
    }

    /**
     * Check if user has accepted the current version of a specific consent
     * Checks tenant-specific version override first
     *
     * @param int $userId
     * @param string $consentType
     * @return bool
     */
    public function hasCurrentVersionConsent(int $userId, string $consentType): bool
    {
        // Get user's consent version and the required version (tenant override or global)
        $result = $this->query(
            "SELECT uc.consent_version,
                    COALESCE(tco.current_version, ct.current_version) AS current_version
             FROM user_consents uc
             JOIN consent_types ct ON uc.consent_type = ct.slug
             LEFT JOIN tenant_consent_overrides tco
                    ON ct.slug = tco.consent_type_slug
                   AND tco.tenant_id = ?
                   AND tco.is_active = 1
             WHERE uc.user_id = ? AND uc.consent_type = ? AND uc.tenant_id = ?
               AND uc.consent_given = 1
             ORDER BY uc.created_at DESC LIMIT 1",
            [$this->tenantId, $userId, $consentType, $this->tenantId]
        )->fetch();

        if (!$result) {
            return false;
        }

        return version_compare($result['consent_version'], $result['current_version'], '>=');
    }

    /**
     * Get all required consents that user has not accepted at current version
     * Checks for tenant-specific version overrides first
     *
     * @param int $userId
     * @return array Array of consent types needing re-acceptance
     */
    public function getOutdatedRequiredConsents(int $userId): array
    {
        // Get all required consent types with tenant override if exists
        // COALESCE picks tenant override version/text if available, otherwise global
        $requiredTypes = $this->query(
            "SELECT ct.slug, ct.name, ct.description,
                    COALESCE(tco.current_version, ct.current_version) AS current_version,
                    COALESCE(tco.current_text, ct.current_text) AS current_text,
                    ct.category,
                    tco.id AS has_tenant_override
             FROM consent_types ct
             LEFT JOIN tenant_consent_overrides tco
                    ON ct.slug = tco.consent_type_slug
                   AND tco.tenant_id = ?
                   AND tco.is_active = 1
             WHERE ct.is_required = TRUE AND ct.is_active = TRUE",
            [$this->tenantId]
        )->fetchAll();

        $outdated = [];

        foreach ($requiredTypes as $type) {
            // Check user's consent for this type
            $userConsent = $this->query(
                "SELECT consent_version, consent_given
                 FROM user_consents
                 WHERE user_id = ? AND consent_type = ? AND tenant_id = ?
                   AND consent_given = 1
                 ORDER BY created_at DESC LIMIT 1",
                [$userId, $type['slug'], $this->tenantId]
            )->fetch();

            // If no consent or version mismatch, add to outdated list
            if (!$userConsent) {
                $type['reason'] = 'never_accepted';
                $outdated[] = $type;
            } elseif (version_compare($userConsent['consent_version'], $type['current_version'], '<')) {
                $type['reason'] = 'version_outdated';
                $type['user_version'] = $userConsent['consent_version'];
                $outdated[] = $type;
            }
        }

        return $outdated;
    }

    /**
     * Check if user needs to re-accept any required consents
     *
     * @param int $userId
     * @return bool
     */
    public function needsReConsent(int $userId): bool
    {
        return !empty($this->getOutdatedRequiredConsents($userId));
    }

    /**
     * Accept multiple consents at once (for re-consent page)
     *
     * @param int $userId
     * @param array $consentSlugs Array of consent type slugs
     * @return array Results for each consent
     */
    public function acceptMultipleConsents(int $userId, array $consentSlugs): array
    {
        $results = [];

        foreach ($consentSlugs as $slug) {
            try {
                $results[$slug] = $this->updateUserConsent($userId, $slug, true);
            } catch (\Exception $e) {
                $results[$slug] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Backfill consent records for existing users
     * Creates consent records with consent_given=0 for users who don't have records
     * These users will be prompted to accept on next login
     *
     * @param string $consentType
     * @param string $version
     * @param string $consentText
     * @return int Number of users backfilled
     */
    public function backfillConsentsForExistingUsers(string $consentType, string $version, string $consentText): int
    {
        // Find users without this consent type
        $usersWithoutConsent = $this->query(
            "SELECT u.id
             FROM users u
             LEFT JOIN user_consents uc ON u.id = uc.user_id
               AND uc.consent_type = ? AND uc.tenant_id = ?
             WHERE u.tenant_id = ? AND uc.id IS NULL
               AND u.deleted_at IS NULL",
            [$consentType, $this->tenantId, $this->tenantId]
        )->fetchAll();

        $count = 0;
        $consentHash = hash('sha256', $consentText);

        foreach ($usersWithoutConsent as $user) {
            // Create a consent record with consent_given=0 (needs to accept)
            $this->query(
                "INSERT INTO user_consents
                 (user_id, tenant_id, consent_type, consent_given, consent_text,
                  consent_version, consent_hash, source, created_at)
                 VALUES (?, ?, ?, 0, ?, ?, ?, 'backfill', NOW())",
                [
                    $user['id'],
                    $this->tenantId,
                    $consentType,
                    $consentText,
                    $version,
                    $consentHash
                ]
            );
            $count++;
        }

        $this->logger->info("Backfilled consent records", [
            'consent_type' => $consentType,
            'users_count' => $count,
            'tenant_id' => $this->tenantId
        ]);

        return $count;
    }

    /**
     * Get effective consent version for this tenant
     * Returns tenant override if exists, otherwise global version
     *
     * @param string $consentSlug
     * @return array|null
     */
    public function getEffectiveConsentVersion(string $consentSlug): ?array
    {
        return $this->query(
            "SELECT ct.slug, ct.name, ct.description, ct.is_required,
                    COALESCE(tco.current_version, ct.current_version) AS current_version,
                    COALESCE(tco.current_text, ct.current_text) AS current_text,
                    tco.id AS tenant_override_id
             FROM consent_types ct
             LEFT JOIN tenant_consent_overrides tco
                    ON ct.slug = tco.consent_type_slug
                   AND tco.tenant_id = ?
                   AND tco.is_active = 1
             WHERE ct.slug = ? AND ct.is_active = TRUE",
            [$this->tenantId, $consentSlug]
        )->fetch() ?: null;
    }

    /**
     * Set or update tenant-specific consent version
     * This allows a tenant to update their terms independently of other tenants
     *
     * @param string $consentSlug
     * @param string $version
     * @param string|null $text Optional override text (null = use global text)
     * @return bool
     */
    public function setTenantConsentVersion(string $consentSlug, string $version, ?string $text = null): bool
    {
        // Verify consent type exists
        $exists = $this->query(
            "SELECT 1 FROM consent_types WHERE slug = ? AND is_active = TRUE",
            [$consentSlug]
        )->fetch();

        if (!$exists) {
            throw new \InvalidArgumentException("Invalid consent type: {$consentSlug}");
        }

        // Upsert tenant override
        $this->query(
            "INSERT INTO tenant_consent_overrides
             (tenant_id, consent_type_slug, current_version, current_text, is_active)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                current_version = VALUES(current_version),
                current_text = VALUES(current_text),
                is_active = 1,
                updated_at = NOW()",
            [$this->tenantId, $consentSlug, $version, $text]
        );

        $this->logger->info("Tenant consent version updated", [
            'tenant_id' => $this->tenantId,
            'consent_type' => $consentSlug,
            'version' => $version,
            'has_custom_text' => $text !== null
        ]);

        return true;
    }

    /**
     * Remove tenant-specific consent override (revert to global version)
     *
     * @param string $consentSlug
     * @return bool
     */
    public function removeTenantConsentOverride(string $consentSlug): bool
    {
        $this->query(
            "UPDATE tenant_consent_overrides
             SET is_active = 0, updated_at = NOW()
             WHERE tenant_id = ? AND consent_type_slug = ?",
            [$this->tenantId, $consentSlug]
        );

        $this->logger->info("Tenant consent override removed", [
            'tenant_id' => $this->tenantId,
            'consent_type' => $consentSlug
        ]);

        return true;
    }

    /**
     * Get all tenant consent overrides for this tenant
     *
     * @return array
     */
    public function getTenantConsentOverrides(): array
    {
        return $this->query(
            "SELECT tco.*, ct.name, ct.current_version AS global_version
             FROM tenant_consent_overrides tco
             JOIN consent_types ct ON tco.consent_type_slug = ct.slug
             WHERE tco.tenant_id = ? AND tco.is_active = 1",
            [$this->tenantId]
        )->fetchAll();
    }

    /**
     * Get all consent types
     */
    public function getConsentTypes(): array
    {
        return $this->query(
            "SELECT slug, name, description, category, is_required, current_version, current_text
             FROM consent_types
             WHERE is_active = TRUE
             ORDER BY display_order, name"
        )->fetchAll();
    }

    /**
     * Get active consent types for user-facing view
     */
    public function getActiveConsentTypes(): array
    {
        return $this->query(
            "SELECT slug, name, description, category, is_required, current_version
             FROM consent_types
             WHERE is_active = TRUE
             ORDER BY display_order, name"
        )->fetchAll();
    }

    /**
     * Update user consent by slug (user-initiated)
     * Uses tenant-specific version if available
     */
    public function updateUserConsent(int $userId, string $slug, bool $given): array
    {
        // Get effective consent type details (with tenant override if exists)
        $consentType = $this->getEffectiveConsentVersion($slug);

        if (!$consentType) {
            throw new \InvalidArgumentException("Invalid consent type: {$slug}");
        }

        // Cannot withdraw required consents
        if (!$given && $consentType['is_required']) {
            throw new \RuntimeException("Cannot withdraw required consent: {$consentType['name']}");
        }

        // Record consent with effective version
        return $this->recordConsent(
            $userId,
            $slug,
            $given,
            $consentType['current_text'] ?? '',
            $consentType['current_version'] ?? '1.0'
        );
    }

    // =========================================================================
    // DATA BREACH MANAGEMENT
    // =========================================================================

    /**
     * Report a data breach
     */
    public function reportBreach(array $data, int $reportedBy): int
    {
        $breachId = 'BREACH-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

        $this->query(
            "INSERT INTO data_breach_log
             (tenant_id, breach_id, breach_type, severity, description,
              data_categories_affected, number_of_records_affected, number_of_users_affected,
              detected_at, occurred_at, created_by, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'detected')",
            [
                $this->tenantId,
                $breachId,
                $data['breach_type'],
                $data['severity'] ?? 'medium',
                $data['description'],
                json_encode($data['data_categories']),
                $data['records_affected'] ?? null,
                $data['users_affected'] ?? null,
                $data['detected_at'] ?? date('Y-m-d H:i:s'),
                $data['occurred_at'] ?? null,
                $reportedBy,
            ]
        );

        $id = (int) $this->lastInsertId();

        $this->logger->critical("DATA BREACH REPORTED", [
            'breach_id' => $breachId,
            'type' => $data['breach_type'],
            'severity' => $data['severity'] ?? 'medium',
        ]);

        $this->metrics->increment('gdpr.breach.reported', ['severity' => $data['severity'] ?? 'medium']);

        return $id;
    }

    /**
     * Get breach notification deadline (72 hours from detection)
     */
    public function getBreachDeadline(int $breachLogId): \DateTime
    {
        $breach = $this->query(
            "SELECT detected_at FROM data_breach_log WHERE id = ?",
            [$breachLogId]
        )->fetch();

        $detected = new \DateTime($breach['detected_at']);
        return $detected->modify('+72 hours');
    }

    // =========================================================================
    // AUDIT LOGGING
    // =========================================================================

    /**
     * Log a GDPR action
     */
    public function logAction(
        int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $adminId = null,
        $oldValue = null,
        $newValue = null
    ): void {
        $this->query(
            "INSERT INTO gdpr_audit_log
             (user_id, admin_id, tenant_id, action, entity_type, entity_id,
              old_value, new_value, ip_address, user_agent, request_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $adminId,
                $this->tenantId,
                $action,
                $entityType,
                $entityId,
                $oldValue ? json_encode($oldValue) : null,
                $newValue ? json_encode($newValue) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $_SERVER['HTTP_X_REQUEST_ID'] ?? null,
            ]
        );
    }

    /**
     * Get audit log for user
     */
    public function getAuditLog(int $userId, int $limit = 100): array
    {
        $limit = (int) $limit;

        return $this->query(
            "SELECT action, entity_type, entity_id, created_at, ip_address
             FROM gdpr_audit_log
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC
             LIMIT {$limit}",
            [$userId, $this->tenantId]
        )->fetchAll();
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Get GDPR statistics for admin dashboard
     */
    public function getStatistics(): array
    {
        $stats = [];

        // Request counts by status
        $stats['requests'] = $this->query(
            "SELECT request_type, status, COUNT(*) as count
             FROM gdpr_requests
             WHERE tenant_id = ?
             GROUP BY request_type, status",
            [$this->tenantId]
        )->fetchAll();

        // Pending requests count
        $stats['pending_count'] = $this->query(
            "SELECT COUNT(*) as count FROM gdpr_requests
             WHERE tenant_id = ? AND status IN ('pending', 'processing')",
            [$this->tenantId]
        )->fetch()['count'];

        // Average processing time
        $stats['avg_processing_time'] = $this->query(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, requested_at, processed_at)) as avg_hours
             FROM gdpr_requests
             WHERE tenant_id = ? AND status = 'completed' AND processed_at IS NOT NULL",
            [$this->tenantId]
        )->fetch()['avg_hours'];

        // Consent statistics
        $stats['consents'] = $this->query(
            "SELECT consent_type, SUM(consent_given) as given, COUNT(*) - SUM(consent_given) as withdrawn
             FROM user_consents
             WHERE tenant_id = ?
             GROUP BY consent_type",
            [$this->tenantId]
        )->fetchAll();

        // Active breaches
        $stats['active_breaches'] = $this->query(
            "SELECT COUNT(*) as count FROM data_breach_log
             WHERE tenant_id = ? AND status NOT IN ('resolved', 'closed')",
            [$this->tenantId]
        )->fetch()['count'];

        // Overdue requests (pending for more than 30 days - GDPR requires response within 30 days)
        $stats['overdue_count'] = $this->query(
            "SELECT COUNT(*) as count FROM gdpr_requests
             WHERE tenant_id = ? AND status IN ('pending', 'processing')
             AND requested_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$this->tenantId]
        )->fetch()['count'];

        return $stats;
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function getStoragePath(string $path): string
    {
        $basePath = getenv('STORAGE_PATH') ?: __DIR__ . '/../../../storage';
        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }

    private function generateHtmlExport(array $data): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<title>Your Data Export - NEXUS</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;max-width:1200px;margin:0 auto;padding:20px}';
        $html .= 'h1,h2,h3{color:#333}table{width:100%;border-collapse:collapse;margin:20px 0}';
        $html .= 'th,td{border:1px solid #ddd;padding:10px;text-align:left}th{background:#f5f5f5}</style>';
        $html .= '</head><body>';
        $html .= '<h1>Your Data Export</h1>';
        $html .= '<p>Generated: ' . htmlspecialchars($data['export_info']['generated_at']) . '</p>';

        foreach ($data as $section => $content) {
            if ($section === 'export_info') continue;

            $html .= '<h2>' . ucfirst(str_replace('_', ' ', $section)) . '</h2>';

            if (is_array($content) && !empty($content)) {
                if (isset($content[0]) && is_array($content[0])) {
                    $html .= '<table><tr>';
                    foreach (array_keys($content[0]) as $key) {
                        $html .= '<th>' . htmlspecialchars($key) . '</th>';
                    }
                    $html .= '</tr>';
                    foreach ($content as $row) {
                        $html .= '<tr>';
                        foreach ($row as $value) {
                            $html .= '<td>' . htmlspecialchars(is_array($value) ? json_encode($value) : (string)$value) . '</td>';
                        }
                        $html .= '</tr>';
                    }
                    $html .= '</table>';
                } else {
                    $html .= '<table>';
                    foreach ($content as $key => $value) {
                        $html .= '<tr><th>' . htmlspecialchars($key) . '</th>';
                        $html .= '<td>' . htmlspecialchars(is_array($value) ? json_encode($value) : (string)$value) . '</td></tr>';
                    }
                    $html .= '</table>';
                }
            } else {
                $html .= '<p>No data available</p>';
            }
        }

        $html .= '</body></html>';
        return $html;
    }

    private function generateExportReadme(array $data): string
    {
        return "NEXUS DATA EXPORT
==================

Generated: {$data['export_info']['generated_at']}
User ID: {$data['export_info']['user_id']}
Platform: {$data['export_info']['platform']}

This archive contains all personal data associated with your account.

FILES INCLUDED:
- data.json: Machine-readable format (JSON)
- data.html: Human-readable format (HTML)
- uploads/: Your uploaded files (if any)

For questions about this export, please contact support.

This export will expire in 7 days.
";
    }

    private function copyUserUploads(int $userId, string $destDir): void
    {
        $uploadsDir = $this->getStoragePath("uploads/users/{$userId}");

        if (is_dir($uploadsDir)) {
            $destUploads = "{$destDir}/uploads";
            mkdir($destUploads, 0755, true);

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($uploadsDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $dest = $destUploads . '/' . $iterator->getSubPathName();
                if ($item->isDir()) {
                    mkdir($dest, 0755, true);
                } else {
                    copy($item, $dest);
                }
            }
        }
    }

    private function deleteUserUploads(int $userId): void
    {
        $uploadsDir = $this->getStoragePath("uploads/users/{$userId}");
        if (is_dir($uploadsDir)) {
            $this->deleteDirectory($uploadsDir);
        }
    }

    private function createZipArchive(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create zip archive: {$zipPath}");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
