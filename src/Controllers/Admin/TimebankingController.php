<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Services\AdminAnalyticsService;
use Nexus\Services\AbuseDetectionService;
use Nexus\Services\OrgNotificationService;

/**
 * Admin Timebanking Controller
 *
 * Handles admin dashboard for timebanking analytics and abuse detection.
 */
class TimebankingController
{
    /**
     * Require admin access
     */
    private function requireAdmin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            header('HTTP/1.0 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>Access Denied.</p>";
            exit;
        }
    }

    /**
     * Timebanking analytics dashboard
     */
    public function index()
    {
        $this->requireAdmin();

        // Get comprehensive dashboard data
        $dashboardData = AdminAnalyticsService::getDashboardSummary();

        // Get abuse alert counts
        $alertCounts = AbuseDetectionService::getAlertCounts();
        $alertCountsByType = AbuseDetectionService::getAlertCountsByType();

        // Get recent alerts for preview
        $recentAlerts = AbuseDetectionService::getAlerts('new', 5);

        View::render('admin/timebanking/dashboard', [
            'pageTitle' => 'Timebanking Analytics',
            'stats' => $dashboardData['stats'],
            'monthlyTrends' => $dashboardData['monthly_trends'],
            'topEarners' => $dashboardData['top_earners'],
            'topSpenders' => $dashboardData['top_spenders'],
            'highestBalances' => $dashboardData['highest_balances'],
            'orgSummary' => $dashboardData['org_summary'],
            'pendingRequests' => $dashboardData['pending_requests'],
            'alertCounts' => $alertCounts,
            'alertCountsByType' => $alertCountsByType,
            'recentAlerts' => $recentAlerts,
        ]);
    }

    /**
     * Abuse alerts management
     */
    public function alerts()
    {
        $this->requireAdmin();

        $status = $_GET['status'] ?? null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $alerts = AbuseDetectionService::getAlerts($status, $limit, $offset);
        $alertCounts = AbuseDetectionService::getAlertCounts();

        View::render('admin/timebanking/alerts', [
            'pageTitle' => 'Abuse Alerts',
            'alerts' => $alerts,
            'alertCounts' => $alertCounts,
            'currentStatus' => $status,
            'page' => $page,
        ]);
    }

    /**
     * View single alert details
     */
    public function viewAlert($alertId)
    {
        $this->requireAdmin();

        $alert = AbuseDetectionService::getAlert($alertId);
        if (!$alert) {
            $this->redirect('/admin/timebanking/alerts', 'Alert not found');
            return;
        }

        // Decode details JSON
        $alert['details_decoded'] = json_decode($alert['details'] ?? '{}', true);

        View::render('admin/timebanking/alert-detail', [
            'pageTitle' => 'Alert Details',
            'alert' => $alert,
        ]);
    }

    /**
     * Update alert status
     */
    public function updateAlertStatus($alertId)
    {
        Csrf::verifyOrDie();
        $this->requireAdmin();

        $status = $_POST['status'] ?? '';
        $notes = trim($_POST['notes'] ?? '');

        if (!in_array($status, ['reviewing', 'resolved', 'dismissed'])) {
            $this->redirect("/admin/timebanking/alert/$alertId", 'Invalid status', 'error');
            return;
        }

        $resolvedBy = in_array($status, ['resolved', 'dismissed']) ? $_SESSION['user_id'] : null;

        AbuseDetectionService::updateAlertStatus($alertId, $status, $resolvedBy, $notes);

        $this->redirect('/admin/timebanking/alerts', 'Alert status updated', 'success');
    }

    /**
     * Run abuse detection checks manually
     */
    public function runDetection()
    {
        Csrf::verifyOrDie();
        $this->requireAdmin();

        $results = AbuseDetectionService::runAllChecks();

        $totalAlerts = array_sum($results);
        $message = $totalAlerts > 0
            ? "Detection complete: $totalAlerts new alert(s) created."
            : "Detection complete: No new alerts.";

        $this->redirect('/admin/timebanking/alerts', $message, 'success');
    }

    /**
     * User activity report
     */
    public function userReport($userId = null)
    {
        $this->requireAdmin();

        if (!$userId) {
            // Show user search form
            View::render('admin/timebanking/user-search', [
                'pageTitle' => 'User Activity Report',
            ]);
            return;
        }

        $tenantId = TenantContext::getId();

        // Get user info
        $user = Database::query(
            "SELECT * FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user) {
            $this->redirect('/admin/timebanking/user-report', 'User not found', 'error');
            return;
        }

        // Get user's transaction history
        $transactions = Database::query(
            "SELECT t.*,
                    CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                    CONCAT(r.first_name, ' ', r.last_name) as receiver_name
             FROM transactions t
             JOIN users s ON t.sender_id = s.id
             JOIN users r ON t.receiver_id = r.id
             WHERE (t.sender_id = ? OR t.receiver_id = ?) AND t.tenant_id = ?
             ORDER BY t.created_at DESC
             LIMIT 100",
            [$userId, $userId, $tenantId]
        )->fetchAll();

        // Get user's alerts
        $alerts = Database::query(
            "SELECT * FROM abuse_alerts WHERE user_id = ? AND tenant_id = ? ORDER BY created_at DESC",
            [$userId, $tenantId]
        )->fetchAll();

        // Calculate stats
        $sentTotal = Database::query(
            "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE sender_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetchColumn();

        $receivedTotal = Database::query(
            "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE receiver_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetchColumn();

        $transactionCount = Database::query(
            "SELECT COUNT(*) FROM transactions WHERE (sender_id = ? OR receiver_id = ?) AND tenant_id = ?",
            [$userId, $userId, $tenantId]
        )->fetchColumn();

        View::render('admin/timebanking/user-report', [
            'pageTitle' => 'User Activity Report',
            'user' => $user,
            'transactions' => $transactions,
            'alerts' => $alerts,
            'stats' => [
                'sent_total' => $sentTotal,
                'received_total' => $receivedTotal,
                'transaction_count' => $transactionCount,
                'net_change' => $receivedTotal - $sentTotal,
            ],
        ]);
    }

    /**
     * Organization wallets overview
     */
    public function orgWallets()
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();

        // Get all org wallets with details (OPTIMIZED - removed subquery)
        $wallets = Database::query(
            "SELECT ow.*, vo.name as org_name, vo.status as org_status,
                    COUNT(om.id) as member_count
             FROM org_wallets ow
             JOIN vol_organizations vo ON ow.organization_id = vo.id
             LEFT JOIN org_members om ON om.organization_id = ow.organization_id AND om.status = 'active'
             WHERE ow.tenant_id = ?
             GROUP BY ow.id
             ORDER BY ow.balance DESC",
            [$tenantId]
        )->fetchAll();

        // Get organizations WITHOUT wallets
        $orgsWithoutWallets = Database::query(
            "SELECT vo.id, vo.name, vo.status, vo.user_id,
                    u.email as owner_email, CONCAT(u.first_name, ' ', u.last_name) as owner_name
             FROM vol_organizations vo
             JOIN users u ON vo.user_id = u.id
             WHERE vo.tenant_id = ?
             AND NOT EXISTS (SELECT 1 FROM org_wallets ow WHERE ow.organization_id = vo.id)
             ORDER BY vo.name",
            [$tenantId]
        )->fetchAll();

        // Get pending transfer requests count per org
        $pendingCounts = Database::query(
            "SELECT organization_id, COUNT(*) as count
             FROM org_transfer_requests
             WHERE tenant_id = ? AND status = 'pending'
             GROUP BY organization_id",
            [$tenantId]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Get overall summary
        $summary = AdminAnalyticsService::getOrgWalletSummary();

        View::render('admin/timebanking/org-wallets', [
            'pageTitle' => 'Organization Wallets',
            'wallets' => $wallets,
            'orgsWithoutWallets' => $orgsWithoutWallets,
            'pendingCounts' => $pendingCounts,
            'summary' => $summary,
        ]);
    }

    /**
     * Initialize wallet for an organization (admin action)
     */
    public function initializeOrgWallet()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $orgId = (int) ($_POST['org_id'] ?? 0);

        if (!$orgId) {
            $this->redirect('/admin/timebanking/org-wallets', 'Invalid organization', 'error');
            return;
        }

        $tenantId = TenantContext::getId();

        // Verify org exists
        $org = Database::query(
            "SELECT id, name, user_id FROM vol_organizations WHERE id = ? AND tenant_id = ?",
            [$orgId, $tenantId]
        )->fetch();

        if (!$org) {
            $this->redirect('/admin/timebanking/org-wallets', 'Organization not found', 'error');
            return;
        }

        // Create wallet
        Database::query(
            "INSERT INTO org_wallets (tenant_id, organization_id, balance, created_at)
             VALUES (?, ?, 0.00, NOW())
             ON DUPLICATE KEY UPDATE tenant_id = tenant_id",
            [$tenantId, $orgId]
        );

        // Add owner to org_members if not already
        Database::query(
            "INSERT INTO org_members (tenant_id, organization_id, user_id, role, status, created_at)
             VALUES (?, ?, ?, 'owner', 'active', NOW())
             ON DUPLICATE KEY UPDATE role = 'owner', status = 'active'",
            [$tenantId, $orgId, $org['user_id']]
        );

        $this->redirect('/admin/timebanking/org-wallets', "Wallet initialized for {$org['name']}", 'success');
    }

    /**
     * Initialize wallets for ALL organizations without one
     */
    public function initializeAllOrgWallets()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();

        // Get all orgs without wallets
        $orgs = Database::query(
            "SELECT vo.id, vo.name, vo.user_id
             FROM vol_organizations vo
             WHERE vo.tenant_id = ?
             AND NOT EXISTS (SELECT 1 FROM org_wallets ow WHERE ow.organization_id = vo.id)",
            [$tenantId]
        )->fetchAll();

        $count = 0;
        foreach ($orgs as $org) {
            // Create wallet
            Database::query(
                "INSERT INTO org_wallets (tenant_id, organization_id, balance, created_at)
                 VALUES (?, ?, 0.00, NOW())
                 ON DUPLICATE KEY UPDATE tenant_id = tenant_id",
                [$tenantId, $org['id']]
            );

            // Add owner to org_members
            Database::query(
                "INSERT INTO org_members (tenant_id, organization_id, user_id, role, status, created_at)
                 VALUES (?, ?, ?, 'owner', 'active', NOW())
                 ON DUPLICATE KEY UPDATE role = 'owner', status = 'active'",
                [$tenantId, $org['id'], $org['user_id']]
            );

            $count++;
        }

        $this->redirect('/admin/timebanking/org-wallets', "Initialized wallets for $count organizations", 'success');
    }

    /**
     * Show form to create organization for a user
     */
    public function createOrgForm()
    {
        $this->requireAdmin();

        View::render('admin/timebanking/create-org', [
            'pageTitle' => 'Create Organization',
        ]);
    }

    /**
     * Create organization on behalf of a user (admin action)
     */
    public function createOrg()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $ownerEmail = trim($_POST['owner_email'] ?? '');
        $orgName = trim($_POST['org_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $status = $_POST['status'] ?? 'approved';

        if (!$ownerEmail || !$orgName) {
            $this->redirect('/admin/timebanking/create-org', 'Owner email and organization name are required', 'error');
            return;
        }

        $tenantId = TenantContext::getId();

        // Find owner by email
        $owner = Database::query(
            "SELECT id, first_name, last_name, email FROM users WHERE email = ? AND tenant_id = ?",
            [$ownerEmail, $tenantId]
        )->fetch();

        if (!$owner) {
            $this->redirect('/admin/timebanking/create-org', 'No user found with that email', 'error');
            return;
        }

        // Use owner's email as contact if not provided
        if (!$contactEmail) {
            $contactEmail = $owner['email'];
        }

        // Create the organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$tenantId, $owner['id'], $orgName, $description, $contactEmail, $status]
        );

        $orgId = Database::getInstance()->lastInsertId();

        // Create wallet for the organization
        Database::query(
            "INSERT INTO org_wallets (tenant_id, organization_id, balance, created_at)
             VALUES (?, ?, 0.00, NOW())",
            [$tenantId, $orgId]
        );

        // Add owner to org_members
        Database::query(
            "INSERT INTO org_members (tenant_id, organization_id, user_id, role, status, created_at)
             VALUES (?, ?, ?, 'owner', 'active', NOW())",
            [$tenantId, $orgId, $owner['id']]
        );

        // Notify the owner that an org was created for them
        OrgNotificationService::notifyOrganizationCreatedForYou($owner['id'], $orgId, $orgName, $_SESSION['user_id']);

        $ownerName = $owner['first_name'] . ' ' . $owner['last_name'];
        $this->redirect('/admin/timebanking/org-wallets', "Created '$orgName' for $ownerName", 'success');
    }

    /**
     * Add a member to an organization (admin action)
     */
    public function addOrgMember()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $orgId = (int) ($_POST['org_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'member';

        if (!$orgId || !$email) {
            $this->redirect('/admin/timebanking/org-wallets', 'Organization ID and email are required', 'error');
            return;
        }

        if (!in_array($role, ['owner', 'admin', 'member'])) {
            $role = 'member';
        }

        $tenantId = TenantContext::getId();

        // Verify org exists
        $org = Database::query(
            "SELECT id, name FROM vol_organizations WHERE id = ? AND tenant_id = ?",
            [$orgId, $tenantId]
        )->fetch();

        if (!$org) {
            $this->redirect('/admin/timebanking/org-wallets', 'Organization not found', 'error');
            return;
        }

        // Find user by email
        $user = Database::query(
            "SELECT id, first_name, last_name FROM users WHERE email = ? AND tenant_id = ?",
            [$email, $tenantId]
        )->fetch();

        if (!$user) {
            $this->redirect('/admin/timebanking/org-wallets', 'No user found with that email', 'error');
            return;
        }

        // Add user to org_members
        Database::query(
            "INSERT INTO org_members (tenant_id, organization_id, user_id, role, status, created_at)
             VALUES (?, ?, ?, ?, 'active', NOW())
             ON DUPLICATE KEY UPDATE role = ?, status = 'active', updated_at = NOW()",
            [$tenantId, $orgId, $user['id'], $role, $role]
        );

        // Notify the user they were added to the organization
        OrgNotificationService::notifyAddedToOrganization($user['id'], $orgId, $role, $_SESSION['user_id']);

        $userName = $user['first_name'] . ' ' . $user['last_name'];
        $this->redirect('/admin/timebanking/org-wallets', "Added $userName as $role to {$org['name']}", 'success');
    }

    /**
     * View/manage members of an organization (admin)
     */
    public function orgMembers($orgId)
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();

        // Get org details
        $org = Database::query(
            "SELECT * FROM vol_organizations WHERE id = ? AND tenant_id = ?",
            [$orgId, $tenantId]
        )->fetch();

        if (!$org) {
            $this->redirect('/admin/timebanking/org-wallets', 'Organization not found', 'error');
            return;
        }

        // Get all members
        $members = Database::query(
            "SELECT om.*, u.email, u.first_name, u.last_name, u.avatar_url,
                    CONCAT(u.first_name, ' ', u.last_name) as display_name
             FROM org_members om
             JOIN users u ON om.user_id = u.id
             WHERE om.organization_id = ? AND om.tenant_id = ?
             ORDER BY FIELD(om.role, 'owner', 'admin', 'member'), om.status, u.first_name",
            [$orgId, $tenantId]
        )->fetchAll();

        View::render('admin/timebanking/org-members', [
            'pageTitle' => $org['name'] . ' - Members',
            'org' => $org,
            'members' => $members,
        ]);
    }

    /**
     * Update member role (admin action)
     */
    public function updateOrgMemberRole()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $orgId = (int) ($_POST['org_id'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? 'member';

        if (!$orgId || !$userId) {
            $this->redirect('/admin/timebanking/org-wallets', 'Invalid request', 'error');
            return;
        }

        if (!in_array($role, ['owner', 'admin', 'member'])) {
            $role = 'member';
        }

        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE org_members SET role = ?, updated_at = NOW()
             WHERE tenant_id = ? AND organization_id = ? AND user_id = ?",
            [$role, $tenantId, $orgId, $userId]
        );

        // Notify the user their role changed
        OrgNotificationService::notifyRoleChanged($userId, $orgId, $role, $_SESSION['user_id']);

        $this->redirect("/admin/timebanking/org-members/$orgId", 'Member role updated', 'success');
    }

    /**
     * Remove member from organization (admin action)
     */
    public function removeOrgMember()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $orgId = (int) ($_POST['org_id'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);

        if (!$orgId || !$userId) {
            $this->redirect('/admin/timebanking/org-wallets', 'Invalid request', 'error');
            return;
        }

        $tenantId = TenantContext::getId();

        // Don't allow removing the owner
        $member = Database::query(
            "SELECT role FROM org_members WHERE tenant_id = ? AND organization_id = ? AND user_id = ?",
            [$tenantId, $orgId, $userId]
        )->fetch();

        if ($member && $member['role'] === 'owner') {
            $this->redirect("/admin/timebanking/org-members/$orgId", 'Cannot remove the organization owner', 'error');
            return;
        }

        Database::query(
            "DELETE FROM org_members WHERE tenant_id = ? AND organization_id = ? AND user_id = ?",
            [$tenantId, $orgId, $userId]
        );

        // Notify the user they were removed
        OrgNotificationService::notifyRemovedFromOrganization($userId, $orgId, $_SESSION['user_id']);

        $this->redirect("/admin/timebanking/org-members/$orgId", 'Member removed', 'success');
    }

    /**
     * Adjust user balance (admin action)
     */
    public function adjustBalance()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $userId = (int) ($_POST['user_id'] ?? 0);
        $type = $_POST['type'] ?? 'add';
        $amount = (float) ($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if (!$userId || $amount <= 0 || !$reason) {
            $this->redirect('/admin/timebanking/user-report', 'Invalid adjustment request', 'error');
            return;
        }

        if (!in_array($type, ['add', 'subtract'])) {
            $type = 'add';
        }

        $tenantId = TenantContext::getId();

        // Verify user exists
        $user = Database::query(
            "SELECT id, first_name, last_name, balance FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user) {
            $this->redirect('/admin/timebanking/user-report', 'User not found', 'error');
            return;
        }

        $currentBalance = (float) ($user['balance'] ?? 0);

        // Calculate new balance
        if ($type === 'add') {
            $newBalance = $currentBalance + $amount;
            $adjustmentAmount = $amount;
        } else {
            $newBalance = $currentBalance - $amount;
            $adjustmentAmount = -$amount;

            // Prevent negative balance
            if ($newBalance < 0) {
                $this->redirect("/admin/timebanking/user-report/$userId", 'Cannot reduce balance below zero', 'error');
                return;
            }
        }

        // Update user balance
        Database::query(
            "UPDATE users SET balance = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$newBalance, $userId, $tenantId]
        );

        // Log the adjustment as a transaction (admin adjustment)
        $adminId = $_SESSION['user_id'];
        Database::query(
            "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $tenantId,
                $type === 'subtract' ? $userId : $adminId,  // From user if subtracting, from admin if adding
                $type === 'add' ? $userId : $adminId,       // To user if adding, to admin if subtracting
                abs($amount),
                '[Admin Adjustment] ' . $reason
            ]
        );

        $userName = $user['first_name'] . ' ' . $user['last_name'];
        $action = $type === 'add' ? 'added to' : 'subtracted from';
        $this->redirect("/admin/timebanking/user-report/$userId", "{$amount}h {$action} {$userName}'s balance", 'success');
    }

    /**
     * API: Search users for timebanking admin
     * Returns JSON with user list matching query
     */
    public function userSearchApi()
    {
        $this->requireAdmin();

        header('Content-Type: application/json');

        $query = trim($_GET['q'] ?? '');

        if (strlen($query) < 2) {
            echo json_encode(['success' => true, 'users' => []]);
            exit;
        }

        $tenantId = TenantContext::getId();
        $searchTerm = '%' . $query . '%';

        // Search by name, email, or ID
        $users = Database::query(
            "SELECT id, first_name, last_name, email, balance
             FROM users
             WHERE tenant_id = ?
             AND (
                 CONCAT(first_name, ' ', last_name) LIKE ?
                 OR email LIKE ?
                 OR id = ?
             )
             ORDER BY first_name, last_name
             LIMIT 20",
            [$tenantId, $searchTerm, $searchTerm, (int)$query]
        )->fetchAll();

        echo json_encode(['success' => true, 'users' => $users]);
        exit;
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
}
