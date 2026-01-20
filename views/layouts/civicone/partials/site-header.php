    <!-- 2. Main Header (Bottom Row) - WCAG 2.1 AA Landmark -->
    <header class="civic-header" role="banner">
        <div class="civic-container civic-header-wrapper">

            <!-- Logo -->
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?: '/' ?>" class="civic-logo" aria-label="<?= htmlspecialchars(\Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS') ?> - Go to homepage">
                <?php
                $civicName = \Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
                if (\Nexus\Core\TenantContext::getId() == 1) {
                    $civicName = 'Project NEXUS';
                }
                echo htmlspecialchars($civicName);
                ?>
            </a>

            <!-- Desktop Navigation - ACCESSIBLE VERSION (2026-01-19) -->
            <!-- WCAG 2.1 AA: Core links visible + single hamburger Menu for all other navigation -->
            <nav id="civic-main-nav" class="civic-desktop-nav" aria-label="Main navigation">
                <?php
                $basePath = \Nexus\Core\TenantContext::getBasePath();
                $isLoggedIn = isset($_SESSION['user_id']);
                ?>

                <!-- Core Navigation Links - Always visible -->
                <a href="<?= $basePath ?>/" class="civic-nav-link" data-nav-match="/">Feed</a>
                <a href="<?= $basePath ?>/listings" class="civic-nav-link" data-nav-match="listings">Listings</a>
                <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                    <a href="<?= $basePath ?>/volunteering" class="civic-nav-link" data-nav-match="volunteering">Volunteering</a>
                <?php endif; ?>

                <?php
                // Database-driven pages (Page Builder)
                $dbPagesMain = \Nexus\Core\MenuGenerator::getMenuPages('main');
                foreach ($dbPagesMain as $mainPage):
                ?>
                    <a href="<?= htmlspecialchars($mainPage['url']) ?>" class="civic-nav-link"><?= htmlspecialchars($mainPage['title']) ?></a>
                <?php endforeach; ?>

                <!-- Single Menu Button - Opens combined mega menu -->
                <button id="civic-mega-menu-btn" class="civic-menu-btn" aria-haspopup="dialog" aria-expanded="false" aria-controls="civic-mega-menu">
                    Menu <span class="civic-arrow" aria-hidden="true">▾</span>
                </button>
            </nav>

            <!-- Combined Mega Menu - All links organized in columns -->
            <div id="civic-mega-menu" class="civic-mega-menu" role="dialog" aria-labelledby="civic-mega-menu-btn" aria-modal="false">
                <div class="civic-mega-grid">
                    <!-- Column 1: Community -->
                    <div class="civic-mega-col">
                        <h3>Community</h3>
                        <?php if (\Nexus\Core\TenantContext::hasFeature('events')): ?>
                        <a href="<?= $basePath ?>/events">Events</a>
                        <?php endif; ?>
                        <a href="<?= $basePath ?>/members">Members</a>
                        <a href="<?= $basePath ?>/community-groups">Community Groups</a>
                        <a href="<?= $basePath ?>/groups">Local Hubs</a>
                    </div>

                    <!-- Column 2: Explore -->
                    <div class="civic-mega-col">
                        <h3>Explore</h3>
                        <?php if ($isLoggedIn): ?>
                        <a href="<?= $basePath ?>/compose">Create New</a>
                        <?php endif; ?>
                        <?php if (\Nexus\Core\TenantContext::hasFeature('goals')): ?>
                        <a href="<?= $basePath ?>/goals">Goals</a>
                        <?php endif; ?>
                        <?php if (\Nexus\Core\TenantContext::hasFeature('polls')): ?>
                        <a href="<?= $basePath ?>/polls">Polls</a>
                        <?php endif; ?>
                        <?php if (\Nexus\Core\TenantContext::hasFeature('resources')): ?>
                        <a href="<?= $basePath ?>/resources">Resources</a>
                        <?php endif; ?>
                        <a href="<?= $basePath ?>/leaderboard">Leaderboards</a>
                        <a href="<?= $basePath ?>/achievements">Achievements</a>
                    </div>

                    <!-- Column 3: Tools & Features -->
                    <div class="civic-mega-col">
                        <h3>Tools</h3>
                        <?php if ($isLoggedIn): ?>
                        <a href="<?= $basePath ?>/nexus-score">My Nexus Score</a>
                        <?php endif; ?>
                        <a href="<?= $basePath ?>/matches">Smart Matching</a>
                        <a href="<?= $basePath ?>/ai">AI Assistant</a>
                        <a href="<?= $basePath ?>/mobile-download">Get Mobile App</a>
                    </div>

                    <!-- Column 4: About & Help -->
                    <div class="civic-mega-col">
                        <h3>About</h3>
                        <?php if (\Nexus\Core\TenantContext::hasFeature('blog')): ?>
                        <a href="<?= $basePath ?>/news">Latest News</a>
                        <?php endif; ?>
                        <?php
                        // Custom file-based pages
                        $customPages = \Nexus\Core\TenantContext::getCustomPages('civicone');
                        if (empty($customPages)) {
                            $customPages = \Nexus\Core\TenantContext::getCustomPages('modern');
                        }
                        $excludedPages = ['about', 'privacy', 'terms', 'privacy policy', 'terms of service',
                            'terms and conditions', 'help', 'contact', 'contact us', 'accessibility',
                            'how it works', 'mobile download'];
                        foreach ($customPages as $page):
                            $pageName = strtolower($page['name']);
                            if (in_array($pageName, $excludedPages)) continue;
                        ?>
                        <a href="<?= htmlspecialchars($page['url']) ?>"><?= htmlspecialchars($page['name']) ?></a>
                        <?php endforeach; ?>
                        <a href="<?= $basePath ?>/help">Help Center</a>
                        <a href="<?= $basePath ?>/contact">Contact Us</a>
                        <a href="<?= $basePath ?>/accessibility">Accessibility</a>
                    </div>
                </div>
            </div>

            <!-- Desktop Search - Simple accessible design -->
            <div class="civic-search-container civic-desktop-search" role="search">
                <form action="<?= $basePath ?>/search" method="GET" class="civic-search-form">
                    <label for="civicSearchInput" class="visually-hidden">Search</label>
                    <input type="search" name="q" id="civicSearchInput" placeholder="Search..." aria-label="Search content" autocomplete="off">
                    <button type="submit" aria-label="Submit search">Search</button>
                </form>
            </div>

            <!-- Mobile Search Toggle -->
            <button id="civic-mobile-search-toggle" class="civic-mobile-search-btn" aria-label="Open search" aria-expanded="false">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
            </button>

            <!-- Mobile Menu Button -->
            <button id="civic-menu-toggle" aria-label="Open Menu" onclick="if(typeof openMobileMenu==='function'){openMobileMenu();}">
                <span class="civic-hamburger"></span>
            </button>
        </div>

        <!-- Mobile Search Bar (Expandable) -->
        <div id="civic-mobile-search-bar" class="civic-mobile-search-bar">
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/search" method="GET">
                <label for="mobile-search-input" class="visually-hidden">Search the site</label>
                <input type="text" id="mobile-search-input" name="q" placeholder="Search..." autocomplete="off">
                <button type="submit" aria-label="Submit search">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                </button>
            </form>
        </div>
    </header>
