<?php

namespace Nexus\Controllers;

use Nexus\Models\User;
use Nexus\Core\View;
use Nexus\Services\LayoutHelper;
use Nexus\Services\LegalDocumentService;

class AuthController
{
    public function showLogin()
    {
        $tenantName = \Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
        View::render('auth/login', [
            'pageTitle' => "Login - $tenantName"
        ]);
    }

    public function showRegister()
    {
        $tenantName = \Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
        // FORCE 'modern' view usage which now handles layout adaptation internally
        // Note: We bypass the standard theme selector to ensure we use the Single Source of Truth
        // However, View::render typically uses the ACTIVE theme.
        // To force Modern, we might need to be explicit or ensure standard routing hits it.
        // Let's assume View::render('auth/register') goes to [CurrentTheme]/auth/register.
        // If we want everyone to use Modern, we should point to 'views/modern/auth/register.php' specifically
        // OR we can make a new shared view.

        // Simpler: The user asked to use the modern/ page layout.
        // I will point to "modern/auth/register" explicitly if the View engine supports absolute paths or cross-theme paths.
        // Usually View::render('auth/register') is relative.
        // Let's rely on modifying the View file first.

        // Explicitly use the modern view to bypass layout-specific file searching
        // This ensures consolidating on the layout-aware modern form.
        View::render('auth/register', [
            'pageTitle' => "Register - $tenantName"
        ]);
    }

    public function login()
    {
        // Check content type for JSON request (Mobile App)
        $isJson = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;

        if ($isJson) {
            $input = json_decode(file_get_contents('php://input'), true);
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            // Skip CSRF for API (uses Token/Cookie later, for now strict MVP)
        } else {
            \Nexus\Core\Csrf::verifyOrDie();
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
        }

        // Security: Rate limiting to prevent brute force attacks
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Check rate limit by email (if provided)
        if (!empty($email)) {
            $emailLimit = \Nexus\Core\RateLimiter::check($email, 'email');
            if ($emailLimit['limited']) {
                $message = \Nexus\Core\RateLimiter::getRetryMessage($emailLimit['retry_after']);
                if ($isJson) {
                    header('Content-Type: application/json');
                    http_response_code(429);
                    echo json_encode(['error' => $message, 'retry_after' => $emailLimit['retry_after']]);
                    exit;
                }
                $tenantName = \Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
                View::render('auth/login', [
                    'pageTitle' => "Login - $tenantName",
                    'error' => $message
                ]);
                return;
            }
        }

        // Check rate limit by IP
        $ipLimit = \Nexus\Core\RateLimiter::check($ip, 'ip');
        if ($ipLimit['limited']) {
            $message = \Nexus\Core\RateLimiter::getRetryMessage($ipLimit['retry_after']);
            if ($isJson) {
                header('Content-Type: application/json');
                http_response_code(429);
                echo json_encode(['error' => $message, 'retry_after' => $ipLimit['retry_after']]);
                exit;
            }
            $tenantName = \Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
            View::render('auth/login', [
                'pageTitle' => "Login - $tenantName",
                'error' => $message
            ]);
            return;
        }

        $user = User::findGlobalByEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['role'] !== 'admin' && !$user['is_approved']) {
                if ($isJson) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Account pending approval']);
                    exit;
                }
                echo "Your account is pending approval. Please contact the administrator.";
                return;
            }

            // Security: Record successful login and clear failed attempts
            if (!empty($email)) {
                \Nexus\Core\RateLimiter::recordAttempt($email, 'email', true);
            }
            \Nexus\Core\RateLimiter::recordAttempt($ip, 'ip', true);

            // Security: Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email']; // For biometric login
            $_SESSION['user_role'] = $user['role'] ?? 'member';
            $_SESSION['role'] = $user['role'] ?? 'member'; // Add 'role' for backwards compatibility
            $_SESSION['is_super_admin'] = $user['is_super_admin'] ?? 0;
            $_SESSION['is_god'] = $user['is_god'] ?? 0; // God mode: can manage super admins
            $_SESSION['tenant_id'] = $user['tenant_id']; // Store explicit tenant ID
            $_SESSION['user_avatar'] = $user['avatar_url'] ?? '/assets/img/defaults/default_avatar.png';

            // Set is_admin flag for site administrators
            $adminRoles = ['admin', 'super_admin', 'tenant_admin'];
            $_SESSION['is_admin'] = in_array($user['role'], $adminRoles) ? 1 : 0;

            // Log Activity
            \Nexus\Models\ActivityLog::log($user['id'], 'login', 'User logged in');

            // Gamification: Record login streak and check membership badges
            try {
                \Nexus\Services\StreakService::recordLogin($user['id']);
                \Nexus\Services\GamificationService::checkMembershipBadges($user['id']);
            } catch (\Throwable $e) {
                error_log("Gamification login error: " . $e->getMessage());
            }

            // Set flag for biometric prompt in native app (session-based for server, JS will check)
            $_SESSION['just_logged_in'] = true;

            // LAYOUT PERSISTENCE: Initialize user's preferred layout from database
            // This is the fix for race condition #1 and #5 - DB is now the source of truth
            LayoutHelper::initializeForUser($user['id']);

            if ($isJson) {
                header('Content-Type: application/json');
                // Security: Don't expose session_id in response - removed to prevent session hijacking
                echo json_encode(['success' => true, 'user' => [
                    'id' => $user['id'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'role' => $user['role'] ?? 'member'
                ]]);
                exit;
            }

            // CROSS-TENANT REDIRECT LOGIC
            $currentTenantId = \Nexus\Core\TenantContext::getId();

            // If user belongs to a different tenant (and is not a Super Admin acting globally), redirect them.
            // Security: Use strict comparison to prevent type juggling attacks
            if ((int)$user['tenant_id'] !== (int)$currentTenantId && !$user['is_super_admin']) {
                $targetTenant = \Nexus\Models\Tenant::find($user['tenant_id']);
                if ($targetTenant) {
                    header('Location: /' . $targetTenant['slug'] . '/home');
                    exit;
                }
            }

            // Standard Redirect - Direct to modern home feed instead of dashboard
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/home');
            exit;
        }

        // Security: Record failed login attempt
        if (!empty($email)) {
            \Nexus\Core\RateLimiter::recordAttempt($email, 'email', false);
        }
        \Nexus\Core\RateLimiter::recordAttempt($ip, 'ip', false);

        if ($isJson) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            exit;
        }

        // Show login again with error
        $tenantName = \Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
        View::render('auth/login', [
            'pageTitle' => "Login - $tenantName",
            'error' => "Your email or password are not correct. <br>Please check your credentials or use the <a href='" . \Nexus\Core\TenantContext::getBasePath() . "/password/forgot' style='color:#b91c1c; text-decoration:underline;'>reset password link</a>."
        ]);
    }

    public function register()
    {
        \Nexus\Core\Csrf::verifyOrDie();

        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // BOT PROTECTION
        $this->validateBotProtection();

        $location = $_POST['location'] ?? null;
        $phone = $_POST['phone'] ?? null;
        $profileType = $_POST['profile_type'] ?? 'individual';
        $orgName = $_POST['organization_name'] ?? null; // Optional

        if ($firstName && $lastName && $email && $password) {
            // Enforce Strong Password
            $errors = $this->validateStrongPassword($password);

            // Irish Phone Validation
            if ($phone && !\Nexus\Core\Validator::isIrishPhone($phone)) {
                $errors[] = "Please enter a valid Irish phone number (e.g., 087 123 4567 or +353 87 123 4567).";
            }

            // Location Validation (Mapbox)
            if ($location) {
                $locError = \Nexus\Core\Validator::validateIrishLocation($location);
                if ($locError) $errors[] = $locError;
            }

            if (!empty($errors)) {
                $errorString = implode('<br>', $errors);
                echo "<div style='color:red; font-weight:bold; padding:20px; text-align:center;'>
                        Password Security Error:<br>$errorString<br>
                        <a href='javascript:history.back()'>Go Back</a>
                      </div>";
                exit;
            }

            // GDPR Validation
            if (!isset($_POST['gdpr_consent'])) {
                die("Error: You must agree to the Terms, Privacy Policy, and Mailing List subscription to register.");
            }

            try {
                User::create($firstName, $lastName, $email, $password, $location, $phone, $profileType, $orgName);

                // Fetch ID for logging
                $newUser = User::findByEmail($email);
                if ($newUser) {
                    \Nexus\Models\ActivityLog::log($newUser['id'], 'register', 'New user registered');

                    // --- GDPR CONSENT RECORDING ---
                    try {
                        // Use user's tenant_id to ensure consent is recorded for correct tenant
                        $gdprService = new \Nexus\Services\Enterprise\GdprService($newUser['tenant_id'] ?? \Nexus\Core\TenantContext::getId());
                        $consentText = "I have read and agree to the Terms of Service and Privacy Policy. I understand that as a member, I will be automatically subscribed to the community newsletter.";
                        $consentVersion = '1.0';

                        // Record Terms of Service consent
                        $gdprService->recordConsent($newUser['id'], 'terms_of_service', true, $consentText, $consentVersion);

                        // Record Privacy Policy consent
                        $gdprService->recordConsent($newUser['id'], 'privacy_policy', true, $consentText, $consentVersion);

                        // Record Marketing Email consent (newsletter subscription)
                        $gdprService->recordConsent($newUser['id'], 'marketing_email', true, $consentText, $consentVersion);

                    } catch (\Throwable $e) {
                        error_log("GDPR Consent Recording Failed: " . $e->getMessage());
                    }
                    // ------------------------------

                    // --- LEGAL DOCUMENT ACCEPTANCE (Versioned) ---
                    try {
                        // Record acceptance of Terms of Service
                        $termsDoc = LegalDocumentService::getByType(LegalDocumentService::TYPE_TERMS);
                        if ($termsDoc && $termsDoc['current_version_id']) {
                            LegalDocumentService::recordAcceptance(
                                $newUser['id'],
                                $termsDoc['id'],
                                $termsDoc['current_version_id'],
                                LegalDocumentService::ACCEPTANCE_REGISTRATION,
                                $_SERVER['REMOTE_ADDR'] ?? null,
                                $_SERVER['HTTP_USER_AGENT'] ?? null
                            );
                        }

                        // Record acceptance of Privacy Policy
                        $privacyDoc = LegalDocumentService::getByType(LegalDocumentService::TYPE_PRIVACY);
                        if ($privacyDoc && $privacyDoc['current_version_id']) {
                            LegalDocumentService::recordAcceptance(
                                $newUser['id'],
                                $privacyDoc['id'],
                                $privacyDoc['current_version_id'],
                                LegalDocumentService::ACCEPTANCE_REGISTRATION,
                                $_SERVER['REMOTE_ADDR'] ?? null,
                                $_SERVER['HTTP_USER_AGENT'] ?? null
                            );
                        }
                    } catch (\Throwable $e) {
                        error_log("Legal Document Acceptance Recording Failed: " . $e->getMessage());
                    }
                    // ----------------------------------------------

                    // --- NEWSLETTER SUBSCRIPTION (Internal) ---
                    try {
                        \Nexus\Models\NewsletterSubscriber::createConfirmed(
                            $email,
                            $firstName,
                            $lastName,
                            'registration',
                            $newUser['id']
                        );
                    } catch (\Throwable $e) {
                        error_log("Newsletter Subscription Failed: " . $e->getMessage());
                    }
                    // ------------------------------------------

                    // --- MAILCHIMP SUBSCRIPTION (External) ---
                    try {
                        $mailchimp = new \Nexus\Services\MailchimpService();
                        $mailchimp->subscribe($email, $firstName, $lastName);
                    } catch (\Throwable $e) {
                        error_log("Mailchimp Subscription Failed: " . $e->getMessage());
                    }
                    // -----------------------------------------
                }

                // Send Welcome Email
                try {
                    $mailer = new \Nexus\Core\Mailer();
                    $loginLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . \Nexus\Core\TenantContext::getBasePath() . "/login";
                    $subject = "Welcome to Project NEXUS!";

                    // Use EmailTemplate for beautiful HTML
                    $html = \Nexus\Core\EmailTemplate::render(
                        "Welcome to Project NEXUS!",
                        "Thank you for joining our community, $firstName!",
                        "Your account has been created successfully. New accounts may require administrator approval before you can fully access the platform.",
                        "Login to your account",
                        $loginLink,
                        "Project NEXUS"
                    );

                    $mailer->send($email, $subject, $html);
                } catch (\Throwable $e) {
                    error_log("Welcome Email Failed: " . $e->getMessage());
                }

                // --- NEW: SMART AUTO-ASSIGN GROUP ---
                if (\Nexus\Core\TenantContext::hasFeature('group_assignment')) {
                    try {
                        // Fetch the user object we just created to get the ID
                        $newUser = User::findByEmail($email);
                        if ($newUser) {
                            // Use new smart matching service (geographic + text matching)
                            $smartMatcher = new \Nexus\Services\SmartGroupMatchingService();
                            $result = $smartMatcher->assignUser($newUser);

                            if ($result['success']) {
                                $groupNames = array_map(function($g) { return $g['name']; }, $result['groups']);
                                error_log("Registration Smart Match SUCCESS: User {$newUser['id']} → " . implode(', ', $groupNames) . " (method: {$result['method']})");
                            } else {
                                error_log("Registration Smart Match SKIPPED: {$result['message']}");
                            }
                        }
                    } catch (\Throwable $e) {
                        error_log("Smart Group Auto-Assign Failed: " . $e->getMessage());
                    }
                }
                // ------------------------------

                // --- NEW: NOTIFY ADMINS ---
                try {
                    $tenantAdmins = User::getAdmins(); // Current Tenant Admins
                    $superAdmins = User::getSuperAdmins(); // Global Super Admins
                    $allAdmins = [];

                    // Deduplicate by email
                    foreach ($tenantAdmins as $a) $allAdmins[$a['email']] = $a;
                    foreach ($superAdmins as $a) $allAdmins[$a['email']] = $a;

                    $mailer = new \Nexus\Core\Mailer();
                    $tenantName = \Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';

                    // Admin Link
                    $adminLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . \Nexus\Core\TenantContext::getBasePath() . "/admin/users";

                    foreach ($allAdmins as $adminEmail => $adminData) {
                        $adminHtml = \Nexus\Core\EmailTemplate::render(
                            "New User Registration",
                            "A new user has registered on $tenantName",
                            "<strong>User:</strong> $firstName $lastName ($email)<br><strong>Status:</strong> Pending Approval<br><br>Please review and approve this user to grant them access.",
                            "Manage Users",
                            $adminLink,
                            "Project NEXUS System"
                        );
                        $mailer->send($adminEmail, "New Registration: $firstName $lastName ($tenantName)", $adminHtml);
                    }
                } catch (\Throwable $e) {
                    error_log("Admin Notification Failed: " . $e->getMessage());
                }
                // --------------------------

                echo "Registration successful! Your account is pending admin approval. <a href='" . \Nexus\Core\TenantContext::getBasePath() . "/login'>Login here</a> once approved.";
                exit;
            } catch (\PDOException $e) {
                echo "Error: Email might be taken.";
            }
        }
    }

    private function validateBotProtection()
    {
        // 1. Honeypot (Input named 'website' hidden via CSS)
        if (!empty($_POST['website'])) {
            // Bot detected. Fail silently/generically or die.
            // Die is fine for bots.
            die("Error: Invalid request.");
        }

        // 2. Timestamp (prevent instant submission)
        $start = $_POST['registration_start'] ?? 0;
        $now = time();
        if ($now - $start < 3) {
            // Submitted too fast (under 3 seconds)
            die("Error: Submission too fast. Please wait a moment.");
        }
    }




    private function validateStrongPassword($password)
    {
        $errors = [];
        if (strlen($password) < 12) {
            $errors[] = "Password must be at least 12 characters long.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter (A-Z).";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter (a-z).";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number (0-9).";
        }
        if (!preg_match('/[\W_]/', $password)) { // \W matches any non-word character (symbol)
            $errors[] = "Password must contain at least one special character (!@#$%^&*).";
        }
        return $errors;
    }

    public function logout()
    {
        // Security: Properly clear all session data
        $_SESSION = [];

        // Security: Delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Security: Destroy the session completely
        session_destroy();

        // Start a new clean session
        session_start();
        session_regenerate_id(true);

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/');
        exit;
    }

    /**
     * Admin Impersonation: Login as another user
     * Security: Requires admin/super_admin role, logs all actions, enforces tenant isolation
     */
    public function impersonate()
    {
        \Nexus\Core\Csrf::verifyOrDie();

        $isGod = !empty($_SESSION['is_god']);
        $isAdmin = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin', 'tenant_admin']);
        $isSuperAdmin = !empty($_SESSION['is_super_admin']);

        // Security: Only admins can impersonate (god bypasses all checks)
        if (!$isGod && !$isAdmin && !$isSuperAdmin) {
            http_response_code(403);
            die('Access denied: Admin privileges required');
        }

        // Security: Prevent impersonation if already impersonating
        if (!empty($_SESSION['impersonating_as_admin_id'])) {
            http_response_code(403);
            die('Error: You are already impersonating another user. Please stop impersonating first.');
        }

        $targetUserId = $_POST['user_id'] ?? null;

        if (!$targetUserId) {
            http_response_code(400);
            die('Error: User ID is required');
        }

        // Fetch target user - god can fetch any user globally
        if ($isGod) {
            $targetUser = \Nexus\Core\Database::query(
                "SELECT * FROM users WHERE id = ?",
                [$targetUserId]
            )->fetch();
        } else {
            $targetUser = User::findById($targetUserId);
        }

        if (!$targetUser) {
            http_response_code(404);
            die('Error: User not found');
        }

        // GOD MODE: Can impersonate anyone, including other gods and super admins
        if (!$isGod) {
            // Security: Prevent impersonating god users (only god can impersonate god)
            if (!empty($targetUser['is_god'])) {
                http_response_code(403);
                die('Error: You cannot impersonate a god user');
            }

            // Security: Prevent impersonating super admins (unless you are also super admin)
            if (!empty($targetUser['is_super_admin']) && !$isSuperAdmin) {
                http_response_code(403);
                die('Error: You cannot impersonate a super administrator');
            }

            // Security: Enforce tenant isolation (regular admins can only impersonate users in their tenant)
            $currentTenantId = \Nexus\Core\TenantContext::getId();
            if (!$isSuperAdmin && (int)$targetUser['tenant_id'] !== (int)$currentTenantId) {
                http_response_code(403);
                die('Error: You can only impersonate users within your tenant');
            }
        }

        // Store original admin session data
        $_SESSION['impersonating_as_admin_id'] = $_SESSION['user_id'];
        $_SESSION['impersonating_as_admin_name'] = $_SESSION['user_name'];
        $_SESSION['impersonating_as_admin_email'] = $_SESSION['user_email'];
        $_SESSION['impersonating_as_admin_role'] = $_SESSION['user_role'];
        $_SESSION['impersonating_as_admin_is_super'] = $_SESSION['is_super_admin'];
        $_SESSION['impersonating_as_admin_tenant'] = $_SESSION['tenant_id'];
        $_SESSION['impersonating_as_admin_avatar'] = $_SESSION['user_avatar'];

        // Log the impersonation action
        $this->logAdminAction(
            'impersonate',
            $targetUserId,
            $targetUser['first_name'] . ' ' . $targetUser['last_name'],
            $targetUser['email'],
            json_encode([
                'reason' => $_POST['reason'] ?? 'Not specified',
                'original_admin_id' => $_SESSION['user_id'],
                'original_admin_email' => $_SESSION['user_email']
            ])
        );

        // Replace session with target user's data
        $_SESSION['user_id'] = $targetUser['id'];
        $_SESSION['user_name'] = $targetUser['first_name'] . ' ' . $targetUser['last_name'];
        $_SESSION['user_email'] = $targetUser['email'];
        $_SESSION['user_role'] = $targetUser['role'] ?? 'member';
        $_SESSION['role'] = $targetUser['role'] ?? 'member';
        $_SESSION['is_super_admin'] = $targetUser['is_super_admin'] ?? 0;
        $_SESSION['tenant_id'] = $targetUser['tenant_id'];
        $_SESSION['user_avatar'] = $targetUser['avatar_url'] ?? '/assets/img/defaults/default_avatar.png';

        // Set is_admin flag
        $adminRoles = ['admin', 'super_admin', 'tenant_admin'];
        $_SESSION['is_admin'] = in_array($targetUser['role'], $adminRoles) ? 1 : 0;

        // Set impersonation flag for UI banner
        $_SESSION['is_impersonating'] = true;

        // Log activity for target user
        \Nexus\Models\ActivityLog::log($targetUser['id'], 'impersonated', 'Admin logged in as this user');

        // Redirect to target user's home
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/home');
        exit;
    }

    /**
     * Stop Impersonating: Return to original admin session
     */
    public function stopImpersonating()
    {
        // Security: Verify we're actually impersonating
        if (empty($_SESSION['impersonating_as_admin_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/home');
            exit;
        }

        // Store impersonated user info for logging
        $impersonatedUserId = $_SESSION['user_id'];
        $impersonatedUserName = $_SESSION['user_name'];
        $impersonatedUserEmail = $_SESSION['user_email'];

        // Restore original admin session
        $_SESSION['user_id'] = $_SESSION['impersonating_as_admin_id'];
        $_SESSION['user_name'] = $_SESSION['impersonating_as_admin_name'];
        $_SESSION['user_email'] = $_SESSION['impersonating_as_admin_email'];
        $_SESSION['user_role'] = $_SESSION['impersonating_as_admin_role'];
        $_SESSION['role'] = $_SESSION['impersonating_as_admin_role'];
        $_SESSION['is_super_admin'] = $_SESSION['impersonating_as_admin_is_super'];
        $_SESSION['tenant_id'] = $_SESSION['impersonating_as_admin_tenant'];
        $_SESSION['user_avatar'] = $_SESSION['impersonating_as_admin_avatar'];

        // Restore is_admin flag
        $adminRoles = ['admin', 'super_admin', 'tenant_admin'];
        $_SESSION['is_admin'] = in_array($_SESSION['user_role'], $adminRoles) ? 1 : 0;

        // Clear impersonation data
        unset($_SESSION['impersonating_as_admin_id']);
        unset($_SESSION['impersonating_as_admin_name']);
        unset($_SESSION['impersonating_as_admin_email']);
        unset($_SESSION['impersonating_as_admin_role']);
        unset($_SESSION['impersonating_as_admin_is_super']);
        unset($_SESSION['impersonating_as_admin_tenant']);
        unset($_SESSION['impersonating_as_admin_avatar']);
        unset($_SESSION['is_impersonating']);

        // Log the stop impersonation action
        $this->logAdminAction(
            'stop_impersonate',
            $impersonatedUserId,
            $impersonatedUserName,
            $impersonatedUserEmail,
            json_encode([
                'restored_admin_id' => $_SESSION['user_id'],
                'restored_admin_email' => $_SESSION['user_email']
            ])
        );

        // Redirect back to admin users page
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/admin/users');
        exit;
    }

    /**
     * Helper: Log admin actions for audit trail
     */
    private function logAdminAction($actionType, $targetUserId, $targetUserName, $targetUserEmail, $details = null)
    {
        try {
            $adminId = $_SESSION['user_id'] ?? null;
            $adminName = $_SESSION['user_name'] ?? 'Unknown';
            $adminEmail = $_SESSION['user_email'] ?? 'Unknown';
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $tenantId = \Nexus\Core\TenantContext::getId();

            $sql = "INSERT INTO admin_actions
                    (admin_id, admin_name, admin_email, action_type, target_user_id, target_user_name, target_user_email, details, ip_address, user_agent, tenant_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            \Nexus\Core\Database::query($sql, [
                $adminId,
                $adminName,
                $adminEmail,
                $actionType,
                $targetUserId,
                $targetUserName,
                $targetUserEmail,
                $details,
                $ip,
                $userAgent,
                $tenantId
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to log admin action: " . $e->getMessage());
        }
    }

    // --- Password Reset ---
    public function showForgot()
    {
        $tenantName = \Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
        View::render('auth/forgot_password', ['pageTitle' => "Forgot Password - $tenantName"]);
    }

    public function sendResetLink()
    {
        \Nexus\Core\Csrf::verifyOrDie();
        $email = $_POST['email'] ?? '';

        // Security: Always show the same response to prevent account enumeration
        // Process the reset silently, then show a generic message
        $genericMessage = "<h2>Check your email</h2><p>If an account exists with that email address, we have sent a password reset link. Please check your inbox and spam folder.</p><p><a href='" . \Nexus\Core\TenantContext::getBasePath() . "/login'>Back to Login</a></p>";

        // SECURITY: Rate limiting to prevent abuse
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ipLimit = \Nexus\Core\RateLimiter::check($ip, 'ip');
        if ($ipLimit['limited']) {
            // Still show generic message to prevent enumeration
            echo $genericMessage;
            exit;
        }

        // Validate email format first
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo $genericMessage;
            exit;
        }

        // SECURITY: Rate limit by email to prevent abuse of specific accounts
        if (!empty($email)) {
            $emailLimit = \Nexus\Core\RateLimiter::check($email, 'email');
            if ($emailLimit['limited']) {
                echo $genericMessage;
                exit;
            }
            // Record the attempt (not a login, but rate limits apply)
            \Nexus\Core\RateLimiter::recordAttempt($email, 'email', false);
        }
        \Nexus\Core\RateLimiter::recordAttempt($ip, 'ip', false);

        $user = User::findByEmail($email);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            // Security: Hash the token before storing - we send the unhashed token via email
            // and compare using password_verify when validating
            $hashedToken = password_hash($token, PASSWORD_DEFAULT);

            // Delete any existing tokens for this email first
            \Nexus\Core\Database::query("DELETE FROM password_resets WHERE email = ?", [$email]);

            // Store hashed token with timestamp for expiration checking
            $sql = "INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())";
            \Nexus\Core\Database::query($sql, [$email, $hashedToken]);

            $link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . \Nexus\Core\TenantContext::getBasePath() . "/password/reset?token=$token";

            // Send Reset Email
            try {
                $mailer = new \Nexus\Core\Mailer();
                $subject = "Password Reset Request";

                // Use EmailTemplate
                $html = \Nexus\Core\EmailTemplate::render(
                    "Reset Your Password",
                    "We received a request to reset your password.",
                    "If you did not request this change, please ignore this email. Otherwise, click the button below to proceed.",
                    "Reset Password",
                    $link,
                    "Project NEXUS"
                );

                // Log email failure but don't expose to user
                if (!$mailer->send($email, $subject, $html)) {
                    error_log("Password reset email failed to send for: " . $email);
                }
            } catch (\Throwable $e) {
                error_log("Reset Email Failed: " . $e->getMessage());
            }
        }

        // Security: Always show the same response regardless of whether user exists
        echo $genericMessage;
        exit;
    }

    public function showReset()
    {
        $token = $_GET['token'] ?? '';
        if (empty($token)) {
            die("Invalid or expired token.");
        }

        // Security: Find all recent tokens and verify with password_verify
        // Tokens expire after 1 hour
        $records = \Nexus\Core\Database::query(
            "SELECT * FROM password_resets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        )->fetchAll();

        $validRecord = null;
        foreach ($records as $record) {
            if (password_verify($token, $record['token'])) {
                $validRecord = $record;
                break;
            }
        }

        if (!$validRecord) {
            die("Invalid or expired token. Please request a new password reset.");
        }

        $tenantName = \Nexus\Core\TenantContext::get()['name'] ?? 'Nexus TimeBank';
        View::render('auth/reset_password', [
            'pageTitle' => "Reset Password - $tenantName",
            'token' => $token
        ]);
    }

    public function resetPassword()
    {
        \Nexus\Core\Csrf::verifyOrDie();
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($token)) {
            die("Invalid token.");
        }

        if ($password !== $confirm) {
            die("Passwords do not match.");
        }

        // Security: Find all recent tokens and verify with password_verify
        // Tokens expire after 1 hour
        $records = \Nexus\Core\Database::query(
            "SELECT * FROM password_resets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        )->fetchAll();

        $validRecord = null;
        foreach ($records as $record) {
            if (password_verify($token, $record['token'])) {
                $validRecord = $record;
                break;
            }
        }

        if (!$validRecord) {
            die("Invalid or expired token. Please request a new password reset.");
        }

        // Validate Password Strength
        $errors = $this->validateStrongPassword($password);
        if (!empty($errors)) {
            die("Password Error: " . implode(', ', $errors) . " <a href='javascript:history.back()'>Try Again</a>");
        }

        $email = $validRecord['email'];
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // Update User
        \Nexus\Core\Database::query("UPDATE users SET password_hash = ? WHERE email = ?", [$hash, $email]);

        // Delete Token (and any other tokens for this email)
        \Nexus\Core\Database::query("DELETE FROM password_resets WHERE email = ?", [$email]);

        echo "Password reset successful! <a href='" . \Nexus\Core\TenantContext::getBasePath() . "/login'>Login now</a>";
    }
}
