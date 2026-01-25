<body class="govuk-template__body civicone nexus-skin-civicone <?= $skinClass ?> <?= $isHome ? 'nexus-home-page' : '' ?> <?= isset($_SESSION['user_id']) ? 'logged-in' : '' ?> <?= ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])) ? 'user-is-admin' : '' ?> <?= $bodyClass ?? '' ?>">

    <!-- Layout switch link moved to utility bar - cleaner approach -->

    <?php if (!empty($bodyClass) && (strpos($bodyClass, 'no-ptr') !== false || strpos($bodyClass, 'chat-page') !== false || strpos($bodyClass, 'messages-fullscreen') !== false)): ?>
        <script>
            // CRITICAL: Prevent PTR before any other scripts load (for messages and chat pages)
            (function() {
                document.documentElement.classList.add('no-ptr');
                document.body.classList.add('no-ptr');
                // Apply chat-specific classes only for chat pages
                if (document.body.classList.contains('chat-page') || document.body.classList.contains('chat-fullscreen')) {
                    document.documentElement.classList.add('chat-page');
                }
                // Disable overscroll on html/body immediately
                var s = 'overflow:hidden!important;overscroll-behavior:none!important;position:fixed!important;inset:0!important;';
                document.documentElement.style.cssText += s;
                document.body.style.cssText += s;
                // Intercept and remove any PTR indicators that might be created
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(m) {
                        m.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1 && (node.classList.contains('nexus-ptr-indicator') || node.classList.contains('ptr-indicator'))) {
                                node.remove();
                            }
                        });
                    });
                });
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
                // Stop observing after 5 seconds (scripts should be loaded by then)
                setTimeout(function() {
                    observer.disconnect();
                }, 5000);
            })();
        </script>
    <?php endif; ?>

    <!-- Layout Preview Banner (if in preview mode) -->
    <?php require __DIR__ . '/preview-banner.php'; ?>

    <!-- LEGENDARY: Keyboard Shortcuts for Power Users -->
    <?php require __DIR__ . '/keyboard-shortcuts.php'; ?>

    <!-- Wrong Turn Popup - REMOVED: Banner at top is sufficient -->
    <!-- The purple "Accessible Layout (Beta)" banner already provides switch-back functionality -->
    <?php


    // Path Resolution
    $basePath = '/';
    if (class_exists('\Nexus\Core\TenantContext')) {
        $basePath = \Nexus\Core\TenantContext::getBasePath();
        if (empty($basePath)) $basePath = '/';
    }
    $basePath = rtrim($basePath, '/') . '/';
    ?>
    <!-- Font loading moved to head with preload -->

    <!-- Layout Switch Helper (prevents visual glitches) -->
    <script defer src="/assets/js/layout-switch-helper.min.js?v=<?= $cssVersion ?>"></script>

    <script>
        const NEXUS_BASE = "<?= \Nexus\Core\TenantContext::getBasePath() ?>";
        const mtBasePath = NEXUS_BASE; // Compatibility alias
    </script>

    <!-- CivicOne Header CSS (Hero, Navigation, Utility Bar - WCAG 2.1 AA) -->
    <link rel="stylesheet" href="/assets/css/civicone-header.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Footer CSS (Footer content and styles - WCAG 2.1 AA) -->
    <link rel="stylesheet" href="/assets/css/civicone-footer.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Events CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-events.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Profile CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-profile.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Groups CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-groups.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Groups Utilities (button alignment, spacing) -->
    <link rel="stylesheet" href="/assets/css/civicone-groups-utilities.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Utilities (shared utility classes, WCAG fixes) -->
    <link rel="stylesheet" href="/assets/css/civicone-utilities.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Volunteering CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-volunteering.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Mini Modules CSS (Polls, Goals, Resources - WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-mini-modules.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Messages & Notifications CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-messages.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Wallet & Insights CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-wallet.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Blog CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-blog.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Help & Settings CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-help.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Matches & Connections CSS (WCAG 2.1 AA 2026-01-19) -->
    <link rel="stylesheet" href="/assets/css/civicone-matches.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Federation CSS (WCAG 2.1 AA 2026-01-20) -->
    <link rel="stylesheet" href="/assets/css/civicone-federation.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Federation Shell - MOJ & GOV.UK Patterns (WCAG 2.1 AA 2026-01-20) -->
    <link rel="stylesheet" href="/assets/css/civicone-federation-shell.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Members Directory - GOV.UK Pattern (WCAG 2.1 AA 2026-01-20) -->
    <link rel="stylesheet" href="/assets/css/civicone-members-directory.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Listings Directory - GOV.UK Pattern (WCAG 2.1 AA 2026-01-20) -->
    <link rel="stylesheet" href="/assets/css/civicone-listings-directory.css?v=<?= $cssVersion ?>">
    <!-- CivicOne Feed/Activity Stream - Template F (WCAG 2.1 AA 2026-01-20) -->
    <link rel="stylesheet" href="/assets/css/civicone-feed.css?v=<?= $cssVersion ?>">
