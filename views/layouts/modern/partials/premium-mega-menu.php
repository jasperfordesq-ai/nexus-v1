<?php
/**
 * Premium Glassmorphism Mega Menu
 * Ultra-premium holographic navigation system
 * Date: 2026-01-12
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
                <div class="mega-menu-item-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Community Groups</span>
                    <span class="mega-menu-item-desc">Join interest-based communities</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/groups" class="mega-menu-item">
                <div class="mega-menu-item-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                    <i class="fa-solid fa-location-dot"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Local Hubs</span>
                    <span class="mega-menu-item-desc">Connect with neighbors nearby</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/members" class="mega-menu-item">
                <div class="mega-menu-item-icon" style="background: rgba(236, 72, 153, 0.1); color: #ec4899;">
                    <i class="fa-solid fa-user-group"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Members Directory</span>
                    <span class="mega-menu-item-desc">Browse all community members</span>
                </div>
            </a>

            <?php if (\Nexus\Core\TenantContext::hasFeature('events')): ?>
            <a href="<?= $basePath ?>/events" class="mega-menu-item">
                <div class="mega-menu-item-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
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
                <div class="mega-menu-item-icon" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
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
                <div class="mega-menu-item-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
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
                <div class="mega-menu-item-icon" style="background: rgba(6, 182, 212, 0.1); color: #06b6d4;">
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
                <div class="mega-menu-item-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
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
                <div class="mega-menu-item-icon" style="background: rgba(251, 191, 36, 0.15); color: #fbbf24;">
                    <i class="fa-solid fa-star"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">🏆 My Nexus Score</span>
                    <span class="mega-menu-item-desc">View your community impact score</span>
                </div>
            </a>
            <?php endif; ?>

            <a href="<?= $basePath ?>/leaderboard" class="mega-menu-item gamification-item">
                <div class="mega-menu-item-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                    <i class="fa-solid fa-trophy"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Leaderboards</span>
                    <span class="mega-menu-item-desc">See top community contributors</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/achievements" class="mega-menu-item gamification-item">
                <div class="mega-menu-item-icon" style="background: rgba(168, 85, 247, 0.15); color: #a855f7;">
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
                <div class="mega-menu-item-icon" style="background: rgba(236, 72, 153, 0.1); color: #ec4899;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Smart Matching</span>
                    <span class="mega-menu-item-desc">AI-powered connections</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/ai" class="mega-menu-item ai-highlight">
                <div class="mega-menu-item-icon">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">✨ AI Assistant</span>
                    <span class="mega-menu-item-desc">Get personalized help & insights</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/mobile-download" class="mega-menu-item">
                <div class="mega-menu-item-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;">
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
                <div class="mega-menu-item-icon" style="background: rgba(99, 102, 241, 0.18); color: #6366f1;">
                    <i class="fa-solid fa-newspaper"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Latest News</span>
                    <span class="mega-menu-item-desc">Community updates & stories</span>
                </div>
            </a>
            <?php endif; ?>

            <?php
            // Custom file-based pages with intelligent icon/color mapping
            $customPages = \Nexus\Core\TenantContext::getCustomPages('modern');

            // Pages to exclude from About menu
            $excludedPages = [
                'about',
                'privacy',
                'terms',
                'privacy policy',
                'terms of service',
                'terms and conditions',
                'help',
                'contact',
                'contact us',
                'accessibility',
                'how it works',
                'mobile download',
                // Impact pages handled separately
                'impact summary',
                'impact report',
                'strategic plan',
            ];

            // Custom display order
            $pageOrder = [
                'about us',
                'our story',
                'about story',
                'timebanking guide',
                'partner with us',
                'partner',
                'social prescribing',
                'timebanking faqs',
                'timebanking faq',
                'faq',
            ];

            // Icon and color mapping
            $pageIconMap = [
                'about us' => ['icon' => 'fa-solid fa-heart', 'color' => '#ec4899', 'desc' => 'Our story & mission'],
                'our story' => ['icon' => 'fa-solid fa-heart', 'color' => '#ec4899', 'desc' => 'Learn about our journey'],
                'about story' => ['icon' => 'fa-solid fa-heart', 'color' => '#ec4899', 'desc' => 'Learn about our journey'],
                'timebanking guide' => ['icon' => 'fa-solid fa-book-open', 'color' => '#8b5cf6', 'desc' => 'How timebanking works'],
                'partner' => ['icon' => 'fa-solid fa-handshake', 'color' => '#f59e0b', 'desc' => 'Collaborate with us'],
                'partner with us' => ['icon' => 'fa-solid fa-handshake', 'color' => '#f59e0b', 'desc' => 'Collaborate with us'],
                'social prescribing' => ['icon' => 'fa-solid fa-hand-holding-medical', 'color' => '#14b8a6', 'desc' => 'Healthcare integration'],
                'faq' => ['icon' => 'fa-solid fa-circle-question', 'color' => '#06b6d4', 'desc' => 'Frequently asked questions'],
                'timebanking faq' => ['icon' => 'fa-solid fa-circle-question', 'color' => '#06b6d4', 'desc' => 'Frequently asked questions'],
                'timebanking faqs' => ['icon' => 'fa-solid fa-circle-question', 'color' => '#06b6d4', 'desc' => 'Frequently asked questions'],
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
                    'color' => '#64748b',
                    'desc' => null
                ];
                $color = $pageData['color'];
                $r = hexdec(substr($color, 1, 2));
                $g = hexdec(substr($color, 3, 2));
                $b = hexdec(substr($color, 5, 2));
                $bgColor = "rgba($r, $g, $b, 0.15)";
            ?>
            <a href="<?= htmlspecialchars($page['url']) ?>" class="mega-menu-item">
                <div class="mega-menu-item-icon" style="background: <?= $bgColor ?>; color: <?= $color ?>;">
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
                'impact summary' => ['icon' => 'fa-solid fa-leaf', 'color' => '#059669', 'desc' => 'Our community impact'],
                'impact report' => ['icon' => 'fa-solid fa-file-contract', 'color' => '#2563eb', 'desc' => 'Detailed impact data'],
                'strategic plan' => ['icon' => 'fa-solid fa-route', 'color' => '#7c3aed', 'desc' => 'Our roadmap forward'],
            ];
            foreach ($impactPages as $page):
                $pageName = strtolower($page['name']);
                $pageData = $impactIconMap[$pageName] ?? ['icon' => 'fa-solid fa-file-lines', 'color' => '#64748b', 'desc' => null];
                $color = $pageData['color'];
                $r = hexdec(substr($color, 1, 2));
                $g = hexdec(substr($color, 3, 2));
                $b = hexdec(substr($color, 5, 2));
                $bgColor = "rgba($r, $g, $b, 0.15)";
            ?>
            <a href="<?= htmlspecialchars($page['url']) ?>" class="mega-menu-item">
                <div class="mega-menu-item-icon" style="background: <?= $bgColor ?>; color: <?= $color ?>;">
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
                <div class="mega-menu-item-icon" style="background: rgba(249, 115, 22, 0.2); color: #f97316;">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Help Center</span>
                    <span class="mega-menu-item-desc">Get support & answers</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/contact" class="mega-menu-item">
                <div class="mega-menu-item-icon" style="background: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <div class="mega-menu-item-content">
                    <span class="mega-menu-item-label">Contact Us</span>
                    <span class="mega-menu-item-desc">Get in touch with us</span>
                </div>
            </a>

            <a href="<?= $basePath ?>/accessibility" class="mega-menu-item">
                <div class="mega-menu-item-icon" style="background: rgba(16, 185, 129, 0.2); color: #10b981;">
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

<script>
// Mega menu interaction with hover delay
document.addEventListener('DOMContentLoaded', function() {
    const megaMenuWrappers = document.querySelectorAll('.mega-menu-wrapper');

    megaMenuWrappers.forEach(wrapper => {
        const trigger = wrapper.querySelector('.mega-menu-trigger');
        const dropdown = wrapper.querySelector('.mega-menu-dropdown');

        if (!trigger || !dropdown) return;

        let hoverTimeout = null;

        // Hover to open (immediate)
        wrapper.addEventListener('mouseenter', () => {
            // Clear any pending close timeout
            if (hoverTimeout) {
                clearTimeout(hoverTimeout);
                hoverTimeout = null;
            }

            trigger.setAttribute('aria-expanded', 'true');
            wrapper.classList.add('active');
        });

        // Hover to close (with delay)
        wrapper.addEventListener('mouseleave', () => {
            // Add 150ms delay before closing to prevent accidental closes
            hoverTimeout = setTimeout(() => {
                trigger.setAttribute('aria-expanded', 'false');
                wrapper.classList.remove('active');
            }, 150);
        });

        // Click support for touch devices
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            const isActive = wrapper.classList.contains('active');

            // Clear any pending timeouts
            if (hoverTimeout) {
                clearTimeout(hoverTimeout);
                hoverTimeout = null;
            }

            // Close all other mega menus
            megaMenuWrappers.forEach(w => {
                if (w !== wrapper) {
                    w.classList.remove('active');
                    w.querySelector('.mega-menu-trigger')?.setAttribute('aria-expanded', 'false');
                }
            });

            // Toggle this menu
            wrapper.classList.toggle('active');
            trigger.setAttribute('aria-expanded', !isActive);
        });
    });

    // Close mega menus when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.mega-menu-wrapper')) {
            megaMenuWrappers.forEach(wrapper => {
                wrapper.classList.remove('active');
                wrapper.querySelector('.mega-menu-trigger')?.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            megaMenuWrappers.forEach(wrapper => {
                wrapper.classList.remove('active');
                wrapper.querySelector('.mega-menu-trigger')?.setAttribute('aria-expanded', 'false');
            });
        }
    });
});
</script>
