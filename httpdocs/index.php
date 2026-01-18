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

        // Send email alert (non-blocking)
        $to = 'jasper.esq@gmail.com';
        $subject = '[NEXUS ALERT] Site Error on ' . ($_SERVER['HTTP_HOST'] ?? 'project-nexus.ie');
        $headers = "From: alerts@project-nexus.ie\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($to, $subject, $errorMsg, $headers);

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

        $to = 'jasper.esq@gmail.com';
        $subject = '[NEXUS WARNING] Error on ' . ($_SERVER['HTTP_HOST'] ?? 'project-nexus.ie');
        $headers = "From: alerts@project-nexus.ie\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($to, $subject, $errorMsg, $headers);
    }

    // Let PHP handle it normally too
    return false;
}, E_ALL);

// ===========================================
// END MAINTENANCE MODE & ERROR HANDLING
// ===========================================

// 0. FILE DOWNLOAD BYPASS - Removed
// Downloads now handled by standalone download.php for clean binary transfer
// The old download bypass is removed because index.php has output buffering/session
// that can corrupt binary files. Use download.php directly instead.

// 0.5 LAYOUT PERSISTENCE
// Skip session for download routes to prevent header corruption
$isDownloadRequest = (strpos($_SERVER['REQUEST_URI'] ?? '', '/download') !== false);

// --- DEBUG TRAP: CONFIRM SERVER REACHABILITY ---
// Remove this after confirmation
// if (isset($_GET['mobile_debug'])) {
//    die('<div style="background:red; color:white; padding:50px; text-align:center; font-size:24px; position:fixed; top:0; left:0; width:100%; height:100%; z-index:99999;"><h1>SERVER CONFIRMED</h1><p>The code is live.</p></div>');
// }
// -----------------------------------------------

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
        $prefix = 'Nexus\\';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relative_class = substr($class, $len);
        $file = $baseDir . '/src/' . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

// 2. ROBUST ENVIRONMENT LOADER
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
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
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
// Content Security Policy (Legacy Compatible + Pusher WebSocket)
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data: blob:; connect-src 'self' https: wss://*.pusher.com wss://ws-eu.pusher.com; img-src 'self' https: data: blob:; font-src 'self' https: data:;");
// Referrer Policy
header("Referrer-Policy: strict-origin-when-cross-origin");

// Secure Session Settings
if (!$isDownloadRequest && session_status() === PHP_SESSION_NONE) {
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

    // Session lifetime: Mobile gets 90 days (like Facebook/Instagram), Desktop gets 2 hours
    // This creates an "install and forget" experience for mobile users
    if ($isMobileDevice) {
        $sessionLifetime = 7776000; // 90 days for mobile (persistent login - matches native apps)
        ini_set('session.gc_maxlifetime', 7776000);
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

    // LAYOUT INITIALIZATION - FIXED RACE CONDITIONS
    // Uses ONLY 'nexus_active_layout' as the single source of truth (session key)
    // The LayoutHelper class handles DB persistence for logged-in users
    //
    // Priority order:
    // 1. If nexus_active_layout is set in session, use it (already validated)
    // 2. For logged-in users, LayoutHelper will fetch from DB
    // 3. Default to 'modern' for new/anonymous sessions
    //
    // CLEANUP: Migrate any legacy 'nexus_layout' key to the unified key
    if (isset($_SESSION['nexus_layout']) && !isset($_SESSION['nexus_active_layout'])) {
        // Migrate legacy key to unified key
        $_SESSION['nexus_active_layout'] = $_SESSION['nexus_layout'];
    }
    // Always remove the legacy key to prevent dual-key conflicts
    unset($_SESSION['nexus_layout']);

    // Set default layout ONLY if neither key was set (truly new session)
    if (!isset($_SESSION['nexus_active_layout'])) {
        $_SESSION['nexus_active_layout'] = 'modern';
    }
}

// --- LAYOUT QUERY PARAMETER PROCESSING ---
// Process ?layout= and ?preview_layout= query parameters
// This allows URL-based layout switching (e.g., ?layout=civicone)
//
// FIXED: Uses ONLY 'nexus_active_layout' as the unified session key
// For logged-in users with ?layout=, we save to DB after autoloader is ready
if (session_status() !== PHP_SESSION_NONE) {
    $shouldRedirect = false;

    // Handle reset to default (Modern) - use ?reset_layout=1
    if (isset($_GET['reset_layout']) && $_GET['reset_layout'] === '1') {
        $_SESSION['nexus_active_layout'] = 'modern';
        unset($_SESSION['is_preview_mode']);
        // Flag to save to DB after autoloader is ready
        if (!empty($_SESSION['user_id'])) {
            $_SESSION['_pending_layout_save'] = 'modern';
        }
        $shouldRedirect = true;
    }
    // Handle preview layout (temporary, doesn't save to DB)
    elseif (isset($_GET['preview_layout'])) {
        $previewLayout = preg_replace('/[^a-z-]/', '', strtolower($_GET['preview_layout']));
        if (in_array($previewLayout, ['modern', 'civicone'], true)) {
            $_SESSION['nexus_active_layout'] = $previewLayout;
            $_SESSION['is_preview_mode'] = true;
            // Preview mode does NOT persist to DB
            $shouldRedirect = true;
        }
    }
    // Handle permanent layout switch via URL parameter
    elseif (isset($_GET['layout'])) {
        $requestedLayout = preg_replace('/[^a-z-]/', '', strtolower($_GET['layout']));
        if (in_array($requestedLayout, ['modern', 'civicone'], true)) {
            // Only switch if different from current
            $currentLayout = $_SESSION['nexus_active_layout'] ?? 'modern';
            if ($requestedLayout !== $currentLayout) {
                $_SESSION['nexus_active_layout'] = $requestedLayout;
                unset($_SESSION['is_preview_mode']);

                // Flag to save to DB after autoloader is ready (for logged-in users)
                if (!empty($_SESSION['user_id'])) {
                    $_SESSION['_pending_layout_save'] = $requestedLayout;
                }
                $shouldRedirect = true;
            }
        }
    }

    // Redirect to clean URL after layout switch to prevent re-processing and ensure clean CSS load
    if ($shouldRedirect) {
        // Build clean URL without layout parameters
        $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
        $queryParams = $_GET;

        // Remove ALL layout-related parameters to prevent redirect loops
        unset($queryParams['layout']);
        unset($queryParams['preview_layout']);
        unset($queryParams['reset_layout']);
        unset($queryParams['_refresh']); // Remove old refresh params

        // Build clean query string
        if (!empty($queryParams)) {
            $cleanUrl .= '?' . http_build_query($queryParams);
        }

        // Set cache control headers to prevent browser caching during layout switch
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');

        header('Location: ' . $cleanUrl);
        exit;
    }

    // Clean up refresh parameter from URL after layout switch (client-side via pushState)
    if (isset($_GET['_refresh'])) {
        // This will be cleaned up by JavaScript in the footer
        $_SESSION['_cleanup_refresh_param'] = true;
    }
}
// -----------------------------

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

// LAYOUT PERSISTENCE: Save pending layout changes to database
// This handles ?layout= URL parameter saves for logged-in users
// The flag was set before autoloader was ready, now we can use LayoutHelper
if (!empty($_SESSION['_pending_layout_save']) && !empty($_SESSION['user_id'])) {
    try {
        $pendingLayout = $_SESSION['_pending_layout_save'];
        \Nexus\Services\LayoutHelper::saveToDatabase((int)$_SESSION['user_id'], $pendingLayout);
        unset($_SESSION['_pending_layout_save']);
    } catch (\Throwable $e) {
        error_log("Failed to save pending layout to DB: " . $e->getMessage());
        unset($_SESSION['_pending_layout_save']);
    }
}

// SEO REDIRECT MIDDLEWARE: Check for 301 redirects (with loop prevention)
\Nexus\Middleware\RedirectMiddleware::handle();

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
