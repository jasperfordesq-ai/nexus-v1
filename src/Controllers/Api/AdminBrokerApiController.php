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
 * - GET  /api/v2/admin/broker/exchanges/{id}           - Exchange detail with history
 * - POST /api/v2/admin/broker/exchanges/{id}/approve   - Approve exchange
 * - POST /api/v2/admin/broker/exchanges/{id}/reject    - Reject exchange
 * - GET  /api/v2/admin/broker/risk-tags               - List risk tags
 * - POST /api/v2/admin/broker/risk-tags/{listingId}    - Create/update risk tag
 * - DELETE /api/v2/admin/broker/risk-tags/{listingId}  - Remove risk tag
 * - GET  /api/v2/admin/broker/messages                - List broker message copies
 * - GET  /api/v2/admin/broker/messages/{id}            - Message detail with thread
 * - POST /api/v2/admin/broker/messages/{id}/review     - Mark message reviewed
 * - POST /api/v2/admin/broker/messages/{id}/flag       - Flag a message
 * - POST /api/v2/admin/broker/messages/{id}/approve    - Approve and archive message
 * - GET  /api/v2/admin/broker/archives                - List archived reviews
 * - GET  /api/v2/admin/broker/archives/{id}            - Archive detail with snapshot
 * - GET  /api/v2/admin/broker/monitoring              - List monitored users
 * - POST /api/v2/admin/broker/monitoring/{userId}      - Set/remove user monitoring
 * - GET  /api/v2/admin/broker/configuration           - Get broker config
 * - POST /api/v2/admin/broker/configuration           - Save broker config
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
        $tenantWhere = $effectiveTenantId !== null ? 'tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        // Pending exchanges
        $pendingExchanges = 0;
        try {
            $row = Database::query(
                "SELECT COUNT(*) as cnt FROM exchange_requests WHERE {$tenantWhere} AND status IN ('pending_broker', 'disputed')",
                $tenantParams
            )->fetch();
            $pendingExchanges = (int) ($row['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Unreviewed messages
        $unreviewedMessages = 0;
        try {
            $row = Database::query(
                "SELECT COUNT(*) as cnt FROM broker_message_copies WHERE {$tenantWhere} AND reviewed_by IS NULL",
                $tenantParams
            )->fetch();
            $unreviewedMessages = (int) ($row['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // High risk listings
        $highRiskListings = 0;
        try {
            $row = Database::query(
                "SELECT COUNT(*) as cnt FROM listing_risk_tags WHERE {$tenantWhere} AND risk_level = 'high'",
                $tenantParams
            )->fetch();
            $highRiskListings = (int) ($row['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Monitored users
        $monitoredUsers = 0;
        try {
            $row = Database::query(
                "SELECT COUNT(*) as cnt FROM user_messaging_restrictions WHERE {$tenantWhere} AND under_monitoring = 1",
                $tenantParams
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
                 FROM vetting_records WHERE {$tenantWhere}",
                $tenantParams
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
                "SELECT COUNT(*) as cnt FROM abuse_alerts WHERE {$tenantWhere} AND status = 'open'",
                $tenantParams
            )->fetch();
            $safeguardingAlerts = (int) ($row['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Recent broker activity (last 20 actions)
        $recentActivity = [];
        try {
            $actWhere = $effectiveTenantId !== null ? 'al.tenant_id = ?' : '1=1';
            $actParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];
            $recentActivity = Database::query(
                "SELECT al.*, u.first_name, u.last_name, t.name as tenant_name
                 FROM activity_logs al
                 LEFT JOIN users u ON u.id = al.user_id
                 LEFT JOIN tenants t ON al.tenant_id = t.id
                 WHERE {$actWhere} AND al.action_type IN (
                     'exchange_approved', 'exchange_rejected', 'message_reviewed',
                     'risk_tag_added', 'user_monitored', 'vetting_verified', 'vetting_rejected',
                     'user_banned', 'user_unbanned', 'balance_adjusted'
                 )
                 ORDER BY al.created_at DESC
                 LIMIT 20",
                $actParams
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $status = $this->query('status');
        $offset = ($page - 1) * $perPage;

        try {
            // Build WHERE clause with tenant scoping
            $conditions = [];
            $params = [];

            $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
            if ($effectiveTenantId !== null) {
                $conditions[] = 'er.tenant_id = ?';
                $params[] = $effectiveTenantId;
            }

            if ($status && $status !== 'all') {
                $conditions[] = 'er.status = ?';
                $params[] = $status;
            }

            $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

            // Get total count
            $countRow = Database::query(
                "SELECT COUNT(*) as cnt FROM exchange_requests er WHERE {$where}",
                $params
            )->fetch();
            $total = (int) ($countRow['cnt'] ?? 0);

            // Get paginated data — join tenants table for cross-tenant name display
            $queryParams = array_merge($params, [$perPage, $offset]);
            $items = Database::query(
                "SELECT er.*,
                    CONCAT(req.first_name, ' ', req.last_name) as requester_name,
                    CONCAT(prov.first_name, ' ', prov.last_name) as provider_name,
                    l.title as listing_title,
                    t.name as tenant_name
                FROM exchange_requests er
                JOIN users req ON er.requester_id = req.id
                JOIN users prov ON er.provider_id = prov.id
                LEFT JOIN listings l ON er.listing_id = l.id
                LEFT JOIN tenants t ON er.tenant_id = t.id
                WHERE {$where}
                ORDER BY er.created_at DESC
                LIMIT ? OFFSET ?",
                $queryParams
            )->fetchAll();

            // Add tenant info to each item
            foreach ($items as &$item) {
                $item['tenant_name'] = $item['tenant_name'] ?? 'Unknown';
            }
            unset($item);

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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $notes = $this->input('notes', '');

        try {
            // Super admins can approve exchanges from any tenant
            if ($isSuperAdmin) {
                $exchange = Database::query(
                    "SELECT id, status, tenant_id FROM exchange_requests WHERE id = ?",
                    [$id]
                )->fetch();
            } else {
                $exchange = Database::query(
                    "SELECT id, status, tenant_id FROM exchange_requests WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $reason = $this->input('reason', '');

        if (empty($reason)) {
            $this->respondWithError('VALIDATION_ERROR', 'A reason is required to reject an exchange', 'reason');
            return;
        }

        try {
            // Super admins can reject exchanges from any tenant
            if ($isSuperAdmin) {
                $exchange = Database::query(
                    "SELECT id, status, tenant_id FROM exchange_requests WHERE id = ?",
                    [$id]
                )->fetch();
            } else {
                $exchange = Database::query(
                    "SELECT id, status, tenant_id FROM exchange_requests WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $riskLevel = $this->query('risk_level');

        try {
            $conditions = [];
            $params = [];

            // Tenant scoping
            $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
            if ($effectiveTenantId !== null) {
                $conditions[] = 'rt.tenant_id = ?';
                $params[] = $effectiveTenantId;
            }

            if ($riskLevel && $riskLevel !== 'all') {
                $conditions[] = 'rt.risk_level = ?';
                $params[] = $riskLevel;
            }

            $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

            $items = Database::query(
                "SELECT rt.*,
                    l.title as listing_title,
                    CONCAT(u.first_name, ' ', u.last_name) as owner_name,
                    t.name as tenant_name
                FROM listing_risk_tags rt
                LEFT JOIN listings l ON rt.listing_id = l.id
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN tenants t ON rt.tenant_id = t.id
                WHERE {$where}
                ORDER BY FIELD(rt.risk_level, 'critical', 'high', 'medium', 'low'), rt.created_at DESC",
                $params
            )->fetchAll();

            foreach ($items as &$item) {
                $item['tenant_name'] = $item['tenant_name'] ?? 'Unknown';
            }
            unset($item);

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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $filter = $this->query('filter', 'all');
        $offset = ($page - 1) * $perPage;

        try {
            $conditions = [];
            $params = [];

            // Tenant scoping
            $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
            if ($effectiveTenantId !== null) {
                $conditions[] = 'bmc.tenant_id = ?';
                $params[] = $effectiveTenantId;
            }

            if ($filter === 'unreviewed') {
                $conditions[] = 'bmc.reviewed_by IS NULL';
            } elseif ($filter === 'flagged') {
                $conditions[] = 'bmc.flagged = 1';
            } elseif ($filter === 'reviewed') {
                $conditions[] = 'bmc.reviewed_by IS NOT NULL';
            }

            $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

            // Count
            $countRow = Database::query(
                "SELECT COUNT(*) as cnt FROM broker_message_copies bmc WHERE {$where}",
                $params
            )->fetch();
            $total = (int) ($countRow['cnt'] ?? 0);

            // Paginated data — join tenants table for cross-tenant name display
            $queryParams = array_merge($params, [$perPage, $offset]);
            $items = Database::query(
                "SELECT bmc.*,
                    CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                    CONCAT(r.first_name, ' ', r.last_name) as receiver_name,
                    l.title as listing_title,
                    t.name as tenant_name
                FROM broker_message_copies bmc
                LEFT JOIN users s ON bmc.sender_id = s.id
                LEFT JOIN users r ON bmc.receiver_id = r.id
                LEFT JOIN listings l ON bmc.related_listing_id = l.id
                LEFT JOIN tenants t ON bmc.tenant_id = t.id
                WHERE {$where}
                ORDER BY bmc.created_at DESC
                LIMIT ? OFFSET ?",
                $queryParams
            )->fetchAll();

            // Normalize boolean fields and add tenant info
            foreach ($items as &$item) {
                $item['flagged'] = (bool) ($item['flagged'] ?? false);
                $item['tenant_name'] = $item['tenant_name'] ?? 'Unknown';
            }
            unset($item);

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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Super admins can review messages from any tenant
            if ($isSuperAdmin) {
                $message = Database::query(
                    "SELECT id, tenant_id FROM broker_message_copies WHERE id = ?",
                    [$id]
                )->fetch();
            } else {
                $message = Database::query(
                    "SELECT id, tenant_id FROM broker_message_copies WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$message) {
                $this->respondWithError('NOT_FOUND', 'Message not found', null, 404);
                return;
            }

            // Use the record's own tenant_id for the update
            $recordTenantId = (int) $message['tenant_id'];
            Database::query(
                "UPDATE broker_message_copies SET reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$adminId, $id, $recordTenantId]
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            $conditions = ['umr.under_monitoring = 1'];
            $params = [];

            // Tenant scoping
            $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
            if ($effectiveTenantId !== null) {
                $conditions[] = 'umr.tenant_id = ?';
                $params[] = $effectiveTenantId;
            }

            $where = implode(' AND ', $conditions);

            $items = Database::query(
                "SELECT umr.*,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    t.name as tenant_name
                FROM user_messaging_restrictions umr
                LEFT JOIN users u ON umr.user_id = u.id
                LEFT JOIN tenants t ON umr.tenant_id = t.id
                WHERE {$where}
                ORDER BY umr.monitoring_started_at DESC",
                $params
            )->fetchAll();

            // Normalize boolean fields and add tenant info
            foreach ($items as &$item) {
                $item['under_monitoring'] = (bool) ($item['under_monitoring'] ?? false);
                $item['tenant_name'] = $item['tenant_name'] ?? 'Unknown';
            }
            unset($item);

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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
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
            // Super admins can flag messages from any tenant
            if ($isSuperAdmin) {
                $message = Database::query(
                    "SELECT id, tenant_id FROM broker_message_copies WHERE id = ?",
                    [$id]
                )->fetch();
            } else {
                $message = Database::query(
                    "SELECT id, tenant_id FROM broker_message_copies WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$message) {
                $this->respondWithError('NOT_FOUND', 'Message not found', null, 404);
                return;
            }

            // Use the record's own tenant_id for the update
            $recordTenantId = (int) $message['tenant_id'];
            Database::query(
                "UPDATE broker_message_copies SET flagged = 1, flag_reason = ?, flag_severity = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$reason, $severity, $adminId, $id, $recordTenantId]
            );

            $this->respondWithData(['id' => $id, 'flagged' => true, 'flag_reason' => $reason, 'flag_severity' => $severity]);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to flag message', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/broker/messages/{id}
     *
     * Returns full detail for a single broker message copy including the
     * complete conversation thread between sender and receiver.
     */
    public function showMessage(int $id): void
    {
        $this->requireBrokerAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Super admins can view messages from any tenant
            if ($isSuperAdmin) {
                $copy = Database::query(
                    "SELECT bmc.*,
                        CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                        CONCAT(r.first_name, ' ', r.last_name) as receiver_name,
                        l.title as listing_title,
                        t.name as tenant_name
                    FROM broker_message_copies bmc
                    LEFT JOIN users s ON bmc.sender_id = s.id
                    LEFT JOIN users r ON bmc.receiver_id = r.id
                    LEFT JOIN listings l ON bmc.related_listing_id = l.id
                    LEFT JOIN tenants t ON bmc.tenant_id = t.id
                    WHERE bmc.id = ?",
                    [$id]
                )->fetch();
            } else {
                $copy = Database::query(
                    "SELECT bmc.*,
                        CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                        CONCAT(r.first_name, ' ', r.last_name) as receiver_name,
                        l.title as listing_title,
                        t.name as tenant_name
                    FROM broker_message_copies bmc
                    LEFT JOIN users s ON bmc.sender_id = s.id
                    LEFT JOIN users r ON bmc.receiver_id = r.id
                    LEFT JOIN listings l ON bmc.related_listing_id = l.id
                    LEFT JOIN tenants t ON bmc.tenant_id = t.id
                    WHERE bmc.id = ? AND bmc.tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$copy) {
                $this->respondWithError('NOT_FOUND', 'Broker message copy not found', null, 404);
                return;
            }

            $copy['flagged'] = (bool) ($copy['flagged'] ?? false);
            $copy['tenant_name'] = $copy['tenant_name'] ?? 'Unknown';
            $copyTenantId = (int) $copy['tenant_id'];

            // Load full conversation thread between sender and receiver — use the copy's own tenant_id
            $thread = Database::query(
                "SELECT m.id, m.sender_id, m.receiver_id, m.body, m.created_at, m.is_deleted,
                    CONCAT(u.first_name, ' ', u.last_name) as sender_name
                FROM messages m
                LEFT JOIN users u ON m.sender_id = u.id
                WHERE m.tenant_id = ?
                  AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                ORDER BY m.created_at ASC
                LIMIT 200",
                [$copyTenantId, $copy['sender_id'], $copy['receiver_id'], $copy['receiver_id'], $copy['sender_id']]
            )->fetchAll();

            // Redact deleted messages
            foreach ($thread as &$msg) {
                if (!empty($msg['is_deleted'])) {
                    $msg['body'] = '[Message deleted]';
                }
            }
            unset($msg);

            // If copy has an archive, load it — use the copy's own tenant_id
            $archive = null;
            if (!empty($copy['archive_id'])) {
                $archive = Database::query(
                    "SELECT id, decision, decision_notes, decided_by_name, decided_at, flag_reason, flag_severity
                    FROM broker_review_archives
                    WHERE id = ? AND tenant_id = ?",
                    [$copy['archive_id'], $copyTenantId]
                )->fetch() ?: null;
            }

            $this->respondWithData([
                'copy' => $copy,
                'thread' => $thread,
                'archive' => $archive,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to load message detail', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/broker/messages/{id}/approve
     *
     * Approve a broker message copy and create an immutable compliance archive.
     * The archive captures a frozen snapshot of the conversation at decision time.
     * Body: { notes?: string }
     */
    public function approveMessage(int $id): void
    {
        $adminId = $this->requireBrokerAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $notes = trim($this->input('notes', ''));

        try {
            // Super admins can approve messages from any tenant
            if ($isSuperAdmin) {
                $copy = Database::query(
                    "SELECT bmc.*,
                        CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                        CONCAT(r.first_name, ' ', r.last_name) as receiver_name,
                        l.title as listing_title
                    FROM broker_message_copies bmc
                    LEFT JOIN users s ON bmc.sender_id = s.id
                    LEFT JOIN users r ON bmc.receiver_id = r.id
                    LEFT JOIN listings l ON bmc.related_listing_id = l.id
                    WHERE bmc.id = ?",
                    [$id]
                )->fetch();
            } else {
                $copy = Database::query(
                    "SELECT bmc.*,
                        CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                        CONCAT(r.first_name, ' ', r.last_name) as receiver_name,
                        l.title as listing_title
                    FROM broker_message_copies bmc
                    LEFT JOIN users s ON bmc.sender_id = s.id
                    LEFT JOIN users r ON bmc.receiver_id = r.id
                    LEFT JOIN listings l ON bmc.related_listing_id = l.id
                    WHERE bmc.id = ? AND bmc.tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$copy) {
                $this->respondWithError('NOT_FOUND', 'Broker message copy not found', null, 404);
                return;
            }

            // Use the record's own tenant_id for all writes
            $copyTenantId = (int) $copy['tenant_id'];

            // Prevent double-archiving
            if (!empty($copy['archive_id'])) {
                $this->respondWithError('ALREADY_ARCHIVED', 'This message copy has already been archived', null, 409);
                return;
            }

            // Get the broker/admin name for the archive
            $adminRow = Database::query(
                "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?",
                [$adminId]
            )->fetch();
            $adminName = $adminRow['name'] ?? 'Unknown';

            // Snapshot the full conversation between sender and receiver — use the copy's own tenant_id
            $conversationRows = Database::query(
                "SELECT m.id, m.sender_id, m.body, m.created_at, m.is_deleted,
                    CONCAT(u.first_name, ' ', u.last_name) as sender_name
                FROM messages m
                LEFT JOIN users u ON m.sender_id = u.id
                WHERE m.tenant_id = ?
                  AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                ORDER BY m.created_at ASC
                LIMIT 500",
                [$copyTenantId, $copy['sender_id'], $copy['receiver_id'], $copy['receiver_id'], $copy['sender_id']]
            )->fetchAll();

            // Redact deleted messages in snapshot
            foreach ($conversationRows as &$msg) {
                if (!empty($msg['is_deleted'])) {
                    $msg['body'] = '[Message deleted]';
                }
                unset($msg['is_deleted']);
            }
            unset($msg);

            $conversationSnapshot = json_encode($conversationRows);

            // Determine decision based on flag status
            $decision = !empty($copy['flagged']) ? 'flagged' : 'approved';

            // INSERT the immutable archive record — use the copy's own tenant_id
            Database::query(
                "INSERT INTO broker_review_archives
                    (tenant_id, broker_copy_id, sender_id, sender_name, receiver_id, receiver_name,
                     related_listing_id, listing_title, copy_reason, target_message_body, target_message_sent_at,
                     conversation_snapshot, decision, decision_notes, decided_by, decided_by_name,
                     decided_at, flag_reason, flag_severity, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())",
                [
                    $copyTenantId,
                    $id,
                    $copy['sender_id'],
                    $copy['sender_name'] ?? '',
                    $copy['receiver_id'],
                    $copy['receiver_name'] ?? '',
                    $copy['related_listing_id'],
                    $copy['listing_title'],
                    $copy['copy_reason'],
                    $copy['message_body'] ?? '',
                    $copy['sent_at'],
                    $conversationSnapshot,
                    $decision,
                    $notes ?: null,
                    $adminId,
                    $adminName,
                    $copy['flag_reason'],
                    $copy['flag_severity'],
                ]
            );

            $archiveId = Database::lastInsertId();

            // Update the broker copy: link to archive, set reviewed if not already — use the copy's own tenant_id
            Database::query(
                "UPDATE broker_message_copies
                 SET archived_at = NOW(),
                     archive_id = ?,
                     reviewed_by = COALESCE(reviewed_by, ?),
                     reviewed_at = COALESCE(reviewed_at, NOW())
                 WHERE id = ? AND tenant_id = ?",
                [$archiveId, $adminId, $id, $copyTenantId]
            );

            $this->respondWithData([
                'id' => $id,
                'archive_id' => $archiveId,
                'decision' => $decision,
                'decided_by' => $adminName,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to archive message', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/broker/archives
     *
     * List archived broker reviews with pagination, search, and filtering.
     * Supports: ?page=, ?per_page=, ?decision=, ?search=, ?from=, ?to=
     */
    public function archives(): void
    {
        $this->requireBrokerAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $decision = $this->query('decision');
        $search = $this->query('search');
        $from = $this->query('from');
        $to = $this->query('to');
        $offset = ($page - 1) * $perPage;

        try {
            $conditions = [];
            $params = [];

            // Tenant scoping
            $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
            if ($effectiveTenantId !== null) {
                $conditions[] = 'bra.tenant_id = ?';
                $params[] = $effectiveTenantId;
            }

            if ($decision && $decision !== 'all') {
                $conditions[] = 'bra.decision = ?';
                $params[] = $decision;
            }

            if ($search) {
                $conditions[] = '(bra.sender_name LIKE ? OR bra.receiver_name LIKE ?)';
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }

            if ($from) {
                $conditions[] = 'bra.decided_at >= ?';
                $params[] = $from;
            }

            if ($to) {
                $conditions[] = 'bra.decided_at <= ?';
                $params[] = $to . ' 23:59:59';
            }

            $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

            // Count total
            $countRow = Database::query(
                "SELECT COUNT(*) as cnt FROM broker_review_archives bra WHERE {$where}",
                $params
            )->fetch();
            $total = (int) ($countRow['cnt'] ?? 0);

            // Paginated data (exclude large conversation_snapshot from list view) — join tenants for name
            $queryParams = array_merge($params, [$perPage, $offset]);
            $items = Database::query(
                "SELECT bra.id, bra.tenant_id, bra.broker_copy_id, bra.sender_id, bra.sender_name, bra.receiver_id, bra.receiver_name,
                    bra.related_listing_id, bra.listing_title, bra.copy_reason, bra.decision, bra.decision_notes,
                    bra.decided_by, bra.decided_by_name, bra.decided_at, bra.flag_reason, bra.flag_severity, bra.created_at,
                    t.name as tenant_name
                FROM broker_review_archives bra
                LEFT JOIN tenants t ON bra.tenant_id = t.id
                WHERE {$where}
                ORDER BY bra.decided_at DESC
                LIMIT ? OFFSET ?",
                $queryParams
            )->fetchAll();

            foreach ($items as &$item) {
                $item['tenant_name'] = $item['tenant_name'] ?? 'Unknown';
            }
            unset($item);

            $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    /**
     * GET /api/v2/admin/broker/archives/{id}
     *
     * Return the full archive detail including frozen conversation snapshot.
     * The conversation snapshot is an immutable record of the thread at decision time.
     */
    public function showArchive(int $id): void
    {
        $this->requireBrokerAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Super admins can view archives from any tenant
            if ($isSuperAdmin) {
                $archive = Database::query(
                    "SELECT bra.*, t.name as tenant_name
                     FROM broker_review_archives bra
                     LEFT JOIN tenants t ON bra.tenant_id = t.id
                     WHERE bra.id = ?",
                    [$id]
                )->fetch();
            } else {
                $archive = Database::query(
                    "SELECT bra.*, t.name as tenant_name
                     FROM broker_review_archives bra
                     LEFT JOIN tenants t ON bra.tenant_id = t.id
                     WHERE bra.id = ? AND bra.tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$archive) {
                $this->respondWithError('NOT_FOUND', 'Archive not found', null, 404);
                return;
            }

            $archive['tenant_name'] = $archive['tenant_name'] ?? 'Unknown';

            // Decode the conversation snapshot from JSON
            if (!empty($archive['conversation_snapshot'])) {
                $archive['conversation_snapshot'] = json_decode($archive['conversation_snapshot'], true) ?? [];
            } else {
                $archive['conversation_snapshot'] = [];
            }

            $this->respondWithData($archive);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to load archive', null, 500);
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $underMonitoring = (bool) $this->input('under_monitoring', true);
        $reason = trim($this->input('reason', ''));
        $messagingDisabled = (bool) $this->input('messaging_disabled', false);

        try {
            // Super admins can manage monitoring for users from any tenant
            if ($isSuperAdmin) {
                $user = Database::query(
                    "SELECT id, tenant_id FROM users WHERE id = ?",
                    [$userId]
                )->fetch();
            } else {
                $user = Database::query(
                    "SELECT id, tenant_id FROM users WHERE id = ? AND tenant_id = ?",
                    [$userId, $tenantId]
                )->fetch();
            }

            if (!$user) {
                $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
                return;
            }

            // Use the user's own tenant_id for all writes
            $userTenantId = (int) $user['tenant_id'];

            // Check if record already exists
            $existing = Database::query(
                "SELECT id FROM user_messaging_restrictions WHERE user_id = ? AND tenant_id = ?",
                [$userId, $userTenantId]
            )->fetch();

            if ($underMonitoring) {
                if (empty($reason)) {
                    $this->respondWithError('VALIDATION_ERROR', 'A reason is required to set monitoring', 'reason');
                    return;
                }

                if ($existing) {
                    Database::query(
                        "UPDATE user_messaging_restrictions SET under_monitoring = 1, monitoring_reason = ?, restriction_reason = ?, messaging_disabled = ?, monitoring_started_at = NOW(), restricted_by = ? WHERE user_id = ? AND tenant_id = ?",
                        [$reason, $reason, $messagingDisabled ? 1 : 0, $adminId, $userId, $userTenantId]
                    );
                } else {
                    Database::query(
                        "INSERT INTO user_messaging_restrictions (user_id, tenant_id, under_monitoring, monitoring_reason, restriction_reason, messaging_disabled, monitoring_started_at, restricted_by) VALUES (?, ?, 1, ?, ?, ?, NOW(), ?)",
                        [$userId, $userTenantId, $reason, $reason, $messagingDisabled ? 1 : 0, $adminId]
                    );
                }
                $this->respondWithData(['user_id' => $userId, 'under_monitoring' => true]);
            } else {
                if ($existing) {
                    Database::query(
                        "UPDATE user_messaging_restrictions SET under_monitoring = 0, monitoring_reason = NULL, restriction_reason = NULL, monitoring_started_at = NULL WHERE user_id = ? AND tenant_id = ?",
                        [$userId, $userTenantId]
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
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
            // Super admins can tag listings from any tenant
            if ($isSuperAdmin) {
                $listing = Database::query(
                    "SELECT id, tenant_id FROM listings WHERE id = ?",
                    [$listingId]
                )->fetch();
            } else {
                $listing = Database::query(
                    "SELECT id, tenant_id FROM listings WHERE id = ? AND tenant_id = ?",
                    [$listingId, $tenantId]
                )->fetch();
            }

            if (!$listing) {
                $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
                return;
            }

            // Use the listing's own tenant_id for all writes
            $listingTenantId = (int) $listing['tenant_id'];

            // Upsert
            $existing = Database::query(
                "SELECT id FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?",
                [$listingId, $listingTenantId]
            )->fetch();

            if ($existing) {
                Database::query(
                    "UPDATE listing_risk_tags SET risk_level = ?, risk_category = ?, risk_notes = ?, member_visible_notes = ?, requires_approval = ?, insurance_required = ?, dbs_required = ?, tagged_by = ?, updated_at = NOW() WHERE listing_id = ? AND tenant_id = ?",
                    [$riskLevel, $riskCategory, $riskNotes, $memberVisibleNotes, $requiresApproval ? 1 : 0, $insuranceRequired ? 1 : 0, $dbsRequired ? 1 : 0, $adminId, $listingId, $listingTenantId]
                );
                $tagId = $existing['id'];
            } else {
                Database::query(
                    "INSERT INTO listing_risk_tags (listing_id, tenant_id, risk_level, risk_category, risk_notes, member_visible_notes, requires_approval, insurance_required, dbs_required, tagged_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$listingId, $listingTenantId, $riskLevel, $riskCategory, $riskNotes, $memberVisibleNotes, $requiresApproval ? 1 : 0, $insuranceRequired ? 1 : 0, $dbsRequired ? 1 : 0, $adminId]
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Super admins can remove risk tags from any tenant
            if ($isSuperAdmin) {
                $existing = Database::query(
                    "SELECT id, tenant_id FROM listing_risk_tags WHERE listing_id = ?",
                    [$listingId]
                )->fetch();
            } else {
                $existing = Database::query(
                    "SELECT id, tenant_id FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?",
                    [$listingId, $tenantId]
                )->fetch();
            }

            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Risk tag not found', null, 404);
                return;
            }

            // Use the record's own tenant_id for the delete
            $recordTenantId = (int) $existing['tenant_id'];
            Database::query(
                "DELETE FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?",
                [$listingId, $recordTenantId]
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);

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
            // Compliance & Safeguarding (defaults OFF — opt-in per tenant)
            'vetting_enabled' => false,
            'insurance_enabled' => false,
            'enforce_vetting_on_exchanges' => false,
            'enforce_insurance_on_exchanges' => false,
            'vetting_expiry_warning_days' => 30,
            'insurance_expiry_warning_days' => 30,
        ];

        // Determine which tenant's config to load
        $scopeTenantId = $effectiveTenantId ?? $tenantId;

        try {
            // Super admin with explicit ?tenant_id=all: show all tenant configs
            if ($effectiveTenantId === null) {
                $rows = Database::query(
                    "SELECT ts.tenant_id, ts.setting_value, t.name as tenant_name
                     FROM tenant_settings ts
                     LEFT JOIN tenants t ON ts.tenant_id = t.id
                     WHERE ts.setting_key = 'broker_config'
                     ORDER BY t.name ASC"
                )->fetchAll();

                $allConfigs = [];
                foreach ($rows as $r) {
                    $saved = json_decode($r['setting_value'], true) ?? [];
                    $allConfigs[] = [
                        'tenant_id' => (int) $r['tenant_id'],
                        'tenant_name' => $r['tenant_name'] ?? 'Unknown',
                        'config' => array_merge($defaults, $saved),
                    ];
                }

                $this->respondWithData($allConfigs);
                return;
            }

            $row = Database::query(
                "SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = 'broker_config'",
                [$scopeTenantId]
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $body = $this->getJsonInput();

        // Super admin can specify which tenant to configure
        $targetTenantId = $tenantId;
        if ($isSuperAdmin && !empty($body['tenant_id'])) {
            $targetTenantId = (int) $body['tenant_id'];
        }

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
            'vetting_enabled', 'insurance_enabled',
            'enforce_vetting_on_exchanges', 'enforce_insurance_on_exchanges',
            'vetting_expiry_warning_days', 'insurance_expiry_warning_days',
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
                [$targetTenantId]
            )->fetch();

            $json = json_encode($config);
            if ($existing) {
                Database::query(
                    "UPDATE tenant_settings SET setting_value = ?, updated_at = NOW() WHERE tenant_id = ? AND setting_key = 'broker_config'",
                    [$json, $targetTenantId]
                );
            } else {
                Database::query(
                    "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, created_at, updated_at) VALUES (?, 'broker_config', ?, NOW(), NOW())",
                    [$targetTenantId, $json]
                );
            }

            // Also sync workflow config to tenants.configuration for BrokerControlConfigService
            $workflowKeys = [
                'require_broker_approval', 'auto_approve_low_risk', 'max_hours_without_approval',
                'exchange_workflow_enabled', 'require_broker_approval_new_members',
                'require_broker_approval_high_risk', 'require_broker_approval_over_hours',
            ];
            $workflowConfig = [];
            foreach ($workflowKeys as $key) {
                if (array_key_exists($key, $body)) {
                    $workflowConfig[$key] = $body[$key];
                }
            }
            if (!empty($workflowConfig)) {
                \Nexus\Services\BrokerControlConfigService::updateConfig($workflowConfig);
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Super admins can view exchanges from any tenant
            if ($isSuperAdmin) {
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
                        l.hours_offered,
                        t.name as tenant_name
                    FROM exchange_requests er
                    JOIN users req ON er.requester_id = req.id
                    JOIN users prov ON er.provider_id = prov.id
                    LEFT JOIN listings l ON er.listing_id = l.id
                    LEFT JOIN tenants t ON er.tenant_id = t.id
                    WHERE er.id = ?",
                    [$id]
                )->fetch();
            } else {
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
                        l.hours_offered,
                        t.name as tenant_name
                    FROM exchange_requests er
                    JOIN users req ON er.requester_id = req.id
                    JOIN users prov ON er.provider_id = prov.id
                    LEFT JOIN listings l ON er.listing_id = l.id
                    LEFT JOIN tenants t ON er.tenant_id = t.id
                    WHERE er.id = ? AND er.tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$exchange) {
                $this->respondWithError('NOT_FOUND', 'Exchange request not found', null, 404);
                return;
            }

            $exchange['tenant_name'] = $exchange['tenant_name'] ?? 'Unknown';
            $exchangeTenantId = (int) $exchange['tenant_id'];

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

            // Risk tag for the listing — use the exchange's own tenant_id
            $riskTag = null;
            if (!empty($exchange['listing_id'])) {
                try {
                    $riskTag = Database::query(
                        "SELECT * FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?",
                        [$exchange['listing_id'], $exchangeTenantId]
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

    /**
     * GET /api/v2/admin/broker/messages/unreviewed-count
     * Lightweight endpoint for admin sidebar badge — returns unreviewed broker message count.
     */
    public function unreviewedCount(): void
    {
        $this->requireBrokerAdmin();

        $count = \Nexus\Services\BrokerMessageVisibilityService::countUnreviewed();

        $this->respondWithData(['count' => $count]);
    }
}
