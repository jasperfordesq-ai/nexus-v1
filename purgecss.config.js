module.exports = {
    // Content to scan for used classes
    content: [
        'views/**/*.php',
        'httpdocs/**/*.php',
        'httpdocs/assets/js/**/*.js',
        'src/**/*.php',
    ],

    // CSS files to purge
    // Updated 2026-01-20: Complete audit - all CSS files included
    css: [
        // Core framework
        'httpdocs/assets/css/nexus-phoenix.css',
        'httpdocs/assets/css/nexus-mobile.css',
        'httpdocs/assets/css/nexus-shared-transitions.css',
        'httpdocs/assets/css/nexus-home.css',
        'httpdocs/assets/css/nexus-score.css',
        'httpdocs/assets/css/nexus-groups.css',
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
        'httpdocs/assets/css/mobile-design-tokens.css',
        'httpdocs/assets/css/mobile-accessibility-fixes.css',
        'httpdocs/assets/css/mobile-loading-states.css',
        'httpdocs/assets/css/mobile-micro-interactions.css',
        'httpdocs/assets/css/social-interactions.css',
        'httpdocs/assets/css/strategic-plan.css',
        'httpdocs/assets/css/federation-realtime.css',
        'httpdocs/assets/css/compose-multidraw.css',
        'httpdocs/assets/css/pwa-install-modal.css',
        'httpdocs/assets/css/achievements.css',
        'httpdocs/assets/css/sidebar.css',
        'httpdocs/assets/css/nexus-score.css',
        // Extracted from PHP files (2026-01-19)
        'httpdocs/assets/css/nexus-modern-footer.css',
        'httpdocs/assets/css/auth.css',
        'httpdocs/assets/css/post-card.css',
        'httpdocs/assets/css/feed-item.css',
        'httpdocs/assets/css/feed-page.css',
        'httpdocs/assets/css/profile-edit.css',
        'httpdocs/assets/css/messages-index.css',
        'httpdocs/assets/css/messages-thread.css',
        'httpdocs/assets/css/notifications.css',
        'httpdocs/assets/css/groups-show.css',
        'httpdocs/assets/css/events-index.css',
        'httpdocs/assets/css/events-calendar.css',
        'httpdocs/assets/css/events-create.css',
        'httpdocs/assets/css/events-show.css',
        'httpdocs/assets/css/blog-index.css',
        'httpdocs/assets/css/blog-show.css',
        'httpdocs/assets/css/listings-index.css',
        'httpdocs/assets/css/listings-show.css',
        'httpdocs/assets/css/listings-create.css',
        // Phase 1: Components, partials, feed-show (2026-01-19)
        'httpdocs/assets/css/components.css',
        'httpdocs/assets/css/partials.css',
        'httpdocs/assets/css/feed-show.css',
        // Phase 2: Federation module (2026-01-19)
        'httpdocs/assets/css/federation.css',
        // Phase 3: Volunteering module (2026-01-19)
        'httpdocs/assets/css/volunteering.css',
        // Phase 4: Groups module (2026-01-19)
        'httpdocs/assets/css/groups.css',
        // Phase 5: Goals module (2026-01-19)
        'httpdocs/assets/css/goals.css',
        // Phase 6: Polls module (2026-01-19)
        'httpdocs/assets/css/polls.css',
        // Phase 7: Resources module (2026-01-19)
        'httpdocs/assets/css/resources.css',
        // Phase 8: Matches module (2026-01-19)
        'httpdocs/assets/css/matches.css',
        // Phase 9: Organizations module (2026-01-19)
        'httpdocs/assets/css/organizations.css',
        // Phase 10: Help module (2026-01-19)
        'httpdocs/assets/css/help.css',
        // Phase 11: Wallet module (2026-01-19)
        'httpdocs/assets/css/wallet.css',
        // Phase 12: Federation reviews (2026-01-19)
        'httpdocs/assets/css/federation-reviews.css',
        // Phase 13: Static pages bundle (2026-01-19)
        'httpdocs/assets/css/static-pages.css',
        // Phase 14: Scattered singles bundle (2026-01-19)
        'httpdocs/assets/css/scattered-singles.css',
        // Polish enhancements (2026-01-19)
        'httpdocs/assets/css/loading-skeletons.css',
        'httpdocs/assets/css/micro-interactions.css',
        'httpdocs/assets/css/modal-polish.css',
        // Responsive enhancements (2026-01-19)
        'httpdocs/assets/css/responsive-forms.css',
        'httpdocs/assets/css/responsive-tables.css',
        // Admin sidebar (2026-01-19)
        'httpdocs/assets/css/admin-sidebar.css',
        // CivicOne footer (extracted 2026-01-19)
        'httpdocs/assets/css/civicone-footer.css',
        // Header extracted styles (2026-01-19)
        'httpdocs/assets/css/nexus-header-extracted.css',
        // Notification drawer (shared component 2026-01-19)
        'httpdocs/assets/css/notification-drawer.css',
        // Feed action pills (extracted from footer.php 2026-01-19)
        'httpdocs/assets/css/feed-action-pills.css',
        // AI Chat Widget (extracted from partials 2026-01-19)
        'httpdocs/assets/css/ai-chat-widget.css',
        // Accessibility enhancements (2026-01-19)
        'httpdocs/assets/css/accessibility.css',
        // CivicOne dashboard (enhanced 2026-01-19)
        'httpdocs/assets/css/civicone-dashboard.css',
        // CivicOne achievements module (extracted 2026-01-19)
        'httpdocs/assets/css/civicone-achievements.css',
        // CivicOne theme files
        'httpdocs/assets/css/civicone-mobile.css',
        'httpdocs/assets/css/civicone-native.css',
        // Admin gold standard
        'httpdocs/assets/css/admin-gold-standard.css',
        // Admin federation module (created 2026-01-19)
        'httpdocs/assets/css/admin-federation.css',
        // Admin menu builder - extracted inline styles (2026-01-21)
        'httpdocs/assets/css/admin-menu-builder.css',
        // Admin menu index - extracted inline styles (2026-01-21)
        'httpdocs/assets/css/admin-menu-index.css',
        // Feed components
        'httpdocs/assets/css/feed-empty-state.css',
        // Empty states unified system (2026-01-19)
        'httpdocs/assets/css/empty-states.css',
        // Image lazy loading (2026-01-19)
        'httpdocs/assets/css/image-lazy-load.css',
        // Hover micro-interactions (2026-01-19)
        'httpdocs/assets/css/hover-interactions.css',
        // Focus rings (2026-01-19)
        'httpdocs/assets/css/focus-rings.css',
        // Groups overlay
        'httpdocs/assets/css/groups-edit-overlay.css',
        // CivicOne header (extracted 2026-01-19)
        'httpdocs/assets/css/civicone-header.css',
        // Page Hero (Section 9C: Page Hero Contract - 2026-01-21)
        'httpdocs/assets/css/civicone-hero.css',
        // CivicOne events module (WCAG 2.1 AA 2026-01-19)
        'httpdocs/assets/css/civicone-events.css',
        // CivicOne profile module (WCAG 2.1 AA 2026-01-19)
        'httpdocs/assets/css/civicone-profile.css',
        // CivicOne groups module (WCAG 2.1 AA 2026-01-19)
        'httpdocs/assets/css/civicone-groups.css',
        // CivicOne volunteering module (WCAG 2.1 AA 2026-01-19)
        'httpdocs/assets/css/civicone-volunteering.css',
        // CivicOne utilities - extracted inline styles (2026-01-21)
        'httpdocs/assets/css/civicone-utilities.css',
        'httpdocs/assets/css/civicone-blog-utilities.css',
        'httpdocs/assets/css/civicone-groups-utilities.css',
        'httpdocs/assets/css/civicone-directory-utilities.css',
        // Modern layout utilities - extracted inline styles (2026-01-21)
        'httpdocs/assets/css/modern-header-utilities.css',
        'httpdocs/assets/css/modern-header-emergency-fixes.css',
        // CivicOne report pages - strategic plan & impact report (2026-01-21)
        'httpdocs/assets/css/civicone-report-pages.css',
        // CivicOne feed item partial - extracted inline styles (2026-01-21)
        'httpdocs/assets/css/civicone-feed-item.css',
        // CivicOne goals show page - extracted inline styles (2026-01-21)
        'httpdocs/assets/css/civicone-goals-show.css',
        'httpdocs/assets/css/biometric-modal.css',
        // Layout banners (2026-01-21)
        'httpdocs/assets/css/modern-experimental-banner.css',
        // CivicOne mini modules - polls, goals, resources (WCAG 2.1 AA 2026-01-19)
        'httpdocs/assets/css/civicone-mini-modules.css',
        // CivicOne messages & notifications (WCAG 2.1 AA 2026-01-19)
        'httpdocs/assets/css/civicone-messages.css',
        // CivicOne wallet & insights (WCAG 2.1 AA 2026-01-19)
        'httpdocs/assets/css/civicone-wallet.css',
        // CivicOne blog module (WCAG 2.1 AA 2026-01-19)
        'httpdocs/assets/css/civicone-blog.css',
        // CivicOne help & settings (WCAG 2.1 AA 2026-01-19)
        'httpdocs/assets/css/civicone-help.css',
        // CivicOne matches & connections (WCAG 2.1 AA 2026-01-19)
        'httpdocs/assets/css/civicone-matches.css',
        // CivicOne federation module (WCAG 2.1 AA 2026-01-20)
        'httpdocs/assets/css/civicone-federation.css',
        // CivicOne members directory - GOV.UK pattern (WCAG 2.1 AA 2026-01-20)
        'httpdocs/assets/css/civicone-members-directory.css',
        // CivicOne listings directory - GOV.UK pattern (WCAG 2.1 AA 2026-01-20)
        'httpdocs/assets/css/civicone-listings-directory.css',
        // CivicOne profile header - MOJ Identity Bar pattern (WCAG 2.1 AA 2026-01-20)
        'httpdocs/assets/css/civicone-profile-header.css',
        // CivicOne profile social components - Posts, Comments, Actions (WCAG 2.1 AA 2026-01-20)
        'httpdocs/assets/css/civicone-profile-social.css',
        // CivicOne account navigation - MOJ Sub navigation pattern (WCAG 2.1 AA 2026-01-20)
        'httpdocs/assets/css/civicone-account-nav.css',
        // GOV.UK component library (WCAG 2.1 AA - 2026-01-20)
        'httpdocs/assets/css/civicone-govuk-buttons.css',
        'httpdocs/assets/css/civicone-govuk-components.css',
        'httpdocs/assets/css/civicone-govuk-focus.css',
        'httpdocs/assets/css/civicone-govuk-forms.css',
        'httpdocs/assets/css/civicone-govuk-spacing.css',
        'httpdocs/assets/css/civicone-govuk-typography.css',
        // Design tokens (2026-01-20)
        'httpdocs/assets/css/design-tokens.css',
        // Micro-interactions and animations (2026-01-20)
        'httpdocs/assets/css/toast-notifications.css',
        'httpdocs/assets/css/page-transitions.css',
        'httpdocs/assets/css/pull-to-refresh.css',
        'httpdocs/assets/css/button-ripple.css',
        'httpdocs/assets/css/card-hover-states.css',
        'httpdocs/assets/css/form-validation.css',
        'httpdocs/assets/css/avatar-placeholders.css',
        'httpdocs/assets/css/scroll-progress.css',
        'httpdocs/assets/css/fab-polish.css',
        'httpdocs/assets/css/badge-animations.css',
        'httpdocs/assets/css/error-states.css',
        // Utility CSS (2026-01-20)
        'httpdocs/assets/css/branding.css',
        'httpdocs/assets/css/consent-required.css',
        'httpdocs/assets/css/glass.css',
        'httpdocs/assets/css/layout-isolation.css',
        'httpdocs/assets/css/sidebar.css',
        'httpdocs/assets/css/strategic-plan.css',
        // Mobile components (2026-01-20)
        'httpdocs/assets/css/mobile-search-overlay.css',
        'httpdocs/assets/css/mobile-select-sheet.css',
        // Native app (2026-01-20)
        'httpdocs/assets/css/native-form-inputs.css',
        'httpdocs/assets/css/native-page-enter.css',
        // Bundles (2026-01-20)
        'httpdocs/assets/css/modern-bundle.css',
        'httpdocs/assets/css/modern-bundle-compiled.css',
        // Admin (2026-01-20)
        'httpdocs/assets/css/admin-header.css',
        // Utility fixes (2026-01-20)
        'httpdocs/assets/css/noscript-fallbacks.css',
        'httpdocs/assets/css/scroll-fix-emergency.css',
        // Shell layouts (2026-01-20)
        'httpdocs/assets/css/civicone-federation-shell.css',
        'httpdocs/assets/css/civicone-feed.css',
        // Responsive breakpoints (2026-01-21)
        'httpdocs/assets/css/breakpoints.css',
        // Mobile navigation v2 (2026-01-21)
        'httpdocs/assets/css/mobile-nav-v2.css',
        // Desktop polish system (2026-01-21)
        'httpdocs/assets/css/desktop-design-tokens.css',
        'httpdocs/assets/css/desktop-hover-system.css',
        'httpdocs/assets/css/desktop-loading-states.css',
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

            // CivicOne button variants (WCAG 2.1 AA contrast 2026-01-20)
            'civicone-page-header-actions__btn--primary',
            'civicone-page-header-actions__btn--secondary',
            'civicone-page-header-actions__btn--warning',
            'civicone-page-header-actions__btn--danger',
            'civicone-page-header-actions__btn--status',

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

            // Profile social components (2026-01-20)
            'civic-composer', 'civic-composer-actions',
            'civic-post-card', 'civic-post-header', 'civic-post-content', 'civic-post-actions', 'civic-post-image',
            'civic-avatar-sm',
            'civic-action-btn',
            'civic-comments-section',
            'civic-comment', 'civic-comment-avatar', 'civic-comment-bubble',
            'civic-comment-author', 'civic-comment-text', 'civic-comment-meta',
            'civic-comment-form', 'civic-comment-input', 'civic-comment-submit',
            'civic-reply-form', 'civic-reply-input',
            'civic-reactions', 'civic-reaction', 'civic-reaction-picker', 'civic-reaction-picker-menu',
            'civic-mention',
            'civic-toast', 'civic-toast-content', 'civic-toast-icon', 'civic-toast-message',
            'civicone-related-content',

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

            // Keep all civicone- prefixed classes (GOV.UK patterns)
            /^civicone-/,

            // Keep all fds- prefixed classes
            /^fds-/,

            // Keep all htb- prefixed classes (timebank specific)
            /^htb-/,

            // Keep all fed- prefixed classes (federation)
            /^fed-/,

            // Keep all vol- prefixed classes (volunteering)
            /^vol-/,

            // Keep all group- prefixed classes (groups module)
            /^group-/,

            // Keep all goal- prefixed classes (goals module)
            /^goal-/,

            // Keep all poll- prefixed classes (polls module)
            /^poll-/,

            // Keep all resource- prefixed classes (resources module)
            /^resource-/,

            // Keep all match- prefixed classes (matches module)
            /^match-/,

            // Keep all org- prefixed classes (organizations module)
            /^org-/,

            // Keep all help- prefixed classes (help module)
            /^help-/,

            // Keep all wallet- prefixed classes (wallet module)
            /^wallet-/,

            // Keep all glass- prefixed classes
            /^glass-/,

            // Keep all admin-sidebar prefixed classes
            /^admin-sidebar/,
            /^admin-layout/,
            /^admin-main-content/,

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
            /^send-/,
            /^recipient-/,
            /^amount-/,
            /^quick-/,

            // Auth pages
            /^auth-/,
            /^biometric-/,
            /^gdpr-/,
            /^data-protection/,

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

            // Keep all lazy loading classes
            /^lazy-/,

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
