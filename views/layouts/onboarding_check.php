<?php
// Onboarding Lockout Check
// Included at the top of layout headers to force new users to complete their profile.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$reqUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($reqUri, PHP_URL_PATH);

if (isset($_SESSION['user_id'])) {

    // Allow Onboarding, Logout, and Assets
    // Also allow "store" action (POST) which might be same path or sub-path
    if (
        strpos($path, '/onboarding') === false &&
        strpos($path, '/logout') === false &&
        strpos($path, '/assets/') === false &&
        strpos($path, '/api/') === false
    ) {

        // 2. Check User Profile Completeness
        // We use the User model directly. 
        // Note: This adds a DB query to every page load for logged-in users. 
        // In a high-traffic production env, this should be cached in SESSION.

        try {
            if (class_exists('\Nexus\Models\User')) {
                $obUser = \Nexus\Models\User::findById($_SESSION['user_id']);

                if ($obUser) {
                    // EMERGENCY BYPASS: Super admins and admins are exempt from onboarding
                    $isSuperAdmin = !empty($obUser['is_super_admin']);
                    $isAdmin = ($obUser['role'] ?? '') === 'admin';

                    if ($isSuperAdmin || $isAdmin) {
                        // Admins can access the site without completing onboarding
                        // This prevents admin lockout scenarios
                        // Log skip for audit trail
                        // error_log("Onboarding check skipped for admin user ID: {$obUser['id']}");
                    } else {
                        // Criteria: Must have BOTH Bio AND Avatar
                        // Phone is optional
                        // (Strict enforcement to ensure quality profiles)

                        $hasBio = !empty(trim($obUser['bio'] ?? ''));
                        $hasAvatar = !empty($obUser['avatar_url']);

                        // FORCE: If Bio OR Avatar is missing -> Redirect.
                        if (!$hasBio || !$hasAvatar) {
                            // Log onboarding redirect for monitoring
                            error_log("[ONBOARDING CHECK] User ID {$obUser['id']} redirected - Bio: " . ($hasBio ? 'YES' : 'NO') . ", Avatar: " . ($hasAvatar ? 'YES' : 'NO'));

                            $base = '';
                            if (class_exists('\Nexus\Core\TenantContext')) {
                                $base = \Nexus\Core\TenantContext::getBasePath();
                            }

                            // Redirect to onboarding
                            header("Location: " . $base . "/onboarding");
                            exit;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // If DB fails, don't lock out the user, just log it or ignore.
        }
    } else {
        // Log skip for verification (optional, can be noisy)
        // error_log($logMsg . " -> SKIPPED (Allowed Path)\n", 3, $logFile);
    }
}
