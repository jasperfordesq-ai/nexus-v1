<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Services\MatchApprovalWorkflowService;

/**
 * MatchApprovalsController - Admin Dashboard for Match Approvals
 *
 * Provides broker/admin interface for:
 * - Viewing pending match approvals
 * - Approving/rejecting matches
 * - Viewing approval history
 * - Statistics on approval workflow
 */
class MatchApprovalsController
{
    /**
     * Require admin or broker access
     */
    private function requireBrokerAccess(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $allowedRoles = ['admin', 'tenant_admin', 'broker', 'super_admin'];
        $isAllowed = in_array($role, $allowedRoles);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAllowed && !$isSuper && !$isAdminSession) {
            header('HTTP/1.0 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>Access Denied. Broker or admin access required.</p>";
            exit;
        }
    }

    /**
     * Main dashboard - pending approvals
     */
    public function index(): void
    {
        $this->requireBrokerAccess();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Get pending requests
        $pendingRequests = MatchApprovalWorkflowService::getPendingRequests($limit, $offset);

        // Get statistics
        $stats = MatchApprovalWorkflowService::getStatistics(30);

        // Get total pending for pagination
        $totalPending = MatchApprovalWorkflowService::getPendingCount();
        $totalPages = ceil($totalPending / $limit);

        View::render('admin/match-approvals/index', [
            'pending_requests' => $pendingRequests,
            'stats' => $stats,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_pending' => $totalPending,
            'page_title' => 'Match Approvals',
            'csrf_token' => Csrf::token(),
        ]);
    }

    /**
     * Approval history
     */
    public function history(): void
    {
        $this->requireBrokerAccess();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $filters = [];
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        $history = MatchApprovalWorkflowService::getApprovalHistory($filters, $limit, $offset);

        View::render('admin/match-approvals/history', [
            'history' => $history,
            'filters' => $filters,
            'page' => $page,
            'page_title' => 'Approval History',
        ]);
    }

    /**
     * View single approval request detail
     */
    public function show(int $id): void
    {
        $this->requireBrokerAccess();

        $request = MatchApprovalWorkflowService::getRequest($id);

        if (!$request) {
            header('HTTP/1.0 404 Not Found');
            echo "<h1>404 Not Found</h1><p>Approval request not found.</p>";
            exit;
        }

        // Get additional user and listing details
        $user = Database::query(
            "SELECT u.*,
                    (SELECT COUNT(*) FROM transactions WHERE (sender_id = u.id OR recipient_id = u.id)) as transaction_count,
                    (SELECT AVG(rating) FROM reviews WHERE user_id = u.id) as avg_rating
             FROM users u WHERE u.id = ?",
            [$request['user_id']]
        )->fetch();

        $listing = Database::query(
            "SELECT l.*, c.name as category_name,
                    u.first_name as owner_first_name, u.last_name as owner_last_name,
                    u.avatar_url as owner_avatar
             FROM listings l
             LEFT JOIN categories c ON l.category_id = c.id
             JOIN users u ON l.user_id = u.id
             WHERE l.id = ?",
            [$request['listing_id']]
        )->fetch();

        // Get reviewer name if reviewed
        $reviewerName = null;
        if (!empty($request['reviewed_by'])) {
            $reviewer = Database::query(
                "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?",
                [$request['reviewed_by']]
            )->fetch();
            $reviewerName = $reviewer['name'] ?? null;
        }

        // Merge user data into request for view compatibility
        $request['user_first_name'] = $user['first_name'] ?? '';
        $request['user_last_name'] = $user['last_name'] ?? '';
        $request['user_name'] = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
        $request['user_email'] = $user['email'] ?? '';
        $request['user_avatar'] = $user['avatar_url'] ?? '';

        // Merge listing/owner data
        $request['listing_title'] = $listing['title'] ?? '';
        $request['listing_description'] = $listing['description'] ?? '';
        $request['listing_type'] = $listing['type'] ?? 'offer';
        $request['category_name'] = $listing['category_name'] ?? '';
        $request['owner_first_name'] = $listing['owner_first_name'] ?? '';
        $request['owner_last_name'] = $listing['owner_last_name'] ?? '';
        $request['owner_name'] = ($listing['owner_first_name'] ?? '') . ' ' . ($listing['owner_last_name'] ?? '');
        $request['owner_avatar'] = $listing['owner_avatar'] ?? '';
        $request['reviewer_name'] = $reviewerName;

        View::render('admin/match-approvals/show', [
            'request' => $request,
            'user' => $user,
            'listing' => $listing,
            'page_title' => 'Match Approval Detail',
            'csrf_token' => Csrf::token(),
        ]);
    }

    /**
     * Approve a match (POST)
     */
    public function approve(): void
    {
        $this->requireBrokerAccess();
        Csrf::verifyOrDie();

        $requestId = (int)($_POST['request_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $userId = $_SESSION['user_id'];

        // Handle bulk approval
        if (!empty($_POST['request_ids']) && is_array($_POST['request_ids'])) {
            $requestIds = array_map('intval', $_POST['request_ids']);
            $count = MatchApprovalWorkflowService::bulkApprove($requestIds, $userId, $notes);

            $this->jsonResponse([
                'success' => true,
                'message' => "$count match(es) approved successfully.",
                'count' => $count
            ]);
            return;
        }

        // Single approval
        if (!$requestId) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid request ID.'], 400);
            return;
        }

        $success = MatchApprovalWorkflowService::approveMatch($requestId, $userId, $notes);

        if ($success) {
            $this->jsonResponse([
                'success' => true,
                'message' => 'Match approved successfully. The user has been notified.'
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to approve match. It may have already been processed.'
            ], 400);
        }
    }

    /**
     * Reject a match (POST)
     */
    public function reject(): void
    {
        $this->requireBrokerAccess();
        Csrf::verifyOrDie();

        $requestId = (int)($_POST['request_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $userId = $_SESSION['user_id'];

        // Require a reason for rejection
        if (empty($reason)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Please provide a reason for rejection. This will be shown to the user.'
            ], 400);
            return;
        }

        // Handle bulk rejection
        if (!empty($_POST['request_ids']) && is_array($_POST['request_ids'])) {
            $requestIds = array_map('intval', $_POST['request_ids']);
            $count = MatchApprovalWorkflowService::bulkReject($requestIds, $userId, $reason);

            $this->jsonResponse([
                'success' => true,
                'message' => "$count match(es) rejected. Users have been notified.",
                'count' => $count
            ]);
            return;
        }

        // Single rejection
        if (!$requestId) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid request ID.'], 400);
            return;
        }

        $success = MatchApprovalWorkflowService::rejectMatch($requestId, $userId, $reason);

        if ($success) {
            $this->jsonResponse([
                'success' => true,
                'message' => 'Match rejected. The user has been notified with your reason.'
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to reject match. It may have already been processed.'
            ], 400);
        }
    }

    /**
     * API endpoint for dashboard statistics (JSON)
     */
    public function apiStats(): void
    {
        $this->requireBrokerAccess();

        $days = (int)($_GET['days'] ?? 30);
        $stats = MatchApprovalWorkflowService::getStatistics($days);

        $this->jsonResponse([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Send JSON response
     */
    private function jsonResponse(array $data, int $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}
