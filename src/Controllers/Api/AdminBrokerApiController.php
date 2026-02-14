<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

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
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Pending exchanges
        $pendingExchanges = 0;
        try {
            $row = Database::query(
                "SELECT COUNT(*) as cnt FROM exchange_requests WHERE tenant_id = ? AND status = 'pending_broker'",
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

        $this->respondWithData([
            'pending_exchanges' => $pendingExchanges,
            'unreviewed_messages' => $unreviewedMessages,
            'high_risk_listings' => $highRiskListings,
            'monitored_users' => $monitoredUsers,
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
        $this->requireAdmin();
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
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $notes = $this->input('notes', '');

        try {
            // Verify the exchange belongs to this tenant and is pending
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

            Database::query(
                "UPDATE exchange_requests SET status = 'approved', broker_id = ?, broker_notes = ?, broker_approved_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$adminId, $notes, $id, $tenantId]
            );

            $this->respondWithData(['id' => $id, 'status' => 'approved']);
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
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $reason = $this->input('reason', '');

        if (empty($reason)) {
            $this->respondWithError('VALIDATION_ERROR', 'A reason is required to reject an exchange', 'reason');
            return;
        }

        try {
            // Verify the exchange belongs to this tenant and is pending
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

            Database::query(
                "UPDATE exchange_requests SET status = 'rejected', broker_id = ?, broker_notes = ? WHERE id = ? AND tenant_id = ?",
                [$adminId, $reason, $id, $tenantId]
            );

            $this->respondWithData(['id' => $id, 'status' => 'rejected']);
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
        $this->requireAdmin();
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
        $this->requireAdmin();
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
        $adminId = $this->requireAdmin();
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
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        try {
            $items = Database::query(
                "SELECT umr.*,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM user_messaging_restrictions umr
                LEFT JOIN users u ON umr.user_id = u.id
                WHERE umr.tenant_id = ?
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
}
