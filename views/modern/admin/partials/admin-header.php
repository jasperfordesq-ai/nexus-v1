<?php
/**
 * Admin Gold Standard Header Component - Modern Theme
 * STANDALONE admin interface - does NOT use main site header/footer
 * Uses shared navigation configuration from views/partials/admin/
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Nexus\Core\TenantContext;

// Include shared navigation configuration
require_once dirname(__DIR__, 3) . '/partials/admin/admin-navigation-config.php';

$basePath = TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$currentPathClean = strtok($currentPath, '?');
$currentUser = $_SESSION['user_name'] ?? 'Admin';
$userInitials = strtoupper(substr($currentUser, 0, 2));

$adminPageTitle = $adminPageTitle ?? 'Admin';
$adminPageSubtitle = $adminPageSubtitle ?? 'Mission Control';
$adminPageIcon = $adminPageIcon ?? 'fa-satellite-dish';

// Check if user is super admin
$isSuperAdmin = !empty($_SESSION['is_super_admin']);

// Get admin navigation modules from shared config
$adminModules = getAdminNavigationModules();
$adminModules = filterAdminModules($adminModules);

$activeModule = getActiveAdminModule($adminModules, $currentPath, $basePath);
$adminBreadcrumbs = generateAdminBreadcrumbs($adminModules, $currentPath, $basePath, $adminPageTitle);
?>
<!DOCTYPE html>
<html lang="en" style="background:#0a0e1a">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($adminPageTitle) ?> - Admin</title>

    <!-- Critical: Prevent flash - must be first -->
    <style>html,body,.admin-gold-wrapper{background:#0a0e1a!important;color:#fff;min-height:100vh;font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;-webkit-font-smoothing:antialiased}</style>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Design Tokens - MUST load first -->
    <link rel="stylesheet" href="/assets/css/design-tokens.css?v=<?= time() ?>">
    <!-- Admin CSS (combined) -->
    <link rel="stylesheet" href="/assets/css/admin-gold-standard.min.css?v=<?= time() ?>">
    <!-- Admin Sidebar CSS -->
    <link rel="stylesheet" href="/assets/css/admin-sidebar.min.css?v=<?= time() ?>">
    <!-- Admin Menu Builder - Extracted inline styles -->
    <link rel="stylesheet" href="/assets/css/admin-menu-builder.css?v=<?= time() ?>">
    <!-- Admin Menu Index - Extracted inline styles -->
    <link rel="stylesheet" href="/assets/css/admin-menu-index.css?v=<?= time() ?>">
</head>
<body style="background:#0a0e1a;margin:0">
<div class="admin-gold-wrapper">
    <div class="admin-gold-bg"></div>

    <!-- Mobile Menu Toggle (floating) -->
    <button type="button" class="admin-mobile-menu-btn" id="adminMobileBtn" aria-label="Toggle Menu">
        <i class="fa-solid fa-bars"></i>
    </button>

    <!-- Search Modal -->
    <div class="admin-search-modal" id="adminSearchModal">
        <div class="admin-search-modal-backdrop"></div>
        <div class="admin-search-modal-content">
            <div class="admin-search-input-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="adminSearchInput" class="admin-search-input" placeholder="Search users, listings, settings..." autocomplete="off">
                <kbd class="admin-search-esc">ESC</kbd>
            </div>
            <div class="admin-search-results" id="adminSearchResults">
                <!-- Live Search Results (populated by AJAX) -->
                <div class="admin-search-section admin-live-section" id="adminLiveSection" style="display: none;">
                    <div id="adminLiveResults"></div>
                </div>

                <!-- Recently Viewed (populated by JS) -->
                <div class="admin-search-section admin-recent-section" id="adminRecentSection" style="display: none;">
                    <div class="admin-search-section-title">
                        <i class="fa-solid fa-clock-rotate-left"></i> Recently Viewed
                    </div>
                    <div id="adminRecentItems"></div>
                </div>

                <div class="admin-search-section" id="adminQuickNav">
                    <div class="admin-search-section-title">Quick Navigation</div>
                    <a href="<?= $basePath ?>/admin/users" class="admin-search-item" data-search="users members people">
                        <i class="fa-solid fa-users"></i>
                        <span>Users</span>
                        <kbd>Alt+U</kbd>
                    </a>
                    <a href="<?= $basePath ?>/admin/listings" class="admin-search-item" data-search="listings services offers">
                        <i class="fa-solid fa-rectangle-list"></i>
                        <span>Listings</span>
                        <kbd>Alt+L</kbd>
                    </a>
                    <a href="<?= $basePath ?>/admin/settings" class="admin-search-item" data-search="settings configuration options">
                        <i class="fa-solid fa-gear"></i>
                        <span>Settings</span>
                        <kbd>Alt+S</kbd>
                    </a>
                    <a href="<?= $basePath ?>/admin/newsletters" class="admin-search-item" data-search="newsletters email campaigns">
                        <i class="fa-solid fa-envelope"></i>
                        <span>Newsletters</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/blog" class="admin-search-item" data-search="blog posts articles news">
                        <i class="fa-solid fa-blog"></i>
                        <span>Blog Posts</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/activity-log" class="admin-search-item" data-search="activity log audit events">
                        <i class="fa-solid fa-list-ul"></i>
                        <span>Activity Log</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/categories" class="admin-search-item" data-search="categories taxonomy tags">
                        <i class="fa-solid fa-folder-tree"></i>
                        <span>Categories</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/pages" class="admin-search-item" data-search="pages content cms">
                        <i class="fa-solid fa-file-lines"></i>
                        <span>Pages</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/enterprise/gdpr" class="admin-search-item" data-search="gdpr privacy compliance consent">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>GDPR Compliance</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/enterprise/monitoring" class="admin-search-item" data-search="monitoring logs errors system health">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>System Monitoring</span>
                    </a>
                </div>
            </div>
            <div class="admin-search-footer">
                <span><kbd>↑</kbd><kbd>↓</kbd> Navigate</span>
                <span><kbd>Enter</kbd> Open</span>
                <span><kbd>ESC</kbd> Close</span>
            </div>
        </div>
    </div>

<!-- Sidebar Layout -->
<div class="admin-layout">
    <?php require dirname(__DIR__, 3) . '/partials/admin/admin-sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="admin-main-content">
        <div class="admin-gold-content">

    <?php // Include breadcrumbs component ?>
    <?php require __DIR__ . '/admin-breadcrumbs.php'; ?>

<!-- Admin Sidebar JS -->
<script src="/assets/js/admin-sidebar.js?v=<?= time() ?>"></script>

<script>
// Admin Search Modal & Keyboard Shortcuts with AJAX Live Search
(function() {
    var searchModal = document.getElementById('adminSearchModal');
    var searchTrigger = document.getElementById('adminSearchTrigger');
    var searchInput = document.getElementById('adminSearchInput');
    var searchResults = document.getElementById('adminSearchResults');
    var activeIndex = -1;
    var basePath = '<?= $basePath ?>';
    var recentSection = document.getElementById('adminRecentSection');
    var recentItemsContainer = document.getElementById('adminRecentItems');
    var liveSection = document.getElementById('adminLiveSection');
    var liveResults = document.getElementById('adminLiveResults');
    var quickNav = document.getElementById('adminQuickNav');
    var RECENT_KEY = 'nexus_admin_recent';
    var MAX_RECENT = 5;
    var searchTimeout = null;
    var isSearching = false;

    // Track current page visit
    function trackPageVisit() {
        var pageTitle = '<?= addslashes($adminPageTitle ?? "Dashboard") ?>';
        var pageIcon = '<?= addslashes($adminPageIcon ?? "fa-gauge-high") ?>';
        var pageUrl = window.location.pathname;

        // Don't track the main dashboard
        if (pageUrl === basePath + '/admin' || pageUrl === basePath + '/admin/') return;

        var recent = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');

        // Remove duplicates
        recent = recent.filter(function(item) { return item.url !== pageUrl; });

        // Add current page to front
        recent.unshift({ title: pageTitle, icon: pageIcon, url: pageUrl, time: Date.now() });

        // Keep only MAX_RECENT items
        recent = recent.slice(0, MAX_RECENT);

        localStorage.setItem(RECENT_KEY, JSON.stringify(recent));
    }

    // Render recently viewed items
    function renderRecentItems() {
        var recent = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');

        if (recent.length === 0 || !recentSection || !recentItemsContainer) {
            if (recentSection) recentSection.style.display = 'none';
            return;
        }

        recentSection.style.display = 'block';
        recentItemsContainer.innerHTML = recent.map(function(item) {
            return '<a href="' + item.url + '" class="admin-search-item recent-item" data-search="' + item.title.toLowerCase() + '">' +
                '<i class="fa-solid ' + item.icon + '"></i>' +
                '<span>' + item.title + '</span>' +
                '</a>';
        }).join('');
    }

    // AJAX Live Search
    function performLiveSearch(query) {
        if (query.length < 2) {
            liveSection.style.display = 'none';
            liveResults.innerHTML = '';
            quickNav.style.display = 'block';
            recentSection.style.display = 'block';
            return;
        }

        isSearching = true;

        fetch(basePath + '/admin/api/search?q=' + encodeURIComponent(query))
            .then(function(response) { return response.json(); })
            .then(function(data) {
                isSearching = false;
                renderLiveResults(data, query);
            })
            .catch(function(err) {
                isSearching = false;
                console.error('Search error:', err);
            });
    }

    // Render live search results with quick actions
    function renderLiveResults(data, query) {
        var html = '';
        var hasResults = false;

        // Users section
        if (data.users && data.users.length > 0) {
            hasResults = true;
            html += '<div class="admin-search-section-title"><i class="fa-solid fa-users"></i> Users</div>';
            data.users.forEach(function(user) {
                html += '<div class="admin-search-result-item">' +
                    '<a href="' + user.url + '" class="admin-search-item live-item">' +
                        '<i class="fa-solid ' + user.icon + '"></i>' +
                        '<div class="admin-search-item-content">' +
                            '<span class="admin-search-item-title">' + escapeHtml(user.title) + '</span>' +
                            '<span class="admin-search-item-subtitle">' + escapeHtml(user.subtitle) + '</span>' +
                        '</div>' +
                    '</a>' +
                    '<div class="admin-search-actions">' +
                        renderQuickActions(user.actions) +
                    '</div>' +
                '</div>';
            });
        }

        // Listings section
        if (data.listings && data.listings.length > 0) {
            hasResults = true;
            html += '<div class="admin-search-section-title"><i class="fa-solid fa-rectangle-list"></i> Listings</div>';
            data.listings.forEach(function(listing) {
                html += '<div class="admin-search-result-item">' +
                    '<a href="' + listing.url + '" class="admin-search-item live-item">' +
                        '<i class="fa-solid ' + listing.icon + '"></i>' +
                        '<div class="admin-search-item-content">' +
                            '<span class="admin-search-item-title">' + escapeHtml(listing.title) + '</span>' +
                            '<span class="admin-search-item-subtitle">' + escapeHtml(listing.subtitle) + '</span>' +
                        '</div>' +
                    '</a>' +
                    '<div class="admin-search-actions">' +
                        renderQuickActions(listing.actions) +
                    '</div>' +
                '</div>';
            });
        }

        // Blog posts section
        if (data.pages && data.pages.length > 0) {
            hasResults = true;
            html += '<div class="admin-search-section-title"><i class="fa-solid fa-file-lines"></i> Content</div>';
            data.pages.forEach(function(page) {
                html += '<div class="admin-search-result-item">' +
                    '<a href="' + page.url + '" class="admin-search-item live-item">' +
                        '<i class="fa-solid ' + page.icon + '"></i>' +
                        '<div class="admin-search-item-content">' +
                            '<span class="admin-search-item-title">' + escapeHtml(page.title) + '</span>' +
                            '<span class="admin-search-item-subtitle">' + escapeHtml(page.subtitle) + '</span>' +
                        '</div>' +
                    '</a>' +
                    '<div class="admin-search-actions">' +
                        renderQuickActions(page.actions) +
                    '</div>' +
                '</div>';
            });
        }

        if (hasResults) {
            liveSection.style.display = 'block';
            liveResults.innerHTML = html;
            quickNav.style.display = 'none';
            recentSection.style.display = 'none';
        } else {
            liveSection.style.display = 'block';
            liveResults.innerHTML = '<div class="admin-search-empty"><i class="fa-solid fa-magnifying-glass"></i> No results for "' + escapeHtml(query) + '"</div>';
            quickNav.style.display = 'block';
            recentSection.style.display = 'block';
        }

        activeIndex = -1;
        updateActiveItem();
    }

    function renderQuickActions(actions) {
        if (!actions || actions.length === 0) return '';
        return actions.map(function(action) {
            return '<a href="' + action.url + '" class="admin-quick-action" title="' + escapeHtml(action.label) + '">' +
                '<i class="fa-solid ' + action.icon + '"></i>' +
            '</a>';
        }).join('');
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Track page on load
    trackPageVisit();

    function openSearch() {
        if (searchModal) {
            searchModal.classList.add('open');
            renderRecentItems();
            liveSection.style.display = 'none';
            quickNav.style.display = 'block';
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
                filterItems('');
            }
            activeIndex = -1;
            updateActiveItem();
        }
    }

    function getAllSearchItems() {
        return searchResults ? searchResults.querySelectorAll('.admin-search-item') : [];
    }

    function closeSearch() {
        if (searchModal) {
            searchModal.classList.remove('open');
        }
    }

    function filterItems(query) {
        query = query.toLowerCase().trim();

        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        // If query is long enough, do AJAX search with debounce
        if (query.length >= 2) {
            searchTimeout = setTimeout(function() {
                performLiveSearch(query);
            }, 250); // 250ms debounce
        } else {
            liveSection.style.display = 'none';
            quickNav.style.display = 'block';
        }

        // Also filter static items
        var allItems = quickNav.querySelectorAll('.admin-search-item');
        allItems.forEach(function(item) {
            var searchText = (item.getAttribute('data-search') || '') + ' ' + item.textContent;
            if (query === '' || searchText.toLowerCase().includes(query)) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });

        // Hide recent section if searching
        if (recentSection) {
            var recentVisible = recentItemsContainer ? recentItemsContainer.querySelectorAll('.admin-search-item:not(.hidden)').length : 0;
            recentSection.style.display = (query === '' || recentVisible > 0) ? 'block' : 'none';
        }

        activeIndex = -1;
        updateActiveItem();
    }

    function getVisibleItems() {
        return Array.from(getAllSearchItems()).filter(function(item) {
            return !item.classList.contains('hidden');
        });
    }

    function updateActiveItem() {
        var allItems = getAllSearchItems();
        allItems.forEach(function(item) {
            item.classList.remove('active');
        });
        var visible = getVisibleItems();
        if (activeIndex >= 0 && activeIndex < visible.length) {
            visible[activeIndex].classList.add('active');
            visible[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    function navigateItems(direction) {
        var visible = getVisibleItems();
        if (visible.length === 0) return;

        if (direction === 'down') {
            activeIndex = activeIndex < visible.length - 1 ? activeIndex + 1 : 0;
        } else {
            activeIndex = activeIndex > 0 ? activeIndex - 1 : visible.length - 1;
        }
        updateActiveItem();
    }

    function selectActive() {
        var visible = getVisibleItems();
        if (activeIndex >= 0 && activeIndex < visible.length) {
            window.location.href = visible[activeIndex].href;
        } else if (visible.length > 0) {
            window.location.href = visible[0].href;
        }
    }

    // Event listeners
    if (searchTrigger) {
        searchTrigger.addEventListener('click', openSearch);
    }

    if (searchModal) {
        searchModal.querySelector('.admin-search-modal-backdrop').addEventListener('click', closeSearch);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterItems(this.value);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                navigateItems('down');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                navigateItems('up');
            } else if (e.key === 'Enter') {
                e.preventDefault();
                selectActive();
            } else if (e.key === 'Escape') {
                closeSearch();
            }
        });
    }

    // Global keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+K or Cmd+K to open search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openSearch();
        }

        // Escape to close search
        if (e.key === 'Escape' && searchModal && searchModal.classList.contains('open')) {
            closeSearch();
        }

        // Alt+shortcuts for quick navigation (only when not in input)
        if (e.altKey && !e.target.matches('input, textarea, select')) {
            var shortcutMap = {
                'u': basePath + '/admin/users',
                'l': basePath + '/admin/listings',
                's': basePath + '/admin/settings',
                'd': basePath + '/admin',
                'n': basePath + '/admin/newsletters'
            };
            var key = e.key.toLowerCase();
            if (shortcutMap[key]) {
                e.preventDefault();
                window.location.href = shortcutMap[key];
            }
        }

        // "?" to show keyboard shortcuts help
        if (e.key === '?' && !e.target.matches('input, textarea, select')) {
            e.preventDefault();
            showKeyboardHelp();
        }
    });

    function showKeyboardHelp() {
        var helpModal = document.getElementById('adminHelpModal');
        if (!helpModal) {
            helpModal = document.createElement('div');
            helpModal.id = 'adminHelpModal';
            helpModal.className = 'admin-search-modal';
            helpModal.innerHTML = '<div class="admin-search-modal-backdrop"></div>' +
                '<div class="admin-search-modal-content" style="max-width: 400px;">' +
                    '<div style="padding: 1.25rem; border-bottom: 1px solid rgba(99, 102, 241, 0.2);">' +
                        '<h3 style="margin: 0; font-size: 1.1rem; color: #fff;"><i class="fa-solid fa-keyboard" style="margin-right: 0.5rem; color: #818cf8;"></i>Keyboard Shortcuts</h3>' +
                    '</div>' +
                    '<div style="padding: 1rem;">' +
                        '<div class="admin-help-shortcuts">' +
                            '<div class="admin-help-row"><div><kbd>Ctrl</kbd><kbd>K</kbd></div><span>Open search</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>D</kbd></div><span>Dashboard</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>U</kbd></div><span>Users</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>L</kbd></div><span>Listings</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>S</kbd></div><span>Settings</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>N</kbd></div><span>Newsletters</span></div>' +
                            '<div class="admin-help-row"><div><kbd>ESC</kbd></div><span>Close modal</span></div>' +
                            '<div class="admin-help-row"><div><kbd>?</kbd></div><span>Show this help</span></div>' +
                        '</div>' +
                    '</div>' +
                    '<div style="padding: 0.75rem 1rem; border-top: 1px solid rgba(99, 102, 241, 0.15); text-align: center;">' +
                        '<span style="font-size: 0.75rem; color: rgba(255,255,255,0.4);">Press <kbd style="background: rgba(99,102,241,0.2); padding: 2px 6px; border-radius: 4px; font-size: 0.65rem;">ESC</kbd> to close</span>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(helpModal);

            helpModal.querySelector('.admin-search-modal-backdrop').addEventListener('click', function() {
                helpModal.classList.remove('open');
            });

            document.addEventListener('keydown', function(ev) {
                if (ev.key === 'Escape' && helpModal.classList.contains('open')) {
                    helpModal.classList.remove('open');
                }
            });
        }
        helpModal.classList.add('open');
    }
})();
</script>

<?php
// Include shared admin partials
$sharedAdminPartials = dirname(__DIR__, 3) . '/partials/admin';
require $sharedAdminPartials . '/admin-modals.php';
require $sharedAdminPartials . '/admin-bulk-actions.php';
require $sharedAdminPartials . '/admin-export.php';
require $sharedAdminPartials . '/admin-validation.php';
require $sharedAdminPartials . '/admin-realtime.php';
?>

