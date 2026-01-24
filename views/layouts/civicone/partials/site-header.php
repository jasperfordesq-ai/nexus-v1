    <!-- CivicOne Site Header - GOV.UK Service Pattern -->
    <!-- See: https://design-system.service.gov.uk/components/service-navigation/ -->
    <!--
        Layer Order:
        1. Skip link (in skip-link-and-banner.php - first focusable)
        2. Phase banner (in skip-link-and-banner.php)
        3. Utility bar (platform, contrast, auth)
        4. Service Navigation (primary navigation)
        5. Search (integrated within header area)
    -->

    <!-- Layer 4: Service Navigation -->
    <header class="govuk-header" role="banner" data-module="govuk-header">
        <?php require __DIR__ . '/service-navigation.php'; ?>
    </header>

    <!-- Federation Scope Switcher (only on /federation/* pages) -->
    <?php
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    $isFederationPage = (strpos($currentPath, '/federation') !== false);
    if ($isFederationPage && isset($_SESSION['user_id'])):
        $partnerCommunities = [];
        $currentScope = $_GET['scope'] ?? 'all';
        try {
            if (class_exists('\Nexus\Services\FederationFeatureService')) {
                $isEnabled = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
                if ($isEnabled) {
                    // Federation scope switcher for multi-community users
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        if (count($partnerCommunities) >= 2):
            require __DIR__ . '/federation-scope-switcher.php';
        endif;
    endif;
    ?>

    <!-- Layer 5: Search -->
    <div class="govuk-width-container">
        <div class="govuk-!-padding-top-2 govuk-!-padding-bottom-2">
            <!-- Desktop Search -->
            <div role="search" class="govuk-!-display-none-print">
                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/search" method="GET" class="govuk-form-group govuk-!-margin-bottom-0">
                    <div class="govuk-input__wrapper">
                        <label for="site-search-input" class="govuk-visually-hidden">Search</label>
                        <input type="search"
                               name="q"
                               id="site-search-input"
                               class="govuk-input govuk-!-width-one-half"
                               placeholder="Search members, listings, events..."
                               aria-label="Search content"
                               autocomplete="off">
                        <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
                            <span class="govuk-visually-hidden">Submit </span>Search
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
