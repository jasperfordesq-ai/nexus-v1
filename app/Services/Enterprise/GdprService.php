<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\Enterprise;

use Illuminate\Support\Facades\DB;
use App\Services\Enterprise\LoggerService;
use App\Services\Enterprise\MetricsService;
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
        $this->db = DB::getPdo();
        $this->tenantId = $tenantId ?? \App\Core\TenantContext::getId();
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
             WHERE id = ? AND tenant_id = ?",
            [$requestId, $this->tenantId]
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
        $exportDir = self::getStoragePath("exports/gdpr/{$userId}_" . time());
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        // Create JSON export
        $jsonPath = "{$exportDir}/data.json";
        file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Create HTML export
        $htmlPath = "{$exportDir}/data.html";
        file_put_contents($htmlPath, self::generateHtmlExport($data));

        // Create README
        $readmePath = "{$exportDir}/README.txt";
        file_put_contents($readmePath, self::generateExportReadme($data));

        // Copy user uploads
        self::copyUserUploads($userId, $exportDir);

        // Create ZIP archive
        $timestamp = date('Ymd_His');
        $zipFilename = "nexus_data_export_{$userId}_{$timestamp}.zip";
        $zipPath = self::getStoragePath("exports/{$zipFilename}");

        self::createZipArchive($exportDir, $zipPath);

        // Cleanup temp directory
        self::deleteDirectory($exportDir);

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
            'volunteer_detailed' => $this->exportVolunteerData($userId),
            'gamification' => $this->getGamificationData($userId),
            'activity_log' => $this->getActivityLogData($userId),
            'consents' => $this->getConsentsData($userId),
            'notifications' => $this->getNotificationsData($userId),
            'connections' => self::getConnectionsData($userId),
            'login_history' => self::getLoginHistoryData($userId),
            'messaging_restrictions' => $this->getMessagingRestrictionsData($userId),
            'ai_chat_history' => $this->getAiChatData($userId),
            'reviews' => $this->getReviewsData($userId),
            'exchanges' => $this->getExchangeData($userId),
            'vetting_records' => $this->getVettingRecordsData($userId),
            'insurance_certificates' => $this->getInsuranceCertificatesData($userId),
            'identity_verification' => $this->getIdentityVerificationData($userId),
            'safeguarding_preferences' => $this->getSafeguardingPreferencesData($userId),
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

    /**
     * Export ALL volunteer-related data for a user (GDPR Article 15 - comprehensive)
     *
     * Queries all volunteer module tables and returns structured data grouped by category.
     * This complements getVolunteeringData() which only covers applications/logs.
     *
     * @param int $userId The user whose volunteer data to export
     * @return array Structured volunteer data grouped by category
     */
    private function exportVolunteerData(int $userId): array
    {
        $t = $this->tenantId;

        // vol_applications
        $applications = $this->query(
            "SELECT va.id, va.opportunity_id, va.shift_id, va.status, va.message,
                    va.reviewed_by, va.reviewed_at, va.created_at, va.updated_at,
                    opp.title as opportunity_title
             FROM vol_applications va
             LEFT JOIN vol_opportunities opp ON va.opportunity_id = opp.id
             WHERE va.user_id = ? AND va.tenant_id = ?
             ORDER BY va.created_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_logs (hour logs)
        $logs = $this->query(
            "SELECT vl.id, vl.organization_id, vl.opportunity_id, vl.hours, vl.description,
                    vl.date_logged, vl.status, vl.verified_by, vl.verified_at, vl.created_at,
                    opp.title as opportunity_title
             FROM vol_logs vl
             LEFT JOIN vol_opportunities opp ON vl.opportunity_id = opp.id
             WHERE vl.user_id = ? AND vl.tenant_id = ?
             ORDER BY vl.date_logged DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_shift_signups
        $shiftSignups = $this->query(
            "SELECT vss.id, vss.shift_id, vss.status, vss.created_at,
                    vs.start_time, vs.end_time
             FROM vol_shift_signups vss
             LEFT JOIN vol_shifts vs ON vss.shift_id = vs.id
             WHERE vss.user_id = ? AND vss.tenant_id = ?
             ORDER BY vss.created_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_shift_checkins
        $shiftCheckins = $this->query(
            "SELECT id, shift_id, qr_token, checked_in_at, checked_out_at,
                    status, created_at
             FROM vol_shift_checkins
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY checked_in_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_reviews (reviews written by or about the user)
        $reviews = $this->query(
            "SELECT id, reviewer_id, target_type, target_id, rating,
                    comment, created_at,
                    CASE WHEN reviewer_id = ? THEN 'given' ELSE 'received' END as direction
             FROM vol_reviews
             WHERE (reviewer_id = ? OR (target_type = 'user' AND target_id = ?)) AND tenant_id = ?
             ORDER BY created_at DESC",
            [$userId, $userId, $userId, $t]
        )->fetchAll();

        // vol_certificates
        $certificates = $this->query(
            "SELECT id, verification_code, total_hours, date_range_start,
                    date_range_end, organizations, generated_at, downloaded_at
             FROM vol_certificates
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY generated_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_mood_checkins
        $moodCheckins = $this->query(
            "SELECT id, mood, note, created_at
             FROM vol_mood_checkins
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_credentials
        $credentials = $this->query(
            "SELECT id, credential_type, file_url, file_name, status,
                    verified_by, verified_at, expires_at, notes, created_at, updated_at
             FROM vol_credentials
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_expenses
        $expenses = $this->query(
            "SELECT id, organization_id, opportunity_id, expense_type, amount, currency,
                    description, receipt_path, receipt_filename, status,
                    reviewed_by, review_notes, reviewed_at, paid_at, payment_reference, submitted_at, created_at
             FROM vol_expenses
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY submitted_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_safeguarding_training
        $safeguardingTraining = $this->query(
            "SELECT id, training_type, training_name, provider, completed_at,
                    expires_at, certificate_url, document_path, status, created_at
             FROM vol_safeguarding_training
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY completed_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_guardian_consents (as minor)
        $guardianConsents = $this->query(
            "SELECT id, opportunity_id, guardian_name, guardian_email, relationship,
                    status, consent_given_at, consent_withdrawn_at, expires_at, created_at
             FROM vol_guardian_consents
             WHERE minor_user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_accessibility_needs
        $accessibilityNeeds = $this->query(
            "SELECT id, need_type, description, accommodations_required,
                    emergency_contact_name, emergency_contact_phone, created_at, updated_at
             FROM vol_accessibility_needs
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_safeguarding_incidents (reported by user)
        $safeguardingIncidents = $this->query(
            "SELECT id, opportunity_id, incident_type, severity, description,
                    action_taken, status, resolution_notes, created_at
             FROM vol_safeguarding_incidents
             WHERE reported_by = ? AND tenant_id = ?
             ORDER BY created_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_custom_field_values (custom form data submitted by user)
        $customFieldValues = $this->query(
            "SELECT cfv.id, cfv.entity_type, cfv.entity_id, cfv.field_value,
                    cf.field_key, cf.field_label, cf.field_type, cfv.created_at
             FROM vol_custom_field_values cfv
             JOIN vol_custom_fields cf ON cfv.custom_field_id = cf.id
             WHERE cfv.entity_type = 'application'
               AND cfv.entity_id IN (SELECT va.id FROM vol_applications va WHERE va.user_id = ? AND va.tenant_id = ?)
               AND cfv.tenant_id = ?
             ORDER BY cfv.created_at DESC",
            [$userId, $t, $t]
        )->fetchAll();

        // vol_donations
        $donations = $this->query(
            "SELECT id, opportunity_id, community_project_id, giving_day_id, amount, currency,
                    payment_method, payment_reference, donor_name, donor_email,
                    message, is_anonymous, status, created_at
             FROM vol_donations
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_community_projects (proposed by user)
        $communityProjects = $this->query(
            "SELECT id, title, description, category, location, status,
                    proposed_date, created_at
             FROM vol_community_projects
             WHERE proposed_by = ? AND tenant_id = ?
             ORDER BY created_at DESC",
            [$userId, $t]
        )->fetchAll();

        // vol_shift_waitlist (user's waitlist entries)
        try {
            $shiftWaitlist = $this->query(
                "SELECT sw.id, sw.shift_id, sw.position, sw.status,
                        sw.notified_at, sw.promoted_at, sw.created_at,
                        vs.start_time, vs.end_time
                 FROM vol_shift_waitlist sw
                 LEFT JOIN vol_shifts vs ON sw.shift_id = vs.id
                 WHERE sw.user_id = ? AND sw.tenant_id = ?
                 ORDER BY sw.created_at DESC",
                [$userId, $t]
            )->fetchAll();
        } catch (\Throwable $e) {
            $shiftWaitlist = [];
        }

        // vol_shift_swap_requests (user's swap requests as requester or target)
        try {
            $shiftSwapRequests = $this->query(
                "SELECT id, from_user_id, to_user_id, from_shift_id, to_shift_id,
                        status, requires_admin_approval, message, created_at, updated_at,
                        CASE WHEN from_user_id = ? THEN 'outgoing' ELSE 'incoming' END as direction
                 FROM vol_shift_swap_requests
                 WHERE (from_user_id = ? OR to_user_id = ?) AND tenant_id = ?
                 ORDER BY created_at DESC",
                [$userId, $userId, $userId, $t]
            )->fetchAll();
        } catch (\Throwable $e) {
            $shiftSwapRequests = [];
        }

        // vol_shift_group_members (user's group reservation memberships)
        try {
            $shiftGroupMemberships = $this->query(
                "SELECT gm.id, gm.reservation_id, gm.status, gm.created_at,
                        gr.shift_id, gr.group_id, gr.reserved_slots
                 FROM vol_shift_group_members gm
                 LEFT JOIN vol_shift_group_reservations gr ON gm.reservation_id = gr.id
                 WHERE gm.user_id = ? AND gm.tenant_id = ?
                 ORDER BY gm.created_at DESC",
                [$userId, $t]
            )->fetchAll();
        } catch (\Throwable $e) {
            $shiftGroupMemberships = [];
        }

        // vol_emergency_alert_recipients (emergency alerts sent to user)
        try {
            $emergencyAlertRecipients = $this->query(
                "SELECT ear.id, ear.alert_id, ear.notified_at, ear.response, ear.responded_at,
                        ea.priority, ea.message, ea.shift_id, ea.status as alert_status
                 FROM vol_emergency_alert_recipients ear
                 LEFT JOIN vol_emergency_alerts ea ON ear.alert_id = ea.id
                 WHERE ear.user_id = ? AND ear.tenant_id = ?
                 ORDER BY ear.notified_at DESC",
                [$userId, $t]
            )->fetchAll();
        } catch (\Throwable $e) {
            $emergencyAlertRecipients = [];
        }

        // vol_wellbeing_alerts (burnout alerts about user)
        try {
            $wellbeingAlerts = $this->query(
                "SELECT id, risk_level, risk_score, indicators, coordinator_notified,
                        coordinator_notes, status, resolved_at, created_at, updated_at
                 FROM vol_wellbeing_alerts
                 WHERE user_id = ? AND tenant_id = ?
                 ORDER BY created_at DESC",
                [$userId, $t]
            )->fetchAll();
        } catch (\Throwable $e) {
            $wellbeingAlerts = [];
        }

        return [
            'applications' => $applications,
            'hour_logs' => $logs,
            'shift_signups' => $shiftSignups,
            'shift_checkins' => $shiftCheckins,
            'shift_waitlist' => $shiftWaitlist,
            'shift_swap_requests' => $shiftSwapRequests,
            'shift_group_memberships' => $shiftGroupMemberships,
            'reviews' => $reviews,
            'certificates' => $certificates,
            'mood_checkins' => $moodCheckins,
            'credentials' => $credentials,
            'expenses' => $expenses,
            'safeguarding_training' => $safeguardingTraining,
            'guardian_consents' => $guardianConsents,
            'accessibility_needs' => $accessibilityNeeds,
            'safeguarding_incidents' => $safeguardingIncidents,
            'custom_field_values' => $customFieldValues,
            'donations' => $donations,
            'community_projects' => $communityProjects,
            'emergency_alert_recipients' => $emergencyAlertRecipients,
            'wellbeing_alerts' => $wellbeingAlerts,
        ];
    }

    private function getGamificationData(int $userId): array
    {
        $badges = $this->query(
            "SELECT b.name, b.description, ub.awarded_at
             FROM user_badges ub
             JOIN badges b ON ub.badge_key = b.badge_key AND ub.tenant_id = b.tenant_id
             WHERE ub.user_id = ? AND ub.tenant_id = ?",
            [$userId, $this->tenantId]
        )->fetchAll();

        $stats = $this->query(
            "SELECT xp_points, level, login_streak
             FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $this->tenantId]
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
        // Connection table uses requester_id/receiver_id columns
        return $this->query(
            "SELECT u.id, u.first_name, u.last_name, c.created_at
             FROM connections c
             JOIN users u ON (c.requester_id = u.id OR c.receiver_id = u.id) AND u.id != ?
             WHERE (c.requester_id = ? OR c.receiver_id = ?) AND c.tenant_id = ? AND c.status = 'accepted'",
            [$userId, $userId, $userId, $this->tenantId]
        )->fetchAll();
    }

    private function getLoginHistoryData(int $userId): array
    {
        return $this->query(
            "SELECT ip_address, user_agent, created_at
             FROM activity_log
             WHERE user_id = ? AND tenant_id = ? AND action = 'login'
             ORDER BY created_at DESC
             LIMIT 100",
            [$userId, $this->tenantId]
        )->fetchAll();
    }

    private function getMessagingRestrictionsData(int $userId): ?array
    {
        $row = $this->query(
            "SELECT messaging_disabled, under_monitoring, monitoring_reason,
                    monitoring_started_at, monitoring_expires_at, restricted_by, created_at, updated_at
             FROM user_messaging_restrictions
             WHERE user_id = ? AND tenant_id = ?",
            [$userId, $this->tenantId]
        )->fetch();

        return $row ?: null;
    }

    private function getAiChatData(int $userId): array
    {
        try {
            return $this->query(
                "SELECT m.id, m.role, m.content, m.model, m.created_at
                 FROM ai_messages m
                 INNER JOIN ai_conversations c ON c.id = m.conversation_id
                 WHERE c.user_id = ? AND c.tenant_id = ?
                 ORDER BY m.created_at DESC",
                [$userId, $this->tenantId]
            )->fetchAll();
        } catch (\Throwable $e) {
            return []; // Table may not exist
        }
    }

    private function getReviewsData(int $userId): array
    {
        try {
            return $this->query(
                "SELECT id, reviewer_id, receiver_id, listing_id, rating, comment, created_at,
                        CASE WHEN reviewer_id = ? THEN 'given' ELSE 'received' END as direction
                 FROM reviews
                 WHERE (reviewer_id = ? OR receiver_id = ?) AND tenant_id = ?
                 ORDER BY created_at DESC",
                [$userId, $userId, $userId, $this->tenantId]
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getExchangeData(int $userId): array
    {
        try {
            return $this->query(
                "SELECT er.id, er.listing_id, er.requester_id, er.provider_id,
                        er.proposed_hours, er.final_hours, er.status,
                        er.requester_notes, er.requester_confirmed_hours,
                        er.provider_confirmed_hours, er.created_at, er.updated_at,
                        l.title as listing_title,
                        CASE WHEN er.requester_id = ? THEN 'requester' ELSE 'provider' END as role
                 FROM exchange_requests er
                 LEFT JOIN listings l ON er.listing_id = l.id
                 WHERE (er.requester_id = ? OR er.provider_id = ?) AND er.tenant_id = ?
                 ORDER BY er.created_at DESC",
                [$userId, $userId, $userId, $this->tenantId]
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
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
                    password_hash = '',
                    password = '',
                    remember_token = NULL,
                    deleted_at = NOW(),
                    anonymized_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$anonymizedEmail, $userId, $this->tenantId]
            );

            // 3. Delete personal content.
            // Messages: we do NOT hard-delete. Doing so orphans message
            // threads for the *other* participant — they'd see half a
            // conversation with no attribution, breaking their right to
            // retain records of communication they received. Instead we
            // anonymise the sender/receiver side belonging to the erased
            // user and scrub the body where this user authored it. The row
            // stays so the counterparty's audit trail remains intact.
            $this->query(
                "UPDATE messages
                    SET body = CASE WHEN sender_id = ? THEN '[message removed — account erased]' ELSE body END,
                        transcript = CASE WHEN sender_id = ? THEN NULL ELSE transcript END,
                        audio_url = CASE WHEN sender_id = ? THEN NULL ELSE audio_url END
                  WHERE (sender_id = ? OR receiver_id = ?) AND tenant_id = ?",
                [$userId, $userId, $userId, $userId, $userId, $this->tenantId]
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

            // 3a. Delete AI chat history (GDPR: user content sent to third-party AI)
            try {
                $convIds = $this->query(
                    "SELECT id FROM ai_conversations WHERE user_id = ? AND tenant_id = ?",
                    [$userId, $this->tenantId]
                )->fetchAll(\PDO::FETCH_COLUMN);
                if (!empty($convIds)) {
                    $placeholders = implode(',', array_fill(0, count($convIds), '?'));
                    $this->query("DELETE FROM ai_messages WHERE conversation_id IN ($placeholders)", $convIds);
                    $this->query(
                        "DELETE FROM ai_conversations WHERE user_id = ? AND tenant_id = ?",
                        [$userId, $this->tenantId]
                    );
                }
            } catch (\Throwable $e) {
                // Table may not exist on all deployments
            }

            // 3b. Delete WebAuthn credentials / passkeys
            try {
                $this->query(
                    "DELETE FROM webauthn_credentials WHERE user_id = ?",
                    [$userId]
                );
            } catch (\Throwable $e) {
                // Table may not exist
            }

            // 3c. Revoke API tokens (Sanctum personal_access_tokens)
            try {
                $this->query(
                    "DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\\\\Models\\\\User' AND tokenable_id = ?",
                    [$userId]
                );
            } catch (\Throwable $e) {
                // Table may not exist
            }

            // 3d. Delete cookie consent records
            try {
                $this->query(
                    "DELETE FROM cookie_consents WHERE user_id = ? AND tenant_id = ?",
                    [$userId, $this->tenantId]
                );
            } catch (\Throwable $e) {
                // Table may not exist
            }

            // 3e. Remove connections (personal relationship data)
            // Connection table uses requester_id/receiver_id columns (not user_id/connected_user_id)
            $this->query(
                "DELETE FROM connections WHERE (requester_id = ? OR receiver_id = ?) AND tenant_id = ?",
                [$userId, $userId, $this->tenantId]
            );

            // 3f. Remove group memberships
            try {
                $this->query(
                    "DELETE FROM group_members WHERE user_id = ? AND tenant_id = ?",
                    [$userId, $this->tenantId]
                );
            } catch (\Throwable $e) {
                // Fallback: group_members may not have tenant_id
                try {
                    $this->query("DELETE FROM group_members WHERE user_id = ?", [$userId]);
                } catch (\Throwable $e2) { $this->logger->warning('GDPR deletion step skipped', ['user_id' => $userId, 'error' => $e2->getMessage()]); }
            }

            // 3g. Remove event RSVPs
            try {
                $this->query(
                    "DELETE FROM event_rsvps WHERE user_id = ?",
                    [$userId]
                );
            } catch (\Throwable $e) { $this->logger->warning('GDPR deletion step skipped', ['user_id' => $userId, 'error' => $e->getMessage()]); }

            // 3h. Anonymize reviews (preserve review content but remove personal link)
            try {
                $this->query(
                    "UPDATE reviews SET reviewer_id = NULL WHERE reviewer_id = ? AND tenant_id = ?",
                    [$userId, $this->tenantId]
                );
            } catch (\Throwable $e) { $this->logger->warning('GDPR deletion step skipped', ['user_id' => $userId, 'error' => $e->getMessage()]); }

            // 3i. Delete TOTP/2FA secrets
            try {
                $this->query(
                    "UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_backup_codes = NULL
                     WHERE id = ? AND tenant_id = ?",
                    [$userId, $this->tenantId]
                );
            } catch (\Throwable $e) { $this->logger->warning('GDPR deletion step skipped', ['user_id' => $userId, 'error' => $e->getMessage()]); }

            // 3j. Delete user notification preferences
            try {
                $this->query(
                    "DELETE FROM user_notification_preferences WHERE user_id = ?",
                    [$userId]
                );
            } catch (\Throwable $e) { $this->logger->warning('GDPR deletion step skipped', ['user_id' => $userId, 'error' => $e->getMessage()]); }

            // 3k. Anonymize exchange requests (preserve transaction history, remove personal notes)
            try {
                $this->query(
                    "UPDATE exchange_requests SET requester_notes = NULL, provider_notes = NULL, broker_notes = NULL
                     WHERE (requester_id = ? OR provider_id = ?) AND tenant_id = ?",
                    [$userId, $userId, $this->tenantId]
                );
            } catch (\Throwable $e) { $this->logger->warning('GDPR deletion step skipped', ['user_id' => $userId, 'error' => $e->getMessage()]); }

            // 3l. Delete feed activity entries (user's posts/shares in the feed)
            try {
                $this->query(
                    "DELETE FROM feed_activity WHERE user_id = ? AND tenant_id = ?",
                    [$userId, $this->tenantId]
                );
            } catch (\Throwable $e) { $this->logger->warning('GDPR deletion step skipped', ['user_id' => $userId, 'error' => $e->getMessage()]); }

            // 3m. Delete user blocks (in both directions)
            try {
                $this->query(
                    "DELETE FROM user_blocks WHERE user_id = ? OR blocked_user_id = ?",
                    [$userId, $userId]
                );
            } catch (\Throwable $e) { $this->logger->warning('GDPR deletion step skipped', ['user_id' => $userId, 'error' => $e->getMessage()]); }

            // 3n. Anonymize transactions (preserve amounts for audit, remove personal link text)
            // Note: transactions are financial records — we keep them but with anonymized sender/receiver names
            try {
                $this->query(
                    "UPDATE transactions SET deleted_for_sender = 1 WHERE sender_id = ? AND tenant_id = ?",
                    [$userId, $this->tenantId]
                );
                $this->query(
                    "UPDATE transactions SET deleted_for_receiver = 1 WHERE receiver_id = ? AND tenant_id = ?",
                    [$userId, $this->tenantId]
                );
            } catch (\Throwable $e) { $this->logger->warning('GDPR deletion step skipped', ['user_id' => $userId, 'error' => $e->getMessage()]); }

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
            self::deleteUserUploads($userId);

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

            // 9. Remove from Meilisearch index (outside transaction — external service)
            try {
                $meiliClient = new \Meilisearch\Client(
                    env('MEILISEARCH_HOST', 'http://meilisearch:7700'),
                    env('MEILISEARCH_KEY') ?: null,
                );
                $meiliClient->index('users')->deleteDocument($userId);

                // Also remove user's listings from the search index
                $listingIds = $this->query(
                    "SELECT id FROM listings WHERE user_id = ? AND tenant_id = ?",
                    [$userId, $this->tenantId]
                )->fetchAll(\PDO::FETCH_COLUMN);

                foreach ($listingIds as $listingId) {
                    try {
                        $meiliClient->index('listings')->deleteDocument($listingId);
                    } catch (\Throwable $e) { $this->logger->warning('GDPR Meilisearch listing deletion skipped', ['listing_id' => $listingId, 'error' => $e->getMessage()]); }
                }
            } catch (\Throwable $e) {
                $this->logger->warning("Meilisearch cleanup failed during account deletion", [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }

            // 10. Purge Redis cache for this user
            try {
                $redis = \Illuminate\Support\Facades\Cache::getStore();
                if (method_exists($redis, 'forget')) {
                    \Illuminate\Support\Facades\Cache::forget("user:{$userId}");
                    \Illuminate\Support\Facades\Cache::forget("user_profile:{$userId}");
                    \Illuminate\Support\Facades\Cache::forget("user_presence:{$userId}");
                }
            } catch (\Throwable $e) {
                // Cache purge is best-effort
            }

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
    // EXPIRED EXPORT CLEANUP (Data Minimization)
    // =========================================================================

    /**
     * Delete expired GDPR export files from disk.
     *
     * Exports are set to expire after 7 days (see generateDataExport).
     * This method should be called periodically (e.g. daily cron) to enforce
     * data minimization — GDPR Article 5(1)(e) storage limitation.
     *
     * @return int Number of expired exports cleaned up
     */
    public function cleanupExpiredExports(): int
    {
        $expired = $this->query(
            "SELECT id, export_file_path FROM gdpr_requests
             WHERE export_file_path IS NOT NULL
             AND export_expires_at IS NOT NULL
             AND export_expires_at < NOW()
             AND status = 'completed'"
        )->fetchAll();

        $cleaned = 0;
        foreach ($expired as $row) {
            $path = $row['export_file_path'];
            if ($path && file_exists($path)) {
                @unlink($path);
                $cleaned++;
            }

            // Clear the file path in the DB so we don't re-process
            $this->query(
                "UPDATE gdpr_requests SET export_file_path = NULL WHERE id = ?",
                [$row['id']]
            );
        }

        if ($cleaned > 0) {
            $this->logger->info("Cleaned up {$cleaned} expired GDPR export files");
        }

        return $cleaned;
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
                \App\Core\ClientIp::get(),
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
                $results[$slug] = self::updateUserConsent($userId, $slug, true);
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
                \App\Core\ClientIp::get(),
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

    private static function getStoragePath(string $path): string
    {
        $basePath = getenv('STORAGE_PATH') ?: __DIR__ . '/../../../storage';
        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }

    private static function generateHtmlExport(array $data): string
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

    private static function generateExportReadme(array $data): string
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

    private static function copyUserUploads(int $userId, string $destDir): void
    {
        $uploadsDir = self::getStoragePath("uploads/users/{$userId}");

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

    // =========================================================================
    // PII data export methods for identity, vetting, insurance, safeguarding
    // =========================================================================

    /**
     * Get vetting/background check records for a user (GDPR Article 15).
     */
    private function getVettingRecordsData(int $userId): array
    {
        try {
            return $this->query(
                "SELECT id, vetting_type, status, reference_number, issue_date, expiry_date,
                        works_with_children, works_with_vulnerable_adults, requires_enhanced_check,
                        notes, rejection_reason, created_at, updated_at
                 FROM vetting_records
                 WHERE user_id = ? AND tenant_id = ?
                 ORDER BY created_at DESC",
                [$userId, $this->tenantId]
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get insurance certificate records for a user (GDPR Article 15).
     */
    private function getInsuranceCertificatesData(int $userId): array
    {
        try {
            return $this->query(
                "SELECT id, insurance_type, provider_name, policy_number, coverage_amount,
                        start_date, expiry_date, status, notes, created_at, updated_at
                 FROM insurance_certificates
                 WHERE user_id = ? AND tenant_id = ?
                 ORDER BY created_at DESC",
                [$userId, $this->tenantId]
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get identity verification session records for a user (GDPR Article 15).
     * Excludes raw provider data/tokens — only includes status and metadata.
     */
    private function getIdentityVerificationData(int $userId): array
    {
        try {
            return $this->query(
                "SELECT id, provider_slug, verification_level, status,
                        failure_reason, created_at, completed_at
                 FROM identity_verification_sessions
                 WHERE user_id = ? AND tenant_id = ?
                 ORDER BY created_at DESC",
                [$userId, $this->tenantId]
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get safeguarding preference selections for a user (GDPR Article 15).
     */
    private function getSafeguardingPreferencesData(int $userId): array
    {
        try {
            return $this->query(
                "SELECT usp.id, tso.option_key, tso.label as option_label,
                        usp.selected_value, usp.notes, usp.consent_given_at, usp.revoked_at, usp.created_at
                 FROM user_safeguarding_preferences usp
                 LEFT JOIN tenant_safeguarding_options tso ON tso.id = usp.option_id
                 WHERE usp.user_id = ? AND usp.tenant_id = ?
                 ORDER BY usp.created_at DESC",
                [$userId, $this->tenantId]
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function deleteUserUploads(int $userId): void
    {
        $uploadsDir = self::getStoragePath("uploads/users/{$userId}");
        if (is_dir($uploadsDir)) {
            self::deleteDirectory($uploadsDir);
        }
    }

    private static function createZipArchive(string $sourceDir, string $zipPath): void
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

    private static function deleteDirectory(string $dir): void
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
