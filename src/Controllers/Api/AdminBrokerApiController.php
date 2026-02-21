<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\ExchangeWorkflowService;

/**
 * AdminBrokerApiController - V2 API for React admin broker controls
 *
 * Provides exchange management, risk tag oversight, message review,
 * and user monitoring for tenant brokers/admins.
 *
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET  /api/v2/admin/broker/dashboard              - Overview stats
 * - GET  /api/v2/admin/broker/exchanges               - List exchange requests
 * - POST /api/v2/admin/broker/exchanges/{id}/approve   - Approve exchange
 * - POST /api/v2/admin/broker/exchanges/{id}/reject    - Reject exchange
 * - GET  /api/v2/admin/broker/risk-tags               - List risk tags
 * - GET  /api/v2/admin/broker/messages                - List broker message copies
 * - POST /api/v2/admin/broker/messages/{id}/review     - Mark message reviewed
 * - GET  /api/v2/admin/broker/monitoring              - List monitored users
 */
class AdminBrokerApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/broker/dashboard
     *
     * Returns aggregate counts for the broker dashboard stat cards.
     * Each query is wrapped in try/catch because the underlying tables
     * may not exist yet in every environment.
     */
    public function dashboard(): void
    {
        $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();

        // Pending exchanges
        $pendingExchanges = 0;
        try {
            $row = Database::query(
                "SELECT COUNT(*) as cnt FROM exchange_requests WHERE tenant_id = ? AND status IN ('pending_broker', 'disputed')",
                [$tenantId]
            )->fetch();
            $pendingExchanges = (int) ($row['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Unreviewed messages
        $unreviewedMessages = 0;
        try {
            $row = Database::query(
                "SELECT COUNT(*) as cnt FROM broker_message_copies WHERE tenant_id = ? AND reviewed_by IS NULL",
                [$tenantId]
            )->fetch();
            $unreviewedMessages = (int) ($row['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // High risk listings
        $highRiskListings = 0;
        try {
            $row = Database::query(
                "SELECT COUNT(*) as cnt FROM listing_risk_tags WHERE tenant_id = ? AND risk_level = 'high'",
                [$tenantId]
            )->fetch();
            $highRiskListings = (int) ($row['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Monitored users
        $monitoredUsers = 0;
        try {
            $row = Database::query(
                "SELECT COUNT(*) as cnt FROM user_messaging_restrictions WHERE tenant_id = ? AND under_monitoring = 1",
                [$tenantId]
            )->fetch();
            $monitoredUsers = (int) ($row['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Vetting summary (pending/expiring counts)
        $vettingPending = 0;
        $vettingExpiring = 0;
        try {
            $row = Database::query(
                "SELECT
                    SUM(CASE WHEN status IN ('pending', 'submitted') THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'verified' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring
                 FROM vetting_records WHERE tenant_id = ?",
                [$tenantId]
            )->fetch();
            $vettingPending = (int) ($row['pending'] ?? 0);
            $vettingExpiring = (int) ($row['expiring'] ?? 0);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Safeguarding alerts count
        $safeguardingAlerts = 0;
        try {
            $row = Database::query(
                "SELECT COUNT(*) as cnt FROM abuse_alerts WHERE tenant_id = ? AND status = 'open'",
                [$tenantId]
            )->fetch();
            $safeguardingAlerts = (int) ($row['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Recent broker activity (last 20 actions)
        $recentActivity = [];
        try {
            $recentActivity = Database::query(
                "SELECT al.*, u.first_name, u.last_name
                 FROM activity_logs al
                 LEFT JOIN users u ON u.id = al.user_id
                 WHERE al.tenant_id = ? AND al.action_type IN (
                     'exchange_approved', 'exchange_rejected', 'message_reviewed',
                     'risk_tag_added', 'user_monitored', 'vetting_verified', 'vetting_rejected',
                     'user_banned', 'user_unbanned', 'balance_adjusted'
                 )
                 ORDER BY al.created_at DESC
                 LIMIT 20",
                [$tenantId]
            )->fetchAll();
        } catch (\Exception $e) {
            // Table may not exist
        }

        $this->respondWithData([
            'pending_exchanges' => $pendingExchanges,
            'unreviewed_messages' => $unreviewedMessages,
            'high_risk_listings' => $highRiskListings,
            'monitored_users' => $monitoredUsers,
            'vetting_pending' => $vettingPending,
            'vetting_expiring' => $vettingExpiring,
            'safeguarding_alerts' => $safeguardingAlerts,
            'recent_activity' => $recentActivity,
        ]);
    }

    /**
     * GET /api/v2/admin/broker/exchanges
     *
     * List exchange requests with pagination. Supports ?status= and ?page= filters.
     * Joins users and listings tables for display names.
     */
    public function exchanges(): void
    {
        $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $status = $this->query('status');
        $offset = ($page - 1) * $perPage;

        try {
            // Build WHERE clause
            $where = "er.tenant_id = ?";
            $params = [$tenantId];

            if ($status && $status !== 'all') {
                $where .= " AND er.status = ?";
                $params[] = $status;
            }

            // Get total count
            $countRow = Database::query(
                "SELECT COUNT(*) as cnt FROM exchange_requests er WHERE {$where}",
                $params
            )->fetch();
            $total = (int) ($countRow['cnt'] ?? 0);

            // Get paginated data
            $queryParams = array_merge($params, [$perPage, $offset]);
            $items = Database::query(
                "SELECT er.*,
                    CONCAT(req.first_name, ' ', req.last_name) as requester_name,
                    CONCAT(prov.first_name, ' ', prov.last_name) as provider_name,
                    l.title as listing_title
                FROM exchange_requests er
                JOIN users req ON er.requester_id = req.id
                JOIN users prov ON er.provider_id = prov.id
                LEFT JOIN listings l ON er.listing_id = l.id
                WHERE {$where}
                ORDER BY er.created_at DESC
                LIMIT ? OFFSET ?",
                $queryParams
            )->fetchAll();

            $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    /**
     * POST /api/v2/admin/broker/exchanges/{id}/approve
     *
     * Approve an exchange request. Accepts optional { notes } in body.
     */
    public function approveExchange(int $id): void
    {
        $adminId = $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();
        $notes = $this->input('notes', '');

        try {
            // Verify the exchange belongs to this tenant and is pending broker approval
            $exchange = Database::query(
                "SELECT id, status FROM exchange_requests WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$exchange) {
                $this->respondWithError('NOT_FOUND', 'Exchange request not found', null, 404);
                return;
            }

            if ($exchange['status'] !== 'pending_broker') {
                $this->respondWithError('INVALID_STATUS', 'Exchange is not pending broker approval');
                return;
            }

            // Delegate to ExchangeWorkflowService so status transitions, history, and
            // notifications all run through the standard workflow engine.
            $success = ExchangeWorkflowService::approveExchange($id, $adminId, $notes);

            if (!$success) {
                $this->respondWithError('SERVER_ERROR', 'Failed to approve exchange', null, 500);
                return;
            }

            $this->respondWithData(['id' => $id, 'status' => 'accepted']);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to approve exchange', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/broker/exchanges/{id}/reject
     *
     * Reject an exchange request. Requires { reason } in body.
     */
    public function rejectExchange(int $id): void
    {
        $adminId = $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();
        $reason = $this->input('reason', '');

        if (empty($reason)) {
            $this->respondWithError('VALIDATION_ERROR', 'A reason is required to reject an exchange', 'reason');
            return;
        }

        try {
            // Verify the exchange belongs to this tenant and is pending broker approval
            $exchange = Database::query(
                "SELECT id, status FROM exchange_requests WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$exchange) {
                $this->respondWithError('NOT_FOUND', 'Exchange request not found', null, 404);
                return;
            }

            if ($exchange['status'] !== 'pending_broker') {
                $this->respondWithError('INVALID_STATUS', 'Exchange is not pending broker approval');
                return;
            }

            // Delegate to ExchangeWorkflowService so status transitions, history, and
            // notifications all run through the standard workflow engine.
            $success = ExchangeWorkflowService::rejectExchange($id, $adminId, $reason);

            if (!$success) {
                $this->respondWithError('SERVER_ERROR', 'Failed to reject exchange', null, 500);
                return;
            }

            $this->respondWithData(['id' => $id, 'status' => 'cancelled']);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to reject exchange', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/broker/risk-tags
     *
     * List all risk tags with listing and owner information.
     * Supports ?risk_level= filter.
     */
    public function riskTags(): void
    {
        $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();
        $riskLevel = $this->query('risk_level');

        try {
            $where = "rt.tenant_id = ?";
            $params = [$tenantId];

            if ($riskLevel && $riskLevel !== 'all') {
                $where .= " AND rt.risk_level = ?";
                $params[] = $riskLevel;
            }

            $items = Database::query(
                "SELECT rt.*,
                    l.title as listing_title,
                    CONCAT(u.first_name, ' ', u.last_name) as owner_name
                FROM listing_risk_tags rt
                LEFT JOIN listings l ON rt.listing_id = l.id
                LEFT JOIN users u ON l.user_id = u.id
                WHERE {$where}
                ORDER BY FIELD(rt.risk_level, 'critical', 'high', 'medium', 'low'), rt.created_at DESC",
                $params
            )->fetchAll();

            $this->respondWithData($items);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    /**
     * GET /api/v2/admin/broker/messages
     *
     * List broker message copies with sender/receiver names.
     * Supports ?filter= (unreviewed, flagged, all) and ?page= parameters.
     */
    public function messages(): void
    {
        $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $filter = $this->query('filter', 'all');
        $offset = ($page - 1) * $perPage;

        try {
            $where = "bmc.tenant_id = ?";
            $params = [$tenantId];

            if ($filter === 'unreviewed') {
                $where .= " AND bmc.reviewed_by IS NULL";
            } elseif ($filter === 'flagged') {
                $where .= " AND bmc.flagged = 1";
            } elseif ($filter === 'reviewed') {
                $where .= " AND bmc.reviewed_by IS NOT NULL";
            }

            // Count
            $countRow = Database::query(
                "SELECT COUNT(*) as cnt FROM broker_message_copies bmc WHERE {$where}",
                $params
            )->fetch();
            $total = (int) ($countRow['cnt'] ?? 0);

            // Paginated data
            $queryParams = array_merge($params, [$perPage, $offset]);
            $items = Database::query(
                "SELECT bmc.*,
                    CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                    CONCAT(r.first_name, ' ', r.last_name) as receiver_name,
                    l.title as listing_title
                FROM broker_message_copies bmc
                LEFT JOIN users s ON bmc.sender_id = s.id
                LEFT JOIN users r ON bmc.receiver_id = r.id
                LEFT JOIN listings l ON bmc.related_listing_id = l.id
                WHERE {$where}
                ORDER BY bmc.created_at DESC
                LIMIT ? OFFSET ?",
                $queryParams
            )->fetchAll();

            // Normalize boolean fields
            foreach ($items as &$item) {
                $item['flagged'] = (bool) ($item['flagged'] ?? false);
            }

            $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    /**
     * POST /api/v2/admin/broker/messages/{id}/review
     *
     * Mark a broker message copy as reviewed by the current admin.
     */
    public function reviewMessage(int $id): void
    {
        $adminId = $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Verify the message belongs to this tenant
            $message = Database::query(
                "SELECT id FROM broker_message_copies WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$message) {
                $this->respondWithError('NOT_FOUND', 'Message not found', null, 404);
                return;
            }

            Database::query(
                "UPDATE broker_message_copies SET reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$adminId, $id, $tenantId]
            );

            $this->respondWithData(['id' => $id, 'reviewed' => true]);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to mark message as reviewed', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/broker/monitoring
     *
     * List users currently under monitoring.
     */
    public function monitoring(): void
    {
        $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();

        try {
            $items = Database::query(
                "SELECT umr.*,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM user_messaging_restrictions umr
                LEFT JOIN users u ON umr.user_id = u.id
                WHERE umr.tenant_id = ? AND umr.under_monitoring = 1
                ORDER BY umr.monitoring_started_at DESC",
                [$tenantId]
            )->fetchAll();

            // Normalize boolean fields
            foreach ($items as &$item) {
                $item['under_monitoring'] = (bool) ($item['under_monitoring'] ?? false);
            }

            $this->respondWithData($items);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    /**
     * POST /api/v2/admin/broker/messages/{id}/flag
     *
     * Flag a broker message copy with a reason and severity.
     * Body: { reason: string (required), severity: 'concern'|'serious'|'urgent' }
     */
    public function flagMessage(int $id): void
    {
        $adminId = $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();
        $reason = trim($this->input('reason', ''));
        $severity = $this->input('severity', 'concern');

        if (empty($reason)) {
            $this->respondWithError('VALIDATION_ERROR', 'A reason is required to flag a message', 'reason');
            return;
        }

        $allowedSeverities = ['info', 'warning', 'concern', 'urgent'];
        if (!in_array($severity, $allowedSeverities)) {
            $severity = 'concern';
        }

        try {
            $message = Database::query(
                "SELECT id FROM broker_message_copies WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$message) {
                $this->respondWithError('NOT_FOUND', 'Message not found', null, 404);
                return;
            }

            Database::query(
                "UPDATE broker_message_copies SET flagged = 1, flag_reason = ?, flag_severity = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$reason, $severity, $adminId, $id, $tenantId]
            );

            $this->respondWithData(['id' => $id, 'flagged' => true, 'flag_reason' => $reason, 'flag_severity' => $severity]);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to flag message', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/broker/monitoring/{userId}
     *
     * Set or remove monitoring for a user.
     * Body: { under_monitoring: bool, reason?: string, messaging_disabled?: bool }
     * If under_monitoring is false, removes monitoring.
     */
    public function setMonitoring(int $userId): void
    {
        $adminId = $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();
        $underMonitoring = (bool) $this->input('under_monitoring', true);
        $reason = trim($this->input('reason', ''));
        $messagingDisabled = (bool) $this->input('messaging_disabled', false);

        try {
            // Verify user belongs to this tenant
            $user = Database::query(
                "SELECT id FROM users WHERE id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetch();

            if (!$user) {
                $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
                return;
            }

            // Check if record already exists
            $existing = Database::query(
                "SELECT id FROM user_messaging_restrictions WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetch();

            if ($underMonitoring) {
                if (empty($reason)) {
                    $this->respondWithError('VALIDATION_ERROR', 'A reason is required to set monitoring', 'reason');
                    return;
                }

                if ($existing) {
                    Database::query(
                        "UPDATE user_messaging_restrictions SET under_monitoring = 1, monitoring_reason = ?, messaging_disabled = ?, monitoring_started_at = NOW(), restricted_by = ? WHERE user_id = ? AND tenant_id = ?",
                        [$reason, $messagingDisabled ? 1 : 0, $adminId, $userId, $tenantId]
                    );
                } else {
                    Database::query(
                        "INSERT INTO user_messaging_restrictions (user_id, tenant_id, under_monitoring, monitoring_reason, messaging_disabled, monitoring_started_at, restricted_by) VALUES (?, ?, 1, ?, ?, NOW(), ?)",
                        [$userId, $tenantId, $reason, $messagingDisabled ? 1 : 0, $adminId]
                    );
                }
                $this->respondWithData(['user_id' => $userId, 'under_monitoring' => true]);
            } else {
                if ($existing) {
                    Database::query(
                        "UPDATE user_messaging_restrictions SET under_monitoring = 0, monitoring_reason = NULL, monitoring_started_at = NULL WHERE user_id = ? AND tenant_id = ?",
                        [$userId, $tenantId]
                    );
                }
                $this->respondWithData(['user_id' => $userId, 'under_monitoring' => false]);
            }
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to update monitoring', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/broker/risk-tags/{listingId}
     *
     * Create or update a risk tag for a listing.
     * Body: { risk_level, risk_category, risk_notes?, member_visible_notes?, requires_approval?, insurance_required?, dbs_required? }
     */
    public function saveRiskTag(int $listingId): void
    {
        $adminId = $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();

        $riskLevel = $this->input('risk_level', 'low');
        $riskCategory = trim($this->input('risk_category', ''));
        $riskNotes = trim($this->input('risk_notes', ''));
        $memberVisibleNotes = trim($this->input('member_visible_notes', ''));
        $requiresApproval = (bool) $this->input('requires_approval', false);
        $insuranceRequired = (bool) $this->input('insurance_required', false);
        $dbsRequired = (bool) $this->input('dbs_required', false);

        $allowedLevels = ['low', 'medium', 'high', 'critical'];
        if (!in_array($riskLevel, $allowedLevels)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid risk level', 'risk_level');
            return;
        }

        if (empty($riskCategory)) {
            $this->respondWithError('VALIDATION_ERROR', 'Risk category is required', 'risk_category');
            return;
        }

        try {
            // Verify listing belongs to this tenant
            $listing = Database::query(
                "SELECT id FROM listings WHERE id = ? AND tenant_id = ?",
                [$listingId, $tenantId]
            )->fetch();

            if (!$listing) {
                $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
                return;
            }

            // Upsert
            $existing = Database::query(
                "SELECT id FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?",
                [$listingId, $tenantId]
            )->fetch();

            if ($existing) {
                Database::query(
                    "UPDATE listing_risk_tags SET risk_level = ?, risk_category = ?, risk_notes = ?, member_visible_notes = ?, requires_approval = ?, insurance_required = ?, dbs_required = ?, tagged_by = ?, updated_at = NOW() WHERE listing_id = ? AND tenant_id = ?",
                    [$riskLevel, $riskCategory, $riskNotes, $memberVisibleNotes, $requiresApproval ? 1 : 0, $insuranceRequired ? 1 : 0, $dbsRequired ? 1 : 0, $adminId, $listingId, $tenantId]
                );
                $tagId = $existing['id'];
            } else {
                Database::query(
                    "INSERT INTO listing_risk_tags (listing_id, tenant_id, risk_level, risk_category, risk_notes, member_visible_notes, requires_approval, insurance_required, dbs_required, tagged_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$listingId, $tenantId, $riskLevel, $riskCategory, $riskNotes, $memberVisibleNotes, $requiresApproval ? 1 : 0, $insuranceRequired ? 1 : 0, $dbsRequired ? 1 : 0, $adminId]
                );
                $tagId = Database::lastInsertId();
            }

            $this->respondWithData(['id' => $tagId, 'listing_id' => $listingId, 'risk_level' => $riskLevel]);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to save risk tag', null, 500);
        }
    }

    /**
     * DELETE /api/v2/admin/broker/risk-tags/{listingId}
     *
     * Remove a risk tag from a listing.
     */
    public function removeRiskTag(int $listingId): void
    {
        $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();

        try {
            $existing = Database::query(
                "SELECT id FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?",
                [$listingId, $tenantId]
            )->fetch();

            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Risk tag not found', null, 404);
                return;
            }

            Database::query(
                "DELETE FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?",
                [$listingId, $tenantId]
            );

            $this->respondWithData(['listing_id' => $listingId, 'removed' => true]);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to remove risk tag', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/broker/configuration
     *
     * Return the broker configuration for this tenant.
     * Reads from tenant_settings table (key = 'broker_config') or returns defaults.
     */
    public function getConfiguration(): void
    {
        $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();

        $defaults = [
            // Messaging
            'broker_messaging_enabled' => true,
            'broker_copy_all_messages' => false,
            'broker_copy_threshold_hours' => 5,
            'new_member_monitoring_days' => 30,
            'require_exchange_for_listings' => false,
            // Risk Tagging
            'risk_tagging_enabled' => true,
            'auto_flag_high_risk' => true,
            'require_approval_high_risk' => false,
            'notify_on_high_risk_match' => true,
            // Exchange Workflow
            'broker_approval_required' => true,
            'auto_approve_low_risk' => false,
            'exchange_timeout_days' => 7,
            'max_hours_without_approval' => 5,
            'confirmation_deadline_hours' => 48,
            'allow_hour_adjustment' => false,
            'max_hour_variance_percent' => 20,
            'expiry_hours' => 168,
            // Broker Visibility
            'broker_visible_to_members' => false,
            'show_broker_name' => false,
            'broker_contact_email' => '',
            // Message Copy Rules
            'copy_first_contact' => true,
            'copy_new_member_messages' => true,
            'copy_high_risk_listing_messages' => true,
            'random_sample_percentage' => 0,
            'retention_days' => 90,
        ];

        try {
            $row = Database::query(
                "SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = 'broker_config'",
                [$tenantId]
            )->fetch();

            $config = $defaults;
            if ($row && !empty($row['setting_value'])) {
                $saved = json_decode($row['setting_value'], true) ?? [];
                $config = array_merge($defaults, $saved);
            }

            $this->respondWithData($config);
        } catch (\Exception $e) {
            $this->respondWithData($defaults);
        }
    }

    /**
     * POST /api/v2/admin/broker/configuration
     *
     * Save the broker configuration for this tenant.
     * Body: flat object with any/all config keys.
     */
    public function saveConfiguration(): void
    {
        $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();

        $body = $this->getJsonInput();

        // Whitelist allowed keys
        $allowedKeys = [
            'broker_messaging_enabled', 'broker_copy_all_messages', 'broker_copy_threshold_hours',
            'new_member_monitoring_days', 'require_exchange_for_listings',
            'risk_tagging_enabled', 'auto_flag_high_risk', 'require_approval_high_risk',
            'notify_on_high_risk_match',
            'broker_approval_required', 'auto_approve_low_risk', 'exchange_timeout_days',
            'max_hours_without_approval', 'confirmation_deadline_hours', 'allow_hour_adjustment',
            'max_hour_variance_percent', 'expiry_hours',
            'broker_visible_to_members', 'show_broker_name', 'broker_contact_email',
            'copy_first_contact', 'copy_new_member_messages', 'copy_high_risk_listing_messages',
            'random_sample_percentage', 'retention_days',
        ];

        $config = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $body)) {
                $config[$key] = $body[$key];
            }
        }

        try {
            $existing = Database::query(
                "SELECT id FROM tenant_settings WHERE tenant_id = ? AND setting_key = 'broker_config'",
                [$tenantId]
            )->fetch();

            $json = json_encode($config);
            if ($existing) {
                Database::query(
                    "UPDATE tenant_settings SET setting_value = ?, updated_at = NOW() WHERE tenant_id = ? AND setting_key = 'broker_config'",
                    [$json, $tenantId]
                );
            } else {
                Database::query(
                    "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, created_at, updated_at) VALUES (?, 'broker_config', ?, NOW(), NOW())",
                    [$tenantId, $json]
                );
            }

            $this->respondWithData($config);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to save configuration', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/broker/exchanges/{id}
     *
     * Return full detail for a single exchange request including history timeline.
     */
    public function showExchange(int $id): void
    {
        $this->requireBrokerAdmin();
        $tenantId = TenantContext::getId();

        try {
            $exchange = Database::query(
                "SELECT er.*,
                    CONCAT(req.first_name, ' ', req.last_name) as requester_name,
                    req.email as requester_email,
                    req.avatar as requester_avatar,
                    CONCAT(prov.first_name, ' ', prov.last_name) as provider_name,
                    prov.email as provider_email,
                    prov.avatar as provider_avatar,
                    l.title as listing_title,
                    l.listing_type,
                    l.hours_offered
                FROM exchange_requests er
                JOIN users req ON er.requester_id = req.id
                JOIN users prov ON er.provider_id = prov.id
                LEFT JOIN listings l ON er.listing_id = l.id
                WHERE er.id = ? AND er.tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$exchange) {
                $this->respondWithError('NOT_FOUND', 'Exchange request not found', null, 404);
                return;
            }

            // Fetch history/timeline
            $history = [];
            try {
                $history = Database::query(
                    "SELECT eh.*,
                        CONCAT(u.first_name, ' ', u.last_name) as actor_name
                    FROM exchange_history eh
                    LEFT JOIN users u ON eh.actor_id = u.id
                    WHERE eh.exchange_id = ?
                    ORDER BY eh.created_at ASC",
                    [$id]
                )->fetchAll();
            } catch (\Exception $e) {
                // exchange_history table may not exist
            }

            // Risk tag for the listing
            $riskTag = null;
            if (!empty($exchange['listing_id'])) {
                try {
                    $riskTag = Database::query(
                        "SELECT * FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?",
                        [$exchange['listing_id'], $tenantId]
                    )->fetch() ?: null;
                } catch (\Exception $e) {}
            }

            $this->respondWithData([
                'exchange' => $exchange,
                'history' => $history,
                'risk_tag' => $riskTag,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to load exchange', null, 500);
        }
    }
}
