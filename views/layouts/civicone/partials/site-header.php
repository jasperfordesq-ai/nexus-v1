    <!-- CivicOne Site Header - 4-Layer Structure (GOV.UK Pattern) -->
    <!-- See: docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md Section 9A -->
    <!--
        MANDATORY Layer Order:
        1. Skip link (in skip-link-and-banner.php - first focusable)
        2. Phase banner (optional - not implemented yet)
        3. Utility bar (platform, contrast, auth)
        4. PRIMARY NAVIGATION (ONE service navigation system)
        5. Search (integrated with service nav area)
    -->

    <!-- Layer 4: Primary Navigation (Service Navigation Pattern) -->
    <header class="civicone-header" role="banner">
        <div class="civicone-width-container">
            <?php require __DIR__ . '/service-navigation.php'; ?>
        </div>
    </header>

    <!-- Federation Scope Switcher (Section 9B.2 - only on /federation/* pages) -->
    <?php
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    $isFederationPage = (strpos($currentPath, '/federation') !== false);
    if ($isFederationPage && isset($_SESSION['user_id'])):
        // Check if federation is enabled and get partner communities
        $partnerCommunities = [];
        $currentScope = $_GET['scope'] ?? 'all';
        try {
            if (class_exists('\Nexus\Services\FederationFeatureService')) {
                $isEnabled = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
                if ($isEnabled) {
                    // TODO: Replace with actual method to get partner communities
                    // $partnerCommunities = \Nexus\Services\FederationService::getPartnerCommunities($_SESSION['user_id']);
                }
            }
        } catch (\Exception $e) {
            // Silently fail - federation switcher won't show
        }

        // Only include if user has 2+ communities (Rule FS-002)
        if (count($partnerCommunities) >= 2):
            require __DIR__ . '/federation-scope-switcher.php';
        endif;
    endif;
    ?>

    <!-- Layer 5: Search (below service nav, within width container) -->
    <div class="civicone-width-container">
        <div class="civicone-search-wrapper">
            <!-- Desktop Search -->
            <div class="civicone-search-container civicone-desktop-search" role="search">
                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/search" method="GET" class="civicone-search-form">
                    <label for="civicone-search-input" class="civicone-visually-hidden">Search</label>
                    <input type="search"
                           name="q"
                           id="civicone-search-input"
                           class="civicone-search-input"
                           placeholder="Search..."
                           aria-label="Search content"
                           autocomplete="off">
                    <button type="submit" class="civicone-search-button" aria-label="Submit search">
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <span class="civicone-visually-hidden">Search</span>
                    </button>
                </form>
            </div>

            <!-- Mobile Search Toggle Button -->
            <button id="civicone-mobile-search-toggle"
                    class="civicone-mobile-search-toggle"
                    aria-label="Open search"
                    aria-expanded="false"
                    aria-controls="civicone-mobile-search-bar">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <span class="civicone-visually-hidden">Search</span>
            </button>
        </div>

        <!-- Mobile Search Bar (Expandable) -->
        <div id="civicone-mobile-search-bar" class="civicone-mobile-search-bar" hidden>
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/search" method="GET" class="civicone-mobile-search-form">
                <label for="civicone-mobile-search-input" class="civicone-visually-hidden">Search the site</label>
                <input type="search"
                       id="civicone-mobile-search-input"
                       name="q"
                       class="civicone-mobile-search-input"
                       placeholder="Search..."
                       autocomplete="off">
                <button type="submit" class="civicone-mobile-search-button" aria-label="Submit search">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                </button>
            </form>
        </div>
    </div>

    <!-- BACKWARD COMPATIBILITY: Keep old mobile menu hook -->
    <!-- The mobile-nav-v2.php drawer still uses #civic-menu-toggle -->
    <!-- Map service nav toggle to work with existing mobile drawer -->
    <button id="civic-menu-toggle"
            class="hidden"
            aria-label="Open Menu"
            onclick="if(typeof openMobileMenu==='function'){openMobileMenu();}">
        <span class="civic-hamburger"></span>
    </button>
