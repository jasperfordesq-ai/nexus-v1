<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class VolunteeringController
{
    /**
     * Require admin role for all actions
     */
    private function requireAdmin()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_admin']);
        if (!$isAdmin) {
            header('HTTP/1.1 403 Forbidden');
            echo 'Access Denied: Admin privileges required';
            exit;
        }
    }

    public function index()
    {
        $this->requireAdmin();

        // Render the volunteering dashboard
        View::render('admin/volunteering/index', []);
    }

    public function approvals()
    {
        $this->requireAdmin();
        // List Pending Orgs
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM vol_organizations WHERE tenant_id = ? AND status = 'pending'");
        $stmt->execute([TenantContext::getId()]);
        $pending = $stmt->fetchAll();

        View::render('admin/volunteering/approvals', [
            'pending' => $pending
        ]);
    }

    public function approve()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();
        $id = $_POST['org_id'];

        // Update to 'approved'
        \Nexus\Models\VolOrganization::updateStatus($id, 'approved');

        // Notify User
        $org = \Nexus\Models\VolOrganization::find($id);
        if ($org) {
            $user = \Nexus\Models\User::findById($org['user_id']);
            if ($user) {
                $mailer = new \Nexus\Core\Mailer();
                $subject = "Organization Approved: " . $org['name'];

                // Construct Absolute URL
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $dashboardLink = $protocol . $domain . TenantContext::getBasePath() . "/volunteering/dashboard";

                $body = "<h2>Organization Approved</h2><p>Congratulations! Your organization <strong>" . htmlspecialchars($org['name']) . "</strong> has been approved. You can now post opportunities and manage your profile.</p><p><a href='" . $dashboardLink . "'>Go to Dashboard</a></p>";
                $mailer->send($user['email'], $subject, $body);
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/volunteering/approvals?msg=approved');
    }

    public function decline()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();
        $id = $_POST['org_id'];

        // Update to 'declined'
        \Nexus\Models\VolOrganization::updateStatus($id, 'declined');

        // Notify User
        $org = \Nexus\Models\VolOrganization::find($id);
        if ($org) {
            $user = \Nexus\Models\User::findById($org['user_id']);
            if ($user) {
                $mailer = new \Nexus\Core\Mailer();
                $subject = "Organization Declined: " . $org['name'];
                $body = "<h2>Organization Declined</h2><p>We regret to inform you that your organization <strong>" . htmlspecialchars($org['name']) . "</strong> has been declined at this time.</p>";
                $mailer->send($user['email'], $subject, $body);
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/volunteering/approvals?msg=declined');
    }

    public function organizations()
    {
        $this->requireAdmin();
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM vol_organizations WHERE tenant_id = ? ORDER BY created_at DESC");
        $stmt->execute([TenantContext::getId()]);
        $orgs = $stmt->fetchAll();

        View::render('admin/volunteering/organizations', [
            'orgs' => $orgs
        ]);
    }

    public function deleteOrg()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();
        $id = $_POST['org_id'];

        // Find and Verify Tenant Ownership
        $org = \Nexus\Models\VolOrganization::find($id);
        // Security: Use strict comparison to prevent type juggling attacks
        if (!$org || (int)$org['tenant_id'] !== (int)TenantContext::getId()) {
            die("Access Denied or Organization Not Found");
        }

        // Delete (We assume cascade or soft delete, but for now strict delete)
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM vol_organizations WHERE id = ?");
        $stmt->execute([$id]);

        // Also could delete associated opportunities, but DB FK cascade should handle or allow orphan logic for now.

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/volunteering/organizations?msg=deleted');
    }
}
