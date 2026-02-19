<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\VolOpportunity;
use Nexus\Models\VolApplication;
use Nexus\Models\VolShift;
use Nexus\Models\VolLog;
use Nexus\Models\VolOrganization;
use Nexus\Models\VolReview;
use Nexus\Models\ActivityLog;
use Nexus\Models\Transaction;

/**
 * VolunteerService - Business logic for volunteering operations
 *
 * Handles all volunteering module operations for both HTML and API controllers:
 * - Opportunities (listings for volunteering)
 * - Applications (user applies to volunteer)
 * - Shifts (time slots for opportunities)
 * - Hour logging and verification
 * - Organizations (volunteer orgs)
 * - Volunteering-specific reviews
 */
class VolunteerService
{
    /**
     * Validation errors
     */
    private static array $errors = [];

    /**
     * Get validation errors
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    // ========================================
    // OPPORTUNITIES
    // ========================================

    /**
     * Get opportunities with cursor-based pagination
     *
     * @param array $filters [
     *   'organization_id' => int,
     *   'category_id' => int,
     *   'search' => string,
     *   'is_remote' => bool,
     *   'active_only' => bool (default true),
     *   'cursor' => string,
     *   'limit' => int (default 20, max 50)
     * ]
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getOpportunities(array $filters = []): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $limit = min($filters['limit'] ?? 20, 50);
        $cursor = $filters['cursor'] ?? null;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT opp.*, org.name as org_name, org.logo_url as org_logo,
                   org.status as org_status, org.user_id as org_owner_id,
                   cat.name as category_name
            FROM vol_opportunities opp
            JOIN vol_organizations org ON opp.organization_id = org.id
            LEFT JOIN categories cat ON opp.category_id = cat.id
            WHERE org.tenant_id = ?
            AND org.status = 'approved'
        ";
        $params = [$tenantId];

        // Active only (default true)
        if ($filters['active_only'] ?? true) {
            $sql .= " AND opp.is_active = 1";
        }

        // Organization filter
        if (!empty($filters['organization_id'])) {
            $sql .= " AND opp.organization_id = ?";
            $params[] = $filters['organization_id'];
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $sql .= " AND opp.category_id = ?";
            $params[] = $filters['category_id'];
        }

        // Search filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $sql .= " AND (opp.title LIKE ? OR opp.description LIKE ? OR org.name LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Remote filter
        if (!empty($filters['is_remote'])) {
            $sql .= " AND (opp.is_remote = 1 OR opp.location LIKE '%Remote%')";
        }

        // Cursor pagination
        if ($cursorId) {
            $sql .= " AND opp.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY opp.created_at DESC, opp.id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $opportunities = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($opportunities) > $limit;
        if ($hasMore) {
            array_pop($opportunities);
        }

        $items = [];
        $lastId = null;

        foreach ($opportunities as $opp) {
            $lastId = $opp['id'];
            $items[] = self::formatOpportunity($opp);
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get single opportunity by ID
     *
     * @param int $id Opportunity ID
     * @param int|null $viewerId Optional viewer user ID (to check application status)
     * @return array|null
     */
    public static function getOpportunityById(int $id, ?int $viewerId = null): ?array
    {
        $opp = VolOpportunity::find($id);

        if (!$opp) {
            return null;
        }

        $formatted = self::formatOpportunity($opp);

        // Get shifts
        $formatted['shifts'] = self::getShiftsForOpportunity($id);

        // Check if viewer has applied
        if ($viewerId) {
            $formatted['has_applied'] = VolApplication::hasApplied($id, $viewerId);
            $formatted['application'] = self::getUserApplicationForOpportunity($id, $viewerId);
        }

        return $formatted;
    }

    /**
     * Create an opportunity
     *
     * @param int $userId User creating the opportunity
     * @param array $data Opportunity data
     * @return int|null Opportunity ID or null on failure
     */
    public static function createOpportunity(int $userId, array $data): ?int
    {
        self::$errors = [];

        // Validate required fields
        if (empty($data['organization_id'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Organization is required', 'field' => 'organization_id'];
            return null;
        }

        if (empty($data['title'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required', 'field' => 'title'];
            return null;
        }

        // Verify user owns the organization
        $org = VolOrganization::find($data['organization_id']);
        if (!$org || (int)$org['user_id'] !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not own this organization'];
            return null;
        }

        // Verify organization is approved
        if ($org['status'] !== 'approved') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Organization must be approved before creating opportunities'];
            return null;
        }

        try {
            $oppId = VolOpportunity::create(
                TenantContext::getId(),
                $userId,
                (int)$data['organization_id'],
                $data['title'],
                $data['description'] ?? '',
                $data['location'] ?? '',
                $data['skills_needed'] ?? '',
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['category_id'] ?? null
            );

            // Log activity
            try {
                ActivityLog::log($userId, 'posted a Volunteer Opportunity', $data['title'], true, '/volunteering/' . $oppId);
            } catch (\Throwable $e) {
                // Silent fail
            }

            // Gamification
            try {
                if (class_exists('\Nexus\Services\GamificationService')) {
                    GamificationService::checkVolunteeringBadges($userId);
                }
            } catch (\Throwable $e) {
                // Silent fail
            }

            return (int)$oppId;
        } catch (\Exception $e) {
            error_log("VolunteerService::createOpportunity error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create opportunity'];
            return null;
        }
    }

    /**
     * Update an opportunity
     *
     * @param int $id Opportunity ID
     * @param int $userId User making the update
     * @param array $data Updated data
     * @return bool Success
     */
    public static function updateOpportunity(int $id, int $userId, array $data): bool
    {
        self::$errors = [];

        $opp = VolOpportunity::find($id);
        if (!$opp) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Opportunity not found'];
            return false;
        }

        // Verify ownership
        if ((int)$opp['org_owner_id'] !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not own this opportunity'];
            return false;
        }

        try {
            VolOpportunity::update(
                $id,
                $data['title'] ?? $opp['title'],
                $data['description'] ?? $opp['description'],
                $data['location'] ?? $opp['location'],
                $data['skills_needed'] ?? $opp['skills_needed'],
                $data['start_date'] ?? $opp['start_date'],
                $data['end_date'] ?? $opp['end_date'],
                $data['category_id'] ?? $opp['category_id']
            );

            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::updateOpportunity error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update opportunity'];
            return false;
        }
    }

    /**
     * Delete (deactivate) an opportunity
     *
     * @param int $id Opportunity ID
     * @param int $userId User making the deletion
     * @return bool Success
     */
    public static function deleteOpportunity(int $id, int $userId): bool
    {
        self::$errors = [];

        $opp = VolOpportunity::find($id);
        if (!$opp) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Opportunity not found'];
            return false;
        }

        // Verify ownership
        if ((int)$opp['org_owner_id'] !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not own this opportunity'];
            return false;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE vol_opportunities SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::deleteOpportunity error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to delete opportunity'];
            return false;
        }
    }

    /**
     * Format opportunity for API response
     */
    private static function formatOpportunity(array $opp): array
    {
        return [
            'id' => (int)$opp['id'],
            'title' => $opp['title'],
            'description' => $opp['description'],
            'location' => $opp['location'],
            'skills_needed' => $opp['skills_needed'],
            'start_date' => $opp['start_date'],
            'end_date' => $opp['end_date'],
            'is_active' => (bool)($opp['is_active'] ?? true),
            'is_remote' => (bool)($opp['is_remote'] ?? false),
            'category' => $opp['category_name'] ?? null,
            'organization' => [
                'id' => (int)$opp['organization_id'],
                'name' => $opp['org_name'],
                'logo_url' => $opp['org_logo'] ?? $opp['logo_url'] ?? null,
            ],
            'created_at' => $opp['created_at'],
        ];
    }

    // ========================================
    // APPLICATIONS
    // ========================================

    /**
     * Apply to an opportunity
     *
     * @param int $userId User applying
     * @param int $opportunityId Opportunity ID
     * @param array $data Application data (message, shift_id)
     * @return int|null Application ID or null on failure
     */
    public static function apply(int $userId, int $opportunityId, array $data = []): ?int
    {
        self::$errors = [];

        // Check opportunity exists and is active
        $opp = VolOpportunity::find($opportunityId);
        if (!$opp) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Opportunity not found'];
            return null;
        }

        if (!$opp['is_active']) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This opportunity is no longer accepting applications'];
            return null;
        }

        // Check not already applied
        if (VolApplication::hasApplied($opportunityId, $userId)) {
            self::$errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'You have already applied to this opportunity'];
            return null;
        }

        // Validate shift if provided
        $shiftId = $data['shift_id'] ?? null;
        if ($shiftId) {
            $shift = VolShift::find($shiftId);
            if (!$shift || (int)$shift['opportunity_id'] !== $opportunityId) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid shift', 'field' => 'shift_id'];
                return null;
            }

            // Check shift capacity
            $signupCount = self::getShiftSignupCount($shiftId);
            if ($shift['capacity'] && $signupCount >= (int)$shift['capacity']) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This shift is at capacity', 'field' => 'shift_id'];
                return null;
            }
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO vol_applications (opportunity_id, user_id, message, shift_id, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$opportunityId, $userId, $data['message'] ?? '', $shiftId]);

            $appId = $db->lastInsertId();

            // Notify org owner
            try {
                if (!empty($opp['org_email'])) {
                    $mailer = new \Nexus\Core\Mailer();
                    $subject = "New Volunteer Application: " . $opp['title'];
                    $body = "<h2>New Applicant!</h2><p>You have received a new application for <strong>{$opp['title']}</strong>.</p><p>Check your dashboard to review it.</p>";
                    $mailer->send($opp['org_email'], $subject, $body);
                }
            } catch (\Throwable $e) {
                // Silent fail
            }

            return (int)$appId;
        } catch (\Exception $e) {
            error_log("VolunteerService::apply error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to submit application'];
            return null;
        }
    }

    /**
     * Withdraw an application
     *
     * @param int $applicationId Application ID
     * @param int $userId User withdrawing
     * @return bool Success
     */
    public static function withdrawApplication(int $applicationId, int $userId): bool
    {
        self::$errors = [];

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM vol_applications WHERE id = ?");
        $stmt->execute([$applicationId]);
        $app = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$app) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Application not found'];
            return false;
        }

        if ((int)$app['user_id'] !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'This is not your application'];
            return false;
        }

        try {
            $stmt = $db->prepare("DELETE FROM vol_applications WHERE id = ?");
            $stmt->execute([$applicationId]);
            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::withdrawApplication error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to withdraw application'];
            return false;
        }
    }

    /**
     * Get user's applications with cursor pagination
     *
     * @param int $userId User ID
     * @param array $filters [status, cursor, limit]
     * @return array
     */
    public static function getMyApplications(int $userId, array $filters = []): array
    {
        $db = Database::getConnection();

        $limit = min($filters['limit'] ?? 20, 50);
        $cursor = $filters['cursor'] ?? null;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT a.*, o.title as opp_title, o.location, org.name as org_name, org.logo_url as org_logo,
                   s.start_time as shift_start, s.end_time as shift_end
            FROM vol_applications a
            JOIN vol_opportunities o ON a.opportunity_id = o.id
            JOIN vol_organizations org ON o.organization_id = org.id
            LEFT JOIN vol_shifts s ON a.shift_id = s.id
            WHERE a.user_id = ?
        ";
        $params = [$userId];

        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }

        if ($cursorId) {
            $sql .= " AND a.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY a.created_at DESC, a.id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($applications) > $limit;
        if ($hasMore) {
            array_pop($applications);
        }

        $items = [];
        $lastId = null;

        foreach ($applications as $app) {
            $lastId = $app['id'];
            $items[] = [
                'id' => (int)$app['id'],
                'status' => $app['status'],
                'message' => $app['message'],
                'opportunity' => [
                    'id' => (int)$app['opportunity_id'],
                    'title' => $app['opp_title'],
                    'location' => $app['location'],
                ],
                'organization' => [
                    'name' => $app['org_name'],
                    'logo_url' => $app['org_logo'],
                ],
                'shift' => $app['shift_id'] ? [
                    'id' => (int)$app['shift_id'],
                    'start_time' => $app['shift_start'],
                    'end_time' => $app['shift_end'],
                ] : null,
                'created_at' => $app['created_at'],
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get applications for an opportunity (org admin only)
     *
     * @param int $opportunityId Opportunity ID
     * @param int $adminUserId Admin user ID
     * @param array $filters [status, cursor, limit]
     * @return array|null Returns null if not authorized
     */
    public static function getApplicationsForOpportunity(int $opportunityId, int $adminUserId, array $filters = []): ?array
    {
        self::$errors = [];

        // Verify admin owns the opportunity
        $opp = VolOpportunity::find($opportunityId);
        if (!$opp) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Opportunity not found'];
            return null;
        }

        if ((int)$opp['org_owner_id'] !== $adminUserId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not own this opportunity'];
            return null;
        }

        $db = Database::getConnection();

        $limit = min($filters['limit'] ?? 20, 50);
        $cursor = $filters['cursor'] ?? null;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT a.*, u.name as user_name, u.email as user_email, u.avatar_url as user_avatar,
                   s.start_time as shift_start, s.end_time as shift_end
            FROM vol_applications a
            JOIN users u ON a.user_id = u.id
            LEFT JOIN vol_shifts s ON a.shift_id = s.id
            WHERE a.opportunity_id = ?
        ";
        $params = [$opportunityId];

        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }

        if ($cursorId) {
            $sql .= " AND a.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY a.created_at DESC, a.id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($applications) > $limit;
        if ($hasMore) {
            array_pop($applications);
        }

        $items = [];
        $lastId = null;

        foreach ($applications as $app) {
            $lastId = $app['id'];
            $items[] = [
                'id' => (int)$app['id'],
                'status' => $app['status'],
                'message' => $app['message'],
                'user' => [
                    'id' => (int)$app['user_id'],
                    'name' => $app['user_name'],
                    'email' => $app['user_email'],
                    'avatar_url' => $app['user_avatar'],
                ],
                'shift' => $app['shift_id'] ? [
                    'id' => (int)$app['shift_id'],
                    'start_time' => $app['shift_start'],
                    'end_time' => $app['shift_end'],
                ] : null,
                'created_at' => $app['created_at'],
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Handle application (approve/decline)
     *
     * @param int $applicationId Application ID
     * @param int $adminUserId Admin user ID
     * @param string $action 'approve' or 'decline'
     * @return bool Success
     */
    public static function handleApplication(int $applicationId, int $adminUserId, string $action): bool
    {
        self::$errors = [];

        if (!in_array($action, ['approve', 'decline'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be approve or decline'];
            return false;
        }

        $db = Database::getConnection();

        // Get application with ownership check
        $stmt = $db->prepare("
            SELECT a.*, org.user_id as org_owner_id
            FROM vol_applications a
            JOIN vol_opportunities opp ON a.opportunity_id = opp.id
            JOIN vol_organizations org ON opp.organization_id = org.id
            WHERE a.id = ?
        ");
        $stmt->execute([$applicationId]);
        $app = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$app) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Application not found'];
            return false;
        }

        if ((int)$app['org_owner_id'] !== $adminUserId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not own this opportunity'];
            return false;
        }

        $status = $action === 'approve' ? 'approved' : 'declined';

        try {
            $stmt = $db->prepare("UPDATE vol_applications SET status = ? WHERE id = ?");
            $stmt->execute([$status, $applicationId]);

            // Notify applicant
            try {
                $applicant = \Nexus\Models\User::findById($app['user_id']);
                if ($applicant && !empty($applicant['email'])) {
                    $mailer = new \Nexus\Core\Mailer();
                    $subject = "Update on your Volunteer Application";
                    $body = "<h2>Application Update</h2><p>Your application has been <strong>" . strtoupper($status) . "</strong>.</p>";
                    $mailer->send($applicant['email'], $subject, $body);
                }
            } catch (\Throwable $e) {
                // Silent fail
            }

            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::handleApplication error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update application'];
            return false;
        }
    }

    /**
     * Get user's application for a specific opportunity
     */
    private static function getUserApplicationForOpportunity(int $opportunityId, int $userId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM vol_applications WHERE opportunity_id = ? AND user_id = ?");
        $stmt->execute([$opportunityId, $userId]);
        $app = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$app) {
            return null;
        }

        return [
            'id' => (int)$app['id'],
            'status' => $app['status'],
            'message' => $app['message'],
            'shift_id' => $app['shift_id'] ? (int)$app['shift_id'] : null,
            'created_at' => $app['created_at'],
        ];
    }

    // ========================================
    // SHIFTS
    // ========================================

    /**
     * Get shifts for an opportunity
     */
    public static function getShiftsForOpportunity(int $opportunityId): array
    {
        $shifts = VolShift::getForOpportunity($opportunityId);

        return array_map(function ($shift) {
            $signupCount = self::getShiftSignupCount($shift['id']);
            return [
                'id' => (int)$shift['id'],
                'start_time' => $shift['start_time'],
                'end_time' => $shift['end_time'],
                'capacity' => $shift['capacity'] ? (int)$shift['capacity'] : null,
                'signup_count' => $signupCount,
                'spots_available' => $shift['capacity'] ? max(0, (int)$shift['capacity'] - $signupCount) : null,
            ];
        }, $shifts);
    }

    /**
     * Get user's shifts (upcoming and past)
     *
     * @param int $userId User ID
     * @param array $filters [upcoming_only, cursor, limit]
     * @return array
     */
    public static function getMyShifts(int $userId, array $filters = []): array
    {
        $db = Database::getConnection();

        $limit = min($filters['limit'] ?? 20, 50);
        $cursor = $filters['cursor'] ?? null;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT a.id as application_id, a.status as app_status,
                   s.*, o.title as opp_title, org.name as org_name, org.logo_url as org_logo
            FROM vol_applications a
            JOIN vol_shifts s ON a.shift_id = s.id
            JOIN vol_opportunities o ON a.opportunity_id = o.id
            JOIN vol_organizations org ON o.organization_id = org.id
            WHERE a.user_id = ?
            AND a.status = 'approved'
            AND a.shift_id IS NOT NULL
        ";
        $params = [$userId];

        if (!empty($filters['upcoming_only'])) {
            $sql .= " AND s.start_time > NOW()";
        }

        if ($cursorId) {
            $sql .= " AND s.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY s.start_time ASC, s.id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($shifts) > $limit;
        if ($hasMore) {
            array_pop($shifts);
        }

        $items = [];
        $lastId = null;

        foreach ($shifts as $shift) {
            $lastId = $shift['id'];
            $items[] = [
                'id' => (int)$shift['id'],
                'application_id' => (int)$shift['application_id'],
                'start_time' => $shift['start_time'],
                'end_time' => $shift['end_time'],
                'opportunity' => [
                    'id' => (int)$shift['opportunity_id'],
                    'title' => $shift['opp_title'],
                ],
                'organization' => [
                    'name' => $shift['org_name'],
                    'logo_url' => $shift['org_logo'],
                ],
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Sign up for a shift (requires approved application)
     *
     * @param int $shiftId Shift ID
     * @param int $userId User ID
     * @return bool Success
     */
    public static function signUpForShift(int $shiftId, int $userId): bool
    {
        self::$errors = [];

        $shift = VolShift::find($shiftId);
        if (!$shift) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
            return false;
        }

        $opportunityId = $shift['opportunity_id'];

        // Check user has approved application for this opportunity
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM vol_applications WHERE opportunity_id = ? AND user_id = ? AND status = 'approved'");
        $stmt->execute([$opportunityId, $userId]);
        $app = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$app) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must have an approved application to sign up for shifts'];
            return false;
        }

        // Check capacity
        $signupCount = self::getShiftSignupCount($shiftId);
        if ($shift['capacity'] && $signupCount >= (int)$shift['capacity']) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This shift is at capacity'];
            return false;
        }

        // Check shift hasn't passed
        if (strtotime($shift['start_time']) < time()) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This shift has already started'];
            return false;
        }

        try {
            // Update application with shift assignment
            $stmt = $db->prepare("UPDATE vol_applications SET shift_id = ? WHERE id = ?");
            $stmt->execute([$shiftId, $app['id']]);
            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::signUpForShift error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to sign up for shift'];
            return false;
        }
    }

    /**
     * Cancel shift signup
     *
     * @param int $shiftId Shift ID
     * @param int $userId User ID
     * @return bool Success
     */
    public static function cancelShiftSignup(int $shiftId, int $userId): bool
    {
        self::$errors = [];

        $shift = VolShift::find($shiftId);
        if (!$shift) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
            return false;
        }

        // Check shift is in the future (allow cancellation up to shift start)
        if (strtotime($shift['start_time']) < time()) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot cancel a shift that has already started'];
            return false;
        }

        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("UPDATE vol_applications SET shift_id = NULL WHERE opportunity_id = ? AND user_id = ? AND shift_id = ?");
            $stmt->execute([$shift['opportunity_id'], $userId, $shiftId]);

            if ($stmt->rowCount() === 0) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'You are not signed up for this shift'];
                return false;
            }

            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::cancelShiftSignup error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel shift signup'];
            return false;
        }
    }

    /**
     * Get count of signups for a shift
     */
    private static function getShiftSignupCount(int $shiftId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM vol_applications WHERE shift_id = ? AND status = 'approved'");
        $stmt->execute([$shiftId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)($result['cnt'] ?? 0);
    }

    // ========================================
    // HOUR LOGGING
    // ========================================

    /**
     * Log volunteering hours
     *
     * @param int $userId User logging hours
     * @param array $data [organization_id, opportunity_id, date, hours, description]
     * @return int|null Log ID or null on failure
     */
    public static function logHours(int $userId, array $data): ?int
    {
        self::$errors = [];

        if (empty($data['organization_id'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Organization is required', 'field' => 'organization_id'];
            return null;
        }

        if (empty($data['date'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Date is required', 'field' => 'date'];
            return null;
        }

        if (empty($data['hours']) || $data['hours'] <= 0) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Hours must be greater than 0', 'field' => 'hours'];
            return null;
        }

        // Validate date is not in the future
        if (strtotime($data['date']) > time()) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot log hours for a future date', 'field' => 'date'];
            return null;
        }

        // Verify organization exists
        $org = VolOrganization::find($data['organization_id']);
        if (!$org) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Organization not found'];
            return null;
        }

        try {
            VolLog::create(
                $userId,
                (int)$data['organization_id'],
                $data['opportunity_id'] ?? null,
                $data['date'],
                (float)$data['hours'],
                $data['description'] ?? ''
            );

            $db = Database::getConnection();
            return (int)$db->lastInsertId();
        } catch (\Exception $e) {
            error_log("VolunteerService::logHours error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to log hours'];
            return null;
        }
    }

    /**
     * Get user's logged hours with cursor pagination
     *
     * @param int $userId User ID
     * @param array $filters [status, cursor, limit]
     * @return array
     */
    public static function getMyHours(int $userId, array $filters = []): array
    {
        $db = Database::getConnection();

        $limit = min($filters['limit'] ?? 20, 50);
        $cursor = $filters['cursor'] ?? null;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT l.*, org.name as org_name, org.logo_url as org_logo, opp.title as opp_title
            FROM vol_logs l
            LEFT JOIN vol_organizations org ON l.organization_id = org.id
            LEFT JOIN vol_opportunities opp ON l.opportunity_id = opp.id
            WHERE l.user_id = ?
        ";
        $params = [$userId];

        if (!empty($filters['status'])) {
            $sql .= " AND l.status = ?";
            $params[] = $filters['status'];
        }

        if ($cursorId) {
            $sql .= " AND l.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY l.date_logged DESC, l.id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($logs) > $limit;
        if ($hasMore) {
            array_pop($logs);
        }

        $items = [];
        $lastId = null;

        foreach ($logs as $log) {
            $lastId = $log['id'];
            $items[] = [
                'id' => (int)$log['id'],
                'hours' => (float)$log['hours'],
                'date' => $log['date_logged'],
                'description' => $log['description'],
                'status' => $log['status'] ?? 'pending',
                'organization' => [
                    'id' => (int)$log['organization_id'],
                    'name' => $log['org_name'],
                    'logo_url' => $log['org_logo'],
                ],
                'opportunity' => $log['opportunity_id'] ? [
                    'id' => (int)$log['opportunity_id'],
                    'title' => $log['opp_title'],
                ] : null,
                'created_at' => $log['created_at'],
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Verify hours (org admin)
     *
     * @param int $logId Log ID
     * @param int $adminUserId Admin user ID
     * @param string $action 'approve' or 'decline'
     * @return bool Success
     */
    public static function verifyHours(int $logId, int $adminUserId, string $action): bool
    {
        self::$errors = [];

        if (!in_array($action, ['approve', 'decline'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be approve or decline'];
            return false;
        }

        $log = VolLog::find($logId);
        if (!$log) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Log entry not found'];
            return false;
        }

        $org = VolOrganization::find($log['organization_id']);
        if (!$org || (int)$org['user_id'] !== $adminUserId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not own this organization'];
            return false;
        }

        $status = $action === 'approve' ? 'approved' : 'declined';

        try {
            VolLog::updateStatus($logId, $status);

            // Auto-pay if enabled and approved
            if ($status === 'approved' && TenantContext::hasFeature('wallet') && $org['auto_pay_enabled']) {
                try {
                    $amount = (float)$log['hours'];
                    $desc = "Volunteering hours at " . $org['name'];

                    Transaction::create($adminUserId, $log['user_id'], $amount, $desc);

                    // Gamification
                    GamificationService::checkTimebankingBadges($adminUserId);
                    GamificationService::checkTimebankingBadges($log['user_id']);
                } catch (\Throwable $e) {
                    error_log("Auto-pay failed: " . $e->getMessage());
                }
            }

            // Gamification for verified hours
            if ($status === 'approved') {
                try {
                    GamificationService::checkVolunteeringBadges($log['user_id']);
                } catch (\Throwable $e) {
                    // Silent fail
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::verifyHours error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to verify hours'];
            return false;
        }
    }

    /**
     * Get hours summary for a user
     *
     * @param int $userId User ID
     * @return array Summary stats
     */
    public static function getHoursSummary(int $userId): array
    {
        $db = Database::getConnection();

        // Total verified hours
        $totalVerified = VolLog::getTotalVerifiedHours($userId);

        // Hours by status
        $stmt = $db->prepare("
            SELECT status, SUM(hours) as total
            FROM vol_logs
            WHERE user_id = ?
            GROUP BY status
        ");
        $stmt->execute([$userId]);
        $byStatus = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Hours by organization
        $stmt = $db->prepare("
            SELECT org.name, SUM(l.hours) as total
            FROM vol_logs l
            JOIN vol_organizations org ON l.organization_id = org.id
            WHERE l.user_id = ? AND l.status = 'approved'
            GROUP BY org.id
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $byOrg = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Hours by month (last 12 months)
        $stmt = $db->prepare("
            SELECT DATE_FORMAT(date_logged, '%Y-%m') as month, SUM(hours) as total
            FROM vol_logs
            WHERE user_id = ? AND status = 'approved'
            AND date_logged >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month DESC
        ");
        $stmt->execute([$userId]);
        $byMonth = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'total_verified' => $totalVerified,
            'total_pending' => (float)($byStatus['pending'] ?? 0),
            'total_declined' => (float)($byStatus['declined'] ?? 0),
            'by_organization' => $byOrg,
            'by_month' => $byMonth,
        ];
    }

    // ========================================
    // ORGANIZATIONS
    // ========================================

    /**
     * Get organizations with cursor pagination
     *
     * @param array $filters [search, cursor, limit]
     * @return array
     */
    public static function getOrganizations(array $filters = []): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $limit = min($filters['limit'] ?? 20, 50);
        $cursor = $filters['cursor'] ?? null;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT vo.*, u.name as owner_name, u.avatar_url as owner_avatar,
                   (SELECT COUNT(*) FROM vol_opportunities WHERE organization_id = vo.id AND is_active = 1) as opportunity_count
            FROM vol_organizations vo
            JOIN users u ON vo.user_id = u.id
            WHERE vo.tenant_id = ? AND vo.status = 'approved'
        ";
        $params = [$tenantId];

        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $sql .= " AND (vo.name LIKE ? OR vo.description LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($cursorId) {
            $sql .= " AND vo.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY vo.name ASC, vo.id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $organizations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($organizations) > $limit;
        if ($hasMore) {
            array_pop($organizations);
        }

        $items = [];
        $lastId = null;

        foreach ($organizations as $org) {
            $lastId = $org['id'];
            $items[] = [
                'id' => (int)$org['id'],
                'name' => $org['name'],
                'description' => $org['description'],
                'logo_url' => $org['logo_url'] ?? null,
                'website' => $org['website'],
                'contact_email' => $org['contact_email'],
                'opportunity_count' => (int)$org['opportunity_count'],
                'owner' => [
                    'name' => $org['owner_name'],
                    'avatar_url' => $org['owner_avatar'],
                ],
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get single organization by ID
     *
     * @param int $id Organization ID
     * @return array|null
     */
    public static function getOrganizationById(int $id): ?array
    {
        $org = VolOrganization::find($id);

        if (!$org) {
            return null;
        }

        $db = Database::getConnection();

        // Get opportunity count
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM vol_opportunities WHERE organization_id = ? AND is_active = 1");
        $stmt->execute([$id]);
        $oppCount = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        // Get total hours logged
        $stmt = $db->prepare("SELECT SUM(hours) as total FROM vol_logs WHERE organization_id = ? AND status = 'approved'");
        $stmt->execute([$id]);
        $totalHours = (float)($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

        // Get reviews
        $reviews = VolReview::getForTarget('organization', $id);

        $avgRating = 0;
        if (!empty($reviews)) {
            $avgRating = array_sum(array_column($reviews, 'rating')) / count($reviews);
        }

        return [
            'id' => (int)$org['id'],
            'name' => $org['name'],
            'description' => $org['description'],
            'logo_url' => $org['logo_url'] ?? null,
            'website' => $org['website'],
            'contact_email' => $org['contact_email'],
            'status' => $org['status'],
            'stats' => [
                'opportunity_count' => $oppCount,
                'total_hours_logged' => $totalHours,
                'review_count' => count($reviews),
                'average_rating' => round($avgRating, 1),
            ],
        ];
    }

    // ========================================
    // VOLUNTEERING REVIEWS
    // ========================================

    /**
     * Create a volunteering review
     *
     * @param int $reviewerId User leaving review
     * @param string $targetType 'organization' or 'user'
     * @param int $targetId Target ID
     * @param int $rating 1-5
     * @param string $comment Review text
     * @return int|null Review ID or null on failure
     */
    public static function createReview(int $reviewerId, string $targetType, int $targetId, int $rating, string $comment = ''): ?int
    {
        self::$errors = [];

        if (!in_array($targetType, ['organization', 'user'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Target type must be organization or user'];
            return null;
        }

        if ($rating < 1 || $rating > 5) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Rating must be between 1 and 5', 'field' => 'rating'];
            return null;
        }

        // Verify target exists
        if ($targetType === 'organization') {
            $org = VolOrganization::find($targetId);
            if (!$org) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Organization not found'];
                return null;
            }
        } else {
            $user = \Nexus\Models\User::findById($targetId);
            if (!$user) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'User not found'];
                return null;
            }
        }

        try {
            VolReview::create($reviewerId, $targetType, $targetId, $rating, $comment);

            $db = Database::getConnection();
            $reviewId = (int)$db->lastInsertId();

            // Notification
            try {
                $sender = \Nexus\Models\User::findById($reviewerId);
                $receiverId = null;

                if ($targetType === 'user') {
                    $receiverId = $targetId;
                } elseif ($targetType === 'organization') {
                    $org = VolOrganization::find($targetId);
                    if ($org) {
                        $receiverId = $org['user_id'];
                    }
                }

                if ($receiverId) {
                    $content = "You received a new {$rating}-star volunteering review from {$sender['first_name']}.";
                    NotificationDispatcher::dispatch(
                        $receiverId,
                        'global',
                        0,
                        'new_review',
                        $content,
                        '/volunteering/dashboard',
                        null
                    );
                }
            } catch (\Throwable $e) {
                // Silent fail
            }

            return $reviewId;
        } catch (\Exception $e) {
            error_log("VolunteerService::createReview error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create review'];
            return null;
        }
    }

    /**
     * Get reviews for a target
     *
     * @param string $targetType 'organization' or 'user'
     * @param int $targetId Target ID
     * @return array
     */
    public static function getReviews(string $targetType, int $targetId): array
    {
        $reviews = VolReview::getForTarget($targetType, $targetId);

        return array_map(function ($r) {
            return [
                'id' => (int)$r['id'],
                'rating' => (int)$r['rating'],
                'comment' => $r['comment'],
                'reviewer' => [
                    'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    'avatar_url' => $r['avatar_url'] ?? null,
                ],
                'created_at' => $r['created_at'],
            ];
        }, $reviews);
    }
}
