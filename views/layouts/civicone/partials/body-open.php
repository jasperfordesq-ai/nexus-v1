<body class="govuk-template__body civicone civicone--govuk nexus-skin-civicone <?= $skinClass ?> <?= $isHome ? 'nexus-home-page' : '' ?> <?= isset($_SESSION['user_id']) ? 'logged-in' : '' ?> <?= ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])) ? 'user-is-admin' : '' ?> <?= $bodyClass ?? '' ?>">

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

    <!--
        NOTE: CivicOne page-specific CSS moved to assets-css.php (in <head>)
        to prevent FOUC (Flash of Unstyled Content) - 2026-01-25
        See: docs/VISUAL_FLASH_FIX_PLAN.md
    -->
