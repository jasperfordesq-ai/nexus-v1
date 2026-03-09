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
use Nexus\Models\OrgMember;
use Nexus\Models\ActivityLog;
use Nexus\Models\Transaction;
use Nexus\Services\EmailTemplateService;
use Nexus\Services\NotificationDispatcher;

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
     * Cached decline status value for vol_logs (declined vs rejected schema variants)
     */
    private static ?string $declineStatusValue = null;

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
            $escapedSearch = addcslashes($filters['search'], '%_');
            $searchTerm = '%' . $escapedSearch . '%';
            $sql .= " AND (opp.title LIKE ? OR opp.description LIKE ? OR org.name LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Remote filter
        if (!empty($filters['is_remote'])) {
            $sql .= " AND opp.location LIKE ?";
            $params[] = '%Remote%';
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
     * Check if a user can manage an opportunity.
     * True if: org creator, org owner/admin in org_members, or site admin/tenant_admin.
     */
    private static function canManageOpportunity(array $opp, int $userId): bool
    {
        // Direct org creator
        if ((int)($opp['org_owner_id'] ?? 0) === $userId) {
            return true;
        }
        // Site-level admin (admin, tenant_admin, super_admin) can manage any opportunity
        $siteRole = Database::query(
            'SELECT role FROM users WHERE id = ?',
            [$userId]
        )->fetchColumn();
        if (in_array($siteRole, ['super_admin', 'admin', 'tenant_admin'], true)) {
            return true;
        }
        // Org-level owner/admin in org_members
        $orgId = (int)($opp['organization_id'] ?? 0);
        if ($orgId <= 0) {
            return false;
        }
        $orgRole = Database::query(
            "SELECT role FROM org_members WHERE tenant_id = ? AND organization_id = ? AND user_id = ? AND status = 'active'",
            [TenantContext::getId(), $orgId, $userId]
        )->fetchColumn();
        return in_array($orgRole, ['owner', 'admin'], true);
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
            $formatted['is_owner'] = self::canManageOpportunity($opp, $viewerId);
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

        // Verify user can manage the organization (owner or org admin)
        $org = VolOrganization::find($data['organization_id']);
        if (!$org) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Organization not found'];
            return null;
        }
        $orgAdminRole = Database::query(
            "SELECT role FROM org_members WHERE tenant_id = ? AND organization_id = ? AND user_id = ? AND status = 'active'",
            [TenantContext::getId(), (int)$org['id'], $userId]
        )->fetchColumn();
        if ((int)$org['user_id'] !== $userId && !in_array($orgAdminRole, ['owner', 'admin'], true)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this organization'];
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

            // Record in feed_activity table
            try {
                FeedActivityService::recordActivity(TenantContext::getId(), $userId, 'volunteer', (int)$oppId, [
                    'title' => $data['title'],
                    'content' => $data['description'] ?? '',
                    'metadata' => [
                        'location' => $data['location'] ?? null,
                        'credits_offered' => $data['credits_offered'] ?? null,
                        'organization' => $org['name'] ?? null,
                    ],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $faEx) {
                error_log("VolunteerService::createOpportunity feed_activity record failed: " . $faEx->getMessage());
            }

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
        if (!self::canManageOpportunity($opp, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this opportunity'];
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
        if (!self::canManageOpportunity($opp, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this opportunity'];
            return false;
        }

        try {
            $db = Database::getConnection();
            $tenantId = TenantContext::getId();

            // Fetch pending applicants before deactivating
            $pendingStmt = $db->prepare("SELECT user_id FROM vol_applications WHERE opportunity_id = ? AND status = 'pending' AND tenant_id = ?");
            $pendingStmt->execute([$id, $tenantId]);
            $pendingApplicants = $pendingStmt->fetchAll(\PDO::FETCH_COLUMN);

            $stmt = $db->prepare("UPDATE vol_opportunities SET is_active = 0 WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenantId]);

            // Notify pending applicants
            try {
                foreach ($pendingApplicants as $applicantId) {
                    $content = "The volunteer opportunity \"{$opp['title']}\" is no longer available.";
                    NotificationDispatcher::dispatch(
                        (int)$applicantId,
                        'volunteering',
                        $id,
                        'vol_opportunity_closed',
                        $content,
                        '/volunteering',
                        "<p>{$content}</p>"
                    );
                }
            } catch (\Throwable $e) {
                // Silent fail
            }

            // Hide from feed_activity
            try {
                FeedActivityService::hideActivity('volunteer', $id);
            } catch (\Exception $faEx) {
                error_log("VolunteerService::deleteOpportunity feed_activity hide failed: " . $faEx->getMessage());
            }

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

        // Cap message length
        if (!empty($data['message']) && strlen($data['message']) > 1000) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Application message must be 1000 characters or fewer', 'field' => 'message'];
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
            $tenantId = TenantContext::getId();
            $stmt = $db->prepare("INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, message, shift_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$tenantId, $opportunityId, $userId, $data['message'] ?? '', $shiftId]);

            $appId = $db->lastInsertId();

            // Notify org owner — in-app bell + email
            try {
                $applierName = 'Someone';
                $orgOwnerId = (int)($opp['org_owner_id'] ?? 0);
                if ($orgOwnerId > 0) {
                    $applier = \Nexus\Models\User::findById($userId);
                    $applierName = $applier ? trim(($applier['first_name'] ?? '') . ' ' . ($applier['last_name'] ?? '')) : 'Someone';
                    $notifContent = "{$applierName} applied for your volunteer opportunity: {$opp['title']}";
                    $notifLink = '/volunteering/opportunities/' . $opportunityId . '/applications';
                    NotificationDispatcher::dispatch(
                        $orgOwnerId,
                        'volunteering',
                        $opportunityId,
                        'vol_application_received',
                        $notifContent,
                        $notifLink,
                        "<p>{$notifContent}</p>",
                        true
                    );
                }
                if (!empty($opp['org_email'])) {
                    $mailer = new \Nexus\Core\Mailer();
                    $subject = "New Volunteer Application: " . $opp['title'];
                    $body = "<h2>New Volunteer Application</h2>" .
                        "<p><strong>{$applierName}</strong> has applied for your opportunity: <strong>" . htmlspecialchars($opp['title']) . "</strong></p>" .
                        (!empty($data['message']) ? "<p><em>Their message:</em> " . htmlspecialchars($data['message']) . "</p>" : "") .
                        "<p>Log in to review and respond to their application.</p>";
                    $mailer->send($opp['org_email'], (TenantContext::get()['name'] ?? 'NEXUS') . ': ' . $subject, EmailTemplateService::wrap($body));
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
        $tenantId = TenantContext::getId();
        $stmt = $db->prepare("
            SELECT a.*, opp.title, opp.id as opportunity_id, org.user_id as org_owner_id
            FROM vol_applications a
            JOIN vol_opportunities opp ON a.opportunity_id = opp.id
            JOIN vol_organizations org ON opp.organization_id = org.id
            WHERE a.id = ? AND a.tenant_id = ?
        ");
        $stmt->execute([$applicationId, $tenantId]);
        $app = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$app) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Application not found'];
            return false;
        }

        if ((int)$app['user_id'] !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'This is not your application'];
            return false;
        }

        if ($app['status'] === 'approved') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'You cannot withdraw an approved application. Please contact the organisation directly.'];
            return false;
        }

        try {
            $stmt = $db->prepare("DELETE FROM vol_applications WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$applicationId, $tenantId]);

            // Notify org owner
            try {
                $orgOwnerId = (int)($app['org_owner_id'] ?? 0);
                if ($orgOwnerId > 0) {
                    $withdrawer = \Nexus\Models\User::findById($userId);
                    $withdrawerName = $withdrawer ? trim(($withdrawer['first_name'] ?? '') . ' ' . ($withdrawer['last_name'] ?? '')) : 'A volunteer';
                    $content = "{$withdrawerName} withdrew their application for: {$app['title']}";
                    NotificationDispatcher::dispatch(
                        $orgOwnerId,
                        'volunteering',
                        (int)$app['opportunity_id'],
                        'vol_application_withdrawn',
                        $content,
                        '/volunteering/opportunities/' . $app['opportunity_id'] . '/applications',
                        "<p>{$content}</p>",
                        true
                    );
                }
            } catch (\Throwable $e) {
                // Silent fail
            }

            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::withdrawApplication error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', "message" => 'Failed to withdraw application'];
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

        $tenantId = TenantContext::getId();

        $sql = "
            SELECT a.*, a.org_note, o.title as opp_title, o.location, org.id as org_id, org.name as org_name, org.logo_url as org_logo,
                   s.start_time as shift_start, s.end_time as shift_end
            FROM vol_applications a
            JOIN vol_opportunities o ON a.opportunity_id = o.id
            JOIN vol_organizations org ON o.organization_id = org.id
            LEFT JOIN vol_shifts s ON a.shift_id = s.id
            WHERE a.user_id = ? AND a.tenant_id = ?
        ";
        $params = [$userId, $tenantId];

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
                'org_note' => $app['org_note'] ?? null,
                'opportunity' => [
                    'id' => (int)$app['opportunity_id'],
                    'title' => $app['opp_title'],
                    'location' => $app['location'],
                ],
                'organization' => [
                    'id' => (int)$app['org_id'],
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

        if (!self::canManageOpportunity($opp, $adminUserId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this opportunity'];
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
            SELECT a.*, a.org_note, u.name as user_name, u.email as user_email, u.avatar_url as user_avatar,
                   s.start_time as shift_start, s.end_time as shift_end
            FROM vol_applications a
            JOIN users u ON a.user_id = u.id
            LEFT JOIN vol_shifts s ON a.shift_id = s.id
            WHERE a.opportunity_id = ? AND a.tenant_id = ?
        ";
        $params = [$opportunityId, TenantContext::getId()];

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
                'org_note' => $app['org_note'] ?? null,
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
     * @param string $orgNote Optional note from the organiser to the applicant
     * @return bool Success
     */
    public static function handleApplication(int $applicationId, int $adminUserId, string $action, string $orgNote = ''): bool
    {
        self::$errors = [];

        if (!in_array($action, ['approve', 'decline'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be approve or decline'];
            return false;
        }

        $db = Database::getConnection();

        $tenantId = TenantContext::getId();

        // Get application with ownership check and tenant scoping
        $stmt = $db->prepare("
            SELECT a.*, opp.title, opp.organization_id, org.user_id as org_owner_id
            FROM vol_applications a
            JOIN vol_opportunities opp ON a.opportunity_id = opp.id
            JOIN vol_organizations org ON opp.organization_id = org.id
            WHERE a.id = ? AND a.tenant_id = ?
        ");
        $stmt->execute([$applicationId, $tenantId]);
        $app = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$app) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Application not found'];
            return false;
        }

        if (!self::canManageOpportunity($app, $adminUserId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this opportunity'];
            return false;
        }

        $status = $action === 'approve' ? 'approved' : 'declined';

        try {
            $stmt = $db->prepare("UPDATE vol_applications SET status = ?, org_note = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$status, $orgNote !== '' ? $orgNote : null, $applicationId, $tenantId]);

            // Notify applicant — in-app bell + email
            try {
                $applicantId = (int)$app['user_id'];
                $oppTitle = $app['title'] ?? 'your volunteer opportunity';
                $statusLabel = $status === 'approved' ? 'approved' : 'declined';
                $notifContent = "Your volunteer application has been {$statusLabel}: {$oppTitle}";
                $notifLink = '/volunteering/my-applications';
                NotificationDispatcher::dispatch(
                    $applicantId,
                    'volunteering',
                    (int)$app['opportunity_id'],
                    'vol_application_' . $statusLabel,
                    $notifContent,
                    $notifLink,
                    "<p>{$notifContent}</p>"
                );
                $applicant = \Nexus\Models\User::findById($applicantId);
                if ($applicant && !empty($applicant['email'])) {
                    $mailer = new \Nexus\Core\Mailer();
                    $subject = "Update on your Volunteer Application";
                    $body = "<h2>Your Volunteer Application</h2>" .
                        "<p>Your application for <strong>" . htmlspecialchars($oppTitle) . "</strong> has been <strong>" . strtoupper($status) . "</strong>.</p>" .
                        ($status === 'approved'
                            ? "<p>Congratulations! The organiser will be in touch with next steps.</p>"
                            : "<p>Thank you for your interest. We encourage you to explore other volunteering opportunities.</p>") .
                        (!empty($orgNote) ? "<p><em>Note from the organiser:</em> " . htmlspecialchars($orgNote) . "</p>" : "");
                    $mailer->send($applicant['email'], (TenantContext::get()['name'] ?? 'NEXUS') . ': ' . $subject, EmailTemplateService::wrap($body));
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
        $tenantId = TenantContext::getId();
        $stmt = $db->prepare("SELECT * FROM vol_applications WHERE opportunity_id = ? AND user_id = ? AND tenant_id = ?");
        $stmt->execute([$opportunityId, $userId, $tenantId]);
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

        $cursorStartTime = null;
        $cursorShiftId = null;
        $legacyCursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded !== false) {
                $decodedCursor = json_decode($decoded, true);
                if (
                    is_array($decodedCursor)
                    && isset($decodedCursor['start_time'], $decodedCursor['id'])
                    && is_numeric($decodedCursor['id'])
                ) {
                    $cursorStartTime = (string)$decodedCursor['start_time'];
                    $cursorShiftId = (int)$decodedCursor['id'];
                } elseif (is_numeric($decoded)) {
                    $legacyCursorId = (int)$decoded;
                }
            }
        }

        $tenantId = TenantContext::getId();
        $sql = "
            SELECT a.id as application_id, a.status as app_status,
                   s.*, o.title as opp_title, org.name as org_name, org.logo_url as org_logo
            FROM vol_applications a
            JOIN vol_shifts s ON a.shift_id = s.id
            JOIN vol_opportunities o ON a.opportunity_id = o.id
            JOIN vol_organizations org ON o.organization_id = org.id
            WHERE a.user_id = ?
            AND a.tenant_id = ?
            AND a.status = 'approved'
            AND a.shift_id IS NOT NULL
        ";
        $params = [$userId, $tenantId];

        if (!empty($filters['upcoming_only'])) {
            $sql .= " AND s.start_time > NOW()";
        }

        if ($cursorStartTime !== null && $cursorShiftId !== null) {
            // Stable keyset pagination for ORDER BY start_time ASC, id ASC
            $sql .= " AND (s.start_time > ? OR (s.start_time = ? AND s.id > ?))";
            $params[] = $cursorStartTime;
            $params[] = $cursorStartTime;
            $params[] = $cursorShiftId;
        } elseif ($legacyCursorId) {
            // Backward compatibility for older numeric cursors
            $sql .= " AND s.id < ?";
            $params[] = $legacyCursorId;
        }

        $sql .= " ORDER BY s.start_time ASC, s.id ASC";
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
        $lastStartTime = null;

        foreach ($shifts as $shift) {
            $lastId = $shift['id'];
            $lastStartTime = $shift['start_time'];
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
            'cursor' => $hasMore && $lastId && $lastStartTime
                ? base64_encode(json_encode(['start_time' => $lastStartTime, 'id' => (int)$lastId]))
                : null,
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
        $stmt = $db->prepare("SELECT id FROM vol_applications WHERE opportunity_id = ? AND user_id = ? AND status = 'approved' AND tenant_id = ?");
        $stmt->execute([$opportunityId, $userId, TenantContext::getId()]);
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
            $stmt = $db->prepare("UPDATE vol_applications SET shift_id = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$shiftId, $app['id'], TenantContext::getId()]);

            // Generate QR check-in token for this shift+user
            try {
                VolunteerCheckInService::generateToken($shiftId, $userId);
            } catch (\Throwable $e) {
                error_log("QR token generation failed: " . $e->getMessage());
            }

            // Notify org owner
            try {
                $ownerStmt = $db->prepare("SELECT org.user_id as org_owner_id, opp.title FROM vol_opportunities opp JOIN vol_organizations org ON opp.organization_id = org.id WHERE opp.id = ? AND opp.tenant_id = ?");
                $ownerStmt->execute([$opportunityId, TenantContext::getId()]);
                $oppInfo = $ownerStmt->fetch(\PDO::FETCH_ASSOC);
                if ($oppInfo && (int)$oppInfo['org_owner_id'] !== $userId) {
                    $volunteer = \Nexus\Models\User::findById($userId);
                    $volunteerName = $volunteer ? trim(($volunteer['first_name'] ?? '') . ' ' . ($volunteer['last_name'] ?? '')) : 'A volunteer';
                    $shiftDate = date('D j M', strtotime($shift['start_time']));
                    $content = "{$volunteerName} signed up for a shift on {$shiftDate}: {$oppInfo['title']}";
                    NotificationDispatcher::dispatch(
                        (int)$oppInfo['org_owner_id'],
                        'volunteering',
                        $opportunityId,
                        'vol_shift_signup',
                        $content,
                        '/volunteering/opportunities/' . $opportunityId . '/applications',
                        "<p>{$content}</p>",
                        true
                    );
                }
            } catch (\Throwable $e) {
                // Silent fail
            }

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
            $stmt = $db->prepare("UPDATE vol_applications SET shift_id = NULL WHERE opportunity_id = ? AND user_id = ? AND shift_id = ? AND tenant_id = ?");
            $stmt->execute([$shift['opportunity_id'], $userId, $shiftId, TenantContext::getId()]);

            if ($stmt->rowCount() === 0) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'You are not signed up for this shift'];
                return false;
            }

            // Trigger waitlist processing — notify next person in queue
            try {
                ShiftWaitlistService::processSpotOpening($shiftId);
            } catch (\Throwable $e) {
                error_log("Waitlist processing failed after cancellation: " . $e->getMessage());
            }

            // Notify org owner
            try {
                $ownerStmt = $db->prepare("SELECT org.user_id as org_owner_id, opp.title, opp.id as opportunity_id FROM vol_opportunities opp JOIN vol_organizations org ON opp.organization_id = org.id WHERE opp.id = ? AND opp.tenant_id = ?");
                $ownerStmt->execute([$shift['opportunity_id'], TenantContext::getId()]);
                $oppInfo = $ownerStmt->fetch(\PDO::FETCH_ASSOC);
                if ($oppInfo && (int)$oppInfo['org_owner_id'] !== $userId) {
                    $volunteer = \Nexus\Models\User::findById($userId);
                    $volunteerName = $volunteer ? trim(($volunteer['first_name'] ?? '') . ' ' . ($volunteer['last_name'] ?? '')) : 'A volunteer';
                    $shiftDate = date('D j M', strtotime($shift['start_time']));
                    $content = "{$volunteerName} cancelled their shift on {$shiftDate}: {$oppInfo['title']}";
                    NotificationDispatcher::dispatch(
                        (int)$oppInfo['org_owner_id'],
                        'volunteering',
                        (int)$oppInfo['opportunity_id'],
                        'vol_shift_cancelled',
                        $content,
                        '/volunteering/opportunities/' . $oppInfo['opportunity_id'] . '/applications',
                        "<p>{$content}</p>",
                        true
                    );
                }
            } catch (\Throwable $e) {
                // Silent fail
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
        $tenantId = TenantContext::getId();
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM vol_applications WHERE shift_id = ? AND status = 'approved' AND tenant_id = ?");
        $stmt->execute([$shiftId, $tenantId]);
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

        if ($data['hours'] > 24) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot log more than 24 hours in a single entry', 'field' => 'hours'];
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

        // If opportunity_id provided, verify user has an approved application for it
        if (!empty($data['opportunity_id'])) {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id FROM vol_applications WHERE opportunity_id = ? AND user_id = ? AND status = 'approved' AND tenant_id = ?");
            $stmt->execute([$data['opportunity_id'], $userId, TenantContext::getId()]);
            if (!$stmt->fetch()) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have an approved application for this opportunity', 'field' => 'opportunity_id'];
                return null;
            }
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
            SELECT l.*, org.name as org_name, org.logo_url as org_logo, opp.title as opp_title
            FROM vol_logs l
            LEFT JOIN vol_organizations org ON l.organization_id = org.id
            LEFT JOIN vol_opportunities opp ON l.opportunity_id = opp.id
            WHERE l.user_id = ? AND l.tenant_id = ?
        ";
        $params = [$userId, $tenantId];

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
        $orgAdminRoleHours = $org ? Database::query(
            "SELECT role FROM org_members WHERE tenant_id = ? AND organization_id = ? AND user_id = ? AND status = 'active'",
            [TenantContext::getId(), (int)$org['id'], $adminUserId]
        )->fetchColumn() : false;
        if (!$org || ((int)$org['user_id'] !== $adminUserId && !in_array($orgAdminRoleHours, ['owner', 'admin'], true))) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this organization'];
            return false;
        }

        $status = $action === 'approve' ? 'approved' : self::getDeclineStatusValue();

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

            // Notify volunteer
            try {
                $statusLabel = $status === 'approved' ? 'approved' : 'declined';
                $hoursLabel = number_format((float)$log['hours'], 1);
                $content = "Your {$hoursLabel} volunteering hours at {$org['name']} have been {$statusLabel}.";
                NotificationDispatcher::dispatch(
                    (int)$log['user_id'],
                    'volunteering',
                    $logId,
                    'vol_hours_' . $statusLabel,
                    $content,
                    '/volunteering/hours',
                    "<p>{$content}</p>"
                );
            } catch (\Throwable $e) {
                // Silent fail
            }

            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::verifyHours error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to verify hours'];
            return false;
        }
    }

    /**
     * Get pending hours waiting for approval by this org owner.
     * Returns all vol_logs with status=pending for organisations owned by $userId.
     */
    public static function getPendingHoursForOrgOwner(int $userId, array $filters = []): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $limit = min($filters['limit'] ?? 20, 50);
        $cursorId = null;
        if (!empty($filters['cursor'])) {
            $decoded = base64_decode($filters['cursor'], true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT l.id, l.hours, l.date, l.description, l.status, l.created_at,
                   u.id as user_id, u.name as user_name, u.avatar_url as user_avatar,
                   org.id as org_id, org.name as org_name, org.logo_url as org_logo,
                   opp.id as opp_id, opp.title as opp_title
            FROM vol_logs l
            JOIN vol_organizations org ON l.organization_id = org.id
            JOIN users u ON l.user_id = u.id
            LEFT JOIN vol_opportunities opp ON l.opportunity_id = opp.id
            WHERE org.user_id = ? AND l.tenant_id = ? AND l.status = 'pending'
        ";
        $params = [$userId, $tenantId];

        if ($cursorId) {
            $sql .= " AND l.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY l.created_at DESC, l.id DESC LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);

        $items = [];
        $lastId = null;
        foreach ($rows as $row) {
            $lastId = $row['id'];
            $items[] = [
                'id'          => (int)$row['id'],
                'hours'       => (float)$row['hours'],
                'date'        => $row['date'],
                'description' => $row['description'],
                'status'      => $row['status'],
                'created_at'  => $row['created_at'],
                'user'        => [
                    'id'         => (int)$row['user_id'],
                    'name'       => $row['user_name'],
                    'avatar_url' => $row['user_avatar'],
                ],
                'organization' => [
                    'id'      => (int)$row['org_id'],
                    'name'    => $row['org_name'],
                    'logo_url'=> $row['org_logo'],
                ],
                'opportunity'  => $row['opp_id'] ? [
                    'id'    => (int)$row['opp_id'],
                    'title' => $row['opp_title'],
                ] : null,
            ];
        }

        return [
            'items'    => $items,
            'cursor'   => ($hasMore && $lastId) ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
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
        $tenantId = TenantContext::getId();

        // Total verified hours
        $totalVerified = VolLog::getTotalVerifiedHours($userId);

        // Hours by status
        $stmt = $db->prepare("
            SELECT status, SUM(hours) as total
            FROM vol_logs
            WHERE user_id = ? AND tenant_id = ?
            GROUP BY status
        ");
        $stmt->execute([$userId, $tenantId]);
        $byStatus = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Hours by organization
        $stmt = $db->prepare("
            SELECT org.name, SUM(l.hours) as total
            FROM vol_logs l
            JOIN vol_organizations org ON l.organization_id = org.id
            WHERE l.user_id = ? AND l.status = 'approved' AND l.tenant_id = ?
            GROUP BY org.id
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute([$userId, $tenantId]);
        $byOrg = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Hours by month (last 12 months)
        $stmt = $db->prepare("
            SELECT DATE_FORMAT(date_logged, '%Y-%m') as month, SUM(hours) as total
            FROM vol_logs
            WHERE user_id = ? AND status = 'approved' AND tenant_id = ?
            AND date_logged >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month DESC
        ");
        $stmt->execute([$userId, $tenantId]);
        $byMonth = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'total_verified' => $totalVerified,
            'total_pending' => (float)($byStatus['pending'] ?? 0),
            'total_declined' => (float)(($byStatus['declined'] ?? 0) + ($byStatus['rejected'] ?? 0)),
            'by_organization' => $byOrg,
            'by_month' => $byMonth,
        ];
    }

    /**
     * Resolve the "declined" state value for vol_logs across schema variants.
     * Some environments use ENUM(...,'declined'), others use ENUM(...,'rejected').
     */
    private static function getDeclineStatusValue(): string
    {
        if (self::$declineStatusValue !== null) {
            return self::$declineStatusValue;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'vol_logs'
                  AND COLUMN_NAME = 'status'
                LIMIT 1
            ");
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $columnType = strtolower((string)($row['COLUMN_TYPE'] ?? ''));

            if (strpos($columnType, "'declined'") !== false) {
                self::$declineStatusValue = 'declined';
            } elseif (strpos($columnType, "'rejected'") !== false) {
                self::$declineStatusValue = 'rejected';
            }
        } catch (\Throwable $e) {
            // Fallback below
        }

        if (self::$declineStatusValue === null) {
            self::$declineStatusValue = 'declined';
        }

        return self::$declineStatusValue;
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

        $cursorName = null;
        $cursorId = null;
        $legacyCursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded !== false) {
                $decodedCursor = json_decode($decoded, true);
                if (
                    is_array($decodedCursor)
                    && isset($decodedCursor['name'], $decodedCursor['id'])
                    && is_numeric($decodedCursor['id'])
                ) {
                    $cursorName = (string)$decodedCursor['name'];
                    $cursorId = (int)$decodedCursor['id'];
                } elseif (is_numeric($decoded)) {
                    $legacyCursorId = (int)$decoded;
                }
            }
        }

        $sql = "
            SELECT vo.*, u.name as owner_name, u.avatar_url as owner_avatar,
                   (SELECT COUNT(*) FROM vol_opportunities WHERE organization_id = vo.id AND tenant_id = vo.tenant_id AND is_active = 1) as opportunity_count
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

        if ($cursorName !== null && $cursorId !== null) {
            // Stable keyset pagination for ORDER BY name ASC, id DESC
            $sql .= " AND (vo.name > ? OR (vo.name = ? AND vo.id < ?))";
            $params[] = $cursorName;
            $params[] = $cursorName;
            $params[] = $cursorId;
        } elseif ($legacyCursorId) {
            // Backward compatibility for older numeric cursors
            $sql .= " AND vo.id < ?";
            $params[] = $legacyCursorId;
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
        $lastName = null;

        foreach ($organizations as $org) {
            $lastId = $org['id'];
            $lastName = $org['name'];
            $items[] = [
                'id' => (int)$org['id'],
                'name' => $org['name'],
                'description' => $org['description'],
                'logo_url' => $org['logo_url'] ?? null,
                'website' => $org['website'],
                'contact_email' => $org['contact_email'],
                'location' => $org['location'] ?? null,
                'created_at' => $org['created_at'] ?? null,
                'opportunity_count' => (int)$org['opportunity_count'],
                'total_hours' => null,
                'volunteer_count' => null,
                'average_rating' => null,
                'owner' => [
                    'name' => $org['owner_name'],
                    'avatar_url' => $org['owner_avatar'],
                ],
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId && $lastName !== null
                ? base64_encode(json_encode(['name' => $lastName, 'id' => (int)$lastId]))
                : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get single organization by ID
     *
     * @param int $id Organization ID
     * @return array|null
     */
    public static function getOrganizationById(int $id, bool $includeNonApproved = false): ?array
    {
        $org = VolOrganization::find($id);

        if (!$org) {
            return null;
        }

        if (!$includeNonApproved && ($org['status'] ?? null) !== 'approved') {
            return null;
        }

        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Get opportunity count
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM vol_opportunities WHERE organization_id = ? AND is_active = 1 AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $oppCount = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        // Get total hours logged
        $stmt = $db->prepare("SELECT SUM(hours) as total FROM vol_logs WHERE organization_id = ? AND status = 'approved' AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
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
            'location' => $org['location'] ?? null,
            'created_at' => $org['created_at'] ?? null,
            'status' => $org['status'],
            'opportunity_count' => $oppCount,
            'total_hours' => $totalHours,
            'volunteer_count' => null,
            'review_count' => count($reviews),
            'average_rating' => round($avgRating, 1),
            'stats' => [
                'opportunity_count' => $oppCount,
                'total_hours_logged' => $totalHours,
                'total_hours' => $totalHours,
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
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();
        if ($targetType === 'organization') {
            $org = VolOrganization::find($targetId);
            if (!$org) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Organization not found'];
                return null;
            }
            // Reviewer must have a verified hour log or approved application for this org
            $historyStmt = $db->prepare("
                SELECT 1 FROM vol_logs WHERE user_id = ? AND organization_id = ? AND status = 'approved' AND tenant_id = ?
                UNION
                SELECT 1 FROM vol_applications a
                JOIN vol_opportunities opp ON a.opportunity_id = opp.id
                WHERE a.user_id = ? AND opp.organization_id = ? AND a.status = 'approved' AND a.tenant_id = ?
                LIMIT 1
            ");
            $historyStmt->execute([$reviewerId, $targetId, $tenantId, $reviewerId, $targetId, $tenantId]);
            if (!$historyStmt->fetch()) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must have volunteered with this organisation to leave a review'];
                return null;
            }
        } else {
            $user = \Nexus\Models\User::findById($targetId);
            if (!$user) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'User not found'];
                return null;
            }
            // Reviewer must have co-volunteered — shared approved application at same org
            $historyStmt = $db->prepare("
                SELECT 1 FROM vol_applications a1
                JOIN vol_applications a2 ON a1.opportunity_id = a2.opportunity_id
                WHERE a1.user_id = ? AND a2.user_id = ? AND a1.status = 'approved' AND a2.status = 'approved' AND a1.tenant_id = ?
                LIMIT 1
            ");
            $historyStmt->execute([$reviewerId, $targetId, $tenantId]);
            if (!$historyStmt->fetch()) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must have volunteered together to leave a review'];
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
                        '/volunteering',
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
                'author' => [
                    'id' => (int)($r['user_id'] ?? $r['reviewer_id'] ?? 0),
                    'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    'avatar' => $r['avatar_url'] ?? null,
                ],
                'reviewer' => [  // keep for backward compat
                    'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    'avatar_url' => $r['avatar_url'] ?? null,
                ],
                'created_at' => $r['created_at'],
            ];
        }, $reviews);
    }

    // ========================================
    // ORGANIZATION REGISTRATION
    // ========================================

    /**
     * Register a new volunteer organisation (created with status='pending')
     */
    public static function createOrganization(int $userId, array $data): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        // Validate required fields
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $contactEmail = trim($data['contact_email'] ?? '');
        $website = trim($data['website'] ?? '');

        if (empty($name)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Organisation name is required', 'field' => 'name'];
            return null;
        }

        if (mb_strlen($name) < 3) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Organisation name must be at least 3 characters', 'field' => 'name'];
            return null;
        }

        if (mb_strlen($name) > 200) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Organisation name must be under 200 characters', 'field' => 'name'];
            return null;
        }

        if (empty($description)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Description is required', 'field' => 'description'];
            return null;
        }

        if (mb_strlen($description) < 20) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Description must be at least 20 characters', 'field' => 'description'];
            return null;
        }

        if (empty($contactEmail)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Contact email is required', 'field' => 'contact_email'];
            return null;
        }

        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Please enter a valid email address', 'field' => 'contact_email'];
            return null;
        }

        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Please enter a valid URL', 'field' => 'website'];
            return null;
        }

        // Check for duplicate name in tenant (case-insensitive, excluding declined)
        $existing = Database::query(
            "SELECT id FROM vol_organizations WHERE tenant_id = ? AND LOWER(name) = LOWER(?) AND status != 'declined'",
            [$tenantId, $name]
        )->fetch();

        if ($existing) {
            self::$errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'An organisation with this name already exists', 'field' => 'name'];
            return null;
        }

        // Generate slug
        $slug = self::generateOrgSlug($name, $tenantId);

        try {
            $orgId = VolOrganization::create(
                $tenantId,
                $userId,
                $name,
                $description,
                $contactEmail,
                $website ?: null,
                $slug
            );

            // Initialize owner membership
            OrgMember::initializeOwner((int)$orgId, $userId);

            // Record in feed_activity table
            try {
                FeedActivityService::recordActivity($tenantId, $userId, 'org_registered', (int)$orgId, [
                    'title' => $name,
                    'content' => $description,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $faEx) {
                error_log("VolunteerService::createOrganization feed_activity record failed: " . $faEx->getMessage());
            }

            // Log activity
            try {
                ActivityLog::log($userId, 'org_registered', 'vol_organizations', (int)$orgId, [
                    'name' => $name,
                ]);
            } catch (\Exception $alEx) {
                error_log("VolunteerService::createOrganization activity_log failed: " . $alEx->getMessage());
            }

            return (int)$orgId;
        } catch (\Throwable $e) {
            error_log("VolunteerService::createOrganization error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to register organisation'];
            return null;
        }
    }

    /**
     * Get organisations the current user owns or is admin of
     */
    public static function getMyOrganizations(int $userId): array
    {
        $orgs = OrgMember::getUserOrganizations($userId);

        return array_map(function ($org) {
            return [
                'id' => (int)$org['id'],
                'name' => $org['name'],
                'description' => $org['description'] ?? null,
                'status' => $org['status'] ?? 'pending',
                'member_role' => $org['member_role'] ?? 'member',
                'logo_url' => $org['logo_url'] ?? null,
                'contact_email' => $org['contact_email'] ?? null,
                'website' => $org['website'] ?? null,
                'created_at' => $org['created_at'] ?? null,
            ];
        }, $orgs);
    }

    /**
     * Generate a unique slug for an organisation within a tenant
     */
    private static function generateOrgSlug(string $name, int $tenantId): string
    {
        // Transliterate to ASCII, lowercase, replace non-alnum with hyphens
        $slug = mb_strtolower($name, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            $slug = 'organisation';
        }

        // Check uniqueness
        $baseSlug = $slug;
        $suffix = 0;
        while (true) {
            $existing = Database::query(
                "SELECT id FROM vol_organizations WHERE tenant_id = ? AND slug = ?",
                [$tenantId, $slug]
            )->fetch();

            if (!$existing) {
                break;
            }

            $suffix++;
            $slug = $baseSlug . '-' . $suffix;
        }

        return $slug;
    }

    /**
     * Send reminder emails to volunteers for shifts starting within the next 24 hours.
     * Designed to be called by a cron job once per day.
     *
     * @return int Number of reminders sent
     */
    public static function sendShiftReminders(): int
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Get shifts starting in next 24 hours for this tenant
        $stmt = $db->prepare("
            SELECT s.id as shift_id, s.start_time, s.end_time,
                   opp.id as opp_id, opp.title as opp_title, opp.location,
                   u.id as user_id, u.email as user_email, u.name as user_name
            FROM vol_shifts s
            JOIN vol_opportunities opp ON s.opportunity_id = opp.id
            JOIN vol_shift_signups ss ON ss.shift_id = s.id
            JOIN users u ON ss.user_id = u.id
            WHERE opp.tenant_id = ?
              AND s.start_time > NOW()
              AND s.start_time <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
              AND opp.is_active = 1
        ");
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $sent = 0;
        foreach ($rows as $row) {
            try {
                if (empty($row['user_email'])) continue;
                $startFormatted = (new \DateTime($row['start_time']))->format('l, j F Y \a\t g:ia');
                $endTime = (new \DateTime($row['end_time']))->format('g:ia');
                $location = !empty($row['location']) ? htmlspecialchars($row['location']) : 'See opportunity for details';
                $title = htmlspecialchars($row['opp_title']);
                $name = htmlspecialchars($row['user_name']);
                $frontendUrl = rtrim(\Nexus\Core\TenantContext::getFrontendUrl(), '/');
                $slugPrefix = \Nexus\Core\TenantContext::getSlugPrefix();
                $oppUrl = $frontendUrl . $slugPrefix . '/volunteering/opportunities/' . $row['opp_id'];

                $body = "<h2>Shift Reminder</h2>"
                    . "<p>Hi {$name},</p>"
                    . "<p>This is a reminder that you have a volunteer shift coming up tomorrow:</p>"
                    . "<table style=\"border-collapse:collapse;width:100%;margin:16px 0;\">"
                    . "<tr><td style=\"padding:8px 0;color:#6b7280;font-size:13px;\">Opportunity</td>"
                    . "<td style=\"padding:8px 0;font-weight:600;\">{$title}</td></tr>"
                    . "<tr><td style=\"padding:8px 0;color:#6b7280;font-size:13px;\">When</td>"
                    . "<td style=\"padding:8px 0;\">{$startFormatted} &ndash; {$endTime}</td></tr>"
                    . "<tr><td style=\"padding:8px 0;color:#6b7280;font-size:13px;\">Where</td>"
                    . "<td style=\"padding:8px 0;\">{$location}</td></tr>"
                    . "</table>"
                    . "<p style=\"margin:24px 0 8px;\"><a href=\"{$oppUrl}\" style=\"display:inline-block;background-color:#6366f1;color:#ffffff;text-decoration:none;padding:10px 24px;border-radius:6px;font-size:14px;font-weight:600;\">View Opportunity</a></p>"
                    . "<p>Thank you for volunteering!</p>";

                $mailer = new \Nexus\Core\Mailer();
                $tenantName = TenantContext::get()['name'] ?? 'NEXUS';
                $mailer->send(
                    $row['user_email'],
                    $tenantName . ': Reminder — ' . $row['opp_title'],
                    EmailTemplateService::wrap($body)
                );
                $sent++;
            } catch (\Throwable $e) {
                error_log('VolunteerService::sendShiftReminders error for user ' . $row['user_id'] . ': ' . $e->getMessage());
            }
        }

        return $sent;
    }
}
