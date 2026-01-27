<?php

/**
 * Modern Layout - Consolidated CSS Loader
 * Optimized loading with proper categorization
 * Updated: 2026-01-26
 *
 * Performance Notes:
 * - Critical CSS: 5 files (sync) - Required for above-fold rendering
 * - Header CSS: 7 files (sync) - Navigation must render immediately
 * - All other CSS: Async loaded via media="print" hack
 * - Mobile/Desktop CSS: Media query conditional loading
 *
 * Font Awesome: Currently loaded from CDN in header.php
 * TODO: Self-host for better performance (requires downloading webfonts)
 *
 * Categories:
 * 1. Critical (render-blocking) - Design tokens, core framework
 * 2. Header (sync) - Header, navbar, mega-menu
 * 3. Components (async) - UI components lazy-loaded
 * 4. Utilities (async) - Polish, accessibility, loading states
 * 5. Mobile (media query) - Mobile-specific styles
 * 6. Page-specific (conditional) - Loaded based on route
 *
 * THEME ISOLATION (2026-01-26):
 * This file is ONLY for the Modern theme. Do NOT load civicone-*.css files here.
 * CivicOne CSS is loaded separately via views/layouts/civicone/partials/assets-css.php
 * Loading CivicOne CSS in Modern theme causes style conflicts and regression loops.
 *
 * TOKEN CONSOLIDATION (2026-01-26):
 * modern-theme-tokens.css is loaded AFTER design-tokens.css and nexus-phoenix.css
 * to override any conflicting :root variables. This is the SINGLE SOURCE OF TRUTH
 * for Modern theme CSS variables, eliminating FOUC from cascading :root definitions.
 */

// Use deployment version for cache busting
$cssVersion = $cssVersionTimestamp ?? time();
$assetBase = $assetBase ?? '';

// Helper function for async CSS loading (guard against redeclaration)
if (!function_exists('asyncCss')) {
     function asyncCss($href, $version, $assetBase = '')
     {
          $fullHref = $assetBase . $href . '?v=' . $version;
          return '<link rel="stylesheet" href="' . $fullHref . '" media="print" onload="this.media=\'all\'">';
     }
}

// Helper for sync CSS (guard against redeclaration)
if (!function_exists('syncCss')) {
     function syncCss($href, $version, $assetBase = '', $media = null)
     {
          $fullHref = $assetBase . $href . '?v=' . $version;
          $mediaAttr = $media ? ' media="' . $media . '"' : '';
          return '<link rel="stylesheet" href="' . $fullHref . '"' . $mediaAttr . '>';
     }
}
?>

<!-- ==========================================
     1. CRITICAL CSS (Render-blocking)

     CSS VARIABLE LOAD ORDER (2026-01-26):
     1. design-tokens.css     - Loaded in header.php (base tokens)
     2. nexus-phoenix.css     - HTB brand variables (below)
     3. modern-theme-tokens.css - SINGLE SOURCE OF TRUTH (wins cascade)

     This order ensures modern-theme-tokens.css overrides any
     conflicting :root variables from the legacy files.
     ========================================== -->
<!-- design-tokens.css loaded in header.php (position 1) -->

<?= syncCss('/assets/css/nexus-phoenix.css', $cssVersion, $assetBase) ?>

<!-- MODERN THEME TOKENS - Single Source of Truth (2026-01-26)
     This file consolidates all :root variables for the Modern theme.
     Loaded AFTER design-tokens.css and nexus-phoenix.css to win the cascade.
     Eliminates FOUC caused by 188 files redefining :root variables. -->
<?= syncCss('/assets/css/modern-theme-tokens.css', $cssVersion, $assetBase) ?>

<!-- MODERN PRIMITIVES - Layout & Utility Classes (2026-01-27)
     Token-driven layout primitives (.container, .stack, .cluster, .grid, .sidebar)
     plus spacing, typography, and accessibility helpers.
     See docs/modern-ui-primitives.md for usage. -->
<?= syncCss('/assets/css/modern-primitives.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/bundles/core.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/bundles/components.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/theme-transitions.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/modern-experimental-banner.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     2. HEADER & NAVIGATION (Sync - above fold)
     ========================================== -->
<?= syncCss('/assets/css/nexus-modern-header.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/nexus-premium-mega-menu.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/mega-menu-icons.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/nexus-native-nav-v2.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/mobile-nav-v2.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/modern-header-utilities.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/modern-header-emergency-fixes.css', $cssVersion, $assetBase) ?>

<!-- ==========================================
     2b. MODERN PAGES BASE (Must load BEFORE page-specific)
     Contains base styles that page-specific CSS overrides
     ========================================== -->
<?= syncCss('/assets/css/bundles/modern-pages.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     3. PAGE-SPECIFIC CSS (Conditional - loads LAST to override)
     ========================================== -->
<?php require __DIR__ . '/page-css-loader.php'; ?>

<!-- ==========================================
     4. COMPONENTS (Async - lazy loaded)
     ========================================== -->
<?= asyncCss('/assets/css/bundles/components-navigation.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/components-buttons.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/components-forms.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/components-cards.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/components-modals.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/components-notifications.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     5. UTILITIES (Async - lazy loaded)
     ========================================== -->
<?= asyncCss('/assets/css/bundles/utilities-polish.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/utilities-loading.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/utilities-accessibility.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/enhancements.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/page-transitions.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/error-states.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/avatar-placeholders.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     6. MOBILE CSS (Media query conditional)
     ========================================== -->
<?= syncCss('/assets/css/mobile-design-tokens.css', $cssVersion, $assetBase, '(max-width: 768px)') ?>

<?= syncCss('/assets/css/nexus-mobile.css', $cssVersion, $assetBase, '(max-width: 768px)') ?>

<?= asyncCss('/assets/css/mobile-accessibility-fixes.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/mobile-loading-states.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/mobile-micro-interactions.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/mobile-sheets.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/mobile-select-sheet.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/mobile-search-overlay.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     7. DESKTOP CSS (Media query conditional)
     ========================================== -->
<?= syncCss('/assets/css/desktop-design-tokens.css', $cssVersion, $assetBase, '(min-width: 769px)') ?>

<?= syncCss('/assets/css/desktop-hover-system.css', $cssVersion, $assetBase, '(min-width: 769px)') ?>

<?= syncCss('/assets/css/desktop-loading-states.css', $cssVersion, $assetBase, '(min-width: 769px)') ?>


<!-- ==========================================
     8. SHARED COMPONENTS & PARTIALS (Async)
     ========================================== -->
<?= asyncCss('/assets/css/social-interactions.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/components.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/partials.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/modern/components-library.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/nexus-modern-footer.css', $cssVersion, $assetBase) ?>

<!-- modern-pages.css moved to section 2b (sync, before page-specific) -->


<!-- ==========================================
     9. UTILITIES & POLISH (Async)

     THEME ISOLATION FIX (2026-01-26):
     REMOVED: civicone-utilities.css and civicone-utilities-extended.css

     These CivicOne-specific files were incorrectly loaded here, causing:
     - Style conflicts between Modern and CivicOne themes
     - Regression loops where fixing one theme broke the other
     - FOUC (Flash of Unstyled Content) due to conflicting CSS rules

     CivicOne CSS is now ONLY loaded via:
     views/layouts/civicone/partials/assets-css.php
     ========================================== -->
<?= asyncCss('/assets/css/nexus-performance-patch.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     10. MODALS & OVERLAYS (Async)
     ========================================== -->
<?= asyncCss('/assets/css/biometric-modal.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/pwa-install-modal.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/dev-notice-modal.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     11. NATIVE APP CSS (Capacitor)
     ========================================== -->
<?= asyncCss('/assets/css/native-page-enter.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/native-form-inputs.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     12. EMERGENCY OVERRIDES (Must be last)
     ========================================== -->
<?= syncCss('/assets/css/scroll-fix-emergency.css', $cssVersion, $assetBase) ?>


<!-- Noscript fallbacks for critical async bundles -->
<noscript>
     <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-navigation.css?v=<?= $cssVersion ?>">
     <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-buttons.css?v=<?= $cssVersion ?>">
     <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-forms.css?v=<?= $cssVersion ?>">
     <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/utilities-polish.css?v=<?= $cssVersion ?>">
     <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/enhancements.css?v=<?= $cssVersion ?>">
</noscript>
