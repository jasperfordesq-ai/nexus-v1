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
// Updated 2026-01-19: Added Phase 1-14 extracted files, theme transitions
const cssFiles = [
    // Core framework
    'nexus-phoenix.css',
    // Theme system
    'theme-transitions.css',
    'nexus-mobile.css',
    'nexus-shared-transitions.css',
    'post-box-home.css',
    // Header and loading
    'nexus-loading-fix.css',
    'nexus-performance-patch.css',
    'nexus-modern-header.css',
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
    // Header extracted styles
    'nexus-header-extracted.css',
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
];

const cssDir = path.join(__dirname, '../httpdocs/assets/css');

console.log('🚀 Minifying CSS files...\n');

let totalOriginal = 0;
let totalMinified = 0;

cssFiles.forEach(file => {
    const inputPath = path.join(cssDir, file);
    const outputPath = path.join(cssDir, file.replace('.css', '.min.css'));

    if (!fs.existsSync(inputPath)) {
        console.log(`⚠️  Skipping ${file} (not found)`);
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

    console.log(`✅ ${file}: ${(originalSize/1024).toFixed(1)}KB → ${(minifiedSize/1024).toFixed(1)}KB (${savings}% smaller)`);
});

console.log('\n' + '='.repeat(50));
console.log(`📊 Total: ${(totalOriginal/1024).toFixed(1)}KB → ${(totalMinified/1024).toFixed(1)}KB`);
console.log(`💾 Savings: ${((totalOriginal - totalMinified)/1024).toFixed(1)}KB (${((totalOriginal - totalMinified)/totalOriginal * 100).toFixed(1)}%)`);
console.log('='.repeat(50));
