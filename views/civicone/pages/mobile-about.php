<?php
/**
 * Mobile-Only About Page - Android Launcher Style
 * Tenant-specific: Hour Timebank (tenant_id = 2)
 * Full holographic glassmorphism design - Gold Standard
 * No header/footer navigation - standalone mobile experience
 */

// Get tenant info
$tenantId = \Nexus\Core\TenantContext::getId();
$base = \Nexus\Core\TenantContext::getBasePath();

// Only show for tenant 2 (Hour Timebank)
if ($tenantId != 2) {
    header("Location: {$base}/");
    exit;
}

// Dark mode detection - defaults to dark, only light if explicitly set
$isDark = !isset($_COOKIE['nexus_mode']) || $_COOKIE['nexus_mode'] !== 'light';

// App icons configuration - ULTIMATE PREMIUM DESIGN
// Each icon has: gradient, glow colors, animation delay, and accent color for effects
$appIcons = [
    [
        'label' => 'Blog',
        'href' => '/blog',
        'icon' => 'fa-solid fa-pen-nib',
        'gradient' => 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 40%, #a855f7 100%)',
        'glow' => 'rgba(79, 70, 229, 0.45)',
        'darkGlow' => 'rgba(124, 58, 237, 0.55)',
        'accentColor' => '#a855f7',
        'animDelay' => '0s'
    ],
    [
        'label' => 'Our Story',
        'href' => '/our-story',
        'icon' => 'fa-solid fa-heart',
        'gradient' => 'linear-gradient(135deg, #db2777 0%, #ec4899 40%, #f472b6 100%)',
        'glow' => 'rgba(219, 39, 119, 0.45)',
        'darkGlow' => 'rgba(236, 72, 153, 0.55)',
        'accentColor' => '#f472b6',
        'animDelay' => '0.1s'
    ],
    [
        'label' => 'Timebanking Guide',
        'href' => '/timebanking-guide',
        'icon' => 'fa-solid fa-book-open-reader',
        'gradient' => 'linear-gradient(135deg, #0284c7 0%, #0ea5e9 40%, #38bdf8 100%)',
        'glow' => 'rgba(2, 132, 199, 0.45)',
        'darkGlow' => 'rgba(14, 165, 233, 0.55)',
        'accentColor' => '#38bdf8',
        'animDelay' => '0.2s'
    ],
    [
        'label' => 'Partners',
        'href' => '/partner',
        'icon' => 'fa-solid fa-handshake',
        'gradient' => 'linear-gradient(135deg, #059669 0%, #10b981 40%, #34d399 100%)',
        'glow' => 'rgba(5, 150, 105, 0.45)',
        'darkGlow' => 'rgba(16, 185, 129, 0.55)',
        'accentColor' => '#34d399',
        'animDelay' => '0.3s'
    ],
    [
        'label' => 'Social Prescribing',
        'href' => '/social-prescribing',
        'icon' => 'fa-solid fa-hand-holding-medical',
        'gradient' => 'linear-gradient(135deg, #d97706 0%, #f59e0b 40%, #fbbf24 100%)',
        'glow' => 'rgba(217, 119, 6, 0.45)',
        'darkGlow' => 'rgba(245, 158, 11, 0.55)',
        'accentColor' => '#fbbf24',
        'animDelay' => '0.4s'
    ],
    [
        'label' => 'FAQ',
        'href' => '/faq',
        'icon' => 'fa-solid fa-circle-question',
        'gradient' => 'linear-gradient(135deg, #7c3aed 0%, #8b5cf6 40%, #a78bfa 100%)',
        'glow' => 'rgba(124, 58, 237, 0.45)',
        'darkGlow' => 'rgba(139, 92, 246, 0.55)',
        'accentColor' => '#a78bfa',
        'animDelay' => '0.5s'
    ],
    [
        'label' => 'Impact Summary',
        'href' => '/impact-summary',
        'icon' => 'fa-solid fa-chart-line',
        'gradient' => 'linear-gradient(135deg, #e11d48 0%, #f43f5e 40%, #fb7185 100%)',
        'glow' => 'rgba(225, 29, 72, 0.45)',
        'darkGlow' => 'rgba(244, 63, 94, 0.55)',
        'accentColor' => '#fb7185',
        'animDelay' => '0.6s'
    ],
    [
        'label' => 'Impact Report',
        'href' => '/impact-report',
        'icon' => 'fa-solid fa-file-lines',
        'gradient' => 'linear-gradient(135deg, #0d9488 0%, #14b8a6 40%, #2dd4bf 100%)',
        'glow' => 'rgba(13, 148, 136, 0.45)',
        'darkGlow' => 'rgba(20, 184, 166, 0.55)',
        'accentColor' => '#2dd4bf',
        'animDelay' => '0.7s'
    ],
    [
        'label' => 'Strategic Plan',
        'href' => '/strategic-plan',
        'icon' => 'fa-solid fa-compass',
        'gradient' => 'linear-gradient(135deg, #4f46e5 0%, #ec4899 50%, #f59e0b 100%)',
        'glow' => 'rgba(79, 70, 229, 0.4)',
        'darkGlow' => 'rgba(236, 72, 153, 0.5)',
        'accentColor' => '#ec4899',
        'animDelay' => '0.8s'
    ]
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="<?= $isDark ? '#0f172a' : '#f8fafc' ?>">
    <title>About - Hour Timebank</title>

    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Mobile About CSS (extracted per CLAUDE.md) -->
    <link rel="stylesheet" href="/assets/css/civicone-mobile-about.css?v=<?= time() ?>">

</head>
<body>
    <!-- Holographic Animated Background -->
    <div class="holo-bg"></div>

    <!-- Desktop Notice -->
    <div class="desktop-notice">
        <div class="desktop-notice-content">
            <div class="desktop-notice-icon">
                <i class="fa-solid fa-mobile-screen-button"></i>
            </div>
            <h2>Mobile Experience</h2>
            <p>This page is optimized for mobile devices. Visit on your phone for the best experience.</p>
            <a href="<?= $base ?>/">
                <i class="fa-solid fa-arrow-left"></i>
                Go to Main Site
            </a>
        </div>
    </div>

    <!-- Mobile App Container -->
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <a href="<?= $base ?>/" class="header-btn" aria-label="Go back home">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <span class="header-title">About Us</span>
            <button class="header-btn" onclick="toggleTheme()" aria-label="Toggle theme">
                <i class="fa-solid <?= $isDark ? 'fa-sun' : 'fa-moon' ?> theme-icon" id="themeIcon"></i>
            </button>
        </header>

        <!-- App Grid -->
        <div class="app-grid-container">
            <div class="app-grid">
                <?php foreach ($appIcons as $index => $app):
                    $glow = $isDark ? $app['darkGlow'] : $app['glow'];
                ?>
                <a href="<?= $base . $app['href'] ?>"
                   class="app-icon"
                   style="--anim-delay: <?= $app['animDelay'] ?>; --accent-color: <?= $app['accentColor'] ?>; --icon-gradient: <?= $app['gradient'] ?>;">
                    <div class="app-icon-circle"
                         style="background: <?= $app['gradient'] ?>; box-shadow: 0 10px 30px <?= $glow ?>, 0 4px 12px rgba(0,0,0,0.15), inset 0 1px 0 rgba(255,255,255,0.2);">
                        <!-- Glow ring for pulsing effect -->
                        <span class="glow-ring" style="background: <?= $app['gradient'] ?>;"></span>
                        <!-- Shimmer overlay -->
                        <span class="shimmer"></span>
                        <!-- Ripple effect -->
                        <span class="ripple"></span>
                        <!-- Icon -->
                        <i class="<?= $app['icon'] ?>"></i>
                    </div>
                    <span class="app-icon-label"><?= htmlspecialchars($app['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Dock Bar -->
        <nav role="navigation" aria-label="Main navigation" class="dock-bar" aria-label="Quick navigation">
            <div class="dock-inner">
                <a href="<?= $base ?>/" class="dock-item dock-home" aria-label="Home">
                    <i class="fa-solid fa-house"></i>
                </a>
                <a href="<?= $base ?>/listings" class="dock-item dock-explore" aria-label="Explore listings">
                    <i class="fa-solid fa-compass"></i>
                </a>
                <a href="<?= $base ?>/dashboard" class="dock-item dock-profile" aria-label="Your dashboard">
                    <i class="fa-solid fa-user"></i>
                </a>
            </div>
        </nav>
    </div>

    <script>
        // Theme Toggle with smooth transition
        function toggleTheme() {
            const html = document.documentElement;
            const icon = document.getElementById('themeIcon');
            const isDark = html.getAttribute('data-theme') === 'dark';

            // Animate icon
            icon.style.transform = 'rotate(360deg) scale(0)';

            setTimeout(() => {
                if (isDark) {
                    html.setAttribute('data-theme', 'light');
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                    document.cookie = 'nexus_mode=light; path=/; max-age=31536000; SameSite=Lax';
                    document.querySelector('meta[name="theme-color"]').content = '#f8fafc';
                } else {
                    html.setAttribute('data-theme', 'dark');
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                    document.cookie = 'nexus_mode=dark; path=/; max-age=31536000; SameSite=Lax';
                    document.querySelector('meta[name="theme-color"]').content = '#0f172a';
                }

                icon.style.transform = 'rotate(0deg) scale(1)';

                // Update icon glow shadows based on new theme
                updateIconGlows(!isDark);
            }, 150);

            // Haptic feedback
            if ('vibrate' in navigator) {
                navigator.vibrate(10);
            }
        }

        // Update icon glow colors when theme changes
        function updateIconGlows(isDark) {
            const icons = document.querySelectorAll('.app-icon-circle');
            const glowData = <?= json_encode(array_map(fn($a) => [
                'glow' => $a['glow'],
                'darkGlow' => $a['darkGlow'],
                'accentColor' => $a['accentColor']
            ], $appIcons)) ?>;

            icons.forEach((icon, index) => {
                if (glowData[index]) {
                    const glow = isDark ? glowData[index].darkGlow : glowData[index].glow;
                    icon.style.boxShadow = `0 10px 30px ${glow}, 0 4px 12px rgba(0,0,0,0.15), inset 0 1px 0 rgba(255,255,255,0.2)`;
                }
            });
        }

        // Touch feedback for app icons
        document.querySelectorAll('.app-icon, .dock-item, .header-btn').forEach(el => {
            el.addEventListener('touchstart', function() {
                if ('vibrate' in navigator) {
                    navigator.vibrate(5);
                }
            }, { passive: true });
        });

        // Prevent overscroll/pull-to-refresh
        let startY = 0;
        document.addEventListener('touchstart', e => {
            startY = e.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchmove', e => {
            const container = document.querySelector('.app-grid-container');
            const touch = e.touches[0];
            const isScrollable = container.scrollHeight > container.clientHeight;
            const isAtTop = container.scrollTop === 0;
            const isScrollingDown = touch.clientY > startY;

            if (!e.target.closest('.app-grid-container') || (isAtTop && isScrollingDown)) {
                if (e.cancelable) {
                    e.preventDefault();
                }
            }
        }, { passive: false });

        // Smooth scroll container
        const gridContainer = document.querySelector('.app-grid-container');
        if (gridContainer) {
            gridContainer.style.scrollBehavior = 'smooth';
        }
    </script>
</body>
</html>
