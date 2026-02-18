<?php

/**
 * Project NEXUS - Master Entry Point
 * ----------------------------------
 */

// ===========================================
// MAINTENANCE MODE & ERROR HANDLING
// ===========================================

// Check for manual maintenance mode (.maintenance file)
if (file_exists(__DIR__ . '/../.maintenance')) {
    // Allow admin IPs through (add your IP here)
    $allowedIPs = ['127.0.0.1', '::1']; // Add your IP: e.g., '123.45.67.89'
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowedIPs)) {
        http_response_code(503);
        header('Retry-After: 300'); // Tell browsers to retry in 5 minutes
        include __DIR__ . '/maintenance.html';
        exit;
    }
}

// Global error handler - catch fatal errors and show maintenance page + email alert
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Log the error
        $errorMsg = sprintf(
            "[%s] FATAL ERROR on %s\nType: %d\nMessage: %s\nFile: %s\nLine: %d\nURL: %s\nIP: %s\nUser Agent: %s",
            date('Y-m-d H:i:s'),
            $_SERVER['HTTP_HOST'] ?? 'unknown',
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line'],
            $_SERVER['REQUEST_URI'] ?? 'unknown',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );

        // Write to error log
        error_log($errorMsg);

        // Send email alert using the app's Mailer (Gmail API or SMTP)
        try {
            $alertEmail = getenv('ERROR_ALERT_EMAIL') ?: getenv('ADMIN_EMAIL');
            $alertFrom = getenv('ERROR_ALERT_FROM') ?: getenv('MAIL_FROM_ADDRESS');
            if ($alertEmail) {
                $mailerFile = __DIR__ . '/../src/Core/Mailer.php';
                if (file_exists($mailerFile)) {
                    require_once $mailerFile;
                    $mailer = new \Nexus\Core\Mailer();
                    $mailer->send(
                        $alertEmail,
                        '[NEXUS ALERT] Site Error on ' . ($_SERVER['HTTP_HOST'] ?? 'project-nexus.ie'),
                        nl2br(htmlspecialchars($errorMsg)),
                        $alertFrom ?: null
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log('Failed to send error alert email: ' . $e->getMessage());
        }

        // Show maintenance page if not already sent headers
        if (!headers_sent()) {
            http_response_code(500);
            if (file_exists(__DIR__ . '/maintenance.html')) {
                include __DIR__ . '/maintenance.html';
                exit;
            }
        }
    }
});

// Set custom error handler for non-fatal errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Only email for serious errors (not notices/warnings)
    if (in_array($errno, [E_USER_ERROR, E_RECOVERABLE_ERROR])) {
        $errorMsg = sprintf(
            "[%s] ERROR on %s\nType: %d\nMessage: %s\nFile: %s\nLine: %d\nURL: %s",
            date('Y-m-d H:i:s'),
            $_SERVER['HTTP_HOST'] ?? 'unknown',
            $errno,
            $errstr,
            $errfile,
            $errline,
            $_SERVER['REQUEST_URI'] ?? 'unknown'
        );

        // Send email alert using the app's Mailer (Gmail API or SMTP)
        try {
            $alertEmail = getenv('ERROR_ALERT_EMAIL') ?: getenv('ADMIN_EMAIL');
            $alertFrom = getenv('ERROR_ALERT_FROM') ?: getenv('MAIL_FROM_ADDRESS');
            if ($alertEmail) {
                $mailerFile = __DIR__ . '/../src/Core/Mailer.php';
                if (file_exists($mailerFile)) {
                    require_once $mailerFile;
                    $mailer = new \Nexus\Core\Mailer();
                    $mailer->send(
                        $alertEmail,
                        '[NEXUS WARNING] Error on ' . ($_SERVER['HTTP_HOST'] ?? 'project-nexus.ie'),
                        nl2br(htmlspecialchars($errorMsg)),
                        $alertFrom ?: null
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log('Failed to send error alert email: ' . $e->getMessage());
        }
    }

    // Let PHP handle it normally too
    return false;
}, E_ALL);

// ===========================================
// END MAINTENANCE MODE & ERROR HANDLING
// ===========================================

// ===========================================
// CORS HANDLING FOR API ROUTES
// ===========================================
// Handle CORS early for API routes (before autoloading for performance).
// This enables React SPAs, mobile apps, and other cross-origin clients.
// Configuration via ALLOWED_ORIGINS env var (comma-separated list).
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isApiRequest = (strpos($requestUri, '/api/') === 0 || strpos($requestUri, '/api/') !== false);

if ($isApiRequest) {
    // Load CorsHelper directly (before autoloader for OPTIONS preflight speed)
    require_once __DIR__ . '/../src/Helpers/CorsHelper.php';

    // Handle OPTIONS preflight immediately (exits if OPTIONS request)
    \Nexus\Helpers\CorsHelper::handlePreflight(
        [], // Additional origins (env-based by default)
        ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-Token', 'X-Tenant-ID', 'Accept', 'Origin']
    );

    // Set CORS headers for actual requests
    \Nexus\Helpers\CorsHelper::setHeaders(
        [],
        ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-Token', 'X-Tenant-ID', 'Accept', 'Origin']
    );
}

// 0. FILE DOWNLOAD BYPASS - Removed
// Downloads now handled by standalone download.php for clean binary transfer
// The old download bypass is removed because index.php has output buffering/session
// that can corrupt binary files. Use download.php directly instead.

// 0.5 LAYOUT PERSISTENCE
// Skip session for download routes to prevent header corruption
$isDownloadRequest = (strpos($_SERVER['REQUEST_URI'] ?? '', '/download') !== false);

// Skip session for stateless API requests (Bearer token auth)
// This enables true stateless API calls for React SPAs and mobile apps
$isStatelessApiRequest = false;
if ($isApiRequest) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    // If request has Bearer token OR explicitly requests stateless auth, skip session
    $isStatelessApiRequest = (
        (stripos($authHeader, 'Bearer ') === 0) ||
        isset($_SERVER['HTTP_X_STATELESS_AUTH'])
    );
}

// 1. SMART PATH DETECTION (Live vs Local)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    // Live Server (Vendor in Home Directory - Secure)
    require_once __DIR__ . '/../vendor/autoload.php';
    $baseDir = dirname(__DIR__);
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    // Local Dev / Legacy (Vendor in Public - Less Secure)
    require_once __DIR__ . '/vendor/autoload.php';
    $baseDir = __DIR__;
} else {
    // Fallback: No Composer? Use manual class loading if needed, or die.
    // Ideally we want Composer, but for now we might rely on internal autoloader if vendor is missing.
    // Check for src/ at least.
    if (is_dir(__DIR__ . '/../src')) {
        $baseDir = dirname(__DIR__);
    } elseif (is_dir(__DIR__ . '/src')) {
        $baseDir = __DIR__;
    } else {
        die("Critical Error: Application root not found.");
    }

    // Manual Autoloader (PSR-4 simplified)
    spl_autoload_register(function ($class) use ($baseDir) {
        // Handle Nexus\ namespace -> src/
        $prefix = 'Nexus\\';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relative_class = substr($class, $len);
            $file = $baseDir . '/src/' . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }

        // Handle App\ namespace -> app/
        $prefix = 'App\\';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relative_class = substr($class, $len);
            $file = $baseDir . '/app/' . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    });
}

// 2. ENVIRONMENT LOADER
// Use the Env class for consistent loading (handles quotes, comments, no-override)
if (class_exists('\Nexus\Core\Env')) {
    \Nexus\Core\Env::load($baseDir . '/.env');
} else {
    // Fallback: Load .env manually if Env class not yet autoloaded
    $envFile = $baseDir . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && substr(trim($line), 0, 1) !== '#') {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
                    $value = substr($value, 1, -1);
                }
                // Only set if not already set (don't override system env)
                if (getenv($key) === false) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}

// 3. BOOT APPLICATION
use Nexus\Core\TenantContext;
use Nexus\Core\Router;

// Load global helper functions
$helpersFile = $baseDir . '/src/helpers.php';
if (file_exists($helpersFile)) {
    require_once $helpersFile;
}

// 1.5 SECURITY HEADERS & SESSION CONFIG
// -------------------------------------
// Prevent Clickjacking
header("X-Frame-Options: SAMEORIGIN");
// Prevent MIME sniffing
header("X-Content-Type-Options: nosniff");
// XSS Protection (legacy browsers)
header("X-XSS-Protection: 1; mode=block");
// Content Security Policy (Tightened - removed unsafe-eval)
// Note: 'unsafe-inline' still needed for inline event handlers in views
header("Content-Security-Policy: default-src 'self' https: data: blob:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; connect-src 'self' https: wss://*.pusher.com wss://ws-eu.pusher.com; img-src 'self' https: data: blob:; font-src 'self' https: data:; frame-ancestors 'self';");
// Referrer Policy
header("Referrer-Policy: strict-origin-when-cross-origin");
// HSTS - Force HTTPS for all future requests (1 year, include subdomains)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// Secure Session Settings
// Skip session for downloads and stateless API requests (Bearer token auth)
if (!$isDownloadRequest && !$isStatelessApiRequest && session_status() === PHP_SESSION_NONE) {
    // Cookie Security
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

    // Detect Capacitor/WebView mobile app requests
    // WebViews may send cookies with different origin rules, so we need SameSite=None
    // But SameSite=None requires Secure=true
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isCapacitorApp = (
        strpos($userAgent, 'Capacitor') !== false ||
        strpos($userAgent, 'nexus-mobile') !== false ||
        isset($_SERVER['HTTP_X_CAPACITOR_APP']) ||
        isset($_SERVER['HTTP_X_NEXUS_MOBILE'])
    );

    // Also detect mobile browsers (for PWA and mobile web)
    $isMobileDevice = (
        $isCapacitorApp ||
        strpos($userAgent, 'Mobile') !== false ||
        strpos($userAgent, 'Android') !== false ||
        strpos($userAgent, 'iPhone') !== false ||
        strpos($userAgent, 'iPad') !== false
    );

    // Session lifetime: Mobile gets 14 days, Desktop gets 2 hours
    // Reduced from 90 days for better security on shared/stolen devices
    if ($isMobileDevice) {
        $sessionLifetime = 1209600; // 14 days for mobile (balanced security/convenience)
        ini_set('session.gc_maxlifetime', 1209600);
    } else {
        $sessionLifetime = 7200; // 2 hours for desktop (security-focused)
        ini_set('session.gc_maxlifetime', 7200);
    }

    // For mobile WebView apps, use SameSite=None to allow cross-origin cookies
    // This is required because Capacitor WebView loads from app:// or capacitor://
    // but sends requests to https://hour-timebank.ie
    if ($isCapacitorApp && $isSecure) {
        $sameSite = 'None';
    } else {
        $sameSite = 'Lax'; // Default for web browsers
    }

    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'domain' => '', // Clean domain
        'secure' => $isSecure,
        'httponly' => true, // No JS Access
        'samesite' => $sameSite
    ]);

    session_start();

    // Store platform info in session for later use
    $_SESSION['_platform'] = $isMobileDevice ? 'mobile' : 'web';
    $_SESSION['_session_lifetime'] = $sessionLifetime;

    // Layout is always 'modern' (legacy CivicOne theme removed)
    $_SESSION['nexus_active_layout'] = 'modern';
    unset($_SESSION['nexus_layout']);
}

// Layout switching removed (only modern layout exists)

// DEBUG: Add ?debug_tenant=1 to any URL to see tenant resolution info
// Security: Only allow in development environment and for super admins
$debugTenantAllowed = false;
if (isset($_GET['debug_tenant'])) {
    $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
    $isSuperAdmin = !empty($_SESSION['is_super_admin']);
    $debugTenantAllowed = ($appEnv === 'development' || $appEnv === 'local') || $isSuperAdmin;

    if ($debugTenantAllowed) {
        echo "<div style='background:yellow; color:black; padding:20px; font-family:monospace; position:fixed; top:0; left:0; right:0; z-index:99999; border-bottom:3px solid red;'>";
        echo "<strong>DEBUG TENANT INFO (index.php)</strong><br>";
        echo "URI: " . htmlspecialchars($_SERVER['REQUEST_URI']) . "<br>";
        echo "HOST: " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'none') . "<br>";
        echo "SESSION tenant_id: " . ($_SESSION['tenant_id'] ?? 'NOT SET') . "<br>";
        echo "SESSION user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
    }
}

TenantContext::resolve();

// SEO REDIRECT MIDDLEWARE: Check for 301 redirects (with loop prevention)
\Nexus\Middleware\RedirectMiddleware::handle();

// MAINTENANCE MODE MIDDLEWARE: Block non-admin users when maintenance mode is enabled
\Nexus\Middleware\MaintenanceModeMiddleware::check();

// TENANT ISOLATION: Ensure logged-in users can only access their own tenant
// This is a CRITICAL security check - users must not access other tenants' data
//
// Behavior: When a logged-in user from tenant A visits tenant B:
// - Super admins: Can access any tenant (for platform management)
// - Regular users: Automatically logged out so they can browse tenant B anonymously
//   A flash message explains why they were logged out
if (!empty($_SESSION['user_id']) && !empty($_SESSION['tenant_id'])) {
    $currentTenantId = TenantContext::getId();
    $userTenantId = (int)$_SESSION['tenant_id'];
    $isSuperAdmin = !empty($_SESSION['is_super_admin']);

    // Super admins can access any tenant, regular users cannot
    if (!$isSuperAdmin && $currentTenantId !== $userTenantId) {
        // User is trying to access a different tenant - auto-logout approach
        // This allows users to browse other tenants anonymously

        // Get user's home tenant name for the message
        $userTenant = \Nexus\Models\Tenant::find($userTenantId);
        $homeTenantName = $userTenant['name'] ?? 'your community';

        // Preserve layout preference before destroying session
        $layoutPreference = $_SESSION['nexus_active_layout'] ?? 'modern';

        // Destroy the session to log out the user
        session_destroy();

        // Start a new session for the flash message and layout
        session_start();
        $_SESSION['nexus_active_layout'] = $layoutPreference;
        $_SESSION['flash_message'] = "You've been logged out because you're now browsing a different community. You were logged into {$homeTenantName}.";
        $_SESSION['flash_type'] = 'info';

        // Continue to the requested page (user is now anonymous)
        // No redirect needed - they can browse this tenant anonymously
    }
}

// ACTIVITY TRACKING: Update last_active_at for logged-in users
// This powers the real-time online status feature
if (!empty($_SESSION['user_id'])) {
    // Throttle updates to once per minute to avoid excessive DB writes
    $lastUpdate = $_SESSION['_last_active_update'] ?? 0;
    if (time() - $lastUpdate >= 60) {
        try {
            if (class_exists('\Nexus\Models\User')) {
                \Nexus\Models\User::updateLastActive((int)$_SESSION['user_id']);
                $_SESSION['_last_active_update'] = time();
            }
        } catch (\Throwable $e) {
            // Silently fail - column may not exist yet
        }
    }

    // A/B TEST METRIC TRACKING REMOVED - Layout A/B testing system has been obliterated
}

if (isset($_GET['debug_tenant']) && $debugTenantAllowed) {
    echo "After resolve() - basePath: '" . TenantContext::getBasePath() . "'<br>";
    echo "After resolve() - tenant ID: " . TenantContext::getId() . "<br>";
    $t = TenantContext::get();
    echo "After resolve() - tenant slug: " . ($t['slug'] ?? 'none') . "<br>";
    echo "</div>";
}

// 4. MOBILE ROUTING REMOVED (2026-01-17)
// The abandoned standalone mobile app has been deleted.
// All devices now use responsive layouts with mobile-nav-v2.
// The Capacitor WebView app wraps the responsive site directly.

// 4.5. INITIALIZE PAGE BUILDER V2
// Load all block definitions and renderers
$pageBuilderBlocks = $baseDir . '/src/PageBuilder/blocks/core-blocks.php';
if (file_exists($pageBuilderBlocks)) {
    require_once $pageBuilderBlocks;
}

// 5. LOAD ROUTES (Standard desktop routing)
$routesFile = __DIR__ . '/routes.php';
if (file_exists($routesFile)) {
    require_once $routesFile;
} else {
    die("Routes definition file not found.");
}
