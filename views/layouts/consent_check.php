<?php
/**
 * GDPR Consent Version Check
 *
 * Included at the top of layout headers to ensure users have accepted
 * the current version of all required consents (Terms of Service, Privacy Policy).
 *
 * Flow:
 * 1. If user is logged in
 * 2. Check if they have accepted the current version of all required consents
 * 3. If not, redirect to /consent-required page
 *
 * Exemptions:
 * - /consent-required (the re-consent page itself)
 * - /consent/accept (the acceptance endpoint)
 * - /consent/decline
 * - /logout
 * - /assets/
 * - /api/
 * - /login, /register
 * - /terms, /privacy (users need to view these)
 * - Super admins and admins (to prevent lockout)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$reqUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($reqUri, PHP_URL_PATH);

if (isset($_SESSION['user_id'])) {
    // Allowed paths that bypass consent check
    $allowedPaths = [
        '/consent-required',
        '/consent/accept',
        '/consent/decline',
        '/logout',
        '/assets/',
        '/api/',
        '/login',
        '/register',
        '/terms',
        '/privacy',
        '/legal',
        '/onboarding', // Allow onboarding to complete first
    ];

    $isAllowed = false;
    foreach ($allowedPaths as $allowed) {
        if (strpos($path, $allowed) !== false) {
            $isAllowed = true;
            break;
        }
    }

    if (!$isAllowed) {
        try {
            // Check for outdated consents using GdprService
            if (class_exists('\Nexus\Services\Enterprise\GdprService')) {
                // Use user's tenant_id from session, not default
                $userTenantId = $_SESSION['tenant_id'] ?? null;
                $gdprService = new \Nexus\Services\Enterprise\GdprService($userTenantId);
                $outdatedConsents = $gdprService->getOutdatedRequiredConsents($_SESSION['user_id']);

                if (!empty($outdatedConsents)) {
                    // Check if user is super admin (exempt from lockout to prevent system inaccessibility)
                    // Regular admins MUST accept updated terms like everyone else
                    if (class_exists('\Nexus\Models\User')) {
                        $user = \Nexus\Models\User::findById($_SESSION['user_id']);
                        $isSuperAdmin = !empty($user['is_super_admin']);

                        if (!$isSuperAdmin) {
                            // Store outdated consents in session for the re-consent page
                            $_SESSION['_pending_consents'] = $outdatedConsents;

                            // Log consent redirect for monitoring
                            error_log("[CONSENT CHECK] User ID {$_SESSION['user_id']} redirected - " . count($outdatedConsents) . " outdated consent(s)");

                            $base = '';
                            if (class_exists('\Nexus\Core\TenantContext')) {
                                $base = \Nexus\Core\TenantContext::getBasePath();
                            }

                            header("Location: " . $base . "/consent-required");
                            exit;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Log error but don't lock out users
            error_log("Consent check error: " . $e->getMessage());
        }
    }
}
