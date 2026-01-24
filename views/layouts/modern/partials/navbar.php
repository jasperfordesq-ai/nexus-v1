<?php
/**
 * Modern Layout - Navbar Partial
 * Contains: brand link, navigation links, mega menus, search, mobile actions
 */

$tName = Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
if (Nexus\Core\TenantContext::getId() == 1) $tName = 'Project NEXUS';

// Helper function to check if current page matches nav link
function isCurrentPage($path) {
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $currentPath = rtrim($currentPath, '/');
    $basePath = rtrim(\Nexus\Core\TenantContext::getBasePath(), '/');

    // Normalize the check path
    $checkPath = $basePath . $path;
    $checkPath = rtrim($checkPath, '/');

    // Exact match or home page
    if ($path === '/' || $path === '') {
        return $currentPath === $basePath || $currentPath === $basePath . '/home' || $currentPath === '' || $currentPath === '/';
    }

    // Check if current path starts with the nav path (for sub-pages)
    return $currentPath === $checkPath || strpos($currentPath, $checkPath . '/') === 0;
}

// Smart word boundary split for brand name
$knownSplits = [
    'timebankireland' => ['TIMEBANK', 'IRELAND'],
    'timebankire land' => ['TIMEBANK', 'IRELAND'],
    'timebank ireland' => ['TIMEBANK', 'IRELAND'],
    'projectnexus' => ['PROJECT', 'NEXUS'],
    'project nexus' => ['PROJECT', 'NEXUS'],
];

$lowerName = strtolower(trim($tName));
if (isset($knownSplits[$lowerName])) {
    $tFirst = $knownSplits[$lowerName][0];
    $tRest = $knownSplits[$lowerName][1];
} elseif (strpos($tName, ' ') !== false) {
    $tParts = explode(' ', $tName, 2);
    $tFirst = strtoupper($tParts[0]);
    $tRest = strtoupper($tParts[1] ?? '');
} else {
    $tFirst = strtoupper($tName);
    $tRest = '';
}

$basePath = Nexus\Core\TenantContext::getBasePath();
?>
<header class="nexus-navbar">
    <a href="<?= $basePath ?: '/' ?>" class="nexus-brand-link" aria-label="<?= htmlspecialchars($tName) ?> - Go to homepage">
        <span class="brand-primary"><?= htmlspecialchars($tFirst) ?></span><?php if ($tRest): ?><span class="brand-secondary"><?= htmlspecialchars($tRest) ?></span><?php endif; ?>
    </a>

    <div class="desktop-only desktop-header-utils">
        <!-- Core Navigation Links (Always Visible) -->
        <a href="<?= $basePath ?>/" class="nav-link" data-nav-match="/"<?= isCurrentPage('/') ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-house"></i> Feed
        </a>
        <a href="<?= $basePath ?>/listings" class="nav-link" data-nav-match="listings"<?= isCurrentPage('/listings') ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-hand-holding-heart"></i> Listings
        </a>
        <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
            <a href="<?= $basePath ?>/volunteering" class="nav-link" data-nav-match="volunteering"<?= isCurrentPage('/volunteering') ? ' aria-current="page"' : '' ?>>
                <i class="fa-solid fa-handshake-angle"></i> Volunteering
            </a>
        <?php endif; ?>

        <!-- Premium Glassmorphism Mega Menus -->
        <?php require __DIR__ . '/premium-mega-menu.php'; ?>

        <?php
        // Database-driven pages (Page Builder) - shown as top-level nav links
        $dbPagesMain = \Nexus\Core\MenuGenerator::getMenuPages('main');
        foreach ($dbPagesMain as $mainPage):
        ?>
            <a href="<?= htmlspecialchars($mainPage['url']) ?>" class="nav-link"><i class="fa-solid fa-file-lines nav-icon--page"></i><?= htmlspecialchars($mainPage['title']) ?></a>
        <?php endforeach; ?>

        <!-- Collapsible Search Container -->
        <div class="collapsible-search-container">
            <!-- Search Toggle Button (visible when collapsed) -->
            <button type="button" class="search-toggle-btn" id="searchToggleBtn" aria-label="Open search" aria-expanded="false">
                <i class="fa fa-search"></i>
            </button>

            <!-- Expandable Search Form -->
            <form action="<?= $basePath ?>/search" method="GET" class="htb-search-box premium-search collapsible-search" id="collapsibleSearch">
                <button type="submit" class="search-icon-btn" aria-label="Submit search">
                    <i class="fa fa-search" aria-hidden="true"></i>
                </button>
                <input type="text" name="q" placeholder="Search Nexus..." aria-label="Search" class="search-input" id="searchInput">
                <button type="button" class="search-close-btn" id="searchCloseBtn" aria-label="Close search">
                    <i class="fa fa-times"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Mobile Right Actions (Notifications + Menu) -->
    <div class="nexus-mobile-actions">
        <?php if (isset($_SESSION['user_id'])):
            $mobileNotifCount = 0;
            if (class_exists('\Nexus\Models\Notification')) {
                try {
                    $mobileNotifCount = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
                } catch (\Exception $e) {
                    $mobileNotifCount = 0;
                }
            }
        ?>
            <!-- Mobile Notifications Bell -->
            <button class="nexus-notif-btn" aria-label="Notifications" onclick="if(typeof openMobileNotifications==='function'){openMobileNotifications();}else{window.location.href='<?= $basePath ?>/notifications';}" aria-haspopup="true">
                <i class="fa-solid fa-bell"></i>
                <?php if ($mobileNotifCount > 0): ?>
                    <span class="nexus-notif-badge"><?= $mobileNotifCount > 99 ? '99+' : $mobileNotifCount ?></span>
                <?php endif; ?>
            </button>
        <?php endif; ?>

        <!-- Mobile Menu Button -->
        <button class="nexus-menu-btn" aria-label="Open Menu" onclick="if(typeof openMobileMenu==='function'){openMobileMenu();}else{var nav=document.getElementById('mobile-nav-drawer');if(nav){nav.classList.add('open');document.body.classList.add('mobile-nav-open');}}">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</header>
