<!-- MadeOpen Community Style Footer -->
<!-- Close Layout Main Container (opened in header.php) -->
</main>
<style>
    .civic-footer {
        background: var(--civic-bg-footer);
        color: var(--civic-footer-text, #E5E7EB);
        padding: 64px 0 32px;
        margin-top: 80px;
    }

    .civic-footer a {
        color: var(--civic-footer-text, #E5E7EB);
        text-decoration: none;
    }

    .civic-footer a:hover {
        color: #FFFFFF;
        text-decoration: underline;
    }

    .civic-footer-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 48px;
        margin-bottom: 48px;
    }

    .civic-footer-brand {
        max-width: 280px;
    }

    .civic-footer-logo {
        font-size: 1.5rem;
        font-weight: 800;
        color: #FFFFFF;
        margin-bottom: 16px;
        display: block;
    }

    .civic-footer-tagline {
        font-size: 15px;
        line-height: 1.6;
        opacity: 0.85;
        margin-bottom: 24px;
    }

    .civic-footer-social {
        display: flex;
        gap: 12px;
    }

    .civic-footer-social a {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .civic-footer-social a:hover {
        background: rgba(255, 255, 255, 0.2);
        text-decoration: none;
    }

    .civic-footer-column h4 {
        color: #FFFFFF;
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 20px;
    }

    .civic-footer-column ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .civic-footer-column li {
        margin-bottom: 12px;
    }

    .civic-footer-column a {
        font-size: 15px;
        opacity: 0.85;
    }

    .civic-footer-column a:hover {
        opacity: 1;
    }

    .civic-footer-bottom {
        padding-top: 32px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .civic-footer-copyright {
        font-size: 14px;
        opacity: 0.7;
    }

    .civic-footer-links {
        display: flex;
        gap: 24px;
    }

    .civic-footer-links a {
        font-size: 14px;
        opacity: 0.7;
    }

    .civic-footer-links a:hover {
        opacity: 1;
    }

    /* Responsive */
    @media (max-width: 900px) {
        .civic-footer-grid {
            grid-template-columns: 1fr 1fr;
            gap: 32px;
        }

        .civic-footer-brand {
            grid-column: span 2;
            max-width: 100%;
        }
    }

    @media (max-width: 600px) {
        .civic-footer-grid {
            grid-template-columns: 1fr;
        }

        .civic-footer-brand {
            grid-column: span 1;
        }

        .civic-footer-bottom {
            flex-direction: column;
            text-align: center;
        }
    }

    /* Hide footer on mobile - replaced by bottom nav + Legal page link in drawer */
    @media (max-width: 768px) {
        .civic-footer {
            display: none !important;
        }
    }
</style>

<footer class="civic-footer" role="contentinfo">
    <div class="civic-container">
        <?php
        $tenantFooter = '';
        $tenantName = 'Project NEXUS';
        $tenantLogo = '';
        if (class_exists('Nexus\Core\TenantContext')) {
            $t = Nexus\Core\TenantContext::get();
            $tenantName = $t['name'] ?? 'Project NEXUS';
            $tenantLogo = $t['logo_url'] ?? '';
            if (!empty($t['configuration'])) {
                $tConfig = json_decode($t['configuration'], true);
                if (!empty($tConfig['footer_text'])) {
                    $tenantFooter = $tConfig['footer_text'];
                }
            }
        }
        $basePath = Nexus\Core\TenantContext::getBasePath();
        $tSlug = $t['slug'] ?? '';
        $isHourTimebank = ($tSlug === 'hour-timebank' || $tSlug === 'hour_timebank');
        ?>

        <div class="civic-footer-grid">
            <!-- Brand Column -->
            <div class="civic-footer-brand">
                <span class="civic-footer-logo"><?= htmlspecialchars($tenantName) ?></span>
                <p class="civic-footer-tagline">
                    <?php if ($tenantFooter): ?>
                        <?= htmlspecialchars($tenantFooter) ?>
                    <?php else: ?>
                        Building stronger communities through time exchange. One hour equals one credit - everyone's time is valued equally.
                    <?php endif; ?>
                </p>
                <div class="civic-footer-social" role="list" aria-label="Social media links">
                    <a href="#" aria-label="Follow us on Facebook" title="Facebook" role="listitem">
                        <svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                        </svg>
                    </a>
                    <a href="#" aria-label="Follow us on X (formerly Twitter)" title="X (Twitter)" role="listitem">
                        <svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                        </svg>
                    </a>
                    <a href="#" aria-label="Follow us on LinkedIn" title="LinkedIn" role="listitem">
                        <svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Explore Column -->
            <div class="civic-footer-column">
                <h4>Explore</h4>
                <ul>
                    <li><a href="<?= $basePath ?>/listings">Offers & Requests</a></li>
                    <li><a href="<?= $basePath ?>/members">Community</a></li>
                    <li><a href="<?= $basePath ?>/groups">Local Hubs</a></li>
                    <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                        <li><a href="<?= $basePath ?>/events">Events</a></li>
                    <?php endif; ?>
                    <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                        <li><a href="<?= $basePath ?>/volunteering">Volunteering</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- About Column -->
            <div class="civic-footer-column">
                <h4>About</h4>
                <ul>
                    <li><a href="<?= $basePath ?>/how-it-works">How It Works</a></li>
                    <?php if ($isHourTimebank): ?>
                        <li><a href="<?= $basePath ?>/our-story">Our Story</a></li>
                        <li><a href="<?= $basePath ?>/faq">FAQ</a></li>
                    <?php endif; ?>
                    <li><a href="<?= $basePath ?>/contact">Contact Us</a></li>
                </ul>
            </div>

            <!-- Support Column -->
            <div class="civic-footer-column">
                <h4>Support</h4>
                <ul>
                    <li><a href="<?= $basePath ?>/help">Help Center</a></li>
                    <?php if ($isHourTimebank): ?>
                        <li><a href="<?= $basePath ?>/timebanking-guide">Timebanking Guide</a></li>
                        <li><a href="<?= $basePath ?>/partner">Partner With Us</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="civic-footer-bottom">
            <p class="civic-footer-copyright">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($tenantName) ?>. Built on
                <a href="https://project-nexus.ie" target="_blank" rel="noopener noreferrer">Nexus TimeBank Platform</a>.
            </p>
            <div class="civic-footer-links">
                <a href="<?= $basePath ?>/privacy">Privacy Policy</a>
                <a href="<?= $basePath ?>/terms">Terms of Service</a>
                <a href="<?= $basePath ?>/accessibility">Accessibility</a>
            </div>
        </div>
    </div>
</footer>

<!-- Mobile Bottom Navigation (Full-Screen Native Style) -->
<?php require __DIR__ . '/partials/mobile-nav-v2.php'; ?>

<!-- Mobile Bottom Sheets (Comments, etc) -->
<?php require __DIR__ . '/../../civicone/partials/mobile-sheets.php'; ?>

<!-- Nexus UI & Maps -->
<!-- Mapbox: Lazy load only when needed (no preload - conditionally loaded) -->
<script>
    // Lazy load Mapbox only when map container exists
    (function() {
        if (document.querySelector('[data-mapbox], .mapbox-container, #map, .mapbox-location-input-v2')) {
            var mapboxCSS = document.createElement('link');
            mapboxCSS.rel = 'stylesheet';
            mapboxCSS.href = 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css';
            document.head.appendChild(mapboxCSS);

            var geocoderCSS = document.createElement('link');
            geocoderCSS.rel = 'stylesheet';
            geocoderCSS.href = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.css';
            document.head.appendChild(geocoderCSS);

            var mapboxJS = document.createElement('script');
            mapboxJS.src = 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js';
            mapboxJS.onload = function() {
                var geocoderJS = document.createElement('script');
                geocoderJS.src = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js';
                document.body.appendChild(geocoderJS);
            };
            document.body.appendChild(mapboxJS);
        }
    })();
</script>

<?php
// Get Mapbox token (matches Modern footer logic)
$mapboxToken = '';
$envPath = __DIR__ . '/../../../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, 'MAPBOX_ACCESS_TOKEN=') === 0) {
            $raw = substr($line, 20);
            $mapboxToken = trim($raw, '"\' ');
            break;
        }
    }
}
if (empty($mapboxToken) && class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    if (!empty($t['configuration'])) {
        $jw = json_decode($t['configuration'], true);
        if (!empty($jw['mapbox_token'])) {
            $mapboxToken = $jw['mapbox_token'];
        }
    }
}
$jsVersion = '2.5.13';

// Get VAPID public key for Web Push notifications
$vapidPublicKey = '';
if (file_exists($envPath)) {
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, 'VAPID_PUBLIC_KEY=') === 0) {
            $vapidPublicKey = trim(substr($line, 17), '"\'');
            break;
        }
    }
}
?>
<script>
    window.NEXUS_MAPBOX_TOKEN = "<?= htmlspecialchars($mapboxToken ?? '') ?>";
    window.BASE_URL = "<?= class_exists('\\Nexus\\Core\\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '' ?>";
    window.NEXUS_VAPID_PUBLIC_KEY = "<?= htmlspecialchars($vapidPublicKey) ?>";
    // SocialInteractions configuration for shared social interactions library
    window.SocialInteractions = {
        isLoggedIn: <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>,
        config: {
            enableReactions: true,
            enableReplies: true,
            enableMentions: true,
            enableEditDelete: true,
            enableHeartBurst: true,
            enableHaptics: true
        }
    };
    // Fallback stubs in case social-interactions.js fails to load (e.g., 403 error)
    window.showLikers = window.showLikers || function() {
        console.warn('[SocialInteractions] showLikers not loaded');
    };
    window.closeLikersModal = window.closeLikersModal || function() {};
    <?php if (isset($_SESSION['user_id'])):
        // Get user email - from session or fetch from DB if not set
        $userEmail = $_SESSION['user_email'] ?? '';
        if (empty($userEmail)) {
            try {
                $user = \Nexus\Models\User::findById($_SESSION['user_id']);
                if ($user) {
                    $userEmail = $user['email'] ?? '';
                    $_SESSION['user_email'] = $userEmail;
                }
            } catch (\Exception $e) {
                // Silently fail - email not critical
            }
        }
    ?>
        window.NEXUS = window.NEXUS || {};
        window.NEXUS.userId = <?= json_encode($_SESSION['user_id']) ?>;
        window.NEXUS.userEmail = <?= json_encode($userEmail ?? '') ?>;
        <?php if (!empty($_SESSION['just_logged_in'])): ?>
            window.NEXUS.justLoggedIn = true;
            sessionStorage.setItem('nexus_just_logged_in', 'true');
        <?php unset($_SESSION['just_logged_in']);
        endif; ?>
    <?php endif; ?>
</script>
<!-- Updated 2026-01-17: All scripts now use minified versions -->
<script src="/assets/js/social-interactions.min.js?v=<?= $jsVersion ?>"></script>
<script src="/assets/js/nexus-mapbox.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-ui.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-turbo.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-auth-handler.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-native.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-mobile.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/civicone-mobile.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/civicone-native.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/civicone-pwa.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/civicone-webauthn.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Skeleton Hydration - Remove loading states after content loads -->
<script>
    (function() {
        // Mark content as hydrated when DOM is ready
        function hydrate() {
            var skeletons = document.querySelectorAll('.skeleton-container');
            var content = document.querySelectorAll('.actual-content');
            skeletons.forEach(function(el) {
                el.classList.add('hydrated');
            });
            content.forEach(function(el) {
                el.classList.add('hydrated');
            });
        }
        // Run on DOMContentLoaded or immediately if already loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hydrate);
        } else {
            hydrate();
        }
    })();
</script>
<!-- Native features for PWA/mobile -->
<script src="/assets/js/nexus-native-push.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-native-features.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-biometric.min.js?v=2.5.0" defer></script>

<?php if (isset($_SESSION['user_id'])): ?>
    <!-- Notification Drawer JavaScript (Matches Modern) -->
    <script>
        window.nexusNotifDrawer = {
            overlay: null,
            drawer: null,
            init: function() {
                this.overlay = document.getElementById('notif-drawer-overlay');
                this.drawer = document.getElementById('notif-drawer');
            },
            open: function() {
                if (!this.overlay || !this.drawer) this.init();
                if (this.overlay) this.overlay.classList.add('open');
                if (this.drawer) this.drawer.classList.add('open');
                document.body.style.overflow = 'hidden';
            },
            close: function() {
                if (!this.overlay || !this.drawer) this.init();
                if (this.overlay) this.overlay.classList.remove('open');
                if (this.drawer) this.drawer.classList.remove('open');
                document.body.style.overflow = '';
            }
        };

        // Keyboard handling for accessibility
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.nexusNotifDrawer.close();
            }
        });

        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                window.nexusNotifDrawer.init();
            });
        } else {
            window.nexusNotifDrawer.init();
        }

        // Mark all notifications as read
        window.nexusNotifications = window.nexusNotifications || {};
        window.nexusNotifications.markAllRead = function(btn) {
            fetch('<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            }).then(function(response) {
                if (response.ok) {
                    // Update UI
                    document.querySelectorAll('.notif-drawer-item').forEach(function(item) {
                        item.classList.add('is-read');
                    });
                    var badge = document.getElementById('nexus-bell-badge');
                    if (badge) badge.style.display = 'none';
                    if (btn) btn.disabled = true;
                }
            });
        };
    </script>

    <!-- Pusher Real-Time WebSocket -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        window.NEXUS_CONFIG = window.NEXUS_CONFIG || {};
        window.NEXUS_CONFIG.tenantId = <?= json_encode(\Nexus\Core\TenantContext::getId()) ?>;
        window.NEXUS_CONFIG.userId = <?= json_encode($_SESSION['user_id']) ?>;
    </script>
    <script src="/assets/js/pusher-client.min.js?v=<?= $jsVersion ?>" defer></script>
    <script src="/assets/js/notifications.min.js?v=<?= $jsVersion ?>" defer></script>
<?php endif; ?>

<!-- Layout Upgrade Prompt (shows when layout is locked) -->
<?php include __DIR__ . '/partials/layout-upgrade-prompt.php'; ?>

<!-- AI Chat Widget (Floating) -->
<?php include __DIR__ . '/partials/ai-chat-widget.php'; ?>

<!-- Loading Fix Script - defer to avoid render blocking -->
<script src="/assets/js/nexus-loading-fix.min.js?v=2.5.0" defer></script>

<!-- Resize Handler (Prevents animation jank during resize) - defer to avoid render blocking -->
<script src="/assets/js/nexus-resize-handler.min.js?v=2.5.0" defer></script>

<!-- Visual Enhancements & Micro-Interactions -->
<script src="/assets/js/nexus-transitions.min.js?v=2.5.0" defer></script>

<!-- Layout Switch URL Cleanup -->
<?php if (isset($_SESSION['_cleanup_refresh_param']) && $_SESSION['_cleanup_refresh_param']): ?>
    <script>
        // Clean up _refresh parameter from URL after layout switch
        if (window.location.search.includes('_refresh=')) {
            const url = new URL(window.location);
            url.searchParams.delete('_refresh');
            window.history.replaceState({}, '', url.toString());
        }
    </script>
    <?php unset($_SESSION['_cleanup_refresh_param']); ?>
<?php endif; ?>

</body>

</html>