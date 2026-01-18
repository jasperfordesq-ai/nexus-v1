<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Models\OrgMember;
use Nexus\Models\OrgWallet;
use Nexus\Models\OrgTransferRequest;
use Nexus\Models\OrgTransaction;
use Nexus\Models\VolOrganization;
use Nexus\Models\User;
use Nexus\Services\OrgWalletService;
use Nexus\Services\OrgNotificationService;

/**
 * OrgWalletController
 *
 * Handles organization wallet UI - dashboard, deposits, transfers, approvals.
 */
class OrgWalletController
{
    /**
     * Organization wallet dashboard
     */
    public function index($orgId)
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $org = VolOrganization::find($orgId);
        if (!$org) {
            $this->redirect('/volunteering/dashboard', 'Organization not found');
            return;
        }

        // Check if user is site admin (can view any org wallet)
        $isSiteAdmin = $this->isSiteAdmin($userId);

        // Check user is a member OR site admin
        if (!$isSiteAdmin && !OrgMember::isMember($orgId, $userId)) {
            $this->redirect('/volunteering/dashboard', 'You are not a member of this organization');
            return;
        }

        // Site admins get full admin access, otherwise check org role
        $isAdmin = $isSiteAdmin || OrgMember::isAdmin($orgId, $userId);
        $role = $isSiteAdmin ? 'admin' : (OrgMember::getRole($orgId, $userId) ?: 'viewer');

        // Get wallet summary
        $summary = OrgWalletService::getWalletSummary($orgId);

        // Get user's personal balance for deposits
        $user = User::findById($userId);

        // Get pending requests if admin
        $pendingRequests = [];
        if ($isAdmin) {
            $pendingRequests = OrgTransferRequest::getPendingForOrganization($orgId);
        }

        // Get transaction history
        $transactions = OrgWallet::getTransactionHistory($orgId, 20);

        // Get monthly stats for chart
        $monthlyStats = OrgTransaction::getMonthlyStats($orgId, 6);

        // Get members for transfer autocomplete
        $members = OrgMember::getMembers($orgId);

        View::render('organizations/wallet', [
            'pageTitle' => $org['name'] . ' - Wallet',
            'org' => $org,
            'user' => $user,
            'summary' => $summary,
            'isAdmin' => $isAdmin,
            'role' => $role,
            'pendingRequests' => $pendingRequests,
            'transactions' => $transactions,
            'monthlyStats' => $monthlyStats,
            'members' => $members,
        ]);
    }

    /**
     * Deposit credits to organization wallet
     */
    public function deposit($orgId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $amount = (float) ($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        $result = OrgWalletService::depositToOrg($userId, $orgId, $amount, $description);

        if ($result['success']) {
            $this->redirect("/organizations/$orgId/wallet", $result['message'], 'success');
        } else {
            $this->redirect("/organizations/$orgId/wallet", $result['message'], 'error');
        }
    }

    /**
     * Request transfer from organization wallet
     */
    public function requestTransfer($orgId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $recipientId = (int) ($_POST['recipient_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        // If recipient_id is 0, they're requesting for themselves
        if ($recipientId === 0) {
            $recipientId = $userId;
        }

        $result = OrgWalletService::createTransferRequest($orgId, $userId, $recipientId, $amount, $description);

        if ($result['success']) {
            $this->redirect("/organizations/$orgId/wallet", $result['message'], 'success');
        } else {
            $this->redirect("/organizations/$orgId/wallet", $result['message'], 'error');
        }
    }

    /**
     * Approve a transfer request (admin only)
     */
    public function approve($orgId, $requestId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $result = OrgWalletService::approveRequest($requestId, $userId);

        if ($result['success']) {
            $this->redirect("/organizations/$orgId/wallet", 'Transfer approved successfully', 'success');
        } else {
            $this->redirect("/organizations/$orgId/wallet", $result['message'], 'error');
        }
    }

    /**
     * Reject a transfer request (admin only)
     */
    public function reject($orgId, $requestId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $reason = trim($_POST['reason'] ?? '');

        $result = OrgWalletService::rejectRequest($requestId, $userId, $reason);

        if ($result['success']) {
            $this->redirect("/organizations/$orgId/wallet", 'Transfer request rejected', 'success');
        } else {
            $this->redirect("/organizations/$orgId/wallet", $result['message'], 'error');
        }
    }

    /**
     * Cancel own transfer request
     */
    public function cancel($orgId, $requestId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $result = OrgWalletService::cancelRequest($requestId, $userId);

        if ($result['success']) {
            $this->redirect("/organizations/$orgId/wallet", 'Transfer request cancelled', 'success');
        } else {
            $this->redirect("/organizations/$orgId/wallet", $result['message'], 'error');
        }
    }

    /**
     * Direct transfer from org wallet (admin only)
     */
    public function directTransfer($orgId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $recipientId = (int) ($_POST['recipient_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        $result = OrgWalletService::directTransferFromOrg($orgId, $recipientId, $amount, $description, $userId);

        if ($result['success']) {
            $this->redirect("/organizations/$orgId/wallet", $result['message'], 'success');
        } else {
            $this->redirect("/organizations/$orgId/wallet", $result['message'], 'error');
        }
    }

    /**
     * View all transfer requests (admin)
     */
    public function requests($orgId)
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $org = VolOrganization::find($orgId);
        if (!$org) {
            $this->redirect('/volunteering/dashboard', 'Organization not found');
            return;
        }

        if (!OrgMember::isAdmin($orgId, $userId)) {
            $this->redirect("/organizations/$orgId/wallet", 'Access denied');
            return;
        }

        $page = (int) ($_GET['page'] ?? 1);
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $requests = OrgTransferRequest::getAllForOrganization($orgId, $limit, $offset);
        $isOwner = OrgMember::isOwner($orgId, $userId);
        $pendingCount = OrgTransferRequest::countPending($orgId);

        View::render('organizations/transfer-requests', [
            'pageTitle' => $org['name'] . ' - Transfer Requests',
            'org' => $org,
            'requests' => $requests,
            'page' => $page,
            'isAdmin' => true,
            'isMember' => true,
            'isOwner' => $isOwner,
            'pendingCount' => $pendingCount,
        ]);
    }

    /**
     * Organization members management
     */
    public function members($orgId)
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $org = VolOrganization::find($orgId);
        if (!$org) {
            $this->redirect('/volunteering/dashboard', 'Organization not found');
            return;
        }

        $isAdmin = OrgMember::isAdmin($orgId, $userId);
        $isOwner = OrgMember::isOwner($orgId, $userId);
        $members = OrgMember::getMembers($orgId);
        $pendingMembers = [];
        $walletBalance = 0;

        if ($isAdmin) {
            $pendingMembers = OrgMember::getPendingRequests($orgId);
            // Get wallet balance for pay member feature
            $walletBalance = OrgWallet::getBalance($orgId);
        }

        View::render('organizations/members', [
            'pageTitle' => $org['name'] . ' - Members',
            'org' => $org,
            'members' => $members,
            'pendingMembers' => $pendingMembers,
            'isAdmin' => $isAdmin,
            'isOwner' => $isOwner,
            'walletBalance' => $walletBalance,
        ]);
    }

    /**
     * Invite a user to join the organization (admin only)
     */
    public function inviteMember($orgId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        if (!OrgMember::isAdmin($orgId, $userId)) {
            $this->redirect("/organizations/$orgId/members", 'Access denied');
            return;
        }

        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $this->redirect("/organizations/$orgId/members", 'Email is required', 'error');
            return;
        }

        // Find user by email
        $invitee = User::findByEmail($email);
        if (!$invitee) {
            $this->redirect("/organizations/$orgId/members", 'No user found with that email', 'error');
            return;
        }

        // Check if already a member
        if (OrgMember::isMember($orgId, $invitee['id'])) {
            $this->redirect("/organizations/$orgId/members", 'User is already a member', 'error');
            return;
        }

        // Add as invited
        OrgMember::add($orgId, $invitee['id'], 'member', 'active');

        // Notify the user they've been added
        OrgNotificationService::notifyAddedToOrganization($invitee['id'], $orgId, 'member', $userId);

        $this->redirect("/organizations/$orgId/members", 'Member added successfully', 'success');
    }

    /**
     * Approve a pending membership request (admin only)
     */
    public function approveMember($orgId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        if (!OrgMember::isAdmin($orgId, $userId)) {
            $this->redirect("/organizations/$orgId/members", 'Access denied');
            return;
        }

        $memberId = (int) ($_POST['member_id'] ?? 0);
        OrgMember::updateStatus($orgId, $memberId, 'active');

        // Notify the member their request was approved
        OrgNotificationService::notifyMembershipApproved($memberId, $orgId, $userId);

        $this->redirect("/organizations/$orgId/members", 'Member approved', 'success');
    }

    /**
     * Reject a pending membership request (admin only)
     */
    public function rejectMember($orgId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        if (!OrgMember::isAdmin($orgId, $userId)) {
            $this->redirect("/organizations/$orgId/members", 'Access denied');
            return;
        }

        $memberId = (int) ($_POST['member_id'] ?? 0);
        OrgMember::updateStatus($orgId, $memberId, 'removed');

        // Notify the member their request was rejected
        OrgNotificationService::notifyMembershipRejected($memberId, $orgId);

        $this->redirect("/organizations/$orgId/members", 'Request rejected', 'success');
    }

    /**
     * Update member role (admin only)
     */
    public function updateMemberRole($orgId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        if (!OrgMember::isOwner($orgId, $userId)) {
            $this->redirect("/organizations/$orgId/members", 'Only the owner can change roles');
            return;
        }

        $memberId = (int) ($_POST['member_id'] ?? 0);
        $role = $_POST['role'] ?? 'member';

        if (!in_array($role, ['admin', 'member'])) {
            $this->redirect("/organizations/$orgId/members", 'Invalid role');
            return;
        }

        OrgMember::updateRole($orgId, $memberId, $role, $userId);

        // Notify the member their role changed
        OrgNotificationService::notifyRoleChanged($memberId, $orgId, $role, $userId);

        $this->redirect("/organizations/$orgId/members", 'Member role updated', 'success');
    }

    /**
     * Remove member (admin only)
     */
    public function removeMember($orgId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        if (!OrgMember::isAdmin($orgId, $userId)) {
            $this->redirect("/organizations/$orgId/members", 'Access denied');
            return;
        }

        $memberId = (int) ($_POST['member_id'] ?? 0);

        // Cannot remove the owner
        if (OrgMember::isOwner($orgId, $memberId)) {
            $this->redirect("/organizations/$orgId/members", 'Cannot remove the organization owner');
            return;
        }

        OrgMember::remove($orgId, $memberId, $userId);

        // Notify the member they were removed
        OrgNotificationService::notifyRemovedFromOrganization($memberId, $orgId, $userId);

        $this->redirect("/organizations/$orgId/members", 'Member removed', 'success');
    }

    /**
     * Request to join an organization (public users)
     */
    public function requestMembership($orgId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $org = VolOrganization::find($orgId);
        if (!$org) {
            $this->redirect('/volunteering/organizations', 'Organization not found', 'error');
            return;
        }

        // Check if already a member
        if (OrgMember::isMember($orgId, $userId)) {
            $this->redirect("/volunteering/organization/$orgId", 'You are already a member of this organization', 'info');
            return;
        }

        // Check if already has pending request
        $existing = OrgMember::getMemberRecord($orgId, $userId);
        if ($existing && $existing['status'] === 'pending') {
            $this->redirect("/volunteering/organization/$orgId", 'You already have a pending membership request', 'info');
            return;
        }

        // Create pending membership request
        OrgMember::add($orgId, $userId, 'member', 'pending');

        // Notify admins about the membership request
        OrgNotificationService::notifyMembershipRequestReceived($orgId, $userId);

        $this->redirect("/volunteering/organization/$orgId", 'Membership request sent! An admin will review your request.', 'success');
    }

    /**
     * Transfer ownership to another member (owner only)
     */
    public function transferOwnership($orgId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        if (!OrgMember::isOwner($orgId, $userId)) {
            $this->redirect("/organizations/$orgId/members", 'Only the owner can transfer ownership', 'error');
            return;
        }

        $newOwnerId = (int) ($_POST['new_owner_id'] ?? 0);

        if (!$newOwnerId) {
            $this->redirect("/organizations/$orgId/members", 'Please select a new owner', 'error');
            return;
        }

        $result = OrgMember::transferOwnership($orgId, $userId, $newOwnerId);

        if ($result['success']) {
            // Notify new owner
            OrgNotificationService::notifyRoleChanged($newOwnerId, $orgId, 'owner', $userId);

            // Notify former owner (now admin)
            OrgNotificationService::notifyRoleChanged($userId, $orgId, 'admin', $newOwnerId);

            $this->redirect("/organizations/$orgId/members", $result['message'], 'success');
        } else {
            $this->redirect("/organizations/$orgId/members", $result['message'], 'error');
        }
    }

    /**
     * API: Get members for autocomplete
     */
    public function apiMembers($orgId)
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            return;
        }

        $members = OrgMember::getMembers($orgId);
        $result = [];

        foreach ($members as $member) {
            $result[] = [
                'id' => $member['user_id'],
                'name' => $member['display_name'],
                'email' => $member['email'],
                'avatar_url' => $member['avatar_url'] ?? '',
                'role' => $member['role'],
            ];
        }

        echo json_encode(['success' => true, 'members' => $result]);
    }

    /**
     * API: Get current wallet balance for live updates
     */
    public function apiBalance($orgId)
    {
        header('Content-Type: application/json');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            return;
        }

        $userId = $_SESSION['user_id'];

        // Check if user is a member or site admin
        $isSiteAdmin = $this->isSiteAdmin($userId);
        if (!$isSiteAdmin && !OrgMember::isMember($orgId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }

        $balance = OrgWallet::getBalance($orgId);
        $pendingCount = OrgTransferRequest::countPending($orgId);

        echo json_encode([
            'success' => true,
            'balance' => $balance,
            'pending_count' => $pendingCount,
            'timestamp' => time()
        ]);
    }

    /**
     * Export transactions to CSV
     */
    public function exportTransactions($orgId)
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $filters = [
            'startDate' => $_GET['start_date'] ?? null,
            'endDate' => $_GET['end_date'] ?? null,
            'direction' => $_GET['direction'] ?? null,
        ];

        $result = \Nexus\Services\TransactionExportService::exportOrgTransactionsCSV($orgId, $userId, $filters);

        if ($result['success']) {
            \Nexus\Services\TransactionExportService::sendCSVDownload($result['csv'], $result['filename']);
        } else {
            $this->redirect("/organizations/$orgId/wallet", $result['message'], 'error');
        }
    }

    /**
     * Export transfer requests to CSV
     */
    public function exportRequests($orgId)
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $filters = [
            'startDate' => $_GET['start_date'] ?? null,
            'endDate' => $_GET['end_date'] ?? null,
            'status' => $_GET['status'] ?? null,
        ];

        $result = \Nexus\Services\TransactionExportService::exportTransferRequestsCSV($orgId, $userId, $filters);

        if ($result['success']) {
            \Nexus\Services\TransactionExportService::sendCSVDownload($result['csv'], $result['filename']);
        } else {
            $this->redirect("/organizations/$orgId/wallet/requests", $result['message'], 'error');
        }
    }

    /**
     * Export members to CSV
     */
    public function exportMembers($orgId)
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $result = \Nexus\Services\TransactionExportService::exportMembersCSV($orgId, $userId);

        if ($result['success']) {
            \Nexus\Services\TransactionExportService::sendCSVDownload($result['csv'], $result['filename']);
        } else {
            $this->redirect("/organizations/$orgId/members", $result['message'], 'error');
        }
    }

    /**
     * Bulk approve transfer requests (admin only)
     */
    public function bulkApprove($orgId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $requestIds = $_POST['request_ids'] ?? [];

        if (empty($requestIds)) {
            $this->redirect("/organizations/$orgId/wallet/requests", 'No requests selected', 'error');
            return;
        }

        $result = OrgWalletService::bulkApprove($requestIds, $userId);

        $message = "Approved {$result['approved']} requests";
        if ($result['failed'] > 0) {
            $message .= ", {$result['failed']} failed";
        }

        $this->redirect("/organizations/$orgId/wallet/requests", $message, $result['success'] ? 'success' : 'warning');
    }

    /**
     * Bulk reject transfer requests (admin only)
     */
    public function bulkReject($orgId)
    {
        Csrf::verifyOrDie();
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $requestIds = $_POST['request_ids'] ?? [];
        $reason = trim($_POST['reason'] ?? 'Bulk rejected by admin');

        if (empty($requestIds)) {
            $this->redirect("/organizations/$orgId/wallet/requests", 'No requests selected', 'error');
            return;
        }

        $result = OrgWalletService::bulkReject($requestIds, $userId, $reason);

        $message = "Rejected {$result['rejected']} requests";
        if ($result['failed'] > 0) {
            $message .= ", {$result['failed']} failed";
        }

        $this->redirect("/organizations/$orgId/wallet/requests", $message, $result['success'] ? 'success' : 'warning');
    }

    /**
     * View audit log (admin only)
     */
    public function auditLog($orgId)
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $org = VolOrganization::find($orgId);
        if (!$org) {
            $this->redirect('/volunteering/dashboard', 'Organization not found');
            return;
        }

        // Check if user is admin
        $isSiteAdmin = $this->isSiteAdmin($userId);
        if (!$isSiteAdmin && !OrgMember::isAdmin($orgId, $userId)) {
            $this->redirect("/organizations/$orgId/wallet", 'Access denied');
            return;
        }

        // Get filters from query string
        $filters = [
            'action' => $_GET['action'] ?? null,
            'userId' => $_GET['user_id'] ?? null,
            'startDate' => $_GET['start_date'] ?? null,
            'endDate' => $_GET['end_date'] ?? null,
        ];

        // Pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Get audit logs
        $logs = \Nexus\Services\AuditLogService::getLog($orgId, $filters, $limit, $offset);
        $totalCount = \Nexus\Services\AuditLogService::getLogCount($orgId, $filters);
        $totalPages = ceil($totalCount / $limit);

        // Get action summary for filter dropdown
        $actionSummary = \Nexus\Services\AuditLogService::getActionSummary($orgId, 90);

        // Get members for filter dropdown
        $members = OrgMember::getMembers($orgId);

        $role = OrgMember::getRole($orgId, $userId) ?: 'admin';
        $isOwner = OrgMember::isOwner($orgId, $userId);

        View::render('organizations/audit-log', [
            'org' => $org,
            'logs' => $logs,
            'filters' => $filters,
            'actionSummary' => $actionSummary,
            'members' => $members,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'role' => $role,
            'isAdmin' => true,
            'isOwner' => $isOwner,
            'isMember' => true,
        ]);
    }

    /**
     * Export audit log to CSV (admin only)
     */
    public function exportAuditLog($orgId)
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        // Check if user is admin
        $isSiteAdmin = $this->isSiteAdmin($userId);
        if (!$isSiteAdmin && !OrgMember::isAdmin($orgId, $userId)) {
            $this->redirect("/organizations/$orgId/wallet", 'Access denied');
            return;
        }

        $filters = [
            'action' => $_GET['action'] ?? null,
            'userId' => $_GET['user_id'] ?? null,
            'startDate' => $_GET['start_date'] ?? null,
            'endDate' => $_GET['end_date'] ?? null,
        ];

        $csv = \Nexus\Services\AuditLogService::exportToCSV($orgId, $filters);

        if ($csv) {
            $filename = "audit_log_{$orgId}_" . date('Y-m-d') . ".csv";
            \Nexus\Services\TransactionExportService::sendCSVDownload($csv, $filename);
        } else {
            $this->redirect("/organizations/$orgId/audit-log", 'No audit logs found', 'error');
        }
    }

    /**
     * Helper: Require authentication
     */
    private function requireAuth()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
    }

    /**
     * Helper: Redirect with flash message
     */
    private function redirect($path, $message = null, $type = 'info')
    {
        if ($message) {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }
        header('Location: ' . TenantContext::getBasePath() . $path);
        exit;
    }

    /**
     * Helper: Check if user is a site admin
     */
    private function isSiteAdmin($userId)
    {
        $user = User::findById($userId);
        if (!$user) {
            return false;
        }
        return in_array($user['role'] ?? '', ['super_admin', 'admin', 'tenant_admin']);
    }
}
