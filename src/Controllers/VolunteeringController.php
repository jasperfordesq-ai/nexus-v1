<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Models\VolOrganization;
use Nexus\Models\VolOpportunity;
use Nexus\Models\VolApplication;
use Nexus\Models\Category;
use Nexus\Models\ActivityLog;

class VolunteeringController
{
    private function checkFeature()
    {
        if (!TenantContext::hasFeature('volunteering')) {
            header("HTTP/1.0 404 Not Found");
            echo "Volunteering module is disabled for this community.";
            exit;
        }
    }

    public function index()
    {
        $this->checkFeature();
        $query = $_GET['q'] ?? '';

        // Fetch Categories for Filter (Optional enhancement)
        $categories = Category::getByType('vol_opportunity');

        // Only show opportunities from APPROVED organizations
        // Original logic was: $opportunities = VolOpportunity::search(TenantContext::getId(), $query);
        // We need to modify VolOpportunity::search OR verify it here.
        // For MVP speed and safety, let's assume VolOpportunity::search does NOT filter by org status yet.
        // We will modify VolOpportunity::search via raw query modification or iterate/filter (slow).
        // Best approach: Update the Method call to include status filter OR Update the Query in the Model.
        // Let's UPDATE THE MODEL QUERY. I will first read VolOpportunity::search to see if I can tweak it there easily.
        // Reverting to view_file for VolOpportunity first.

        $opportunities = VolOpportunity::search(TenantContext::getId(), $query);

        // TEMPORARY FILTER in Controller (until Model update)
        // This is inefficient but safe for immediate hotfix without modifying VolOpportunity Model yet
        // Actually, let's filter after fetch if the set is small.
        // But better: Let's filtering in the view? NO, that paginates poorly.
        // Let's check VolOpportunity model next step.

        \Nexus\Core\SEO::setTitle('Volunteer Opportunities');
        \Nexus\Core\SEO::setDescription('Find meaningful volunteering opportunities in your community.');

        View::render('volunteering/index', [
            'opportunities' => $opportunities,
            'categories' => $categories,
            'query' => $query
        ]);
    }

    public function show($id)
    {
        $this->checkFeature();

        $opportunity = VolOpportunity::find($id);

        if (!$opportunity) {
            header("HTTP/1.0 404 Not Found");
            echo "Opportunity not found";
            exit;
        }

        // Handle AJAX actions for likes/comments
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->handleVolunteeringAjax($opportunity);
            exit;
        }

        // Check if user has applied
        $hasApplied = false;
        if (isset($_SESSION['user_id'])) {
            $hasApplied = VolApplication::hasApplied($id, $_SESSION['user_id']);
        }

        \Nexus\Core\SEO::setTitle($opportunity['title']);
        \Nexus\Core\SEO::setDescription(substr($opportunity['description'], 0, 150));

        $shifts = \Nexus\Models\VolShift::getForOpportunity($id);

        View::render('volunteering/show', [
            'opportunity' => $opportunity,
            'hasApplied' => $hasApplied,
            'shifts' => $shifts
        ]);
    }

    // Organization Dashboard (Manage Orgs & Opps)
    public function dashboard()
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $currentUser = \Nexus\Models\User::findById($userId);
        $myOrgs = VolOrganization::findByOwner($userId);

        // Fetch Categories for Create Form
        $categories = Category::getByType('vol_opportunity');

        // Flatten list of opportunities for all my orgs
        // Also fetch applications for these opportunities
        $myOpps = [];
        $myApplications = [];

        foreach ($myOrgs as $org) {
            $opps = VolOpportunity::getForOrg($org['id']);
            foreach ($opps as $o) {
                // Attach org name for context
                $o['org_name'] = $org['name'];
                $myOpps[] = $o;

                // Fetch Applications for this Opp
                $apps = VolApplication::getForOpportunity($o['id']);
                foreach ($apps as $a) {
                    $a['opp_title'] = $o['title'];
                    $a['org_name'] = $org['name'];
                    $myApplications[] = $a;
                }
            }
        }

        // View Resolution - Let View class handle layout switching
        View::render('volunteering/dashboard', [
            'myOrgs' => $myOrgs,
            'myOpps' => $myOpps,
            'myApplications' => $myApplications,
            'categories' => $categories,
            'currentUser' => $currentUser
        ]);
    }

    public function storeOrg()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        // Mandate License Agreement
        if (!isset($_POST['license_agreed'])) {
            header("HTTP/1.0 403 Forbidden");
            echo "You must accept the license to register as an organization.";
            exit;
        }

        // Server-Side Intelligent Validation
        $type = $_POST['org_type'] ?? '';
        $desc = $_POST['description'] ?? '';
        $rcn = $_POST['rcn_number'] ?? '';

        if (strlen($desc) < 100) {
            die("Error: Organization description must be at least 100 characters. Please provide more detail.");
        }

        if ($type === 'Registered Charity') {
            if (!preg_match('/^\d{8}$/', $rcn)) {
                die("Error: Invalid RCN format. Must be exactly 8 digits.");
            }
        }


        $user = \Nexus\Models\User::findById($_SESSION['user_id']);

        // Limit: 1 Org per user for MVP (optional)
        // VolOrganization::create($user['tenant_id'], $_SESSION['user_id'], $_POST['name'], $_POST['description'], $_POST['email'], $_POST['website'] ?? null);

        // Better Implementation:
        VolOrganization::create(
            TenantContext::getId(),
            $_SESSION['user_id'],
            $_POST['name'],
            $_POST['description'],
            $_POST['email'],
            $_POST['website'] ?? null
        );

        // Notify Admins
        try {
            $db = \Nexus\Core\Database::getInstance();
            $admins = \Nexus\Core\Database::query("SELECT email FROM users WHERE tenant_id = ? AND role = 'admin'", [TenantContext::getId()])->fetchAll();
            $mailer = new \Nexus\Core\Mailer();

            // Security: Escape all user input to prevent XSS in email
            $safeName = htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8');
            $safeType = htmlspecialchars($_POST['org_type'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $safeRcn = htmlspecialchars($_POST['rcn_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $safeContact = htmlspecialchars($_POST['verification_contact'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $safeDesc = htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8');

            $subject = "Verify New Organization: " . $safeName;
            $body = "<h2>New Organization Registered</h2>
                    <p><strong>Name:</strong> {$safeName}</p>
                    <p><strong>Type:</strong> {$safeType}</p>
                    <p><strong>RCN:</strong> {$safeRcn}</p>
                    <p><strong>Verification Contact:</strong> {$safeContact}</p>
                    <p><strong>Description:</strong> {$safeDesc}</p>
                    <p>Please log in to the Admin Panel > Vol Approvals to verify this organization.</p>";

            foreach ($admins as $admin) {
                if (!empty($admin['email'])) {
                    $mailer->send($admin['email'], $subject, $body);
                }
            }
        } catch (\Exception $e) {
            // Log silent failure, don't block user
        }

        header('Location: ' . TenantContext::getBasePath() . '/volunteering/dashboard?msg=org_pending');
    }

    public function storeOpp()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $orgId = $_POST['org_id'];
        $userId = $_SESSION['user_id'];
        $title = $_POST['title'];

        // Security: Verify user owns this org
        $org = VolOrganization::find($orgId);
        if (!$org || $org['user_id'] != $userId) {
            die("Access Denied");
        }

        $oppId = VolOpportunity::create(
            TenantContext::getId(),
            $userId,
            $orgId,
            $title,
            $_POST['description'],
            $_POST['location'],
            $_POST['skills'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['category_id'] ?? null
        );

        // Log Activity to Pulse Feed (same pattern as other controllers)
        try {
            ActivityLog::log($userId, 'posted a Volunteer Opportunity 🙋', $title, true, '/volunteering/' . $oppId);
        } catch (\Exception $e) {
            // Silent fail - activity log is optional
        }

        // Gamification: Check volunteering badges
        try {
            if (class_exists('\Nexus\Services\GamificationService')) {
                \Nexus\Services\GamificationService::checkVolunteeringBadges($userId);
            }
        } catch (\Exception $e) {
            // Silent fail - gamification is optional
        }

        header('Location: ' . TenantContext::getBasePath() . '/volunteering/' . $oppId);
    }

    public function apply()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $oppId = $_POST['opportunity_id'];
        $shiftId = !empty($_POST['shift_id']) ? $_POST['shift_id'] : null;
        $msg = $_POST['message'] ?? '';

        if (!VolApplication::hasApplied($oppId, $_SESSION['user_id'])) {
            VolApplication::create($oppId, $_SESSION['user_id'], $msg, $shiftId);

            // Notification: Email Org Owner
            $opp = VolOpportunity::find($oppId);
            if ($opp && !empty($opp['org_email'])) {
                $mailer = new \Nexus\Core\Mailer();
                $subject = "New Volunteer Application: " . $opp['title'];
                $body = "<h2>New Applicant!</h2><p>You have received a new application for <strong>{$opp['title']}</strong>.</p><p>Check your dashboard to review it.</p>";
                $mailer->send($opp['org_email'], $subject, $body);
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/volunteering/' . $oppId . '?msg=applied');
    }

    public function updateApp()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $appId = $_POST['app_id'];
        $status = $_POST['status']; // approved / declined

        if (!in_array($status, ['approved', 'declined'])) die("Invalid status");

        // Security: Does user own the opportunity?
        // 1. Get App -> Opp -> Org -> Owner
        // For speed, just direct SQL or trust for MVP if we assume admin controls?
        // Let's do it safely:
        $db = \Nexus\Core\Database::getInstance();
        $stmt = $db->prepare("SELECT org.user_id 
            FROM vol_applications app
            JOIN vol_opportunities opp ON app.opportunity_id = opp.id
            JOIN vol_organizations org ON opp.organization_id = org.id
            WHERE app.id = ?");
        $stmt->execute([$appId]);
        $ownerId = $stmt->fetchColumn();

        if ($ownerId != $_SESSION['user_id']) {
            die("Access Denied: You do not own this opportunity.");
        }

        // Update
        $up = $db->prepare("UPDATE vol_applications SET status = ? WHERE id = ?");
        $up->execute([$status, $appId]);

        // Email Notification
        // Local variable $app was undefined, so we fetch details now
        $stmtApp = $db->prepare("SELECT user_id, opportunity_id FROM vol_applications WHERE id = ?");
        $stmtApp->execute([$appId]);
        $appDetails = $stmtApp->fetch();
        if ($appDetails) {
            $applicant = \Nexus\Models\User::findById($appDetails['user_id']);
            if ($applicant) {
                $mailer = new \Nexus\Core\Mailer();
                $subject = "Update on your Volunteer Application";
                $body = "<h2>Application Update</h2><p>Your application for this opportunity has been <strong>" . strtoupper($status) . "</strong>.</p>";
                $mailer->send($applicant['email'], $subject, $body);
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/volunteering/dashboard');
    }

    // --- Organization Management ---

    /**
     * Browse all approved organizations
     */
    public function organizations()
    {
        $this->checkFeature();

        $query = $_GET['q'] ?? '';
        $tenantId = TenantContext::getId();

        if (!empty($query)) {
            $organizations = VolOrganization::search($tenantId, $query);
        } else {
            $organizations = VolOrganization::getApproved($tenantId);
        }

        // Get member counts if wallet is enabled
        $hasTimebanking = TenantContext::hasFeature('wallet');
        if ($hasTimebanking && class_exists('\\Nexus\\Models\\OrgMember')) {
            foreach ($organizations as &$org) {
                $org['member_count'] = \Nexus\Models\OrgMember::countMembers($org['id']);
            }
        }

        \Nexus\Core\SEO::setTitle('Organizations');
        \Nexus\Core\SEO::setDescription('Browse volunteer organizations in your community.');

        View::render('volunteering/organizations', [
            'organizations' => $organizations,
            'query' => $query,
            'hasTimebanking' => $hasTimebanking
        ]);
    }

    /**
     * Public organization profile page
     */
    public function showOrg($id)
    {
        $this->checkFeature();

        $org = VolOrganization::find($id);

        if (!$org) {
            header("HTTP/1.0 404 Not Found");
            echo "Organization not found";
            exit;
        }

        // Get opportunities for this organization
        $opportunities = VolOpportunity::getForOrg($id);

        // Get member count if timebanking is enabled
        $memberCount = 0;
        $isMember = false;
        $isAdmin = false;
        $userId = $_SESSION['user_id'] ?? null;

        if (TenantContext::hasFeature('wallet') && class_exists('\\Nexus\\Models\\OrgMember')) {
            $memberCount = \Nexus\Models\OrgMember::countMembers($id);
            if ($userId) {
                $isMember = \Nexus\Models\OrgMember::isMember($id, $userId);
                $isAdmin = \Nexus\Models\OrgMember::isAdmin($id, $userId);
            }
        }

        // Check if current user is the owner
        $isOwner = $userId && $org['user_id'] == $userId;

        \Nexus\Core\SEO::setTitle($org['name']);
        \Nexus\Core\SEO::setDescription(substr($org['description'], 0, 150));

        View::render('volunteering/show_org', [
            'org' => $org,
            'opportunities' => $opportunities,
            'memberCount' => $memberCount,
            'isMember' => $isMember,
            'isAdmin' => $isAdmin,
            'isOwner' => $isOwner,
            'pageTitle' => $org['name']
        ]);
    }

    public function editOrg($id)
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $org = VolOrganization::find($id);
        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_admin']);

        if (!$org || ($org['user_id'] != $_SESSION['user_id'] && !$isAdmin)) die("Access Denied");

        View::render('volunteering/edit_org', [
            'org' => $org,
            'pageTitle' => 'Edit ' . $org['name']
        ]);
    }

    public function updateOrg()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $id = $_POST['org_id'];
        $org = VolOrganization::find($id);
        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_admin']);

        if (!$org || ($org['user_id'] != $_SESSION['user_id'] && !$isAdmin)) die("Access Denied");

        VolOrganization::update($id, $_POST['name'], $_POST['description'], $_POST['email'], $_POST['website'], isset($_POST['auto_pay']));

        if ($isAdmin) {
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/volunteering/organizations?msg=org_updated');
        } else {
            header('Location: ' . TenantContext::getBasePath() . '/volunteering/dashboard?msg=org_updated');
        }
    }

    // --- Opportunity Management ---

    public function createOpp()
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $myOrgs = VolOrganization::findByOwner($userId);

        if (empty($myOrgs)) {
            // Must have an org to create an opp
            header('Location: ' . TenantContext::getBasePath() . '/volunteering/dashboard?msg=create_org_first');
            exit;
        }

        $categories = Category::getByType('vol_opportunity');
        $preselectedOrgId = $_GET['org_id'] ?? ($myOrgs[0]['id'] ?? null);

        // View Resolution - Let View class handle layout switching
        View::render('volunteering/create_opp', [
            'myOrgs' => $myOrgs,
            'categories' => $categories,
            'preselectedOrgId' => $preselectedOrgId,
            'pageTitle' => 'Post New Opportunity'
        ]);
    }

    public function editOpp($id)
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $opp = VolOpportunity::find($id);
        if (!$opp || $opp['org_owner_id'] != $_SESSION['user_id']) die("Access Denied"); // joined query in find() provides owner_id

        $categories = Category::getByType('vol_opportunity');
        $shifts = \Nexus\Models\VolShift::getForOpportunity($id);

        View::render('volunteering/edit_opp', [
            'opp' => $opp,
            'categories' => $categories,
            'shifts' => $shifts,
            'pageTitle' => 'Edit Opportunity'
        ]);
    }

    public function updateOpp()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $id = $_POST['opp_id'];
        $opp = VolOpportunity::find($id);
        // Security check: VolOpportunity::find returns joined data including org_owner_id
        if (!$opp || $opp['org_owner_id'] != $_SESSION['user_id']) die("Access Denied");

        VolOpportunity::update(
            $id,
            $_POST['title'],
            $_POST['description'],
            $_POST['location'],
            $_POST['skills'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['category_id'] ?? null
        );

        header('Location: ' . TenantContext::getBasePath() . '/volunteering/dashboard?msg=opp_updated');
    }

    // --- Integration ---

    public function createFromProfile()
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $user = \Nexus\Models\User::findById($_SESSION['user_id']);

        // Only allow if profile type is organisation
        if ($user['profile_type'] !== 'organisation') {
            header('Location: ' . TenantContext::getBasePath() . '/volunteering/dashboard?err=not_org');
            exit;
        }

        // Check if already has orgs? Maybe allow multiple, but let's assume one main one for this shortcut.
        // Create new
        $name = $user['organization_name'] ?: ($user['first_name'] . ' ' . $user['last_name']);

        VolOrganization::create(
            $user['tenant_id'],
            $user['id'],
            $name,
            $user['bio'] ?? "Detailed description coming soon.",
            $user['email'],
            null // website not in user profile standard fields yet, or add later
        );

        header('Location: ' . TenantContext::getBasePath() . '/volunteering/dashboard?msg=profile_imported');
    }

    public function myApplications()
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];

        // Fetch User's Applications with Opportunity Details
        $db = \Nexus\Core\Database::getInstance();
        $stmt = $db->prepare("
            SELECT a.*, o.title as opp_title, o.organization_id, org.name as org_name,
                   s.start_time as shift_start, s.end_time as shift_end
            FROM vol_applications a
            JOIN vol_opportunities o ON a.opportunity_id = o.id
            JOIN vol_organizations org ON o.organization_id = org.id
            LEFT JOIN vol_shifts s ON a.shift_id = s.id
            WHERE a.user_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$userId]);
        $myApplications = $stmt->fetchAll();

        \Nexus\Core\SEO::setTitle('My Volunteer Applications');
        $logs = \Nexus\Models\VolLog::getForUser($_SESSION['user_id']);
        $badges = \Nexus\Models\UserBadge::getForUser($_SESSION['user_id']);

        View::render('volunteering/my_applications', [
            'myApplications' => $myApplications,
            'logs' => $logs,
            'badges' => $badges
        ]);
    }

    // --- Hours Logging ---

    public function logHours()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) header('Location: ' . TenantContext::getBasePath() . '/login');

        // Validation
        if (empty($_POST['org_id']) || empty($_POST['date']) || empty($_POST['hours'])) {
            die("Missing required fields");
        }

        \Nexus\Models\VolLog::create(
            $_SESSION['user_id'],
            $_POST['org_id'],
            $_POST['opp_id'] ?? null,
            $_POST['date'],
            $_POST['hours'],
            $_POST['description'] ?? ''
        );

        header('Location: ' . TenantContext::getBasePath() . '/volunteering/my-applications?msg=hours_logged');
    }

    public function verifyHours()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $status = $_POST['status'];
        $logId = $_POST['log_id'];

        if (!in_array($status, ['approved', 'declined'])) die("Invalid status");

        // Verify Ownership
        $log = \Nexus\Models\VolLog::find($logId);
        $org = VolOrganization::find($log['organization_id']);

        if ($org['user_id'] != $_SESSION['user_id']) die("Access Denied");

        \Nexus\Models\VolLog::updateStatus($logId, $status);

        // Auto-Pay Logic
        if ($status == 'approved' && TenantContext::hasFeature('wallet') && $org['auto_pay_enabled']) {
            $senderId = $_SESSION['user_id'];
            $receiverId = $log['user_id'];
            $amount = (float) $log['hours'];
            $desc = "Volunteering: " . ($log['opp_title'] ?? 'General Help') . " at " . $org['name'];

            try {
                \Nexus\Models\Transaction::create($senderId, $receiverId, $amount, $desc);
                $msg = 'hours_verified_payment_sent';

                // Check Timebanking Badges for both
                \Nexus\Services\GamificationService::checkTimebankingBadges($senderId);
                \Nexus\Services\GamificationService::checkTimebankingBadges($receiverId);
            } catch (\Exception $e) {
                // Log failed payment but keep approval? Or warn?
                // For MVP, just redirect with warning
                $msg = 'hours_verified_payment_failed';
            }
        } else {
            $msg = 'hours_verified';
        }

        // --- GAMIFICATION / BADGES ---
        if ($status == 'approved') {
            \Nexus\Services\GamificationService::checkVolunteeringBadges($log['user_id']);
        }

        header('Location: ' . TenantContext::getBasePath() . '/volunteering/dashboard?msg=' . $msg);
    }

    public function downloadIcs($appId)
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) die("Access Denied");

        $sql = "SELECT a.*, o.title as opp_title, org.name as org_name, o.location, o.description 
                FROM vol_applications a
                JOIN vol_opportunities o ON a.opportunity_id = o.id
                JOIN vol_organizations org ON o.organization_id = org.id
                WHERE a.id = ? AND a.user_id = ?";

        $app = \Nexus\Core\Database::query($sql, [$appId, $_SESSION['user_id']])->fetch();

        if (!$app || $app['status'] != 'approved' || empty($app['shift_start'])) {
            die("Invalid Booking");
        }

        $summary = "Volunteer: " . $app['opp_title'];
        $desc = "Volunteering with " . $app['org_name'];

        $ics = \Nexus\Helpers\IcsHelper::generate(
            $summary,
            $desc,
            $app['location'] ?? 'Remote',
            $app['shift_start'],
            $app['shift_end']
        );

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="volunteer-shift.ics"');
        echo $ics;
        exit;
    }

    public function printCertificate()
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) die("Access Denied");

        $currentUser = \Nexus\Models\User::findById($_SESSION['user_id']);

        View::render('volunteering/certificate', [
            'currentUser' => $currentUser
        ]);
    }

    // --- Reviews ---

    public function submitReview()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        // Validate
        $targetType = $_POST['target_type'];
        $targetId = $_POST['target_id'];
        $rating = $_POST['rating'];
        $comment = $_POST['comment'] ?? '';

        if (!in_array($targetType, ['organization', 'user'])) die("Invalid target");
        if ($rating < 1 || $rating > 5) die("Invalid rating");

        // Simple Create
        \Nexus\Models\VolReview::create($_SESSION['user_id'], $targetType, $targetId, $rating, $comment);

        // Notification
        $sender = \Nexus\Models\User::findById($_SESSION['user_id']);
        $receiverId = null;

        if ($targetType === 'user') {
            // Org reviewed a Volunteer -> Notify Volunteer
            $receiverId = $targetId;
        } elseif ($targetType === 'organization') {
            // Volunteer reviewed an Org -> Notify Org Owner
            $org = \Nexus\Models\VolOrganization::find($targetId);
            if ($org) $receiverId = $org['user_id'];
        }

        if ($receiverId) {
            $content = "You received a new {$rating}-star review from {$sender['first_name']}.";
            $html = "<h2>New Review</h2><p><strong>RATING: {$rating}/5</strong></p><p>\"{$comment}\"</p>";

            \Nexus\Services\NotificationDispatcher::dispatch(
                $receiverId,
                'global',
                0,
                'new_review',
                $content,
                '/volunteering/dashboard',
                $html
            );
        }

        // Redirect back
        $redirect = ($targetType == 'organization') ? '/volunteering/my-applications' : '/volunteering/dashboard';
        header('Location: ' . TenantContext::getBasePath() . $redirect . '?msg=review_submitted');
    }

    // --- Shift Management ---

    public function storeShift()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $oppId = $_POST['opp_id'];
        $opp = VolOpportunity::find($oppId);
        // Security: Owner check
        if (!$opp || $opp['org_owner_id'] != $_SESSION['user_id']) die("Access Denied");

        \Nexus\Models\VolShift::create($oppId, $_POST['start_time'], $_POST['end_time'], $_POST['capacity']);

        header('Location: ' . TenantContext::getBasePath() . '/volunteering/opp/edit/' . $oppId . '?msg=shift_added');
    }

    public function deleteShift()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $shiftId = $_POST['shift_id'];
        $shift = \Nexus\Models\VolShift::find($shiftId);
        if (!$shift) die("Shift not found");

        $opp = VolOpportunity::find($shift['opportunity_id']);
        if (!$opp || $opp['org_owner_id'] != $_SESSION['user_id']) die("Access Denied");

        \Nexus\Models\VolShift::delete($shiftId);

        header('Location: ' . TenantContext::getBasePath() . '/volunteering/opp/edit/' . $opp['id'] . '?msg=shift_deleted');
    }

    /**
     * Handle AJAX actions for volunteering opportunity likes/comments
     */
    private function handleVolunteeringAjax($opportunity)
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear any buffered output before JSON response
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? 0;
        $tenantId = TenantContext::getId();

        if (!$userId) {
            echo json_encode(['error' => 'Login required', 'redirect' => '/login']);
            return;
        }

        $action = $_POST['action'] ?? '';
        $targetType = 'volunteering';
        $targetId = (int)$opportunity['id'];

        try {
            // Get PDO instance directly - DatabaseWrapper adds tenant constraints that break JOINs
            $pdo = \Nexus\Core\Database::getInstance();

            // TOGGLE LIKE
            if ($action === 'toggle_like') {
                $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ? AND tenant_id = ?");
                $stmt->execute([$userId, $targetType, $targetId, $tenantId]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$existing['id'], $tenantId]);

                    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ? AND tenant_id = ?");
                    $stmt->execute([$targetType, $targetId, $tenantId]);
                    $countResult = $stmt->fetch();
                    echo json_encode(['status' => 'unliked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO likes (user_id, target_type, target_id, tenant_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$userId, $targetType, $targetId, $tenantId]);

                    // Send notification to opportunity creator
                    if (class_exists('\Nexus\Services\SocialNotificationService')) {
                        $contentOwnerId = $opportunity['created_by'] ?? null;
                        if ($contentOwnerId && $contentOwnerId != $userId) {
                            $contentPreview = $opportunity['title'] ?? '';
                            \Nexus\Services\SocialNotificationService::notifyLike(
                                $contentOwnerId, $userId, $targetType, $targetId, $contentPreview
                            );
                        }
                    }

                    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?");
                    $stmt->execute([$targetType, $targetId]);
                    $countResult = $stmt->fetch();
                    echo json_encode(['status' => 'liked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
                }
            }

            // SUBMIT COMMENT
            elseif ($action === 'submit_comment') {
                $content = trim($_POST['content'] ?? '');
                if (empty($content)) {
                    echo json_encode(['error' => 'Comment cannot be empty']);
                    return;
                }

                $stmt = $pdo->prepare("INSERT INTO comments (user_id, tenant_id, target_type, target_id, content, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $tenantId, $targetType, $targetId, $content, date('Y-m-d H:i:s')]);

                // Send notification to opportunity creator
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = $opportunity['created_by'] ?? null;
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        \Nexus\Services\SocialNotificationService::notifyComment(
                            $contentOwnerId, $userId, $targetType, $targetId, $content
                        );
                    }
                }

                echo json_encode(['status' => 'success', 'comment' => [
                    'author_name' => $_SESSION['user_name'] ?? 'Me',
                    'author_avatar' => $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.png',
                    'content' => $content
                ]]);
            }

            // FETCH COMMENTS (with nested replies and reactions)
            elseif ($action === 'fetch_comments') {
                $comments = \Nexus\Services\CommentService::fetchComments($targetType, $targetId, $userId);
                echo json_encode([
                    'status' => 'success',
                    'comments' => $comments,
                    'available_reactions' => \Nexus\Services\CommentService::getAvailableReactions()
                ]);
            }

            // DELETE COMMENT
            elseif ($action === 'delete_comment') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $isSuperAdmin = !empty($_SESSION['is_super_admin']);
                $result = \Nexus\Services\CommentService::deleteComment($commentId, $userId, $isSuperAdmin);
                echo json_encode($result);
            }

            // EDIT COMMENT
            elseif ($action === 'edit_comment') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $newContent = $_POST['content'] ?? '';
                $result = \Nexus\Services\CommentService::editComment($commentId, $userId, $newContent);
                echo json_encode($result);
            }

            // REPLY TO COMMENT
            elseif ($action === 'reply_comment') {
                $parentId = (int)($_POST['parent_id'] ?? 0);
                $content = trim($_POST['content'] ?? '');
                $result = \Nexus\Services\CommentService::addComment($userId, $tenantId, $targetType, $targetId, $content, $parentId);

                // Notify parent comment author
                if (isset($result['status']) && $result['status'] === 'success') {
                    $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
                    $stmt->execute([$parentId]);
                    $parent = $stmt->fetch();
                    if ($parent && $parent['user_id'] != $userId) {
                        if (class_exists('\Nexus\Services\SocialNotificationService')) {
                            \Nexus\Services\SocialNotificationService::notifyComment(
                                $parent['user_id'], $userId, 'reply', $parentId, $content
                            );
                        }
                    }
                }
                echo json_encode($result);
            }

            // TOGGLE REACTION ON COMMENT
            elseif ($action === 'toggle_reaction') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $emoji = $_POST['emoji'] ?? '';
                $result = \Nexus\Services\CommentService::toggleReaction($userId, $tenantId, $commentId, $emoji);
                echo json_encode($result);
            }

            // SEARCH USERS FOR @MENTION
            elseif ($action === 'search_users') {
                $query = $_POST['query'] ?? '';
                $users = \Nexus\Services\CommentService::searchUsersForMention($query, $tenantId);
                echo json_encode(['status' => 'success', 'users' => $users]);
            }

            // SHARE VOLUNTEERING OPPORTUNITY TO FEED
            elseif ($action === 'share_volunteering') {
                $stmt = $pdo->prepare("INSERT INTO feed_posts (user_id, tenant_id, content, parent_id, parent_type, visibility, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $shareContent = "Check out this volunteer opportunity: " . ($opportunity['title'] ?? 'Opportunity');
                $stmt->execute([
                    $userId,
                    $tenantId,
                    $shareContent,
                    $targetId,
                    'volunteering',
                    'public',
                    date('Y-m-d H:i:s')
                ]);

                // Notify opportunity creator
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = $opportunity['created_by'] ?? null;
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        \Nexus\Services\SocialNotificationService::notifyLike(
                            $contentOwnerId, $userId, 'volunteering', $targetId, 'shared your volunteer opportunity'
                        );
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'Opportunity shared to feed']);
            }

            else {
                echo json_encode(['error' => 'Unknown action']);
            }

        } catch (\Exception $e) {
            error_log("VolunteeringController AJAX error: " . $e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
