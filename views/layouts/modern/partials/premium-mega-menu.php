<?php
/**
 * Premium Glassmorphism Mega Menu
 * Ultra-premium holographic navigation system
 * Updated: 2026-01-24 - Extracted inline styles to CSS classes
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
?>

<!-- Community Mega Menu -->
<div class="mega-menu-wrapper">
    <button class="mega-menu-trigger" aria-label="Community Menu" aria-expanded="false">
        <i class="fa-solid fa-users"></i>
        <span>Community</span>
        <span class="arrow">▾</span>
    </button>

    <div class="mega-menu-dropdown">
        <div class="mega-menu-section">
            <div class="mega-menu-section-title">
                <i class="fa-solid fa-heart"></i>
                CONNECT WITH PEOPLE
            </div>

            <a href="<?= $basePath ?>/community-groups" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--community-groups">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Community Groups</span>
                    <span class="mega-menu-item-desc">Join interest-based communities</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/groups" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--local-hubs">
                    <i class="fa-solid fa-location-dot"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Local Hubs</span>
                    <span class="mega-menu-item-desc">Connect with neighbors nearby</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/members" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--members">
                    <i class="fa-solid fa-user-group"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Members Directory</span>
                    <span class="mega-menu-item-desc">Browse all community members</span>
                </div>
            </a>

            <?php if (\Nexus\Core\TenantContext::hasFeature('events')): ?>
            <a href="<?= $basePath ?>/events" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--events">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Events</span>
                    <span class="mega-menu-item-desc">Upcoming gatherings & activities</span>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Explore Everything Mega Menu -->
<div class="mega-menu-wrapper">
    <button class="mega-menu-trigger" aria-label="Explore Menu" aria-expanded="false">
        <i class="fa-solid fa-compass"></i>
        <span>Explore</span>
        <span class="arrow">▾</span>
    </button>

    <div class="mega-menu-dropdown">
        <!-- Quick Actions -->
        <?php if ($isLoggedIn): ?>
        <div class="mega-menu-quick-actions">
            <a href="<?= $basePath ?>/compose" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--create">
                    <i class="fa-solid fa-plus-circle"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Create Something New</span>
                    <span class="mega-menu-item-desc">Post, listing, event, or more</span>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <!-- Discovery Section -->
        <div class="mega-menu-section">
            <div class="mega-menu-section-title">
                <i class="fa-solid fa-sparkles"></i>
                DISCOVER
            </div>

            <?php if (\Nexus\Core\TenantContext::hasFeature('goals')): ?>
            <a href="<?= $basePath ?>/goals" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--goals">
                    <i class="fa-solid fa-bullseye"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Goals</span>
                    <span class="mega-menu-item-desc">Set and track your goals</span>
                </div>
            </a>
            <?php endif; ?>

            <?php if (\Nexus\Core\TenantContext::hasFeature('polls')): ?>
            <a href="<?= $basePath ?>/polls" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--polls">
                    <i class="fa-solid fa-square-poll-vertical"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Polls</span>
                    <span class="mega-menu-item-desc">Vote on community questions</span>
                </div>
            </a>
            <?php endif; ?>

            <?php if (\Nexus\Core\TenantContext::hasFeature('resources')): ?>
            <a href="<?= $basePath ?>/resources" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--resources">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Resources</span>
                    <span class="mega-menu-item-desc">Helpful community resources</span>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <div class="mega-menu-divider"></div>

        <!-- Gamification Section -->
        <div class="mega-menu-section">
            <div class="mega-menu-section-title">
                <i class="fa-solid fa-trophy"></i>
                GAMIFICATION & REWARDS
            </div>

            <?php if ($isLoggedIn): ?>
            <a href="<?= $basePath ?>/nexus-score" class="mega-menu-item gamification-item">
                <div class="mega-menu-item-icon mega-icon--nexus-score">
                    <i class="fa-solid fa-star"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">🏆 My Nexus Score</span>
                    <span class="mega-menu-item-desc">View your community impact score</span>
                </div>
            </a>
            <?php endif; ?>

            <a href="<?= $basePath ?>/leaderboard" class="mega-menu-item gamification-item">
                <div class="mega-menu-item-icon mega-icon--leaderboard">
                    <i class="fa-solid fa-trophy"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Leaderboards</span>
                    <span class="mega-menu-item-desc">See top community contributors</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/achievements" class="mega-menu-item gamification-item">
                <div class="mega-menu-item-icon mega-icon--achievements">
                    <i class="fa-solid fa-medal"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Achievements</span>
                    <span class="mega-menu-item-desc">Unlock badges & rewards</span>
                </div>
            </a>
        </div>

        <div class="mega-menu-divider"></div>

        <!-- Smart Tools Section -->
        <div class="mega-menu-section">
            <div class="mega-menu-section-title">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
                SMART TOOLS
            </div>

            <a href="<?= $basePath ?>/matches" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--matching">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Smart Matching</span>
                    <span class="mega-menu-item-desc">AI-powered connections</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/ai" class="mega-menu-item ai-highlight">
                <div class="mega-menu-item-icon mega-icon--ai">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">✨ AI Assistant</span>
                    <span class="mega-menu-item-desc">Get personalized help & insights</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/mobile-download" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--mobile-app">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Get Mobile App</span>
                    <span class="mega-menu-item-desc">Download for iOS & Android</span>
                </div>
            </a>
        </div>

    </div>
</div>

<!-- About Mega Menu -->
<div class="mega-menu-wrapper">
    <button class="mega-menu-trigger" aria-label="About Menu" aria-expanded="false">
        <i class="fa-solid fa-circle-info"></i>
        <span>About</span>
        <span class="arrow">▾</span>
    </button>

    <div class="mega-menu-dropdown">
        <!-- About Section -->
        <div class="mega-menu-section">
            <div class="mega-menu-section-title">
                <i class="fa-solid fa-circle-info"></i>
                ABOUT
            </div>

            <?php if (\Nexus\Core\TenantContext::hasFeature('blog')): ?>
            <a href="<?= $basePath ?>/news" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--news">
                    <i class="fa-solid fa-newspaper"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Latest News</span>
                    <span class="mega-menu-item-desc">Community updates & stories</span>
                </div>
            </a>
            <?php endif; ?>

            <?php
            // Custom file-based pages with CSS class mapping
            $customPages = \Nexus\Core\TenantContext::getCustomPages('modern');

            // Pages to exclude from About menu
            $excludedPages = [
                'about', 'privacy', 'terms', 'privacy policy', 'terms of service',
                'terms and conditions', 'help', 'contact', 'contact us', 'accessibility',
                'how it works', 'mobile download', 'impact summary', 'impact report', 'strategic plan',
            ];

            // Custom display order
            $pageOrder = [
                'about us', 'our story', 'about story', 'timebanking guide',
                'partner with us', 'partner', 'social prescribing',
                'timebanking faqs', 'timebanking faq', 'faq',
            ];

            // Icon and CSS class mapping
            $pageIconMap = [
                'about us' => ['icon' => 'fa-solid fa-heart', 'class' => 'mega-icon--about', 'desc' => 'Our story & mission'],
                'our story' => ['icon' => 'fa-solid fa-heart', 'class' => 'mega-icon--about', 'desc' => 'Learn about our journey'],
                'about story' => ['icon' => 'fa-solid fa-heart', 'class' => 'mega-icon--about', 'desc' => 'Learn about our journey'],
                'timebanking guide' => ['icon' => 'fa-solid fa-book-open', 'class' => 'mega-icon--guide', 'desc' => 'How timebanking works'],
                'partner' => ['icon' => 'fa-solid fa-handshake', 'class' => 'mega-icon--partner', 'desc' => 'Collaborate with us'],
                'partner with us' => ['icon' => 'fa-solid fa-handshake', 'class' => 'mega-icon--partner', 'desc' => 'Collaborate with us'],
                'social prescribing' => ['icon' => 'fa-solid fa-hand-holding-medical', 'class' => 'mega-icon--social-prescribing', 'desc' => 'Healthcare integration'],
                'faq' => ['icon' => 'fa-solid fa-circle-question', 'class' => 'mega-icon--faq', 'desc' => 'Frequently asked questions'],
                'timebanking faq' => ['icon' => 'fa-solid fa-circle-question', 'class' => 'mega-icon--faq', 'desc' => 'Frequently asked questions'],
                'timebanking faqs' => ['icon' => 'fa-solid fa-circle-question', 'class' => 'mega-icon--faq', 'desc' => 'Frequently asked questions'],
            ];

            // Sort pages according to custom order
            usort($customPages, function($a, $b) use ($pageOrder) {
                $aName = strtolower($a['name']);
                $bName = strtolower($b['name']);
                $aPos = array_search($aName, $pageOrder);
                $bPos = array_search($bName, $pageOrder);
                if ($aPos === false && $bPos === false) return 0;
                if ($aPos !== false && $bPos === false) return -1;
                if ($aPos === false && $bPos !== false) return 1;
                return $aPos - $bPos;
            });

            foreach ($customPages as $page):
                $pageName = strtolower($page['name']);
                if (in_array($pageName, $excludedPages)) continue;

                $pageData = $pageIconMap[$pageName] ?? [
                    'icon' => 'fa-solid fa-file-lines',
                    'class' => 'mega-icon--default',
                    'desc' => null
                ];
            ?>
            <a href="<?= htmlspecialchars($page['url']) ?>" class="mega-menu-item">
                <div class="mega-menu-item-icon <?= $pageData['class'] ?>">
                    <i class="<?= $pageData['icon'] ?>"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label"><?= htmlspecialchars($page['name']) ?></span>
                    <?php if (!empty($pageData['desc'])): ?>
                    <span class="mega-menu-item-desc"><?= $pageData['desc'] ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php
        // Check if any impact pages exist
        $impactPages = array_filter($customPages, function($p) {
            return in_array(strtolower($p['name']), ['impact summary', 'impact report', 'strategic plan']);
        });
        if (!empty($impactPages)):
        ?>
        <div class="mega-menu-divider"></div>

        <!-- Our Impact Section -->
        <div class="mega-menu-section">
            <div class="mega-menu-section-title">
                <i class="fa-solid fa-chart-line"></i>
                OUR IMPACT
            </div>

            <?php
            $impactIconMap = [
                'impact summary' => ['icon' => 'fa-solid fa-leaf', 'class' => 'mega-icon--impact-summary', 'desc' => 'Our community impact'],
                'impact report' => ['icon' => 'fa-solid fa-file-contract', 'class' => 'mega-icon--impact-report', 'desc' => 'Detailed impact data'],
                'strategic plan' => ['icon' => 'fa-solid fa-route', 'class' => 'mega-icon--strategic-plan', 'desc' => 'Our roadmap forward'],
            ];
            foreach ($impactPages as $page):
                $pageName = strtolower($page['name']);
                $pageData = $impactIconMap[$pageName] ?? ['icon' => 'fa-solid fa-file-lines', 'class' => 'mega-icon--default', 'desc' => null];
            ?>
            <a href="<?= htmlspecialchars($page['url']) ?>" class="mega-menu-item">
                <div class="mega-menu-item-icon <?= $pageData['class'] ?>">
                    <i class="<?= $pageData['icon'] ?>"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label"><?= htmlspecialchars($page['name']) ?></span>
                    <?php if (!empty($pageData['desc'])): ?>
                    <span class="mega-menu-item-desc"><?= $pageData['desc'] ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="mega-menu-divider"></div>

        <!-- Help & Support Section -->
        <div class="mega-menu-section">
            <div class="mega-menu-section-title">
                <i class="fa-solid fa-life-ring"></i>
                HELP & SUPPORT
            </div>

            <a href="<?= $basePath ?>/help" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--help">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Help Center</span>
                    <span class="mega-menu-item-desc">Get support & answers</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/contact" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--contact">
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Contact Us</span>
                    <span class="mega-menu-item-desc">Get in touch with us</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/accessibility" class="mega-menu-item">
                <div class="mega-menu-item-icon mega-icon--accessibility">
                    <i class="fa-solid fa-universal-access"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Accessibility</span>
                    <span class="mega-menu-item-desc">Our accessibility commitment</span>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Mega Menu JS loaded externally in header.php for better caching -->
