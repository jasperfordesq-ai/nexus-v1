<?php
/**
 * CivicOne Bottom Navigation Bar
 * Mobile-only fixed navigation for key actions
 * WCAG 2.1 AA Compliant
 *
 * NOW SYNCHRONIZED WITH DESKTOP HEADER MENU
 * Uses unified navigation configuration
 */

// Load unified navigation configuration
require_once __DIR__ . '/../config/navigation.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
$userId = $_SESSION['user_id'] ?? null;

// Get navigation items from unified config
$navItems = \Nexus\Config\Navigation::getBottomNavItems();

// Helper function to check if path is active
function isNavActive($path, $currentPath, $basePath) {
    $fullPath = rtrim($basePath, '/') . $path;
    // Handle base path matching
    if ($path === '/') {
        $normalizedCurrent = rtrim($currentPath, '/');
        $normalizedBase = rtrim($basePath, '/');
        return $normalizedCurrent === $normalizedBase || $normalizedCurrent === $normalizedBase . '/';
    }
    // Exact match or starts with (for nested pages)
    return $currentPath === $fullPath ||
           strpos($currentPath, $fullPath) === 0;
}

// Get message count for badge
$msgCount = 0;
if ($userId && class_exists('\Nexus\Models\MessageThread')) {
    try {
        $threads = \Nexus\Models\MessageThread::getForUser($userId);
        foreach ($threads as $thread) {
            if (!empty($thread['unread_count'])) {
                $msgCount += (int)$thread['unread_count'];
            }
        }
    } catch (\Exception $e) {
        $msgCount = 0;
    }
}
?>

<!-- Mobile Bottom Navigation (WCAG 2.1 AA) -->
<!-- NOW SYNCHRONIZED WITH DESKTOP HEADER MENU -->
<nav class="civic-bottom-nav" role="navigation" aria-label="Mobile navigation">
    <div class="civic-bottom-nav-inner">
        <?php foreach ($navItems as $key => $item):
            if (!\Nexus\Config\Navigation::shouldShow($item)) continue;

            // Extract URL path for active state checking
            $urlPath = parse_url($item['url'], PHP_URL_PATH);
            $urlPath = str_replace($basePath, '', $urlPath);
            if (empty($urlPath)) $urlPath = '/';

            $isActive = isNavActive($urlPath, $currentPath, $basePath);
            $classes = ['civic-bottom-nav-item'];
            if ($isActive) $classes[] = 'active';
            if ($item['elevated'] ?? false) $classes[] = 'civic-bottom-nav-item--create';

            $ariaLabel = $item['description'] ?? $item['display_label'];

            // Add badge count to aria-label for messages
            if ($key === 'messages' && $userId && $msgCount > 0) {
                $ariaLabel .= ', ' . $msgCount . ' unread';
            }
        ?>
            <a href="<?= htmlspecialchars($item['url']) ?>"
               class="<?= implode(' ', $classes) ?>"
               aria-label="<?= htmlspecialchars($ariaLabel) ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
                <?php if ($item['elevated'] ?? false): ?>
                    <span class="civic-nav-icon-wrapper">
                        <span class="civic-nav-icon"><?= $item['svg'] ?></span>
                    </span>
                <?php else: ?>
                    <span class="civic-nav-icon">
                        <?= $item['svg'] ?>
                        <?php if (($item['has_badge'] ?? false) && $key === 'messages' && $userId): ?>
                            <?php if ($msgCount > 0): ?>
                                <span class="civic-nav-badge" id="civic-messages-badge" aria-label="unread messages"><?= $msgCount > 99 ? '99+' : $msgCount ?></span>
                            <?php else: ?>
                                <span class="civic-nav-badge" id="civic-messages-badge" aria-label="unread messages" style="display:none;"></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
                <span><?= htmlspecialchars($item['display_label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</nav>

<script>
(function() {
    'use strict';

    // Add body class for bottom nav padding
    document.body.classList.add('has-civic-bottom-nav');

    // Update active state on navigation (for SPA-like behavior if using Turbo)
    document.addEventListener('turbo:load', function() {
        const nav = document.querySelector('.civic-bottom-nav');
        if (!nav) return;

        const currentPath = window.location.pathname;
        const items = nav.querySelectorAll('.civic-bottom-nav-item');

        items.forEach(item => {
            const href = item.getAttribute('href');
            const isActive = currentPath === href ||
                            (href !== '/' && currentPath.startsWith(href));

            item.classList.toggle('active', isActive);
            if (isActive) {
                item.setAttribute('aria-current', 'page');
            } else {
                item.removeAttribute('aria-current');
            }
        });

        // Re-fetch badges on navigation
        updateBottomNavBadges();
    });

    // =========================================
    // NOTIFICATION BADGES
    // =========================================

    function updateBottomNavBadges() {
        // Only if user is logged in (badges exist)
        const messagesBadge = document.getElementById('civic-messages-badge');

        if (!messagesBadge) return;

        // Fetch unread message count for Messages badge
        if (messagesBadge && typeof NEXUS_BASE !== 'undefined') {
            fetch(NEXUS_BASE + '/api/messages/unread-count')
                .then(r => r.json())
                .then(data => {
                    if (data.count > 0) {
                        messagesBadge.textContent = data.count > 99 ? '99+' : data.count;
                        messagesBadge.style.display = 'flex';
                        // Update aria-label for screen readers
                        const messagesLink = messagesBadge.closest('.civic-bottom-nav-item');
                        if (messagesLink) {
                            messagesLink.setAttribute('aria-label', `Messages, ${data.count} unread`);
                        }
                    } else {
                        messagesBadge.style.display = 'none';
                        const messagesLink = messagesBadge.closest('.civic-bottom-nav-item');
                        if (messagesLink) {
                            messagesLink.setAttribute('aria-label', 'View your messages');
                        }
                    }
                })
                .catch(() => {});
        }
    }

    // Initial fetch
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateBottomNavBadges);
    } else {
        updateBottomNavBadges();
    }

    // Refresh every 60 seconds
    setInterval(updateBottomNavBadges, 60000);

    // Expose for manual refresh
    window.updateBottomNavBadges = updateBottomNavBadges;
})();
</script>
