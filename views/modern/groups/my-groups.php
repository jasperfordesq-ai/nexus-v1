<?php
// My Groups/Hubs - Glassmorphism 2025
$pageTitle = "My Hubs";
$pageSubtitle = "Hubs you have joined";
$hideHero = true;

require __DIR__ . '/../../layouts/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
<div id="my-groups-glass-wrapper">

    <style>
        /* ============================================
           GOLD STANDARD - Native App Features
           ============================================ */

        /* Offline Banner */
        .offline-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10001;
            padding: 12px 20px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transform: translateY(-100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .offline-banner.visible {
            transform: translateY(0);
        }

        /* Content Reveal Animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        #my-groups-glass-wrapper .group-card {
            animation: fadeInUp 0.4s ease-out;
        }

        /* Button Press States */
        .quick-action-btn:active,
        .visit-btn:active,
        .browse-btn:active,
        button:active {
            transform: scale(0.96) !important;
            transition: transform 0.1s ease !important;
        }

        /* Touch Targets - WCAG 2.1 AA (44px minimum) */
        .quick-action-btn,
        .visit-btn,
        .browse-btn,
        button {
            min-height: 44px;
        }

        /* Focus Visible */
        .quick-action-btn:focus-visible,
        .visit-btn:focus-visible,
        .browse-btn:focus-visible,
        button:focus-visible,
        a:focus-visible {
            outline: 3px solid rgba(219, 39, 119, 0.5);
            outline-offset: 2px;
        }

        /* Smooth Scroll */
        html {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        /* Mobile Responsive - Gold Standard */
        @media (max-width: 768px) {
            .quick-action-btn,
            .visit-btn,
            .browse-btn,
            button {
                min-height: 48px;
            }
        }

        /* ===================================
           GLASSMORPHISM MY GROUPS/HUBS
           Theme: Pink/Purple (#db2777)
           ================================= */

        #my-groups-glass-wrapper {
            --mg-theme: #db2777;
            --mg-theme-rgb: 219, 39, 119;
            --mg-theme-light: #ec4899;
            position: relative;
            min-height: 80vh;
            padding-top: 120px;
            padding-bottom: 60px;
        }

        /* Animated Gradient Background */
        #my-groups-glass-wrapper::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            pointer-events: none;
        }

        [data-theme="light"] #my-groups-glass-wrapper::before {
            background: linear-gradient(135deg,
                rgba(219, 39, 119, 0.08) 0%,
                rgba(236, 72, 153, 0.08) 25%,
                rgba(139, 92, 246, 0.08) 50%,
                rgba(99, 102, 241, 0.08) 75%,
                rgba(168, 85, 247, 0.08) 100%);
            background-size: 400% 400%;
            animation: mgGradientShift 15s ease infinite;
        }

        [data-theme="dark"] #my-groups-glass-wrapper::before {
            background: radial-gradient(circle at 20% 30%,
                rgba(219, 39, 119, 0.15) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%,
                rgba(139, 92, 246, 0.12) 0%, transparent 50%);
        }

        @keyframes mgGradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Page Header */
        #my-groups-glass-wrapper .page-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        #my-groups-glass-wrapper .page-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--htb-text-main);
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        #my-groups-glass-wrapper .page-header p {
            font-size: 1.1rem;
            color: var(--htb-text-muted);
            margin: 0;
        }

        /* Quick Actions */
        #my-groups-glass-wrapper .quick-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 2.5rem;
        }

        #my-groups-glass-wrapper .quick-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        [data-theme="light"] #my-groups-glass-wrapper .quick-action-btn {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(219, 39, 119, 0.2);
            color: var(--mg-theme);
        }

        [data-theme="dark"] #my-groups-glass-wrapper .quick-action-btn {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(219, 39, 119, 0.3);
            color: var(--mg-theme-light);
        }

        #my-groups-glass-wrapper .quick-action-btn:hover {
            transform: translateY(-2px);
            border-color: var(--mg-theme);
        }

        [data-theme="light"] #my-groups-glass-wrapper .quick-action-btn:hover {
            box-shadow: 0 8px 24px rgba(219, 39, 119, 0.15);
        }

        [data-theme="dark"] #my-groups-glass-wrapper .quick-action-btn:hover {
            box-shadow: 0 8px 24px rgba(219, 39, 119, 0.25);
        }

        #my-groups-glass-wrapper .quick-action-btn.primary {
            background: linear-gradient(135deg, var(--mg-theme) 0%, var(--mg-theme-light) 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 16px rgba(219, 39, 119, 0.3);
        }

        #my-groups-glass-wrapper .quick-action-btn.primary:hover {
            box-shadow: 0 8px 24px rgba(219, 39, 119, 0.4);
        }

        /* Groups Grid */
        #my-groups-glass-wrapper .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        /* Group Card */
        #my-groups-glass-wrapper .group-card {
            border-radius: 20px;
            overflow: hidden;
            backdrop-filter: blur(20px) saturate(120%);
            -webkit-backdrop-filter: blur(20px) saturate(120%);
            transition: all 0.3s ease;
        }

        [data-theme="light"] #my-groups-glass-wrapper .group-card {
            background: rgba(255, 255, 255, 0.75);
            border: 1px solid rgba(219, 39, 119, 0.15);
            box-shadow: 0 8px 32px rgba(219, 39, 119, 0.1);
        }

        [data-theme="dark"] #my-groups-glass-wrapper .group-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(219, 39, 119, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        #my-groups-glass-wrapper .group-card:hover {
            transform: translateY(-6px);
        }

        [data-theme="light"] #my-groups-glass-wrapper .group-card:hover {
            box-shadow: 0 16px 48px rgba(219, 39, 119, 0.2);
            border-color: rgba(219, 39, 119, 0.3);
        }

        [data-theme="dark"] #my-groups-glass-wrapper .group-card:hover {
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
            border-color: rgba(219, 39, 119, 0.4);
        }

        /* Card Cover */
        #my-groups-glass-wrapper .card-cover {
            height: 160px;
            position: relative;
            overflow: hidden;
        }

        #my-groups-glass-wrapper .card-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #my-groups-glass-wrapper .card-cover-gradient {
            height: 160px;
            background: linear-gradient(135deg, #db2777 0%, #ec4899 50%, #f472b6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #my-groups-glass-wrapper .card-cover-gradient i {
            font-size: 3rem;
            color: rgba(255, 255, 255, 0.8);
        }

        #my-groups-glass-wrapper .card-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255, 255, 255, 0.95);
            color: var(--mg-theme);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* Card Body */
        #my-groups-glass-wrapper .card-body {
            padding: 1.5rem;
        }

        #my-groups-glass-wrapper .card-body h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--htb-text-main);
            margin: 0 0 0.5rem 0;
        }

        #my-groups-glass-wrapper .card-body h3 a {
            text-decoration: none;
            color: inherit;
            transition: color 0.2s ease;
        }

        #my-groups-glass-wrapper .card-body h3 a:hover {
            color: var(--mg-theme);
        }

        #my-groups-glass-wrapper .card-body p {
            font-size: 0.9rem;
            color: var(--htb-text-muted);
            margin: 0 0 1rem 0;
            line-height: 1.5;
        }

        #my-groups-glass-wrapper .card-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid rgba(219, 39, 119, 0.1);
        }

        #my-groups-glass-wrapper .member-count {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--htb-text-main);
        }

        #my-groups-glass-wrapper .member-count i {
            color: var(--mg-theme);
        }

        #my-groups-glass-wrapper .visit-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            background: linear-gradient(135deg, var(--mg-theme) 0%, var(--mg-theme-light) 100%);
            color: white;
        }

        #my-groups-glass-wrapper .visit-btn:hover {
            transform: translateX(3px);
            box-shadow: 0 4px 12px rgba(219, 39, 119, 0.3);
        }

        /* Clickable Card Link Wrapper */
        #my-groups-glass-wrapper a.group-card {
            display: block;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }

        #my-groups-glass-wrapper a.group-card:hover {
            text-decoration: none;
        }

        /* Empty State */
        #my-groups-glass-wrapper .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
            border-radius: 20px;
            backdrop-filter: blur(20px) saturate(120%);
            -webkit-backdrop-filter: blur(20px) saturate(120%);
        }

        [data-theme="light"] #my-groups-glass-wrapper .empty-state {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(219, 39, 119, 0.15);
        }

        [data-theme="dark"] #my-groups-glass-wrapper .empty-state {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(219, 39, 119, 0.2);
        }

        #my-groups-glass-wrapper .empty-state .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            display: block;
        }

        #my-groups-glass-wrapper .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--htb-text-main);
            margin: 0 0 0.5rem 0;
        }

        #my-groups-glass-wrapper .empty-state p {
            color: var(--htb-text-muted);
            margin: 0 0 1.5rem 0;
        }

        #my-groups-glass-wrapper .empty-state .browse-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            background: linear-gradient(135deg, var(--mg-theme) 0%, var(--mg-theme-light) 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(219, 39, 119, 0.3);
            transition: all 0.3s ease;
        }

        #my-groups-glass-wrapper .empty-state .browse-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(219, 39, 119, 0.4);
        }

        /* Responsive */
        @media (max-width: 768px) {
            #my-groups-glass-wrapper {
                padding-top: 100px;
            }

            #my-groups-glass-wrapper .page-header h1 {
                font-size: 1.85rem;
                flex-direction: column;
                gap: 0.25rem;
            }

            #my-groups-glass-wrapper .groups-grid {
                grid-template-columns: 1fr;
            }

            #my-groups-glass-wrapper .quick-actions {
                flex-direction: column;
                align-items: stretch;
            }

            #my-groups-glass-wrapper .quick-action-btn {
                justify-content: center;
            }

            @keyframes mgGradientShift {
                0%, 100% { background-position: 50% 50%; }
            }
        }

        /* Browser Fallback */
        @supports not (backdrop-filter: blur(10px)) {
            [data-theme="light"] #my-groups-glass-wrapper .group-card,
            [data-theme="light"] #my-groups-glass-wrapper .empty-state {
                background: rgba(255, 255, 255, 0.95);
            }

            [data-theme="dark"] #my-groups-glass-wrapper .group-card,
            [data-theme="dark"] #my-groups-glass-wrapper .empty-state {
                background: rgba(30, 41, 59, 0.95);
            }
        }
    </style>

    <!-- Page Header -->
    <div class="page-header">
        <h1><span>👥</span> My Hubs</h1>
        <p>Community hubs you have joined</p>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups" class="quick-action-btn">
            <i class="fa-solid fa-compass"></i> Browse All Hubs
        </a>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/dashboard?tab=groups" class="quick-action-btn">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
    </div>

    <!-- Groups Grid -->
    <div class="groups-grid">
        <?php if (empty($myGroups)): ?>
            <div class="empty-state">
                <span class="empty-icon">🏘️</span>
                <h3>No hubs yet</h3>
                <p>You haven't joined any community hubs. Explore and find groups that match your interests!</p>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups" class="browse-btn">
                    <i class="fa-solid fa-compass"></i> Browse Hubs
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($myGroups as $group): ?>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>" class="group-card">
                    <!-- Cover Image -->
                    <?php
                    $displayImage = !empty($group['cover_image_url']) ? $group['cover_image_url'] : ($group['image_url'] ?? '');
                    ?>
                    <?php if (!empty($displayImage)): ?>
                        <div class="card-cover">
                            <img src="<?= htmlspecialchars($displayImage) ?>" loading="lazy" alt="<?= htmlspecialchars($group['name']) ?>">
                            <span class="card-badge">MEMBER</span>
                        </div>
                    <?php else: ?>
                        <div class="card-cover card-cover-gradient">
                            <i class="fa-solid fa-users-rectangle"></i>
                            <span class="card-badge">MEMBER</span>
                        </div>
                    <?php endif; ?>

                    <!-- Card Body -->
                    <div class="card-body">
                        <h3><?= htmlspecialchars($group['name']) ?></h3>
                        <p><?= htmlspecialchars(substr($group['description'] ?? 'A community hub for members to connect and collaborate.', 0, 100)) ?>...</p>

                        <div class="card-meta">
                            <div class="member-count">
                                <i class="fa-solid fa-user-group"></i>
                                <span><?= $group['member_count'] ?? 0 ?> members</span>
                            </div>
                            <span class="visit-btn">
                                Enter <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div><!-- #my-groups-glass-wrapper -->
</div>

<script>
// ============================================
// GOLD STANDARD - Native App Features
// ============================================

// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Button Press States
document.querySelectorAll('.quick-action-btn, .visit-btn, .browse-btn, button').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#db2777';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#db2777');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
