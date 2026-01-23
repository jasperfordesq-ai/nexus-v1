#!/usr/bin/env node

/**
 * CSS Minify Script (No Purging)
 *
 * Just minifies CSS files without removing any selectors.
 * Safe to use - won't break styles.
 *
 * Usage: node scripts/minify-css.js
 */

const fs = require('fs');
const path = require('path');

// Simple CSS minifier
function minifyCSS(css) {
    return css
        .replace(/\/\*[\s\S]*?\*\//g, '')  // Remove comments
        .replace(/\s+/g, ' ')               // Collapse whitespace
        .replace(/\s*{\s*/g, '{')           // Remove space around {
        .replace(/\s*}\s*/g, '}')           // Remove space around }
        .replace(/\s*;\s*/g, ';')           // Remove space around ;
        .replace(/\s*:\s*/g, ':')           // Remove space around :
        .replace(/\s*,\s*/g, ',')           // Remove space around ,
        .replace(/;}/g, '}')                // Remove last semicolon
        .trim();
}

// CSS files to minify
// Updated 2026-01-20: Complete audit - all CSS files included
const cssFiles = [
    // Core framework
    'nexus-phoenix.css',
    'nexus-mobile.css',
    'nexus-shared-transitions.css',
    'nexus-home.css',
    'nexus-score.css',
    'nexus-groups.css',
    'post-box-home.css',
    // Header and loading
    'nexus-loading-fix.css',
    'nexus-performance-patch.css',
    'nexus-modern-header.css',
    'nexus-header-extracted.css',
    // Premium components
    'premium-search.css',
    'premium-dropdowns.css',
    'nexus-premium-mega-menu.css',
    // Consolidated polish (replaces 5 separate files)
    'nexus-polish.css',
    'nexus-interactions.css',
    // Navigation (v2 only)
    'nexus-native-nav-v2.css',
    // CivicOne theme
    'nexus-civicone.css',
    'civicone-mobile.css',
    'civicone-native.css',
    // Extracted component CSS
    'feed-filter.css',
    'dashboard.css',
    'mobile-sheets.css',
    'mobile-design-tokens.css',
    'mobile-accessibility-fixes.css',
    'mobile-loading-states.css',
    'mobile-micro-interactions.css',
    'social-interactions.css',
    'federation-realtime.css',
    'compose-multidraw.css',
    // Bundled CSS
    'civicone-bundle-compiled.css',
    // Admin area (combined)
    'admin-gold-standard.css',
    // Gamification/Achievements
    'achievements.css',
    // Profile page
    'profile-holographic.css',
    'modern-profile-show.css',
    // Groups show page
    'modern-groups-show.css',
    // Volunteering show page
    'modern-volunteering-show.css',
    // Footer
    'nexus-modern-footer.css',
    // Auth pages
    'auth.css',
    // Post components
    'post-card.css',
    'feed-item.css',
    'feed-page.css',
    'feed-show.css',
    // Profile
    'profile-edit.css',
    // Messages
    'messages-index.css',
    'messages-thread.css',
    // Notifications
    'notifications.css',
    // Groups
    'groups-show.css',
    'groups.css',
    // Events
    'events-index.css',
    'events-calendar.css',
    'events-create.css',
    'events-show.css',
    'modern-events-show.css',
    // Blog
    'blog-index.css',
    'blog-show.css',
    // Listings
    'listings-index.css',
    'listings-show.css',
    'listings-create.css',
    // Phase 1: Components, partials
    'components.css',
    'partials.css',
    // Phase 2: Federation module
    'federation.css',
    'federation-reviews.css',
    // Phase 3: Volunteering module
    'volunteering.css',
    // Phase 5: Goals module
    'goals.css',
    // Phase 6: Polls module
    'polls.css',
    // Phase 7: Resources module
    'resources.css',
    // Phase 8: Matches module
    'matches.css',
    // Phase 9: Organizations module
    'organizations.css',
    // Phase 10: Help module
    'help.css',
    // Phase 11: Wallet module
    'wallet.css',
    // Phase 13: Static pages
    'static-pages.css',
    // Phase 14: Scattered singles
    'scattered-singles.css',
    // Polish enhancements
    'loading-skeletons.css',
    'micro-interactions.css',
    'modal-polish.css',
    // Responsive enhancements
    'responsive-forms.css',
    'responsive-tables.css',
    // Admin sidebar
    'admin-sidebar.css',
    // CivicOne components
    'civicone-footer.css',
    'civicone-dashboard.css',
    'civicone-achievements.css',
    'civicone-compose-index.css',
    'civicone-profile-header.css',
    'civicone-profile-social.css',
    // Shared components
    'notification-drawer.css',
    'feed-action-pills.css',
    'feed-empty-state.css',
    'groups-edit-overlay.css',
    // Accessibility
    'accessibility.css',
    // AI Chat Widget (extracted from partials 2026-01-19)
    'ai-chat-widget.css',
    // Empty states unified system (2026-01-19)
    'empty-states.css',
    // Image lazy loading (2026-01-19)
    'image-lazy-load.css',
    // Hover micro-interactions (2026-01-19)
    'hover-interactions.css',
    // Focus rings (2026-01-19)
    'focus-rings.css',
    // Toast notifications (2026-01-19)
    'toast-notifications.css',
    // Page transitions (2026-01-19)
    'page-transitions.css',
    // Pull-to-refresh (2026-01-19)
    'pull-to-refresh.css',
    // Button ripple effects (2026-01-19)
    'button-ripple.css',
    // Card hover states (2026-01-19)
    'card-hover-states.css',
    // Form validation animations (2026-01-19)
    'form-validation.css',
    // Avatar placeholders (2026-01-19)
    'avatar-placeholders.css',
    // Scroll progress indicator (2026-01-19)
    'scroll-progress.css',
    // FAB polish (2026-01-19)
    'fab-polish.css',
    // Badge animations (2026-01-19)
    'badge-animations.css',
    // Error states (2026-01-19)
    'error-states.css',
    // Admin federation (2026-01-20)
    'admin-federation.css',
    // CivicOne module-specific CSS (2026-01-20)
    'civicone-blog.css',
    'civicone-events.css',
    'civicone-federation.css',
    'civicone-groups.css',
    'civicone-header.css',
    // Page Hero (Section 9C: Page Hero Contract - 2026-01-21)
    'civicone-hero.css',
    'civicone-help.css',
    'civicone-matches.css',
    'civicone-messages.css',
    'civicone-mini-modules.css',
    'civicone-profile.css',
    'civicone-volunteering.css',
    'civicone-wallet.css',
    // Account navigation (MOJ Sub navigation pattern - 2026-01-20)
    'civicone-account-nav.css',
    // GOV.UK component library (WCAG 2.2 AA - 2026-01-20/22)
    'civicone-govuk-buttons.css',
    'civicone-govuk-components.css',
    'civicone-govuk-focus.css',
    'civicone-govuk-forms.css',
    'civicone-govuk-spacing.css',
    'civicone-govuk-typography.css',
    // GOV.UK feedback components (WCAG 2.2 AA - 2026-01-22)
    'civicone-govuk-feedback.css',
    // GOV.UK navigation components (WCAG 2.2 AA - 2026-01-22)
    'civicone-govuk-navigation.css',
    // GOV.UK content components (WCAG 2.2 AA - 2026-01-22)
    'civicone-govuk-content.css',
    // GOV.UK tabs component (WCAG 2.2 AA - 2026-01-22)
    'civicone-govuk-tabs.css',
    // MOJ Filter Component (WCAG 2.2 AA - Members Directory v1.6.0 - 2026-01-22)
    'moj-filter.css',
    // Members Directory v1.6.0 (Mobile Bottom Sheet + Prominent Tabs - 2026-01-22)
    'members-directory-v1.6.css',
    // Directory pages (GOV.UK patterns - 2026-01-20/22 - enhanced with v1.4 components)
    'civicone-listings-directory.css',
    'civicone-members-directory.css',
    // Design tokens (2026-01-20)
    'design-tokens.css',
    // Utility CSS
    'branding.css',
    'consent-required.css',
    'glass.css',
    'layout-isolation.css',
    'sidebar.css',
    'strategic-plan.css',
    // Mobile components
    'mobile-search-overlay.css',
    'mobile-select-sheet.css',
    // Native app
    'native-form-inputs.css',
    'native-page-enter.css',
    // Bundles
    'modern-bundle.css',
    'modern-bundle-compiled.css',
    // Admin
    'admin-header.css',
    // Utility fixes
    'noscript-fallbacks.css',
    'scroll-fix-emergency.css',
    // Shell layouts
    'civicone-federation-shell.css',
    'civicone-feed.css',
    // PWA
    'pwa-install-modal.css',
    // Report pages - strategic plan & impact report (2026-01-21)
    'civicone-report-pages.css',
    // Feed item partial (2026-01-21)
    'civicone-feed-item.css',
    // Goals show page (2026-01-21)
    'civicone-goals-show.css',
    // Admin menu files (2026-01-21)
    'admin-menu-builder.css',
    'admin-menu-index.css',
    // Biometric modal (2026-01-21)
    'biometric-modal.css',
    // Responsive breakpoints (2026-01-21)
    'breakpoints.css',
    // CivicOne utilities - extracted inline styles (2026-01-21)
    'civicone-utilities.css',
    'civicone-blog-utilities.css',
    'civicone-groups-utilities.css',
    // Mobile navigation v2 (2026-01-21)
    'mobile-nav-v2.css',
    // Modern layout utilities - extracted inline styles (2026-01-21)
    'modern-experimental-banner.css',
    'modern-header-utilities.css',
    // Desktop polish system (2026-01-21)
    'desktop-design-tokens.css',
    'desktop-hover-system.css',
    'desktop-loading-states.css',
];

const cssDir = path.join(__dirname, '../httpdocs/assets/css');
const bundlesDir = path.join(cssDir, 'bundles');

console.log('🚀 Minifying CSS files...\n');

let totalOriginal = 0;
let totalMinified = 0;

// Helper function to minify a file
function minifyFile(inputPath, outputPath, displayName) {
    if (!fs.existsSync(inputPath)) {
        console.log(`⚠️  Skipping ${displayName} (not found)`);
        return;
    }

    const original = fs.readFileSync(inputPath, 'utf8');
    const minified = minifyCSS(original);

    fs.writeFileSync(outputPath, minified);

    const originalSize = Buffer.byteLength(original, 'utf8');
    const minifiedSize = Buffer.byteLength(minified, 'utf8');
    const savings = ((originalSize - minifiedSize) / originalSize * 100).toFixed(1);

    totalOriginal += originalSize;
    totalMinified += minifiedSize;

    console.log(`✅ ${displayName}: ${(originalSize/1024).toFixed(1)}KB → ${(minifiedSize/1024).toFixed(1)}KB (${savings}% smaller)`);
}

// Minify individual CSS files
cssFiles.forEach(file => {
    const inputPath = path.join(cssDir, file);
    const outputPath = path.join(cssDir, file.replace('.css', '.min.css'));
    minifyFile(inputPath, outputPath, file);
});

// Minify bundle files
console.log('\n📦 Minifying bundle files...\n');
if (fs.existsSync(bundlesDir)) {
    const bundleFiles = fs.readdirSync(bundlesDir)
        .filter(file => file.endsWith('.css') && !file.endsWith('.min.css'));

    bundleFiles.forEach(file => {
        const inputPath = path.join(bundlesDir, file);
        const outputPath = path.join(bundlesDir, file.replace('.css', '.min.css'));
        minifyFile(inputPath, outputPath, `bundles/${file}`);
    });
}

console.log('\n' + '='.repeat(50));
console.log(`📊 Total: ${(totalOriginal/1024).toFixed(1)}KB → ${(totalMinified/1024).toFixed(1)}KB`);
console.log(`💾 Savings: ${((totalOriginal - totalMinified)/1024).toFixed(1)}KB (${((totalOriginal - totalMinified)/totalOriginal * 100).toFixed(1)}%)`);
console.log('='.repeat(50));
