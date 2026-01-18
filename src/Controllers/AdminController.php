<?php

namespace Nexus\Controllers;

use Nexus\Models\User;
use Nexus\Models\Listing;
use Nexus\Models\Transaction;
use Nexus\Core\Database;

class AdminController
{
    private function checkAdmin()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        // GOD MODE: Bypass all permission checks
        if (!empty($_SESSION['is_god'])) {
            return;
        }

        // Check multiple admin conditions:
        // 1. Role is 'admin' or 'tenant_admin'
        // 2. is_super_admin flag is set
        // 3. is_admin session flag (set during login for admin roles)
        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        // Check if user has any admin privileges
        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            $this->forbidden();
        }

        // If not super admin, verify tenant match
        if (!$isSuper) {
            $currentUser = Database::query("SELECT tenant_id FROM users WHERE id = ?", [$_SESSION['user_id']])->fetch();
            // Security: Use strict comparison to prevent type juggling attacks
            if ((int)$currentUser['tenant_id'] !== (int)\Nexus\Core\TenantContext::getId()) {
                $this->forbidden();
            }
        }
    }

    private function forbidden()
    {
        header('HTTP/1.0 403 Forbidden');
        echo "<h1>403 Forbidden</h1><p>You do not have permission to access this area.</p><a href='" . \Nexus\Core\TenantContext::getBasePath() . "/dashboard'>Go Home</a>";
        exit;
    }

    public function index()
    {
        $this->checkAdmin();
        $tenantId = \Nexus\Core\TenantContext::getId();

        // Fetch Stats (Scoped to Tenant)
        $stats = [
            'total_users' => Database::query("SELECT COUNT(*) as c FROM users WHERE tenant_id = ?", [$tenantId])->fetch()['c'],
            'total_listings' => Database::query("SELECT COUNT(*) as c FROM listings WHERE tenant_id = ?", [$tenantId])->fetch()['c'],
            'total_transactions' => Database::query("SELECT COUNT(*) as c FROM transactions WHERE tenant_id = ?", [$tenantId])->fetch()['c'],
            'total_volume' => Database::query("SELECT SUM(amount) as s FROM transactions WHERE tenant_id = ?", [$tenantId])->fetch()['s'] ?? 0,
        ];

        // Monthly Stats (Last 6 Months) for Chart
        $monthly_stats = Database::query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as volume 
            FROM transactions 
            WHERE tenant_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY month 
            ORDER BY month ASC
        ", [$tenantId])->fetchAll();

        // Fill gaps if needed, but for pitch mode strict SQL is fine.

        // Scoped Lists
        $recent_users = Database::query("SELECT * FROM users WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 10", [$tenantId])->fetchAll();
        $pending_users = User::findPending(); // This method in User model is already tenant-scoped!
        $recent_listings = Database::query("
            SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) as author
            FROM listings l
            JOIN users u ON l.user_id = u.id
            WHERE l.tenant_id = ?
            ORDER BY l.created_at DESC LIMIT 10", [$tenantId])->fetchAll();

        // Pending items for actionable alerts
        $pending_listings = Database::query("SELECT COUNT(*) as c FROM listings WHERE tenant_id = ? AND status = 'pending'", [$tenantId])->fetch()['c'] ?? 0;
        $pending_orgs = 0;
        try {
            $pending_orgs = Database::query("SELECT COUNT(*) as c FROM vol_organizations WHERE tenant_id = ? AND status = 'pending'", [$tenantId])->fetch()['c'] ?? 0;
        } catch (\Exception $e) { /* Table may not exist */
        }

        // Recent Activity (more items for scrollable list)
        $activity_logs = \Nexus\Models\ActivityLog::getRecent(25);

        // Dynamic System Status
        $systemStatus = [
            'database' => ['status' => 'online', 'label' => 'Connected'],
            'cache' => ['status' => 'online', 'label' => 'Active'],
            'queue' => ['status' => 'online', 'label' => 'Running'],
            'api' => ['status' => 'online', 'label' => 'Operational'],
        ];

        // Test database connection
        try {
            Database::query("SELECT 1");
            $systemStatus['database'] = ['status' => 'online', 'label' => 'Connected'];
        } catch (\Exception $e) {
            $systemStatus['database'] = ['status' => 'offline', 'label' => 'Disconnected'];
        }

        // Check cache (if Redis or file cache exists)
        $cacheDir = __DIR__ . '/../../storage/cache';
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $systemStatus['cache'] = ['status' => 'online', 'label' => 'Active'];
        } else {
            $systemStatus['cache'] = ['status' => 'warning', 'label' => 'File-based'];
        }

        // Check queue/cron - look at last cron run
        try {
            $lastCron = Database::query("SELECT MAX(last_run) as lr FROM cron_jobs WHERE tenant_id = ?", [$tenantId])->fetch();
            if ($lastCron && $lastCron['lr']) {
                $lastRunTime = strtotime($lastCron['lr']);
                $hoursSinceRun = (time() - $lastRunTime) / 3600;
                if ($hoursSinceRun < 2) {
                    $systemStatus['queue'] = ['status' => 'online', 'label' => 'Active'];
                } elseif ($hoursSinceRun < 24) {
                    $systemStatus['queue'] = ['status' => 'warning', 'label' => 'Delayed'];
                } else {
                    $systemStatus['queue'] = ['status' => 'offline', 'label' => 'Stale'];
                }
            } else {
                $systemStatus['queue'] = ['status' => 'warning', 'label' => 'Not Run'];
            }
        } catch (\Exception $e) {
            $systemStatus['queue'] = ['status' => 'warning', 'label' => 'Unknown'];
        }

        // API status - check if mail is configured
        try {
            $mailerConfigured = !empty(getenv('SMTP_HOST')) || !empty(getenv('USE_GMAIL_API'));
            if ($mailerConfigured) {
                $systemStatus['api'] = ['status' => 'online', 'label' => 'Operational'];
            } else {
                $systemStatus['api'] = ['status' => 'warning', 'label' => 'No Email'];
            }
        } catch (\Exception $e) {
            $systemStatus['api'] = ['status' => 'warning', 'label' => 'Limited'];
        }

        // View Resolution - Let View class handle layout switching
        \Nexus\Core\View::render('admin/dashboard', [
            'stats' => array_merge($stats, ['monthly_stats' => $monthly_stats]),
            'recent_users' => $recent_users,
            'recent_listings' => $recent_listings,
            'activity_logs' => $activity_logs,
            'pending_users' => $pending_users,
            'pending_listings' => $pending_listings,
            'pending_orgs' => $pending_orgs,
            'monthly_stats' => $monthly_stats,
            'systemStatus' => $systemStatus
        ]);
    }

    // --- USER MANAGEMENT ---

    public function users()
    {
        $this->checkAdmin();
        $tenantId = \Nexus\Core\TenantContext::getId();

        // Fetch all users for this tenant
        $users = Database::query("SELECT * FROM users WHERE tenant_id = ? ORDER BY created_at DESC", [$tenantId])->fetchAll();

        // View Resolution - Let View class handle layout switching
        \Nexus\Core\View::render('admin/users/index', ['users' => $users]);
    }

    public function editUser($id)
    {
        $this->checkAdmin();
        $tenantId = \Nexus\Core\TenantContext::getId();

        // Verify user exists and belongs to this tenant
        $user = Database::query("SELECT * FROM users WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();

        if (!$user) {
            echo "User not found or access denied.";
            return;
        }

        \Nexus\Core\View::render('admin/users/edit', [
            'user' => $user
        ]);
    }

    public function updateUser()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDie();

        $id = $_POST['user_id'];
        $firstName = $_POST['first_name'];
        $lastName = $_POST['last_name'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $status = $_POST['status']; // 1 or 0 (is_approved)
        $location = $_POST['location'] ?? null;
        $phone = $_POST['phone'] ?? null;

        // Validate Tenant Scope
        $target = Database::query("SELECT id FROM users WHERE id = ? AND tenant_id = ?", [$id, \Nexus\Core\TenantContext::getId()])->fetch();
        if (!$target) {
            die("Access Denied");
        }

        Database::query(
            "UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, is_approved = ?, location = ?, phone = ? WHERE id = ?",
            [$firstName, $lastName, $email, $role, $status, $location, $phone, $id]
        );

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/users');
    }

    public function deleteUser()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDie();

        $id = $_POST['user_id'];

        // Validate Tenant Scope
        $target = Database::query("SELECT id FROM users WHERE id = ? AND tenant_id = ?", [$id, \Nexus\Core\TenantContext::getId()])->fetch();
        if (!$target) {
            die("Access Denied");
        }

        // Prevent deleting self!
        if ($id == $_SESSION['user_id']) {
            die("Cannot delete yourself.");
        }

        Database::query("DELETE FROM users WHERE id = ?", [$id]);
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/users');
    }

    // -----------------------

    public function approveUser()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDie();
        $id = $_POST['user_id'];

        // Verify user belongs to this tenant and fetch email
        $target = Database::query("SELECT tenant_id, name, email FROM users WHERE id = ?", [$id])->fetch();

        // Security: Use strict comparison to prevent type juggling attacks
        if ($target && (int)$target['tenant_id'] === (int)\Nexus\Core\TenantContext::getId()) {
            User::approve($id);

            // Send Approval Email
            try {
                $tenant = \Nexus\Core\TenantContext::get();
                $tenantName = $tenant['name'] ?? 'Project NEXUS';
                $config = json_decode($tenant['configuration'] ?? '{}', true);
                $welcomeConfig = $config['welcome_email'] ?? [];

                $subject = !empty($welcomeConfig['subject']) ? $welcomeConfig['subject'] : "Account Approved - Welcome to $tenantName";

                // Construct Body
                // If custom body exists, use it. Otherwise use default.
                if (!empty($welcomeConfig['body'])) {
                    $mainMessage = $welcomeConfig['body'];
                    // We wrap this custom message in the standard template wrapper below
                } else {
                    $mainMessage = "Congratulations, <strong>{$target['name']}</strong>! Your account has been approved by the administrators.<br><br>You can now login and complete your onboarding profile to join the community.";
                }

                $loginLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . \Nexus\Core\TenantContext::getBasePath() . "/login";

                $html = \Nexus\Core\EmailTemplate::render(
                    "Account Approved!",
                    "You are now a member of $tenantName",
                    $mainMessage,
                    "Login & Get Started",
                    $loginLink,
                    "Project NEXUS" // Or $tenantName
                );

                $mailer = new \Nexus\Core\Mailer(); # Fixed missing instantiation
                $mailer->send($target['email'], $subject, $html);
            } catch (\Throwable $e) {
                error_log("Approval Email Failed: " . $e->getMessage());
            }
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin');
    }

    public function deleteListing()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDie();
        $id = $_POST['listing_id'];

        // Verify listing belongs to this tenant
        // Security: Use strict comparison to prevent type juggling attacks
        $target = Database::query("SELECT tenant_id FROM listings WHERE id = ?", [$id])->fetch();
        if ($target && (int)$target['tenant_id'] === (int)\Nexus\Core\TenantContext::getId()) {
            Database::query("DELETE FROM listings WHERE id = ?", [$id]);
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin');
    }

    public function settings()
    {
        $this->checkAdmin();
        $isSuper = !empty($_SESSION['is_super_admin']);

        // Global Config (Super Admin Only)
        $config = [];
        if ($isSuper) {
            $envPath = __DIR__ . '/../../.env';
            $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';
            $getValue = function ($key) use ($envContent) {
                preg_match("/^{$key}=(.*)$/m", $envContent, $matches);
                $value = isset($matches[1]) ? trim($matches[1]) : '';
                // Remove surrounding quotes (handles Windows line endings too)
                $value = trim($value, "\"\r\n '");
                return $value;
            };
            $config = [
                'APP_NAME' => $getValue('APP_NAME'),
                'APP_URL' => $getValue('APP_URL'),
                'SMTP_HOST' => $getValue('SMTP_HOST'),
                'SMTP_PORT' => $getValue('SMTP_PORT'),
                'SMTP_ENCRYPTION' => $getValue('SMTP_ENCRYPTION'),
                'SMTP_USER' => $getValue('SMTP_USER'),
                'SMTP_PASS' => $getValue('SMTP_PASS'),
                'SMTP_FROM_EMAIL' => $getValue('SMTP_FROM_EMAIL'),
                'MAPBOX_ACCESS_TOKEN' => $getValue('MAPBOX_ACCESS_TOKEN'),
                // Gmail API Settings
                'USE_GMAIL_API' => $getValue('USE_GMAIL_API'),
                'GMAIL_CLIENT_ID' => $getValue('GMAIL_CLIENT_ID'),
                'GMAIL_CLIENT_SECRET' => $getValue('GMAIL_CLIENT_SECRET'),
                'GMAIL_REFRESH_TOKEN' => $getValue('GMAIL_REFRESH_TOKEN'),
                'GMAIL_SENDER_EMAIL' => $getValue('GMAIL_SENDER_EMAIL'),
                'GMAIL_SENDER_NAME' => $getValue('GMAIL_SENDER_NAME'),
            ];
        }

        // Tenant Config (For Everyone)
        $tenant = \Nexus\Core\TenantContext::get();
        $gamification = json_decode($tenant['gamification_config'] ?? '{}', true);
        $configJson = json_decode($tenant['configuration'] ?? '{}', true);
        $notifications = $configJson['notifications'] ?? [];

        // Layout Switcher - Refactored to View::render
        \Nexus\Core\View::render('admin/settings', [
            'config' => $config,
            'notifications' => $notifications,
            'gamification' => $gamification,
            'tenant' => $tenant,
            'isSuper' => $isSuper
        ]);
    }

    public function saveSettings()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDie();

        // LOCK: Only Super Admins can edit global env settings
        if (empty($_SESSION['is_super_admin'])) {
            $this->forbidden();
        }

        $envPath = __DIR__ . '/../../.env';
        $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';

        $fields = [
            'APP_NAME',
            'APP_URL',
            'SMTP_HOST',
            'SMTP_PORT',
            'SMTP_ENCRYPTION',
            'SMTP_USER',
            'SMTP_PASS',
            'SMTP_FROM_EMAIL',
            'MAPBOX_ACCESS_TOKEN',
            'USE_GMAIL_API',
            'GMAIL_CLIENT_ID',
            'GMAIL_CLIENT_SECRET',
            'GMAIL_REFRESH_TOKEN',
            'GMAIL_SENDER_EMAIL',
            'GMAIL_SENDER_NAME'
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                // Simple regex replacement or append
                if (preg_match("/^{$field}=/m", $envContent)) {
                    $envContent = preg_replace("/^{$field}=.*/m", "{$field}=\"{$value}\"", $envContent);
                } else {
                    $envContent .= "\n{$field}=\"{$value}\"";
                }
            }
        }

        file_put_contents($envPath, $envContent);
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/settings?saved=true');
    }

    public function saveTenantSettings()
    {
        $this->checkAdmin(); // Ensures admin of current tenant
        \Nexus\Core\Csrf::verifyOrDie();

        $tenantId = \Nexus\Core\TenantContext::getId();

        // Gamification Config
        $gamification = [
            'enabled' => isset($_POST['gamification_enabled']),
            'volunteering' => isset($_POST['gamification_volunteering']),
            'timebanking' => isset($_POST['gamification_timebanking']),
        ];

        $json = json_encode($gamification);

        // Notification Config
        $notifConfig = [
            'enabled' => isset($_POST['notifications_enabled']),
            'default_frequency' => $_POST['notifications_default_frequency'] ?? 'daily',
        ];

        // Fetch current config to merge
        $current = Database::query("SELECT configuration FROM tenants WHERE id = ?", [$tenantId])->fetchColumn();
        $configArr = json_decode($current ?? '{}', true);
        $configArr['notifications'] = $notifConfig;

        // Social Login Config - PROTECT EXISTING SECRETS
        $existingSocial = $configArr['social_login'] ?? [];
        $existingGoogle = $existingSocial['providers']['google'] ?? [];
        $existingFacebook = $existingSocial['providers']['facebook'] ?? [];

        $newGoogleId = trim($_POST['social_google_id'] ?? '');
        $newGoogleSecret = trim($_POST['social_google_secret'] ?? '');
        $newFacebookId = trim($_POST['social_facebook_id'] ?? '');
        $newFacebookSecret = trim($_POST['social_facebook_secret'] ?? '');

        $socialConfig = [
            'enabled' => isset($_POST['social_login_enabled']),
            'providers' => [
                'google' => [
                    'client_id' => $newGoogleId !== '' ? $newGoogleId : ($existingGoogle['client_id'] ?? ''),
                    'client_secret' => $newGoogleSecret !== '' ? $newGoogleSecret : ($existingGoogle['client_secret'] ?? ''),
                ],
                'facebook' => [
                    'client_id' => $newFacebookId !== '' ? $newFacebookId : ($existingFacebook['client_id'] ?? ''),
                    'client_secret' => $newFacebookSecret !== '' ? $newFacebookSecret : ($existingFacebook['client_secret'] ?? ''),
                ]
            ]
        ];
        $configArr['social_login'] = $socialConfig;

        // Welcome Email Config - PROTECT EXISTING DATA
        // Only update if new values are provided, otherwise preserve existing
        $existingWelcome = $configArr['welcome_email'] ?? [];
        $newSubject = trim($_POST['welcome_email_subject'] ?? '');
        $newBody = trim($_POST['welcome_email_body'] ?? '');

        $welcomeEmail = [
            'subject' => $newSubject !== '' ? $newSubject : ($existingWelcome['subject'] ?? ''),
            'body' => $newBody !== '' ? $newBody : ($existingWelcome['body'] ?? ''),
        ];
        $configArr['welcome_email'] = $welcomeEmail;

        // Mailchimp Config - PROTECT EXISTING DATA
        $newMailchimpKey = trim($_POST['mailchimp_api_key'] ?? '');
        $newMailchimpList = trim($_POST['mailchimp_list_id'] ?? '');
        $configArr['mailchimp_api_key'] = $newMailchimpKey !== '' ? $newMailchimpKey : ($configArr['mailchimp_api_key'] ?? '');
        $configArr['mailchimp_list_id'] = $newMailchimpList !== '' ? $newMailchimpList : ($configArr['mailchimp_list_id'] ?? '');

        $configJson = json_encode($configArr);

        // Update Tenant
        $defaultLayout = $_POST['default_layout'] ?? 'modern';

        Database::query(
            "UPDATE tenants SET gamification_config = ?, configuration = ?, default_layout = ? WHERE id = ?",
            [$json, $configJson, $defaultLayout, $tenantId]
        );

        // Force refresh context if needed, but page reload will handle it
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/settings?saved_tenant=true');
    }

    public function activityLogs()
    {
        $this->checkAdmin();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;

        $limit = 20;
        $offset = ($page - 1) * $limit;

        $logs = \Nexus\Models\ActivityLog::getAll($limit, $offset);
        $total = \Nexus\Models\ActivityLog::count();
        $totalPages = ceil($total / $limit);

        \Nexus\Core\View::render('admin/activity_log', [
            'logs' => $logs,
            'currentPage' => $page,
            'totalPages' => $totalPages
        ]);
    }

    public function groupLocations()
    {
        $this->checkAdmin();
        \Nexus\Core\View::render('admin/group-locations');
    }

    /**
     * Diagnostic test for smart matching system
     */
    public function testSmartMatch()
    {
        $this->checkAdmin();

        header('Content-Type: text/html; charset=utf-8');

        echo "<!DOCTYPE html><html><head><title>Smart Match Diagnostic</title>";
        echo "<style>body{font-family:monospace;padding:20px;background:#1e293b;color:#e2e8f0;}";
        echo "h1,h2{color:#60a5fa;}hr{border-color:#334155;}";
        echo ".pass{color:#10b981;}.fail{color:#ef4444;}.warn{color:#f59e0b;}</style></head><body>";

        echo "<h1>Smart Match Diagnostic Test</h1>";
        echo "<p>Testing all components of the smart matching system...</p><hr>";

        $allPassed = true;

        // Test 1: Basic PHP
        echo "<h2>Test 1: PHP Execution</h2>";
        echo "<span class='pass'>✓ PHP is working</span><br>";
        echo "PHP Version: " . phpversion() . "<br>";
        echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
        echo "Max Execution Time: " . ini_get('max_execution_time') . "s<br><br>";

        // Test 2: File existence
        echo "<h2>Test 2: Required Files</h2>";
        $files = [
            'src/Controllers/AdminController.php',
            'src/Services/SmartGroupMatchingService.php',
            'src/Core/Database.php',
            'src/Core/TenantContext.php'
        ];

        foreach ($files as $file) {
            $fullPath = __DIR__ . '/../../' . $file;
            if (file_exists($fullPath)) {
                echo "<span class='pass'>✓ $file exists</span><br>";
            } else {
                echo "<span class='fail'>✗ $file NOT FOUND</span><br>";
                $allPassed = false;
            }
        }
        echo "<br>";

        // Test 3: TenantContext
        echo "<h2>Test 3: Tenant Context</h2>";
        try {
            $tenantId = \Nexus\Core\TenantContext::getId();
            echo "<span class='pass'>✓ TenantContext works, Tenant ID: $tenantId</span><br><br>";
        } catch (\Exception $e) {
            echo "<span class='fail'>✗ TenantContext failed: " . htmlspecialchars($e->getMessage()) . "</span><br><br>";
            $allPassed = false;
        }

        // Test 4: Database connection
        echo "<h2>Test 4: Database Connection</h2>";
        try {
            $tenantId = \Nexus\Core\TenantContext::getId();
            $result = Database::query("SELECT COUNT(*) as count FROM users WHERE tenant_id = ?", [$tenantId])->fetch();
            echo "<span class='pass'>✓ Database query works, found {$result['count']} users</span><br><br>";
        } catch (\Exception $e) {
            echo "<span class='fail'>✗ Database failed: " . htmlspecialchars($e->getMessage()) . "</span><br><br>";
            $allPassed = false;
        }

        // Test 5: SmartGroupMatchingService class
        echo "<h2>Test 5: SmartGroupMatchingService Class</h2>";
        try {
            if (class_exists('\\Nexus\\Services\\SmartGroupMatchingService')) {
                echo "<span class='pass'>✓ SmartGroupMatchingService class found</span><br>";

                $matcher = new \Nexus\Services\SmartGroupMatchingService();
                echo "<span class='pass'>✓ SmartGroupMatchingService instantiated successfully</span><br><br>";
            } else {
                echo "<span class='fail'>✗ SmartGroupMatchingService class NOT FOUND</span><br><br>";
                $allPassed = false;
            }
        } catch (\Exception $e) {
            echo "<span class='fail'>✗ SmartGroupMatchingService error: " . htmlspecialchars($e->getMessage()) . "</span><br><br>";
            $allPassed = false;
        }

        // Test 6: Fetch sample user
        echo "<h2>Test 6: Sample User Fetch</h2>";
        try {
            $tenantId = \Nexus\Core\TenantContext::getId();
            $users = Database::query("
                SELECT u.id, u.name, u.location, u.latitude, u.longitude
                FROM users u
                WHERE u.tenant_id = ?
                AND u.status = 'active'
                AND (u.location IS NOT NULL OR (u.latitude IS NOT NULL AND u.longitude IS NOT NULL))
                ORDER BY u.id
                LIMIT 1
            ", [$tenantId])->fetchAll();

            if (count($users) > 0) {
                $user = $users[0];
                echo "<span class='pass'>✓ Fetched sample user: " . htmlspecialchars($user['name']) . " (ID: {$user['id']})</span><br>";
                echo "&nbsp;&nbsp;Location: " . htmlspecialchars($user['location'] ?? 'none') . "<br>";
                echo "&nbsp;&nbsp;Coordinates: " . ($user['latitude'] ?? 'none') . ", " . ($user['longitude'] ?? 'none') . "<br><br>";
            } else {
                echo "<span class='warn'>⚠ No users found matching criteria</span><br><br>";
            }
        } catch (\Exception $e) {
            echo "<span class='fail'>✗ User fetch failed: " . htmlspecialchars($e->getMessage()) . "</span><br><br>";
            $allPassed = false;
        }

        // Test 7: Try actual smart match on sample user
        echo "<h2>Test 7: Test Smart Match on Sample User</h2>";
        try {
            $tenantId = \Nexus\Core\TenantContext::getId();
            $users = Database::query("
                SELECT u.id, u.name, u.location, u.latitude, u.longitude
                FROM users u
                WHERE u.tenant_id = ?
                AND u.status = 'active'
                AND (u.location IS NOT NULL OR (u.latitude IS NOT NULL AND u.longitude IS NOT NULL))
                ORDER BY u.id
                LIMIT 1
            ", [$tenantId])->fetchAll();

            if (count($users) > 0) {
                $matcher = new \Nexus\Services\SmartGroupMatchingService();
                $result = $matcher->assignUser($users[0]);

                echo "<span class='pass'>✓ Smart match executed</span><br>";
                echo "&nbsp;&nbsp;Success: " . ($result['success'] ? '<span class="pass">YES</span>' : '<span class="warn">NO</span>') . "<br>";
                echo "&nbsp;&nbsp;Message: " . htmlspecialchars($result['message']) . "<br>";
                echo "&nbsp;&nbsp;Method: " . htmlspecialchars($result['method'] ?? 'none') . "<br>";

                if (isset($result['groups']) && count($result['groups']) > 0) {
                    echo "&nbsp;&nbsp;Groups assigned: " . count($result['groups']) . "<br>";
                    foreach ($result['groups'] as $group) {
                        echo "&nbsp;&nbsp;&nbsp;&nbsp;- " . htmlspecialchars($group['name']) . "<br>";
                    }
                }
                echo "<br>";
            }
        } catch (\Exception $e) {
            echo "<span class='fail'>✗ Smart match failed: " . htmlspecialchars($e->getMessage()) . "</span><br>";
            echo "<pre style='color:#f59e0b;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre><br>";
            $allPassed = false;
        }

        // Test 8: JSON output test
        echo "<h2>Test 8: JSON Output Test</h2>";
        $testData = [
            'batch' => [
                ['user_id' => 1, 'success' => true, 'message' => 'Test'],
            ],
            'hasMore' => false
        ];
        $json = json_encode($testData);
        if ($json) {
            echo "<span class='pass'>✓ JSON encoding works</span><br>";
            echo "&nbsp;&nbsp;Sample: " . htmlspecialchars($json) . "<br><br>";
        } else {
            echo "<span class='fail'>✗ JSON encoding failed</span><br><br>";
            $allPassed = false;
        }

        // Summary
        echo "<hr><h2>Summary</h2>";
        if ($allPassed) {
            echo "<p><span class='pass'>✓✓✓ ALL TESTS PASSED ✓✓✓</span></p>";
            echo "<p>The smart match system should work. Try accessing:</p>";
            echo "<p><a href='/admin/smart-match-users?action=match_batch&offset=0' style='color:#60a5fa;'>/admin/smart-match-users?action=match_batch&offset=0</a></p>";
        } else {
            echo "<p><span class='fail'>✗ SOME TESTS FAILED</span></p>";
            echo "<p>Fix the failed tests above before running smart matching.</p>";
        }

        echo "<p><a href='/admin/smart-match-users' style='color:#60a5fa;'>← Back to Smart Match Users</a></p>";
        echo "</body></html>";
        exit;
    }

    public function smartMatchUsers()
    {
        // Handle AJAX request for smart matching batch FIRST (before checkAdmin which might output)
        if (isset($_GET['action']) && $_GET['action'] === 'match_batch') {
            // Clean any output buffers and start fresh
            while (ob_get_level()) {
                ob_end_clean();
            }
            ob_start();

            $this->checkAdmin();

            header('Content-Type: application/json');
            header('Cache-Control: no-cache, must-revalidate');

            try {
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                $batchSize = 5;
                $tenantId = \Nexus\Core\TenantContext::getId();

                // Get batch of users who are NOT already in hub groups
                $users = Database::query("
                    SELECT u.id, u.name, u.location, u.latitude, u.longitude
                    FROM users u
                    WHERE u.tenant_id = ?
                    AND u.status = 'active'
                    AND (u.location IS NOT NULL OR (u.latitude IS NOT NULL AND u.longitude IS NOT NULL))
                    AND NOT EXISTS (
                        SELECT 1
                        FROM group_members gm
                        JOIN groups g ON gm.group_id = g.id
                        JOIN group_types gt ON g.type_id = gt.id
                        WHERE gm.user_id = u.id
                        AND gt.is_hub = 1
                    )
                    ORDER BY u.id
                    LIMIT $batchSize OFFSET $offset
                ", [$tenantId])->fetchAll();

                $matcher = new \Nexus\Services\SmartGroupMatchingService();
                $results = [];

                foreach ($users as $user) {
                    try {
                        $result = $matcher->assignUser($user);

                        $results[] = [
                            'user_id' => $user['id'],
                            'user_name' => $user['name'],
                            'user_location' => $user['location'],
                            'success' => $result['success'],
                            'message' => $result['message'],
                            'method' => $result['method'] ?? null,
                            'groups' => $result['groups'] ?? []
                        ];
                    } catch (\Exception $e) {
                        $results[] = [
                            'user_id' => $user['id'],
                            'user_name' => $user['name'],
                            'user_location' => $user['location'],
                            'success' => false,
                            'message' => 'Error: ' . $e->getMessage(),
                            'method' => null,
                            'groups' => []
                        ];
                    }
                }

                // Clear buffer and output clean JSON
                ob_clean();
                echo json_encode([
                    'batch' => $results,
                    'hasMore' => count($users) === $batchSize
                ]);
                ob_end_flush();
            } catch (\Exception $e) {
                // Clear buffer and output error JSON
                ob_clean();
                echo json_encode([
                    'error' => true,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                ob_end_flush();
            }
            exit;
        }

        // Handle AJAX request for completion notification
        if (isset($_GET['action']) && $_GET['action'] === 'notify_complete') {
            // Clean any output buffers and start fresh
            while (ob_get_level()) {
                ob_end_clean();
            }
            ob_start();

            $this->checkAdmin();

            header('Content-Type: application/json');
            header('Cache-Control: no-cache, must-revalidate');

            try {
                // Get POST data
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);

                $matched = $data['matched'] ?? 0;
                $skipped = $data['skipped'] ?? 0;
                $total = $data['total'] ?? 0;
                $tenantId = \Nexus\Core\TenantContext::getId();

                // Log completion
                error_log("Smart Match Complete: Tenant $tenantId - Matched: $matched, Skipped: $skipped, Total: $total");

                // Optionally store completion stats in database or trigger notifications
                // For now, just acknowledge receipt
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Completion notification received'
                ]);
                ob_end_flush();
            } catch (\Exception $e) {
                error_log("Smart Match Notification Error: " . $e->getMessage());
                ob_clean();
                echo json_encode([
                    'error' => true,
                    'message' => $e->getMessage()
                ]);
                ob_end_flush();
            }
            exit;
        }

        // Render the interface (for non-AJAX requests)
        $this->checkAdmin();
        \Nexus\Core\View::render('admin/smart-match-users');
    }

    public function smartMatchMonitoring()
    {
        $this->checkAdmin();
        \Nexus\Core\View::render('admin/smart-match-monitoring');
    }

    public function geocodeGroups()
    {
        $this->checkAdmin();

        // Handle AJAX request for geocoding batch
        if (isset($_GET['action']) && $_GET['action'] === 'geocode_batch') {
            header('Content-Type: application/json');

            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $batchSize = 10;
            $tenantId = \Nexus\Core\TenantContext::getId();

            // Get batch of groups needing geocoding
            $groups = Database::query("
                SELECT id, name, location
                FROM `groups`
                WHERE tenant_id = ?
                AND type_id = 26
                AND (visibility IS NULL OR visibility = 'public')
                AND (latitude IS NULL OR longitude IS NULL)
                AND location IS NOT NULL
                AND location != ''
                ORDER BY name
                LIMIT $batchSize OFFSET $offset
            ", [$tenantId])->fetchAll();

            $results = [];
            foreach ($groups as $group) {
                $result = \Nexus\Services\GeocodingService::geocode($group['location']);

                if ($result && isset($result['latitude']) && isset($result['longitude'])) {
                    Database::query("
                        UPDATE `groups`
                        SET latitude = ?, longitude = ?
                        WHERE id = ?
                    ", [
                        $result['latitude'],
                        $result['longitude'],
                        $group['id']
                    ]);

                    $results[] = [
                        'success' => true,
                        'id' => $group['id'],
                        'name' => $group['name'],
                        'location' => $group['location'],
                        'coords' => [$result['latitude'], $result['longitude']]
                    ];

                    sleep(1); // Rate limiting
                } else {
                    $results[] = [
                        'success' => false,
                        'id' => $group['id'],
                        'name' => $group['name'],
                        'location' => $group['location'],
                        'error' => 'Could not geocode'
                    ];
                }
            }

            echo json_encode([
                'batch' => $results,
                'hasMore' => count($groups) === $batchSize
            ]);
            exit;
        }

        // Render the geocoding interface
        \Nexus\Core\View::render('admin/geocode-groups');
    }

    /**
     * Group Types Management - Index
     */
    public function groupTypes()
    {
        $this->checkAdmin();
        \Nexus\Core\View::render('admin/group-types/index');
    }

    /**
     * Group Types Management - Create/Edit Form
     */
    public function groupTypeForm($id = null)
    {
        $this->checkAdmin();

        // Pass the ID to the view via the data array
        \Nexus\Core\View::render('admin/group-types/form', [
            'editId' => $id
        ]);
    }

    /**
     * Test Gmail API connection (AJAX endpoint)
     */
    public function testGmailConnection()
    {
        $this->checkAdmin();

        // Only Super Admins can test (as they configure it)
        if (empty($_SESSION['is_super_admin'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Super Admin access required']);
            return;
        }

        header('Content-Type: application/json');

        try {
            $result = \Nexus\Core\Mailer::testGmailConnection();
            echo json_encode($result);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // =============================================
    // NATIVE APP MANAGEMENT
    // =============================================

    /**
     * Native App Dashboard - Shows FCM device stats and allows test notifications
     */
    public function nativeApp()
    {
        $this->checkAdmin();
        $tenantId = \Nexus\Core\TenantContext::getId();

        // Ensure FCM table exists
        try {
            \Nexus\Services\FCMPushService::ensureTableExists();
        } catch (\Exception $e) {
            // Table might not exist yet, that's okay
        }

        // Get FCM device stats
        $stats = [
            'total_devices' => 0,
            'unique_users' => 0,
            'android_devices' => 0,
            'ios_devices' => 0,
            'recent_registrations' => [],
        ];

        try {
            // Total devices
            $result = Database::query("SELECT COUNT(*) as c FROM fcm_device_tokens WHERE tenant_id = ?", [$tenantId])->fetch();
            $stats['total_devices'] = (int)($result['c'] ?? 0);

            // Unique users with devices
            $result = Database::query("SELECT COUNT(DISTINCT user_id) as c FROM fcm_device_tokens WHERE tenant_id = ?", [$tenantId])->fetch();
            $stats['unique_users'] = (int)($result['c'] ?? 0);

            // Platform breakdown
            $result = Database::query("SELECT COUNT(*) as c FROM fcm_device_tokens WHERE tenant_id = ? AND platform = 'android'", [$tenantId])->fetch();
            $stats['android_devices'] = (int)($result['c'] ?? 0);

            $result = Database::query("SELECT COUNT(*) as c FROM fcm_device_tokens WHERE tenant_id = ? AND platform = 'ios'", [$tenantId])->fetch();
            $stats['ios_devices'] = (int)($result['c'] ?? 0);

            // Recent registrations (last 10)
            $stats['recent_registrations'] = Database::query("
                SELECT d.*, u.first_name, u.last_name, u.email
                FROM fcm_device_tokens d
                LEFT JOIN users u ON d.user_id = u.id
                WHERE d.tenant_id = ?
                ORDER BY d.created_at DESC
                LIMIT 10
            ", [$tenantId])->fetchAll();
        } catch (\Exception $e) {
            // Table doesn't exist yet - no devices registered
        }

        // Get PWA subscription stats for comparison
        $pwaStats = [
            'total_subscriptions' => 0,
            'unique_users' => 0,
        ];

        try {
            $result = Database::query("SELECT COUNT(*) as c FROM push_subscriptions WHERE tenant_id = ?", [$tenantId])->fetch();
            $pwaStats['total_subscriptions'] = (int)($result['c'] ?? 0);

            $result = Database::query("SELECT COUNT(DISTINCT user_id) as c FROM push_subscriptions WHERE tenant_id = ?", [$tenantId])->fetch();
            $pwaStats['unique_users'] = (int)($result['c'] ?? 0);
        } catch (\Exception $e) {
            // Table might not exist
        }

        // Check if FCM is configured (uses service account for v1 API)
        $fcmConfigured = \Nexus\Services\FCMPushService::isConfigured();

        \Nexus\Core\View::render('admin/native-app', [
            'stats' => $stats,
            'pwaStats' => $pwaStats,
            'fcmConfigured' => $fcmConfigured,
        ]);
    }

    /**
     * Send test push notification to native app users (AJAX)
     */
    public function sendTestPush()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDieJson();

        header('Content-Type: application/json');

        $title = trim($_POST['title'] ?? 'Test Notification');
        $body = trim($_POST['body'] ?? 'This is a test notification from the admin panel.');
        $targetUserId = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;

        if (empty($title) || empty($body)) {
            echo json_encode(['success' => false, 'message' => 'Title and body are required']);
            return;
        }

        try {
            if ($targetUserId) {
                // Send to specific user
                $result = \Nexus\Services\FCMPushService::sendToUser($targetUserId, $title, $body, ['type' => 'test']);
            } else {
                // Send to all users in tenant
                $tenantId = \Nexus\Core\TenantContext::getId();
                $userIds = Database::query("SELECT DISTINCT user_id FROM fcm_device_tokens WHERE tenant_id = ?", [$tenantId])->fetchAll(\PDO::FETCH_COLUMN);

                if (empty($userIds)) {
                    echo json_encode(['success' => false, 'message' => 'No devices registered']);
                    return;
                }

                $result = \Nexus\Services\FCMPushService::sendToUsers($userIds, $title, $body, ['type' => 'test']);
            }

            echo json_encode([
                'success' => true,
                'message' => "Sent: {$result['sent']}, Failed: {$result['failed']}",
                'sent' => $result['sent'],
                'failed' => $result['failed']
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Feed Algorithm (EdgeRank) Settings Page
     */
    public function feedAlgorithm()
    {
        $this->checkAdmin();

        $tenantId = \Nexus\Core\TenantContext::getId();
        $tenant = Database::query("SELECT * FROM tenants WHERE id = ?", [$tenantId])->fetch();

        $isSuper = !empty($_SESSION['is_super_admin']);

        \Nexus\Core\View::render('admin/feed-algorithm', [
            'tenant' => $tenant,
            'isSuper' => $isSuper,
        ]);
    }

    /**
     * Save Feed Algorithm Settings
     */
    public function saveFeedAlgorithm()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDie();

        $tenantId = \Nexus\Core\TenantContext::getId();

        // Fetch current config to merge
        $current = Database::query("SELECT configuration FROM tenants WHERE id = ?", [$tenantId])->fetchColumn();
        $configArr = json_decode($current ?? '{}', true);
        if (!is_array($configArr)) $configArr = [];

        // Build feed algorithm config
        $feedAlgo = [
            'enabled' => isset($_POST['enabled']),
            'like_weight' => (float)($_POST['like_weight'] ?? 1),
            'comment_weight' => (float)($_POST['comment_weight'] ?? 5),
            'share_weight' => (float)($_POST['share_weight'] ?? 8),
            'vitality_full_days' => (int)($_POST['vitality_full_days'] ?? 7),
            'vitality_decay_days' => (int)($_POST['vitality_decay_days'] ?? 30),
            'vitality_minimum' => (float)($_POST['vitality_minimum'] ?? 0.5),
            'geo_full_radius' => (int)($_POST['geo_full_radius'] ?? 10),
            'geo_decay_interval' => (int)($_POST['geo_decay_interval'] ?? 10),
            'geo_decay_rate' => (float)($_POST['geo_decay_rate'] ?? 0.10),
            'geo_minimum' => (float)($_POST['geo_minimum'] ?? 0.1),
            // Content Freshness Decay
            'freshness_enabled' => isset($_POST['freshness_enabled']),
            'freshness_full_hours' => (int)($_POST['freshness_full_hours'] ?? 24),
            'freshness_half_life' => (int)($_POST['freshness_half_life'] ?? 72),
            'freshness_minimum' => (float)($_POST['freshness_minimum'] ?? 0.3),
            // Social Graph
            'social_graph_enabled' => isset($_POST['social_graph_enabled']),
            'social_graph_max_boost' => (float)($_POST['social_graph_max_boost'] ?? 2.0),
            'social_graph_lookback_days' => (int)($_POST['social_graph_lookback_days'] ?? 90),
            'social_graph_follower_boost' => (float)($_POST['social_graph_follower_boost'] ?? 1.5),
            // Negative Signals
            'negative_signals_enabled' => isset($_POST['negative_signals_enabled']),
            'hide_penalty' => (float)($_POST['hide_penalty'] ?? 0.0),
            'mute_penalty' => (float)($_POST['mute_penalty'] ?? 0.1),
            'block_penalty' => (float)($_POST['block_penalty'] ?? 0.0),
            'report_penalty_per' => (float)($_POST['report_penalty_per'] ?? 0.15),
            // Content Quality
            'quality_enabled' => isset($_POST['quality_enabled']),
            'quality_image_boost' => (float)($_POST['quality_image_boost'] ?? 1.3),
            'quality_link_boost' => (float)($_POST['quality_link_boost'] ?? 1.1),
            'quality_length_min' => (int)($_POST['quality_length_min'] ?? 50),
            'quality_length_bonus' => (float)($_POST['quality_length_bonus'] ?? 1.2),
            'quality_video_boost' => (float)($_POST['quality_video_boost'] ?? 1.4),
            'quality_hashtag_boost' => (float)($_POST['quality_hashtag_boost'] ?? 1.1),
            'quality_mention_boost' => (float)($_POST['quality_mention_boost'] ?? 1.15),
            // Content Diversity
            'diversity_enabled' => isset($_POST['diversity_enabled']),
            'diversity_max_consecutive' => (int)($_POST['diversity_max_consecutive'] ?? 2),
            'diversity_penalty' => (float)($_POST['diversity_penalty'] ?? 0.5),
            'diversity_type_enabled' => isset($_POST['diversity_type_enabled']),
            'diversity_type_max_consecutive' => (int)($_POST['diversity_type_max_consecutive'] ?? 3),
        ];

        $configArr['feed_algorithm'] = $feedAlgo;
        $configJson = json_encode($configArr);

        // Update Tenant
        Database::query(
            "UPDATE tenants SET configuration = ? WHERE id = ?",
            [$configJson, $tenantId]
        );

        // Log the change
        \Nexus\Models\ActivityLog::log($_SESSION['user_id'], 'settings_update', 'Updated feed algorithm settings');

        // Clear the FeedRankingService config cache
        if (class_exists('\Nexus\Services\FeedRankingService')) {
            \Nexus\Services\FeedRankingService::clearCache();
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/feed-algorithm?saved=true');
    }

    /**
     * Unified Algorithm Settings Page
     * Shows all ranking algorithms: EdgeRank, MatchRank, CommunityRank
     */
    public function algorithmSettings()
    {
        $this->checkAdmin();

        $tenantId = \Nexus\Core\TenantContext::getId();
        $tenant = Database::query("SELECT * FROM tenants WHERE id = ?", [$tenantId])->fetch();

        $isSuper = !empty($_SESSION['is_super_admin']);

        // Get current configuration
        $configJson = $tenant['configuration'] ?? '{}';
        $config = json_decode($configJson, true) ?: [];

        // Get algorithm settings or defaults
        $algorithms = $config['algorithms'] ?? [];

        \Nexus\Core\View::render('admin/algorithm-settings', [
            'tenant' => $tenant,
            'isSuper' => $isSuper,
            'config' => $config,
            'algorithms' => $algorithms,
        ]);
    }

    /**
     * Save Unified Algorithm Settings
     */
    public function saveAlgorithmSettings()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDie();

        $tenantId = \Nexus\Core\TenantContext::getId();

        // Fetch current config
        $current = Database::query("SELECT configuration FROM tenants WHERE id = ?", [$tenantId])->fetchColumn();
        $configArr = json_decode($current ?? '{}', true);
        if (!is_array($configArr)) $configArr = [];

        $algorithmType = $_POST['algorithm_type'] ?? '';

        // Initialize algorithms array if not exists
        if (!isset($configArr['algorithms'])) {
            $configArr['algorithms'] = [];
        }

        switch ($algorithmType) {
            case 'shared':
                $configArr['algorithms']['shared'] = [
                    'geo_enabled' => isset($_POST['geo_enabled']),
                    'geo_full_radius_km' => (int)($_POST['geo_full_radius_km'] ?? 25),
                    'geo_decay_per_km' => (float)($_POST['geo_decay_per_km'] ?? 0.005),
                    'geo_minimum_score' => (float)($_POST['geo_minimum_score'] ?? 0.1),
                    'freshness_enabled' => isset($_POST['freshness_enabled']),
                    'freshness_full_hours' => (int)($_POST['freshness_full_hours'] ?? 48),
                    'freshness_half_life_hours' => (int)($_POST['freshness_half_life_hours'] ?? 168),
                    'freshness_minimum' => (float)($_POST['freshness_minimum'] ?? 0.2),
                    'activity_lookback_days' => (int)($_POST['activity_lookback_days'] ?? 30),
                    'quality_enabled' => isset($_POST['quality_enabled']),
                ];
                break;

            case 'listings':
                $configArr['algorithms']['listings'] = [
                    'enabled' => isset($_POST['enabled']),
                    // Relevance
                    'relevance_category_match' => (float)($_POST['relevance_category_match'] ?? 1.5),
                    'relevance_search_boost' => (float)($_POST['relevance_search_boost'] ?? 2.0),
                    // Freshness
                    'freshness_full_days' => (int)($_POST['freshness_full_days'] ?? 7),
                    'freshness_half_life_days' => (int)($_POST['freshness_half_life_days'] ?? 30),
                    'freshness_minimum' => (float)($_POST['freshness_minimum'] ?? 0.3),
                    // Engagement
                    'engagement_view_weight' => (float)($_POST['engagement_view_weight'] ?? 0.1),
                    'engagement_inquiry_weight' => (float)($_POST['engagement_inquiry_weight'] ?? 1.0),
                    // Quality
                    'quality_image_boost' => (float)($_POST['quality_image_boost'] ?? 1.3),
                    'quality_location_boost' => (float)($_POST['quality_location_boost'] ?? 1.2),
                    'quality_verified_boost' => (float)($_POST['quality_verified_boost'] ?? 1.4),
                    // Reciprocity
                    'reciprocity_enabled' => isset($_POST['reciprocity_enabled']),
                    'reciprocity_match_boost' => (float)($_POST['reciprocity_match_boost'] ?? 1.5),
                    'reciprocity_mutual_boost' => (float)($_POST['reciprocity_mutual_boost'] ?? 2.0),
                    // Geo
                    'geo_enabled' => isset($_POST['geo_enabled']),
                    'geo_full_radius_km' => (int)($_POST['geo_full_radius_km'] ?? 50),
                ];
                break;

            case 'members':
                $configArr['algorithms']['members'] = [
                    'enabled' => isset($_POST['enabled']),
                    // Activity
                    'activity_lookback_days' => (int)($_POST['activity_lookback_days'] ?? 30),
                    'activity_minimum' => (float)($_POST['activity_minimum'] ?? 0.1),
                    // Contribution
                    'contribution_giver_bonus' => (float)($_POST['contribution_giver_bonus'] ?? 1.5),
                    // Reputation
                    'reputation_verified_boost' => (float)($_POST['reputation_verified_boost'] ?? 1.5),
                    'reputation_account_age_days' => (int)($_POST['reputation_account_age_days'] ?? 90),
                    'reputation_minimum' => (float)($_POST['reputation_minimum'] ?? 0.3),
                    // Connectivity
                    'connectivity_shared_group' => (float)($_POST['connectivity_shared_group'] ?? 1.2),
                    'connectivity_past_interaction' => (float)($_POST['connectivity_past_interaction'] ?? 1.3),
                    // Complementary
                    'complementary_enabled' => isset($_POST['complementary_enabled']),
                    'complementary_match_boost' => (float)($_POST['complementary_match_boost'] ?? 1.8),
                    'complementary_mutual_boost' => (float)($_POST['complementary_mutual_boost'] ?? 2.5),
                    // Geo
                    'geo_enabled' => isset($_POST['geo_enabled']),
                    'geo_full_radius_km' => (int)($_POST['geo_full_radius_km'] ?? 30),
                ];
                break;

            default:
                header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/algorithm-settings?error=invalid_type');
                return;
        }

        $configJson = json_encode($configArr);

        // Update Tenant
        Database::query(
            "UPDATE tenants SET configuration = ? WHERE id = ?",
            [$configJson, $tenantId]
        );

        // Log the change
        \Nexus\Models\ActivityLog::log($_SESSION['user_id'], 'settings_update', "Updated {$algorithmType} algorithm settings");

        // Clear all ranking service caches
        if (class_exists('\Nexus\Services\RankingService')) {
            \Nexus\Services\RankingService::clearAllCaches();
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/algorithm-settings?saved=' . $algorithmType);
    }

    /**
     * AJAX Live Search for Admin Command Palette
     * Searches users, listings, and pages in real-time
     */
    public function liveSearch()
    {
        $this->checkAdmin();

        header('Content-Type: application/json');

        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            echo json_encode(['users' => [], 'listings' => [], 'pages' => []]);
            return;
        }

        $tenantId = \Nexus\Core\TenantContext::getId();
        $basePath = \Nexus\Core\TenantContext::getBasePath();
        $searchTerm = '%' . $query . '%';
        $results = ['users' => [], 'listings' => [], 'pages' => []];

        // Search Users (limit 5)
        try {
            $users = Database::query("
                SELECT id, first_name, last_name, email, role, is_approved
                FROM users
                WHERE tenant_id = ?
                AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)
                ORDER BY created_at DESC
                LIMIT 5
            ", [$tenantId, $searchTerm, $searchTerm, $searchTerm, $searchTerm])->fetchAll();

            foreach ($users as $user) {
                $name = trim($user['first_name'] . ' ' . $user['last_name']);
                $status = $user['is_approved'] ? '' : ' (Pending)';
                $results['users'][] = [
                    'id' => $user['id'],
                    'title' => $name . $status,
                    'subtitle' => $user['email'],
                    'icon' => 'fa-user',
                    'url' => $basePath . '/admin/users/edit/' . $user['id'],
                    'actions' => [
                        ['label' => 'Edit', 'url' => $basePath . '/admin/users/edit/' . $user['id'], 'icon' => 'fa-pen'],
                        ['label' => 'View Profile', 'url' => $basePath . '/member/' . $user['id'], 'icon' => 'fa-eye'],
                    ]
                ];
            }
        } catch (\Exception $e) {
            // Table might not exist or other error
        }

        // Search Listings (limit 5)
        try {
            $listings = Database::query("
                SELECT l.id, l.title, l.status, l.type, u.first_name, u.last_name
                FROM listings l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.tenant_id = ?
                AND (l.title LIKE ? OR l.description LIKE ?)
                ORDER BY l.created_at DESC
                LIMIT 5
            ", [$tenantId, $searchTerm, $searchTerm])->fetchAll();

            foreach ($listings as $listing) {
                $author = trim($listing['first_name'] . ' ' . $listing['last_name']);
                $statusBadge = $listing['status'] === 'pending' ? ' (Pending)' : '';
                $results['listings'][] = [
                    'id' => $listing['id'],
                    'title' => $listing['title'] . $statusBadge,
                    'subtitle' => 'by ' . $author . ' • ' . ucfirst($listing['type'] ?? 'offer'),
                    'icon' => 'fa-rectangle-list',
                    'url' => $basePath . '/admin/listings/edit/' . $listing['id'],
                    'actions' => [
                        ['label' => 'Edit', 'url' => $basePath . '/admin/listings/edit/' . $listing['id'], 'icon' => 'fa-pen'],
                        ['label' => 'View', 'url' => $basePath . '/listing/' . $listing['id'], 'icon' => 'fa-eye'],
                    ]
                ];
            }
        } catch (\Exception $e) {
            // Table might not exist or other error
        }

        // Search Blog Posts (limit 3)
        // DISABLED: blog_posts table missing
        /*
        try {
            $posts = Database::query("
                SELECT id, title, status
                FROM blog_posts
                WHERE tenant_id = ?
                AND (title LIKE ? OR content LIKE ?)
                ORDER BY created_at DESC
                LIMIT 3
            ", [$tenantId, $searchTerm, $searchTerm])->fetchAll();

            foreach ($posts as $post) {
                $results['pages'][] = [
                    'id' => $post['id'],
                    'title' => $post['title'],
                    'subtitle' => 'Blog Post',
                    'icon' => 'fa-blog',
                    'url' => $basePath . '/admin/blog/edit/' . $post['id'],
                    'actions' => [
                        ['label' => 'Edit', 'url' => $basePath . '/admin/blog/edit/' . $post['id'], 'icon' => 'fa-pen'],
                    ]
                ];
            }
        } catch (\Exception $e) {
            // Table might not exist
        }
        */

        echo json_encode($results);
    }

    /**
     * Group Ranking Dashboard
     */
    public function groupRanking()
    {
        $this->checkAdmin();

        // Get current featured groups with scores
        $localHubs = \Nexus\Services\SmartGroupRankingService::getFeaturedGroupsWithScores('local_hubs');
        $communityGroups = \Nexus\Services\SmartGroupRankingService::getFeaturedGroupsWithScores('community_groups');
        $lastUpdate = \Nexus\Services\SmartGroupRankingService::getLastUpdateTime();

        \Nexus\Core\View::render('admin/group-ranking', [
            'localHubs' => $localHubs,
            'communityGroups' => $communityGroups,
            'lastUpdate' => $lastUpdate,
            'pageTitle' => 'Smart Group Ranking'
        ]);
    }

    /**
     * Update Featured Groups (AJAX endpoint)
     */
    public function updateFeaturedGroups()
    {
        $this->checkAdmin();

        // Clean output buffer to prevent HTML errors
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();

        header('Content-Type: application/json');

        try {
            $stats = \Nexus\Services\SmartGroupRankingService::updateAllFeaturedGroups();

            // Check for errors in stats
            if (isset($stats['local_hubs']['error'])) {
                throw new \Exception('Local hubs: ' . $stats['local_hubs']['error']);
            }
            if (isset($stats['community_groups']['error'])) {
                throw new \Exception('Community groups: ' . $stats['community_groups']['error']);
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'message' => 'Featured groups updated successfully'
            ]);
            ob_end_flush();
        } catch (\Exception $e) {
            ob_clean();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            ob_end_flush();
        }
    }

    /**
     * Toggle Featured Status (AJAX endpoint)
     */
    public function toggleFeaturedGroup()
    {
        $this->checkAdmin();

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $groupId = $input['group_id'] ?? null;
        $featured = $input['featured'] ?? false;

        if (!$groupId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Group ID required'
            ]);
            return;
        }

        try {
            $result = \Nexus\Services\SmartGroupRankingService::setFeaturedStatus($groupId, $featured);

            echo json_encode([
                'success' => $result,
                'message' => $featured ? 'Group pinned as featured' : 'Group unpinned'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test ranking service directly
     */
    public function testRanking()
    {
        $this->checkAdmin();

        header('Content-Type: text/plain');

        echo "=== SMART GROUP RANKING TEST ===\n\n";

        try {
            echo "1. Testing updateFeaturedLocalHubs()...\n\n";

            $stats = \Nexus\Services\SmartGroupRankingService::updateFeaturedLocalHubs(2, 6);

            echo "SUCCESS!\n\n";
            echo "Results:\n";
            print_r($stats);
        } catch (\Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
            echo "\nStack trace:\n";
            echo $e->getTraceAsString();
        }

        echo "\n\n=== END TEST ===\n";
        exit;
    }

    /**
     * Cron endpoint for automated updates
     * Call this daily: /admin/cron/update-featured-groups?key=YOUR_SECRET_KEY
     */
    public function cronUpdateFeaturedGroups()
    {
        // Skip key check if running internally from admin panel
        if (!defined('CRON_INTERNAL_RUN') || !CRON_INTERNAL_RUN) {
            // Verify secret key - check both CRON_KEY and CRON_SECRET_KEY for compatibility
            $secretKey = $_GET['key'] ?? '';
            $expectedKey = \Nexus\Core\Env::get('CRON_KEY') ?: (getenv('CRON_SECRET_KEY') ?: 'change-me-in-production');

            if ($secretKey !== $expectedKey) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid secret key'
                ]);
                return;
            }
        }

        header('Content-Type: application/json');

        try {
            $stats = \Nexus\Services\SmartGroupRankingService::updateAllFeaturedGroups();

            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    // =============================================
    // DELIVERABILITY TRACKING MODULE
    // =============================================

    /**
     * Deliverability Tracking Dashboard
     */
    public function deliverabilityDashboard()
    {
        $this->checkAdmin();
        $tenantId = \Nexus\Core\TenantContext::getId();
        $userId = $_SESSION['user_id'];

        // Get analytics and stats
        $analytics = \Nexus\Services\DeliverabilityTrackingService::getAnalytics();
        $userDashboard = \Nexus\Services\DeliverabilityTrackingService::getUserDashboard($userId);

        \Nexus\Core\View::render('admin/deliverability/dashboard', [
            'analytics' => $analytics,
            'userDashboard' => $userDashboard
        ]);
    }

    /**
     * Deliverables List View
     */
    public function deliverablesList()
    {
        $this->checkAdmin();
        $tenantId = \Nexus\Core\TenantContext::getId();

        // Parse filters from query params
        $filters = [];
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (!empty($_GET['priority'])) {
            $filters['priority'] = $_GET['priority'];
        }
        if (!empty($_GET['category'])) {
            $filters['category'] = $_GET['category'];
        }
        if (!empty($_GET['assigned_to'])) {
            $filters['assigned_to'] = (int)$_GET['assigned_to'];
        }
        if (!empty($_GET['owner_id'])) {
            $filters['owner_id'] = (int)$_GET['owner_id'];
        }
        if (isset($_GET['overdue']) && $_GET['overdue'] === 'true') {
            $filters['overdue'] = true;
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $deliverables = \Nexus\Models\Deliverable::getAll($filters, $limit, $offset);
        $totalCount = \Nexus\Models\Deliverable::getCount($filters);
        $totalPages = ceil($totalCount / $limit);

        // Get all users for filter dropdown
        $users = Database::query("SELECT id, first_name, last_name FROM users WHERE tenant_id = ? ORDER BY first_name", [$tenantId])->fetchAll();

        \Nexus\Core\View::render('admin/deliverability/list', [
            'deliverables' => $deliverables,
            'totalCount' => $totalCount,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'users' => $users
        ]);
    }

    /**
     * Create Deliverable Form
     */
    public function deliverableCreate()
    {
        $this->checkAdmin();
        $tenantId = \Nexus\Core\TenantContext::getId();

        // Get users and groups for assignment dropdowns
        $users = Database::query("SELECT id, first_name, last_name FROM users WHERE tenant_id = ? ORDER BY first_name", [$tenantId])->fetchAll();
        $groups = Database::query("SELECT id, name FROM groups WHERE tenant_id = ? ORDER BY name", [$tenantId])->fetchAll();

        \Nexus\Core\View::render('admin/deliverability/create', [
            'users' => $users,
            'groups' => $groups
        ]);
    }

    /**
     * Store New Deliverable
     */
    public function deliverableStore()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDie();

        $userId = $_SESSION['user_id'];

        // Build tags array
        $tags = [];
        if (!empty($_POST['tags'])) {
            $tags = array_map('trim', explode(',', $_POST['tags']));
            $tags = array_filter($tags);
        }

        $options = [
            'category' => $_POST['category'] ?? 'general',
            'priority' => $_POST['priority'] ?? 'medium',
            'assigned_to' => !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null,
            'assigned_group_id' => !empty($_POST['assigned_group_id']) ? (int)$_POST['assigned_group_id'] : null,
            'start_date' => $_POST['start_date'] ?? null,
            'due_date' => $_POST['due_date'] ?? null,
            'status' => $_POST['status'] ?? 'draft',
            'estimated_hours' => !empty($_POST['estimated_hours']) ? (float)$_POST['estimated_hours'] : null,
            'tags' => $tags,
            'delivery_confidence' => $_POST['delivery_confidence'] ?? 'medium',
            'risk_level' => $_POST['risk_level'] ?? 'low',
            'risk_notes' => $_POST['risk_notes'] ?? null,
        ];

        $deliverable = \Nexus\Services\DeliverabilityTrackingService::createDeliverable(
            $userId,
            $_POST['title'],
            $_POST['description'] ?? null,
            $options
        );

        if ($deliverable) {
            $_SESSION['flash_success'] = 'Deliverable created successfully!';
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/deliverability/view/' . $deliverable['id']);
        } else {
            $_SESSION['flash_error'] = 'Failed to create deliverable.';
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/deliverability/create');
        }
        exit;
    }

    /**
     * View Deliverable Details
     */
    public function deliverableView($id)
    {
        $this->checkAdmin();
        $tenantId = \Nexus\Core\TenantContext::getId();

        $deliverable = \Nexus\Models\Deliverable::findById($id);
        if (!$deliverable) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/deliverability');
            exit;
        }

        // Get milestones, comments, and history
        $milestones = \Nexus\Models\DeliverableMilestone::getByDeliverable($id);
        $milestoneStats = \Nexus\Models\DeliverableMilestone::getStats($id);
        $comments = \Nexus\Models\DeliverableComment::getByDeliverable($id);
        $history = \Nexus\Models\Deliverable::getHistory($id, 50);

        // Get users for assignment
        $users = Database::query("SELECT id, first_name, last_name FROM users WHERE tenant_id = ? ORDER BY first_name", [$tenantId])->fetchAll();

        \Nexus\Core\View::render('admin/deliverability/view', [
            'deliverable' => $deliverable,
            'milestones' => $milestones,
            'milestoneStats' => $milestoneStats,
            'comments' => $comments,
            'history' => $history,
            'users' => $users
        ]);
    }

    /**
     * Edit Deliverable Form
     */
    public function deliverableEdit($id)
    {
        $this->checkAdmin();
        $tenantId = \Nexus\Core\TenantContext::getId();

        $deliverable = \Nexus\Models\Deliverable::findById($id);
        if (!$deliverable) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/deliverability');
            exit;
        }

        // Get users and groups for assignment dropdowns
        $users = Database::query("SELECT id, first_name, last_name FROM users WHERE tenant_id = ? ORDER BY first_name", [$tenantId])->fetchAll();
        $groups = Database::query("SELECT id, name FROM groups WHERE tenant_id = ? ORDER BY name", [$tenantId])->fetchAll();

        \Nexus\Core\View::render('admin/deliverability/edit', [
            'deliverable' => $deliverable,
            'users' => $users,
            'groups' => $groups
        ]);
    }

    /**
     * Update Deliverable
     */
    public function deliverableUpdate($id)
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDie();

        $userId = $_SESSION['user_id'];

        // Build tags array
        $tags = [];
        if (!empty($_POST['tags'])) {
            $tags = array_map('trim', explode(',', $_POST['tags']));
            $tags = array_filter($tags);
        }

        $data = [
            'title' => $_POST['title'],
            'description' => $_POST['description'] ?? null,
            'category' => $_POST['category'] ?? 'general',
            'priority' => $_POST['priority'] ?? 'medium',
            'assigned_to' => !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null,
            'assigned_group_id' => !empty($_POST['assigned_group_id']) ? (int)$_POST['assigned_group_id'] : null,
            'start_date' => $_POST['start_date'] ?? null,
            'due_date' => $_POST['due_date'] ?? null,
            'status' => $_POST['status'] ?? 'draft',
            'estimated_hours' => !empty($_POST['estimated_hours']) ? (float)$_POST['estimated_hours'] : null,
            'actual_hours' => !empty($_POST['actual_hours']) ? (float)$_POST['actual_hours'] : null,
            'tags' => $tags,
            'delivery_confidence' => $_POST['delivery_confidence'] ?? 'medium',
            'risk_level' => $_POST['risk_level'] ?? 'low',
            'risk_notes' => $_POST['risk_notes'] ?? null,
        ];

        $result = \Nexus\Models\Deliverable::update($id, $data, $userId);

        if ($result) {
            $_SESSION['flash_success'] = 'Deliverable updated successfully!';
        } else {
            $_SESSION['flash_error'] = 'Failed to update deliverable.';
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/deliverability/view/' . $id);
        exit;
    }

    /**
     * Delete Deliverable
     */
    public function deliverableDelete($id)
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDie();

        $userId = $_SESSION['user_id'];
        $result = \Nexus\Models\Deliverable::delete($id, $userId);

        if ($result) {
            $_SESSION['flash_success'] = 'Deliverable deleted successfully!';
        } else {
            $_SESSION['flash_error'] = 'Failed to delete deliverable.';
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/deliverability');
        exit;
    }

    /**
     * Analytics and Reporting View
     */
    public function deliverabilityAnalytics()
    {
        $this->checkAdmin();

        // Parse filters from query params
        $filters = [];
        if (!empty($_GET['user_id'])) {
            $filters['user_id'] = (int)$_GET['user_id'];
        }
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        $analytics = \Nexus\Services\DeliverabilityTrackingService::getAnalytics($filters);
        $report = \Nexus\Services\DeliverabilityTrackingService::generateReport($filters);

        \Nexus\Core\View::render('admin/deliverability/analytics', [
            'analytics' => $analytics,
            'report' => $report,
            'filters' => $filters
        ]);
    }

    /**
     * AJAX: Update Deliverable Status
     */
    public function deliverableUpdateStatus()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDieJson();

        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $userId = $_SESSION['user_id'];

        if (!$id || !$status) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            return;
        }

        $result = \Nexus\Services\DeliverabilityTrackingService::updateDeliverableStatus($id, $status, $userId);

        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Status updated successfully' : 'Failed to update status'
        ]);
    }

    /**
     * AJAX: Complete Milestone
     */
    public function milestoneComplete()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDieJson();

        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $userId = $_SESSION['user_id'];

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid milestone ID']);
            return;
        }

        $result = \Nexus\Models\DeliverableMilestone::complete($id, $userId);

        if ($result) {
            // Recalculate parent deliverable progress
            $milestone = \Nexus\Models\DeliverableMilestone::findById($id);
            if ($milestone) {
                \Nexus\Services\DeliverabilityTrackingService::recalculateProgress($milestone['deliverable_id'], $userId);
            }
        }

        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Milestone completed successfully' : 'Failed to complete milestone'
        ]);
    }

    /**
     * AJAX: Add Comment
     */
    public function deliverableAddComment()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDieJson();

        header('Content-Type: application/json');

        $deliverableId = (int)($_POST['deliverable_id'] ?? 0);
        $commentText = trim($_POST['comment_text'] ?? '');
        $userId = $_SESSION['user_id'];

        if (!$deliverableId || !$commentText) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            return;
        }

        $comment = \Nexus\Models\DeliverableComment::create($deliverableId, $userId, $commentText);

        echo json_encode([
            'success' => $comment !== false,
            'message' => $comment ? 'Comment added successfully' : 'Failed to add comment',
            'comment' => $comment
        ]);
    }

    /**
     * WebP Image Converter - Convert images to WebP format for better performance
     */
    public function webpConverter()
    {
        $this->checkAdmin();
        require __DIR__ . '/../../views/admin/webp-converter.php';
    }

    /**
     * WebP Batch Conversion API - Process images in batches via AJAX
     */
    public function webpConvertBatch()
    {
        $this->checkAdmin();

        header('Content-Type: application/json');

        // Verify CSRF
        if (!\Nexus\Core\Csrf::verify($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Invalid security token']);
            return;
        }

        $action = $_POST['action'] ?? '';
        $converter = new \Nexus\Admin\WebPConverter();

        if ($action === 'get_pending') {
            // Return list of images that need conversion
            $pending = $converter->getPendingImages();
            echo json_encode([
                'success' => true,
                'images' => $pending,
                'total' => count($pending)
            ]);
            return;
        }

        if ($action === 'get_oversized') {
            // Return list of oversized images
            $maxDimension = (int)($_POST['max_dimension'] ?? 1920);
            $oversized = $converter->getOversizedImages($maxDimension);
            $stats = $converter->getOversizedStats($maxDimension);
            echo json_encode([
                'success' => true,
                'images' => $oversized,
                'stats' => $stats
            ]);
            return;
        }

        if ($action === 'resize_single') {
            // Resize a single oversized image
            $imagePath = $_POST['image_path'] ?? '';
            $maxDimension = (int)($_POST['max_dimension'] ?? 1920);

            if (empty($imagePath)) {
                echo json_encode(['success' => false, 'error' => 'No image path provided']);
                return;
            }

            // Security: Validate path is within allowed directories
            $baseDir = dirname(__DIR__, 2);
            $allowedPaths = [
                realpath($baseDir . '/httpdocs/assets/img'),
                realpath($baseDir . '/httpdocs/uploads')
            ];

            $realPath = realpath($imagePath);
            $isAllowed = false;
            foreach ($allowedPaths as $allowed) {
                if ($allowed && $realPath && strpos($realPath, $allowed) === 0) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                echo json_encode(['success' => false, 'error' => 'Invalid image path']);
                return;
            }

            $result = $converter->resizeImage($imagePath, $maxDimension);
            echo json_encode($result);
            return;
        }

        if ($action === 'convert_single') {
            // Convert a single image
            $imagePath = $_POST['image_path'] ?? '';

            if (empty($imagePath)) {
                echo json_encode(['success' => false, 'error' => 'No image path provided']);
                return;
            }

            // Security: Validate path is within allowed directories
            $baseDir = dirname(__DIR__, 2);
            $allowedPaths = [
                realpath($baseDir . '/httpdocs/assets/img'),
                realpath($baseDir . '/httpdocs/uploads')
            ];

            $realPath = realpath($imagePath);
            $isAllowed = false;
            foreach ($allowedPaths as $allowed) {
                if ($allowed && $realPath && strpos($realPath, $allowed) === 0) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                echo json_encode(['success' => false, 'error' => 'Invalid image path']);
                return;
            }

            $result = $converter->convertImage($imagePath);
            echo json_encode($result);
            return;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }

    /**
     * Image Optimization Settings - Configure WebP quality and auto-conversion
     */
    public function imageSettings()
    {
        $this->checkAdmin();
        require __DIR__ . '/../../views/admin/image-settings.php';
    }

    /**
     * Save Image Optimization Settings
     */
    public function saveImageSettings()
    {
        $this->checkAdmin();
        \Nexus\Core\Csrf::verifyOrDie();

        $tenantId = \Nexus\Core\TenantContext::getId();

        // Get current configuration
        $current = Database::query("SELECT configuration FROM tenants WHERE id = ?", [$tenantId])->fetchColumn();
        $configArr = json_decode($current ?? '{}', true);

        // Image optimization settings
        $imageConfig = [
            'serving_enabled' => isset($_POST['serving_enabled']),
            'lazy_loading' => isset($_POST['lazy_loading']),
            'auto_convert' => isset($_POST['auto_convert']),
            'webp_quality' => (int) ($_POST['webp_quality'] ?? 85),
        ];

        // Validate quality range
        $imageConfig['webp_quality'] = max(50, min(100, $imageConfig['webp_quality']));

        $configArr['image_optimization'] = $imageConfig;

        // Update tenant configuration
        Database::query(
            "UPDATE tenants SET configuration = ? WHERE id = ?",
            [json_encode($configArr), $tenantId]
        );

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/image-settings?saved=true');
    }
}
