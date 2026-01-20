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
$envPath = __DIR__ . '/../../../../.env';
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
<script src="/assets/js/mobile-select-sheet.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/mobile-search-overlay.js?v=<?= $jsVersion ?>" defer></script>

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
<?php include __DIR__ . '/layout-upgrade-prompt.php'; ?>

<!-- AI Chat Widget (Floating) -->
<?php include __DIR__ . '/ai-chat-widget.php'; ?>

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
