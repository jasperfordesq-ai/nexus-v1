<?php
// Modern Layout Header - 3.4 Unified Fix (No External JS Dependency)

// ============================================
// NO-CACHE HEADERS FOR THEME SWITCHING
// Prevents browser from serving stale cached pages when theme changes
// ============================================
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Generate a dynamic CSS version for cache busting
// Uses deployment version file to force cache refresh on all users after deploy
$deploymentVersion = file_exists(__DIR__ . '/../../config/deployment-version.php')
    ? require __DIR__ . '/../../config/deployment-version.php'
    : ['version' => time()];
$cssVersionTimestamp = $deploymentVersion['version'] ?? time();

require_once __DIR__ . '/../onboarding_check.php';
require_once __DIR__ . '/../consent_check.php';
$mode = $_COOKIE['nexus_mode'] ?? 'dark';
$hTitle = $hero_title ?? $pageTitle ?? 'Project NEXUS';
$hSubtitle = $hero_subtitle ?? $pageSubtitle ?? 'Welcome to the future.';
$hGradient = $hero_gradient ?? 'htb-hero-gradient-brand';
$hType = $hero_type ?? 'Platform';

// --- STRICT HOME DETECTION ---
$isHome = false;
$reqUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($reqUri, PHP_URL_PATH);
$normPath = rtrim($parsedPath, '/');
$normBase = '';
$assetBase = ''; // Assets always at root - subdirectory paths are virtual routes only
if (class_exists('\Nexus\Core\TenantContext')) {
    $normBase = rtrim(\Nexus\Core\TenantContext::getBasePath(), '/');
    // Assets stay at root path '/' since /hour-timebank/ is a virtual route, not a real directory
    // The actual files are in httpdocs/assets/ which is accessible at /assets/
    $assetBase = '';
    if ($normPath === '' || $normPath === '/' || $normPath === '/home' || $normPath === $normBase || $normPath === $normBase . '/home') {
        $isHome = true;
    }
}


// Fail-safe SEO - Load global settings from database first
try {
    if (class_exists('\Nexus\Core\SEO')) {
        // Load global SEO defaults from database
        \Nexus\Core\SEO::load('global');
        // Override with page-specific values if set
        if (isset($hTitle)) \Nexus\Core\SEO::setTitle($hTitle);
        if (isset($hSubtitle)) \Nexus\Core\SEO::setDescription($hSubtitle);
    }
} catch (\Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $mode ?>" data-layout="modern">

<head>
    <meta charset="UTF-8">
    <!-- Design Tokens MUST load first - CSS variables used by all other stylesheets -->
    <!-- NOTE: Using non-minified - minified causes visual problems (2026-01-25) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/design-tokens.css?v=<?= $cssVersionTimestamp ?>">
    <!-- Critical scroll/layout styles - loaded second to prevent FOUC -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/nexus-header-extracted.css?v=<?= $cssVersionTimestamp ?>">
    <meta name="csrf-token" content="<?= \Nexus\Core\Csrf::generate() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="<?= $mode === 'dark' ? 'dark' : 'light' ?>">
    <?= \Nexus\Core\SEO::render() ?>

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NEXUS">
    <link rel="apple-touch-icon" href="/assets/images/pwa/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/images/pwa/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/images/pwa/icon-512x512.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="NEXUS">
    <meta name="msapplication-TileColor" content="#6366f1">

    <!-- Performance: Resource Hints for Critical Resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://api.mapbox.com">

    <!-- Preload Critical CSS (above-the-fold) - Using non-minified for stability -->
    <!-- Note: design-tokens.css already loaded sync above, no preload needed -->
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/nexus-phoenix.css?v=<?= $cssVersionTimestamp ?>">
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/bundles/core.css?v=<?= $cssVersionTimestamp ?>">
    <!-- FOUC FIX (2026-01-27): Preload async bundles that style above-fold navigation/buttons -->
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/bundles/components-navigation.css?v=<?= $cssVersionTimestamp ?>">
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/bundles/components-buttons.css?v=<?= $cssVersionTimestamp ?>">

    <!-- PHASE 8 FOUC FIX (2026-01-27): Load CRITICAL page CSS as real stylesheets in <head>
         For blog/auth routes, preload is NOT enough - CSS must be applied before first paint.
         These routes load their primary CSS here; page-css-loader.php skips them to avoid duplicates. -->
<?php
// Track which CSS files are loaded in <head> to prevent duplicates in page-css-loader.php
$GLOBALS['css_already_in_head'] = [];

// Blog index: /news, /blog (with tenant prefix variants)
$isBlogIndex = $normPath === '/news' || $normPath === '/blog' || preg_match('/\/(news|blog)$/', $normPath);
// Blog show: /news/{slug}, /blog/{slug}
$isBlogShow = preg_match('/\/(news|blog)\/[^\/]+$/', $normPath) && !preg_match('/\/(news|blog)\/(create|edit)/', $normPath);
// Auth pages: /login, /register, /password
$isAuthPage = preg_match('/\/(login|register|password)/', $normPath);

if ($isBlogIndex):
    $GLOBALS['css_already_in_head'][] = 'blog-index.css';
    $GLOBALS['css_already_in_head'][] = 'utilities-polish.css';
    $GLOBALS['css_already_in_head'][] = 'components-navigation.css';
    $GLOBALS['css_already_in_head'][] = 'components-buttons.css';
?>
    <!-- PHASE 11: Blog index CSS in <head> for content-first load (no skeleton swap) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/blog-index.css?v=<?= $cssVersionTimestamp ?>">
    <!-- utilities-polish.css sync for consistent styling -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/utilities-polish.css?v=<?= $cssVersionTimestamp ?>">
    <!-- PHASE 15B: Sync nav+button bundles for blog to eliminate header/button snap -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-navigation.css?v=<?= $cssVersionTimestamp ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-buttons.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif ($isBlogShow):
    $GLOBALS['css_already_in_head'][] = 'blog-show.css';
    $GLOBALS['css_already_in_head'][] = 'components-navigation.css';
    $GLOBALS['css_already_in_head'][] = 'components-buttons.css';
?>
    <!-- PHASE 8: Blog show CSS in <head> -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/blog-show.css?v=<?= $cssVersionTimestamp ?>">
    <!-- PHASE 15B: Sync nav+button bundles for blog to eliminate header/button snap -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-navigation.css?v=<?= $cssVersionTimestamp ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-buttons.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif ($isAuthPage):
    $GLOBALS['css_already_in_head'][] = 'auth.css';
?>
    <!-- PHASE 8: Auth CSS in <head> -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/auth.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif ($isHome): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/nexus-home.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif (strpos($normPath, '/dashboard') !== false): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/dashboard.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif (preg_match('/\/profile\/[^\/]+$/', $normPath) && strpos($normPath, '/edit') === false): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/profile-holographic.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif ($normPath === '/events' || preg_match('/\/events$/', $normPath)): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/events-index.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif ($normPath === '/groups' || preg_match('/\/groups$/', $normPath) || preg_match('/\/groups\/\d+$/', $normPath)): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/groups-show.css?v=<?= $cssVersionTimestamp ?>">
<?php endif; ?>

    <!-- Preload JavaScript (critical for interactivity) -->
    <link rel="preload" as="script" href="/assets/js/mobile-interactions.js?v=<?= $cssVersionTimestamp ?>">
    <link rel="preload" as="script" href="/assets/js/mega-menu.js?v=<?= $cssVersionTimestamp ?>">

    <!-- Critical CSS Inline (Lighthouse: Save 660ms) -->
    <?php include __DIR__ . '/critical-css.php'; ?>

    <!-- ==========================================
         CONSOLIDATED CSS LOADER
         See partials/css-loader.php for categories
         ========================================== -->
    <?php require __DIR__ . '/partials/css-loader.php'; ?>

    <!-- Font Awesome -->
<?php if ($isBlogIndex || $isBlogShow || $isAuthPage): ?>
    <!-- PHASE 13 (2026-01-27): Sync FA for blog/auth routes to eliminate icon pop-in -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
<?php else: ?>
    <!-- Async FA for other routes (non-blocking) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous"></noscript>
<?php endif; ?>

    <!-- Google Fonts (Roboto) -->
<?php if ($isBlogIndex || $isBlogShow || $isAuthPage): ?>
    <!-- PHASE 14 (2026-01-27): Sync Roboto for blog/auth routes to eliminate font swap -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
<?php else: ?>
    <!-- Async Roboto for other routes (non-blocking) -->
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet"></noscript>
<?php endif; ?>

    <?php
    // PRIORITY: Load page-specific CSS if needed
    if (isset($additionalCSS) && !empty($additionalCSS)) {
        echo "<!-- Page-Specific CSS (Priority Load) -->\n";
        echo $additionalCSS . "\n";
    }
    ?>

    <!-- PHASE 12 (2026-01-27): nexus-instant-load.js DISABLED
         Root cause analysis (Phase 11 audit) identified this script as the PRIMARY cause of FOUC.
         The script artificially hides body with opacity:0, then fades in after CSS check.
         With Phases 8-11 CSS optimizations, sync CSS now loads before first paint - hiding is counterproductive.
         To re-enable: uncomment the script tag below.
    -->
    <!-- <script defer src="/assets/js/nexus-instant-load.min.js?v=<?= $cssVersionTimestamp ?>"></script> -->
    <!-- Layout Switch Helper (prevents visual glitches) -->
    <script defer src="/assets/js/layout-switch-helper.min.js?v=<?= $cssVersionTimestamp ?>"></script>
    <!-- Mobile Interactions (ripple effects, haptic feedback, loading states) -->
    <script defer src="/assets/js/mobile-interactions.js?v=<?= $cssVersionTimestamp ?>"></script>

    <!-- Cookie Consent Library (EU compliance) -->
    <script src="/assets/js/cookie-consent.js?v=<?= $cssVersionTimestamp ?>"></script>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/cookie-banner.css?v=<?= $cssVersionTimestamp ?>">

    <!-- Global NEXUS_BASE for AJAX calls -->
    <script>const NEXUS_BASE = "<?= rtrim(\Nexus\Core\TenantContext::getBasePath(), '/') ?>";</script>
    <!-- Header Scripts (error trap, offline banner, bfcache cleanup) -->
    <script src="/assets/js/header-scripts.js?v=<?= $cssVersionTimestamp ?>"></script>
    <!-- Mega Menu (accessible navigation with keyboard support) -->
    <script defer src="/assets/js/mega-menu.js?v=<?= $cssVersionTimestamp ?>"></script>

</head>

<body class="nexus-skin-modern <?= $isHome ? 'nexus-home-page' : '' ?> <?= isset($_SESSION['user_id']) ? 'logged-in' : '' ?> <?= ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])) ? 'user-is-admin' : '' ?> <?= $bodyClass ?? '' ?>">

    <?php if (!empty($bodyClass) && (strpos($bodyClass, 'no-ptr') !== false || strpos($bodyClass, 'chat-page') !== false || strpos($bodyClass, 'messages-fullscreen') !== false)): ?>
        <!-- PTR Prevention - must load immediately for chat/messages pages -->
        <script src="/assets/js/ptr-prevention.js?v=<?= $cssVersionTimestamp ?>"></script>
    <?php endif; ?>

    <!-- Skip Link and Layout Banner -->
    <?php require __DIR__ . '/partials/skip-link-and-banner.php'; ?>

    <!-- Layout Preview Banner (if in preview mode) -->
    <?php require __DIR__ . '/partials/preview-banner.php'; ?>

    <!-- LEGENDARY: Keyboard Shortcuts for Power Users -->
    <?php require __DIR__ . '/partials/keyboard-shortcuts.php'; ?>

    <?php if (empty($hideUtilityBar)): ?>
        <?php require __DIR__ . '/partials/utility-bar.php'; ?>
        <?php require __DIR__ . '/partials/navbar.php'; ?>
    <?php endif; ?>

    <!-- Admin Impersonation Banner -->
    <?php require __DIR__ . '/../../modern/partials/impersonation-banner.php'; ?>

    <!-- Cookie Consent Banner (EU compliance) -->
    <?php require __DIR__ . '/../../modern/partials/cookie-banner.php'; ?>

    <!-- Main Content Area (for skip-link target) -->
    <main id="main-content" role="main">

        <!-- Modern Header Behavior (Extracted to external file) - defer to avoid render blocking -->
        <script src="/assets/js/modern-header-behavior.min.js?v=<?= $cssVersionTimestamp ?>" defer></script>

        <!-- Hero section removed for cleaner mobile experience -->
        <!-- Notification scripts now loaded in footer.php with Pusher WebSocket support -->