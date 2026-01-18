module.exports = {
    // Content to scan for used classes
    content: [
        'views/**/*.php',
        'httpdocs/**/*.php',
        'httpdocs/assets/js/**/*.js',
        'src/**/*.php',
    ],

    // CSS files to purge
    // Updated 2026-01-18: Added bundle files and page-specific CSS
    css: [
        // Core framework
        'httpdocs/assets/css/nexus-phoenix.css',
        'httpdocs/assets/css/nexus-mobile.css',
        'httpdocs/assets/css/nexus-shared-transitions.css',
        'httpdocs/assets/css/post-box-home.css',
        // Header and loading
        'httpdocs/assets/css/nexus-loading-fix.css',
        'httpdocs/assets/css/nexus-performance-patch.css',
        'httpdocs/assets/css/nexus-modern-header.css',
        // Premium components
        'httpdocs/assets/css/premium-search.css',
        'httpdocs/assets/css/premium-dropdowns.css',
        'httpdocs/assets/css/nexus-premium-mega-menu.css',
        // Consolidated polish (replaces 5 separate files)
        'httpdocs/assets/css/nexus-polish.css',
        'httpdocs/assets/css/nexus-interactions.css',
        // Navigation (v2 only)
        'httpdocs/assets/css/nexus-native-nav-v2.css',
        // Bundle files
        'httpdocs/assets/css/modern-bundle-compiled.css',
        'httpdocs/assets/css/civicone-bundle-compiled.css',
        // Page-specific
        'httpdocs/assets/css/nexus-home.css',
        'httpdocs/assets/css/profile-holographic.css',
        // Extracted component CSS
        'httpdocs/assets/css/feed-filter.css',
        'httpdocs/assets/css/dashboard.css',
        'httpdocs/assets/css/mobile-sheets.css',
        'httpdocs/assets/css/social-interactions.css',
        'httpdocs/assets/css/strategic-plan.css',
        'httpdocs/assets/css/federation-realtime.css',
        'httpdocs/assets/css/compose-multidraw.css',
        'httpdocs/assets/css/pwa-install-modal.css',
        'httpdocs/assets/css/achievements.css',
        'httpdocs/assets/css/sidebar.css',
        'httpdocs/assets/css/nexus-score.css',
    ],

    // Output directory for purged CSS
    output: 'httpdocs/assets/css/purged/',

    // Safelist - classes to never remove
    safelist: {
        // Exact class names to keep
        standard: [
            // Dynamic state classes
            'active', 'open', 'closed', 'visible', 'hidden', 'show', 'hide',
            'loading', 'loaded', 'error', 'success', 'warning', 'info',
            'disabled', 'enabled', 'selected', 'checked', 'focused',
            'expanded', 'collapsed', 'animating', 'animated',
            'scrolled', 'sticky', 'fixed', 'ready', 'valid', 'invalid',
            'dark', 'light', 'mobile', 'desktop', 'tablet',

            // App state classes
            'verified-offline', 'hydrated', 'content-loaded',
            'no-ptr', 'chat-page', 'messages-fullscreen',
            'logged-in', 'user-is-admin',
            'nexus-home-page', 'nexus-skin-modern',
            'feed-loaded', 'page-loaded', 'fonts-loaded',
            'is-pwa', 'is-pwa-installed', 'is-native', 'is-native-app',
            'is-offline', 'pwa-installed', 'push-enabled',

            // Navigation states
            'drawer-open', 'nav-hidden', 'navigating', 'navigating-back',
            'hide-on-scroll', 'hiding', 'keyboard-open',
            'search-expanded', 'layout-switching',

            // Header states
            'nexus-header-compact', 'nexus-header-hidden', 'nexus-header-visible',
            'nexus-header-is-compact', 'nexus-header-is-hidden',
            'nexus-collapsing-header', 'back-nav',

            // Animation classes
            'fade-in', 'fade-out', 'slide-in', 'slide-out',
            'bounce', 'shake', 'exit', 'closing',
            'nexus-content-enter', 'nexus-skeleton-exit',
            'page-transition-enter', 'page-transitioning', 'page-hidden',
            'view-transition-complete', 'turbo-loading',

            // Interaction states
            'tap', 'tapped', 'tap-highlight', 'touch-active',
            'ripple', 'ripple-effect', 'rippling',
            'dragging', 'pulling', 'resizing', 'long-press',
            'tilt-active', 'like-pop', 'liked',

            // Toast/modal classes
            'nexus-toast', 'nexus-toast-container',
            'fds-toast', 'civic-toast-container',
            'revealed', 'reveal-hidden', 'removed', 'complete',

            // Button states
            'btn-success', 'btn-error', 'btn-loading',

            // Optimistic UI
            'nexus-optimistic-comment', 'nexus-optimistic-error',
            'nexus-optimistic-pending', 'nexus-optimistic-success',

            // Skeleton/loading
            'nexus-loading', 'nexus-loading-bar', 'nexus-loading-overlay',
            'nexus-page-skeleton', 'nexus-skeleton-container',
            'skeleton-card-full', 'nexus-infinite-loader', 'nexus-infinite-end',

            // Civic theme classes
            'civic-badge', 'civic-badge--neutral', 'civic-badge--success',
            'civic-bottom-sheet', 'civic-bottom-sheet-backdrop',
            'civic-offline-bar', 'civic-offline-indicator', 'civic-online-indicator',
            'civic-pwa-banner', 'civic-update-banner',

            // Native app classes
            'nexus-native-nav-enabled', 'nexus-native-nav-badge',
            'has-bottom-nav', 'has-civic-bottom-nav',
            'biometrics-available', 'webauthn-supported', 'webauthn-registered',

            // Form states
            'has-error', 'has-success', 'has-warning',
            'is-active', 'is-open', 'is-visible', 'is-hidden', 'is-loading',

            // Third party
            'jGravity-active', 'mapbox-geocoder-container',
        ],

        // Patterns to keep (regex)
        deep: [
            // Keep all Font Awesome classes
            /^fa-/,
            /^fas$/,
            /^far$/,
            /^fab$/,
            /^fal$/,
            /^fad$/,

            // Keep all data-theme variations
            /data-theme/,

            // Keep all nexus- prefixed classes
            /^nexus-/,

            // Keep all civic- prefixed classes
            /^civic-/,

            // Keep all fds- prefixed classes
            /^fds-/,

            // Keep all htb- prefixed classes (timebank specific)
            /^htb-/,

            // Keep all fed- prefixed classes (federation)
            /^fed-/,

            // Keep all glass- prefixed classes
            /^glass-/,

            // Home page feed classes
            /^feed-/,
            /^composer-/,
            /^compose-/,
            /^sidebar-/,
            /^fb-/,
            /^comment/,
            /^liked/,
            /^emoji-/,
            /^edgerank/,
            /^sdg-/,
            /^home-/,
            /^infinite-/,
            /^offline-/,
            /^online-/,

            // Profile page classes
            /^badge/,
            /^profile-/,
            /^holo-/,

            // Achievement/Gamification pages
            /^achievement/,
            /^challenge/,
            /^collection/,
            /^season/,
            /^shop-/,
            /^xp-/,
            /^streak/,
            /^rank/,
            /^level/,
            /^progress/,
            /^leaderboard/,
            /^reward/,
            /^tier/,
            /^rarity/,
            /^showcase/,
            /^confetti/,
            /^hero-/,
            /^activity-/,
            /^cta-/,

            // Compose multidraw
            /^md-/,
            /^multidraw-/,
            /^rating-/,
            /^featured-/,
            /^reviews-/,
            /^star-/,

            // Avatar and images
            /^avatar/,
            /^blur-/,
            /^card-/,
            /^thumbnail/,

            // Modern theme - UI components
            /^btn-/,
            /^modal-/,
            /^drawer-/,
            /^dropdown-/,
            /^mega-/,
            /^nav-/,
            /^notif/,
            /^premium-/,
            /^search-/,
            /^icon-/,
            /^chip/,

            // Modern theme - layout
            /^hero-/,
            /^main-/,
            /^form-/,
            /^page-/,
            /^section-/,
            /^container/,
            /^grid/,
            /^col-/,
            /^row/,

            // Modern theme - content
            /^event-/,
            /^listing-/,
            /^member-/,
            /^group-/,
            /^message-/,
            /^post-/,
            /^create-/,

            // Modern theme - effects
            /^gradient/,
            /^glow/,
            /^blur/,
            /^animate/,
            /^transition/,
            /^pulse/,
            /^shimmer/,
            /^ripple/,

            // Modern theme - states
            /^is-/,
            /^has-/,
            /^light-/,
            /^dark-/,
            /mode$/,
            /^load/,
            /^clos/,

            // FAB buttons
            /fab$/,
            /^fab-/,

            // Keep all skeleton classes
            /skeleton/,

            // Keep all toast classes
            /toast/,

            // Keep animation keyframe names
            /@keyframes/,

            // Keep media query content
            /@media/,

            // Keep CSS variables
            /^--/,

            // Keep pseudo-elements
            /::before/,
            /::after/,

            // Keep responsive prefixes if any
            /^sm:/,
            /^md:/,
            /^lg:/,
            /^xl:/,
        ],

        // Keep classes matching these patterns in selectors
        greedy: [
            // Keep hover/focus/active state variations
            /hover/,
            /focus/,
            /active/,
            /visited/,

            // Keep responsive variations
            /mobile/,
            /desktop/,
            /tablet/,
        ],
    },

    // Keep font-face declarations
    fontFace: true,

    // Keep keyframes
    keyframes: true,

    // Keep CSS variables
    variables: true,
};
