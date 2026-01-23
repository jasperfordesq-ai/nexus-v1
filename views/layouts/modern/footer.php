</main><!-- End main-content -->

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
$footerBasePath = Nexus\Core\TenantContext::getBasePath();
$tSlug = $t['slug'] ?? '';
$isHourTimebank = ($tSlug === 'hour-timebank' || $tSlug === 'hour_timebank');

// Get tenant contact email - use contact_email column, fallback to default
$tenantContactEmail = $t['contact_email'] ?? 'support@project-nexus.ie';
?>

<!-- Footer CSS now loaded via header.php: nexus-modern-footer.min.css -->

<footer class="nexus-modern-footer nexus-desktop-only">
    <!-- Ambient Glow Effects -->
    <div class="nexus-footer-glow nexus-footer-glow-1"></div>
    <div class="nexus-footer-glow nexus-footer-glow-2"></div>

    <div class="nexus-footer-inner">
        <!-- Main Grid -->
        <div class="nexus-footer-grid">
            <!-- Brand Column -->
            <div class="nexus-footer-brand">
                <a href="<?= $footerBasePath ?>/" class="nexus-footer-logo">
                    <div class="nexus-footer-logo-icon">
                        <?php if ($tenantLogo): ?>
                            <img src="<?= htmlspecialchars($tenantLogo) ?>" alt="<?= htmlspecialchars($tenantName) ?>">
                        <?php else: ?>
                            <i class="fa-solid fa-infinity"></i>
                        <?php endif; ?>
                    </div>
                    <span class="nexus-footer-logo-text"><?= htmlspecialchars($tenantName) ?></span>
                </a>
                <p class="nexus-footer-description">
                    Building stronger communities through time-based exchange. Share skills, earn credits, and connect with neighbors.
                </p>
                <div class="nexus-footer-social">
                    <a href="mailto:<?= htmlspecialchars($tenantContactEmail) ?>" title="Email Us">
                        <i class="fa-solid fa-envelope"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links Column -->
            <div class="nexus-footer-column">
                <h4>Explore</h4>
                <nav class="nexus-footer-links">
                    <a href="<?= $footerBasePath ?>/listings"><i class="fa-solid fa-chevron-right"></i> Offers & Requests</a>
                    <a href="<?= $footerBasePath ?>/members"><i class="fa-solid fa-chevron-right"></i> Community</a>
                    <a href="<?= $footerBasePath ?>/groups"><i class="fa-solid fa-chevron-right"></i> Local Hubs</a>
                    <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                        <a href="<?= $footerBasePath ?>/events"><i class="fa-solid fa-chevron-right"></i> Events</a>
                    <?php endif; ?>
                    <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                        <a href="<?= $footerBasePath ?>/volunteering"><i class="fa-solid fa-chevron-right"></i> Volunteering</a>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- Support Column -->
            <div class="nexus-footer-column">
                <h4>Support</h4>
                <nav class="nexus-footer-links">
                    <a href="<?= $footerBasePath ?>/help"><i class="fa-solid fa-chevron-right"></i> Help Center</a>
                    <?php if ($isHourTimebank): ?>
                        <a href="<?= $footerBasePath ?>/faq"><i class="fa-solid fa-chevron-right"></i> FAQ</a>
                    <?php endif; ?>
                    <a href="<?= $footerBasePath ?>/contact"><i class="fa-solid fa-chevron-right"></i> Contact Us</a>
                    <a href="<?= $footerBasePath ?>/mobile-download"><i class="fa-solid fa-chevron-right"></i> Get the App</a>
                    <a href="https://project-nexus.canny.io/" target="_blank" rel="noopener"><i class="fa-solid fa-bug"></i> Report Bug / Request Feature</a>
                </nav>
            </div>

            <?php
            // Footer pages from Page Builder
            $footerPages = \Nexus\Core\MenuGenerator::getMenuPages('footer');
            if (!empty($footerPages)):
            ?>
                <!-- Custom Pages Column -->
                <div class="nexus-footer-column">
                    <h4>Pages</h4>
                    <nav class="nexus-footer-links">
                        <?php foreach ($footerPages as $fPage): ?>
                            <a href="<?= htmlspecialchars($fPage['url']) ?>"><i class="fa-solid fa-chevron-right"></i> <?= htmlspecialchars($fPage['title']) ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($tenantFooter): ?>
            <!-- Custom Tenant Message -->
            <div class="nexus-footer-message">
                <p><?= preg_replace('/\r?\n/', ' ', htmlspecialchars($tenantFooter)) ?></p>
            </div>
        <?php endif; ?>

        <!-- Bottom Bar -->
        <div class="nexus-footer-bottom">
            <p class="nexus-footer-copyright">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($tenantName) ?>. Powered by <a href="https://project-nexus.ie" target="_blank" rel="noopener">Project NEXUS</a>
            </p>
            <nav class="nexus-footer-legal">
                <a href="<?= $footerBasePath ?>/privacy">Privacy Policy</a>
                <a href="<?= $footerBasePath ?>/terms">Terms of Service</a>
            </nav>
        </div>
    </div>
</footer>
<!-- Nexus UI -->
<!-- Mapbox: Lazy load only when map container exists -->
<script>
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

            var style = document.createElement('style');
            style.textContent = '.mapboxgl-ctrl-geocoder { min-width: 100%; width: 100%; box-shadow: none; border: 1px solid #d1d5db; border-radius: 6px; }';
            document.head.appendChild(style);

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
// Get Mapbox token
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
?>
<script>
    window.NEXUS_MAPBOX_TOKEN = "<?= htmlspecialchars($mapboxToken ?? '') ?>";
    window.BASE_URL = "<?= class_exists('\\Nexus\\Core\\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '' ?>";
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
</script>
<!-- Dev Notice Modal - Preview mode notification for users -->
<script src="/assets/js/dev-notice-modal.min.js?v=<?= $jsVersion ?>"></script>
<script src="/assets/js/nexus-mapbox.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/social-interactions.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-ui.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-turbo.min.js?v=2.5.0" defer></script>
<script src="/assets/js/nexus-auth-handler.min.js?v=2.5.0" defer></script>
<script src="/assets/js/nexus-app-updater.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-native.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-mobile.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-capacitor-bridge.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/mobile-nav-v2.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/mobile-select-sheet.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/mobile-search-overlay.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-shared-transitions.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Visual Polish Helpers - Auto-apply micro-interactions and provide utility functions -->
<script src="/assets/js/polish-helpers.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Toast Notifications - Slide-in animations, stacking, auto-dismiss -->
<script src="/assets/js/toast-notifications.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Page Transitions - Smooth fade/slide between pages -->
<script src="/assets/js/page-transitions.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Pull-to-Refresh - Native iOS/Android style (mobile only) -->
<script src="/assets/js/pull-to-refresh.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Button Ripple Effects - Material-style touch feedback -->
<script src="/assets/js/button-ripple.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Form Validation - Shake on error, checkmark on success -->
<script src="/assets/js/form-validation.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Avatar Placeholders - Shimmer loading, initials fallback -->
<script src="/assets/js/avatar-placeholders.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Scroll Progress - Top bar showing page scroll position -->
<script src="/assets/js/scroll-progress.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- FAB Polish - Floating action button animations -->
<script src="/assets/js/fab-polish.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Badge Animations - Pop effect when count changes -->
<script src="/assets/js/badge-animations.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Error States - Friendly error pages, offline detection -->
<script src="/assets/js/error-states.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Image Lazy Loading - Progressive blur-up effect (2026-01-19) -->
<script src="/assets/js/image-lazy-load.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- 2026-01-17: Removed old polish JS (nexus-10x-polish, nexus-ux-polish, nexus-native-nav v1) -->
<!-- CSS consolidated into nexus-polish.css and nexus-interactions.css -->
<?php
// Get VAPID public key for Web Push notifications
$vapidPublicKey = '';
$envPath = dirname(__DIR__, 3) . '/.env';
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
    window.NEXUS_VAPID_PUBLIC_KEY = "<?= htmlspecialchars($vapidPublicKey) ?>";
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
<script src="/assets/js/nexus-pwa.min.js?v=<?= $jsVersion ?>" defer></script>
<!-- Native features for Capacitor/Android app (only runs if in native context) -->
<script src="/assets/js/nexus-native-push.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-native-features.min.js?v=<?= $jsVersion ?>" defer></script>
<script src="/assets/js/nexus-biometric.min.js?v=2.5.0" defer></script>

<?php if (isset($_SESSION['user_id'])): ?>
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

<?php if (isset($_SESSION['user_id'])): ?>
    <!-- Biometric Setup Prompt (Shows once after login on supported devices) -->
    <div id="biometric-setup-modal">
        <div class="biometric-modal-content">
            <div class="biometric-modal-handle"></div>

            <div class="biometric-modal-header">
                <div class="biometric-modal-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364a6 6 0 0112 0c0 .894-.074 1.771-.214 2.626M5 11a7 7 0 1114 0"></path>
                    </svg>
                </div>
                <h3 class="biometric-modal-title">Enable Biometric Login</h3>
                <p class="biometric-modal-description">Sign in instantly with your fingerprint or Face ID next time. Fast, secure, and convenient.</p>
            </div>

            <button id="btn-setup-biometric-now">
                <i class="fas fa-fingerprint"></i> Set Up Now
            </button>

            <button id="btn-skip-biometric">
                Maybe Later
            </button>
        </div>
    </div>

    <!-- Feed action pills CSS now loaded via header.php: feed-action-pills.css -->

    <script>
        (function() {
            // Skip in native Capacitor app - nexus-biometric.js handles native biometrics
            if (window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) {
                console.log('[Footer Biometric] Skipping WebAuthn - native app uses Capacitor biometric plugin');
                return;
            }

            // Skip if dismissed or already enrolled this session
            const dismissed = sessionStorage.getItem('biometric_prompt_dismissed');
            const enrolled = sessionStorage.getItem('biometric_enrolled');
            if (dismissed || enrolled) return;

            async function checkAndShowPrompt() {
                // Check WebAuthn support
                if (!window.PublicKeyCredential) return;

                try {
                    const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
                    if (!available) return;

                    // Check if user has biometrics in database
                    const response = await fetch('/api/webauthn/status', {
                        credentials: 'include'
                    });
                    const data = await response.json();

                    // Only show prompt if user has NO credentials at all
                    // If they have credentials on another device, let them add more via Settings
                    if (!data.registered || data.count === 0) {
                        document.getElementById('biometric-setup-modal').classList.add('active');
                    }
                    // If user already has credentials, don't show popup - they can add this device via Settings > Security
                } catch (e) {
                    console.log('[Biometric] Check failed:', e);
                }
            }

            // Setup button
            document.getElementById('btn-setup-biometric-now')?.addEventListener('click', async function() {
                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Setting up...';

                try {
                    // Get registration challenge
                    const challengeResponse = await fetch('/api/webauthn/register-challenge', {
                        method: 'POST',
                        credentials: 'include'
                    });

                    if (!challengeResponse.ok) throw new Error('Failed to get challenge');

                    const options = await challengeResponse.json();

                    // Convert base64url to ArrayBuffer
                    options.challenge = base64UrlToBuffer(options.challenge);
                    options.user.id = base64UrlToBuffer(options.user.id);
                    if (options.excludeCredentials) {
                        options.excludeCredentials = options.excludeCredentials.map(c => ({
                            ...c,
                            id: base64UrlToBuffer(c.id)
                        }));
                    }

                    // Create credential (triggers biometric)
                    const credential = await navigator.credentials.create({
                        publicKey: options
                    });

                    // Verify with server
                    const verifyResponse = await fetch('/api/webauthn/register-verify', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            id: credential.id,
                            rawId: bufferToBase64Url(credential.rawId),
                            type: credential.type,
                            response: {
                                clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                                attestationObject: bufferToBase64Url(credential.response.attestationObject)
                            }
                        })
                    });

                    if (verifyResponse.ok) {
                        // Mark as enrolled to prevent re-prompting
                        sessionStorage.setItem('biometric_enrolled', '1');
                        document.getElementById('biometric-setup-modal').classList.remove('active');

                        // Show success toast
                        if (window.NexusMobile && NexusMobile.showToast) {
                            NexusMobile.showToast('Biometric login enabled!', 'success');
                        } else {
                            alert('Biometric login enabled! Use your fingerprint or Face ID next time.');
                        }
                    } else {
                        throw new Error('Registration failed');
                    }
                } catch (e) {
                    console.error('[Biometric]', e);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    if (e.name !== 'NotAllowedError') {
                        alert('Setup failed: ' + e.message);
                    }
                }
            });

            // Skip button
            document.getElementById('btn-skip-biometric')?.addEventListener('click', function() {
                sessionStorage.setItem('biometric_prompt_dismissed', '1');
                document.getElementById('biometric-setup-modal').classList.remove('active');
            });

            // Close on backdrop click
            document.getElementById('biometric-setup-modal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    sessionStorage.setItem('biometric_prompt_dismissed', '1');
                    this.style.display = 'none';
                }
            });

            // Helper functions
            function base64UrlToBuffer(base64url) {
                const padding = '='.repeat((4 - base64url.length % 4) % 4);
                const base64 = (base64url + padding).replace(/-/g, '+').replace(/_/g, '/');
                const rawData = atob(base64);
                const buffer = new Uint8Array(rawData.length);
                for (let i = 0; i < rawData.length; i++) buffer[i] = rawData.charCodeAt(i);
                return buffer.buffer;
            }

            function bufferToBase64Url(buffer) {
                const bytes = new Uint8Array(buffer);
                let binary = '';
                for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
                return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
            }

            // Check immediately on page load
            checkAndShowPrompt();
        })();
    </script>
<?php endif; ?>

<!-- Mobile Navigation v2 (Full-Screen Native Style) -->
<?php include __DIR__ . '/partials/mobile-nav-v2.php'; ?>

<!-- Mobile Bottom Sheets (Comments, etc) -->
<?php include __DIR__ . '/../../modern/partials/mobile-sheets.php'; ?>

<!-- AI Chat Widget (Floating) -->
<?php include __DIR__ . '/partials/ai-chat-widget.php'; ?>

<!-- Layout Upgrade Prompt (removed - feature deprecated) -->

<!-- CRITICAL: Loading Fix Script (defer to avoid render blocking) -->
<script src="/assets/js/nexus-loading-fix.min.js?v=2.5.0" defer></script>

<!-- Navigation Active Indicator Fix -->

<!-- Resize Handler (Prevents animation jank during resize) -->
<script src="/assets/js/nexus-resize-handler.min.js?v=2.5.0" defer></script>

<!-- Visual Enhancements & Micro-Interactions -->
<script src="/assets/js/nexus-transitions.min.js?v=2.5.0" defer></script>

<!-- Flash Message Toast Handler -->
<?php if (!empty($_SESSION['flash_message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Wait for Toast library to be available
            var checkToast = setInterval(function() {
                if (typeof Toast !== 'undefined') {
                    clearInterval(checkToast);
                    var type = '<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?>';
                    var message = '<?= addslashes(htmlspecialchars($_SESSION['flash_message'])) ?>';
                    if (type === 'success') Toast.success('Success', message);
                    else if (type === 'error') Toast.error('Error', message);
                    else if (type === 'warning') Toast.warning('Warning', message);
                    else Toast.info('Notice', message);
                }
            }, 100);
            // Timeout after 5 seconds to avoid infinite loop
            setTimeout(function() { clearInterval(checkToast); }, 5000);
        });
    </script>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>

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